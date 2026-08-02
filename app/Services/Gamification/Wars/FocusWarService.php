<?php

namespace App\Services\Gamification\Wars;

use App\Models\Challenge;
use App\Models\FocusWar;
use App\Models\FocusWarMember;
use App\Models\User;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\WalletGateway;
use App\Services\Gamification\Wars\Exceptions\WarRuleException;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * حرب التركيز (15.3).
 *
 * الاقتصاد كلّه **تحويلٌ لا سكّ**: الإنشاء يُخصَم 5 تذاكر (رسوم غير قابلة
 * للاسترجاع تمنع العبث بالإنشاء/الإلغاء المتكرّر)، وتذكرة الانضمام **تُحوَّل
 * لصاحب التحدّي** — فلا تدخل تذكرة واحدة للنظام من العدم.
 *
 * والمكافأة **غير اقتصاديّة**: دقائق تركيز + شارة عند 24 ساعة تراكميّة؛
 * ولأنّها غير قابلة للصرف، الفارمينج بلا معنى (15.3).
 */
class FocusWarService
{
    public function __construct(
        private readonly WarRules $rules,
        private readonly WalletGateway $wallet,
        private readonly WarStats $stats,
        private readonly BadgeService $badges,
    ) {}

    /** رسالة الأمانة — نصّها إعداد لا نصّ محروق (2.13) */
    public function honestyMessage(): string
    {
        return (string) setting(
            'wars.focus.honesty_message',
            'هذا التحدي أمانة بينك وبين نفسك. لو سجّلت إنجازًا ما عملتوش، إنت ما غششتش المنصة — غششت نفسك، '
            .'وعوّدتها تاخد مكسب مش من حقها؛ وده أخطر من إنك ما تعملش حاجة أصلًا. '
            .'كن صادقًا مع نفسك… الجائزة الحقيقية مش النقاط، الجائزة هي إنت وإنت بتكبر.',
        );
    }

    // ------------------------------------------------------------------ الإنشاء

    /** @throws WarRuleException */
    public function create(User $user, Challenge $challenge, int $minutes, ?string $intention, bool $isGroup): FocusWar
    {
        $durations = $this->rules->focusDurations($challenge);

        if (! in_array($minutes, $durations, true)) {
            throw new WarRuleException('اختار مدّة من المدد المتاحة: '.implode(' · ', $durations).' دقيقة.');
        }

        $max = $this->rules->maxActiveFocus($challenge);

        if ($this->activeOwnedCount($user) >= $max) {
            throw new WarRuleException("عندك {$max} تحديات نشطة — اقفل واحدًا قبل ما تضيف جديدًا.");
        }

        $cost = $this->rules->focusCreateCost($challenge);
        $balance = $this->wallet->balance($user, 'tickets');

        if ($balance < $cost) {
            throw new WarRuleException(
                'إنشاء التحدّي بـ'.(int) $cost.' تذاكر ورصيدك '.(int) $balance.' — اشحن وابدأ.',
                $cost - $balance,
            );
        }

        return DB::transaction(function () use ($user, $challenge, $minutes, $intention, $isGroup, $cost) {
            // رسوم الإنشاء غير قابلة للاسترجاع (15.3) — لذلك تُخصَم مرّة واحدة هنا
            if ($cost > 0) {
                $this->wallet->debit($user, 'tickets', $cost, 'إنشاء تحدّي تركيز', $challenge);
            }

            $war = FocusWar::create([
                'owner_id' => $user->id,
                'duration_minutes' => $minutes,
                'intention' => $intention ?: null,
                'is_group' => $isGroup,
                'status' => 'active',
                'create_cost' => $cost,
            ]);

            // صاحب التحدّي عضوٌ فيه بلا تذكرة انضمام — هو دفع رسوم الإنشاء
            FocusWarMember::create([
                'focus_war_id' => $war->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'ends_at' => now()->addMinutes($minutes),
                'paid' => 0,
            ]);

            return $war;
        });
    }

    /** @throws WarRuleException */
    public function join(User $user, FocusWar $war): FocusWarMember
    {
        if ($war->status !== 'active') {
            throw new WarRuleException('التحدّي ده اتقفل — اختار واحدًا نشطًا.');
        }

        if (! $war->is_group) {
            throw new WarRuleException('التحدّي ده فرديّ — مش مفتوح للانضمام.');
        }

        if ((int) $war->owner_id === (int) $user->id) {
            throw new WarRuleException('إنت صاحب التحدّي ده أصلًا 🙂');
        }

        if ($war->members()->where('user_id', $user->id)->exists()) {
            throw new WarRuleException('إنت منضمّ للتحدّي ده بالفعل — كمّل تركيزك.');
        }

        $cost = $this->rules->focusJoinCost();
        $balance = $this->wallet->balance($user, 'tickets');

        if ($balance < $cost) {
            throw new WarRuleException(
                'الانضمام بـ'.(int) $cost.' تذكرة ورصيدك '.(int) $balance.' — اشحن وانضمّ.',
                $cost - $balance,
            );
        }

        return DB::transaction(function () use ($user, $war, $cost) {
            // ⭐ تحويل مباشر لصاحب التحدّي — لا سكّ ولا حرق (15.3)
            if ($cost > 0) {
                $this->wallet->debit($user, 'tickets', $cost, 'انضمام لتحدّي تركيز', $war);
                $this->wallet->credit($war->owner, 'tickets', $cost, 'تذكرة انضمام لتحدّيك', $war);
            }

            return FocusWarMember::create([
                'focus_war_id' => $war->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'ends_at' => now()->addMinutes($war->duration_minutes),
                'paid' => $cost,
            ]);
        });
    }

    // ------------------------------------------------------------------ الإلغاء

    /**
     * إلغاء تحدٍّ نشط فيه منضمّون (15.3).
     *
     * الشرط الأساسيّ: رصيد صاحب التحدّي **يغطّي التذاكر المُستَرجَعة قبل الإلغاء**
     * (عدد مَن لم يُكمِل وقته × تذكرة). ومَن أكمل وقته لا يُستَرجَع له شيء
     * لأنّه استفاد كاملًا. والدقائق تبقى للجميع احترامًا للمجهود الحقيقيّ.
     *
     * @throws WarRuleException
     */
    public function cancel(User $actor, FocusWar $war): int
    {
        if ((int) $war->owner_id !== (int) $actor->id) {
            throw new WarRuleException('التحدّي ده مش بتاعك.');
        }

        if ($war->status !== 'active') {
            throw new WarRuleException('التحدّي ده مقفول أصلًا.');
        }

        $refundables = $war->members()
            ->where('user_id', '!=', $war->owner_id)
            ->whereNull('refunded_at')
            ->get()
            ->filter(fn (FocusWarMember $m) => ! $m->isDue() && $m->paid > 0);

        $due = (float) $refundables->sum(fn (FocusWarMember $m) => (float) $m->paid);
        $balance = $this->wallet->balance($actor, 'tickets');

        if ($balance < $due) {
            throw new WarRuleException(
                'الإلغاء محتاج '.(int) $due.' تذكرة ترجع للمنضمّين ورصيدك '.(int) $balance
                .' — وفّر الفرق وارجع ألغِ.',
                $due - $balance,
            );
        }

        return DB::transaction(function () use ($actor, $war, $refundables) {
            foreach ($refundables as $member) {
                $amount = (float) $member->paid;

                $this->wallet->debit($actor, 'tickets', $amount, 'استرجاع تذكرة انضمام بعد إلغاء تحدّيك', $war);
                $this->wallet->credit($member->user, 'tickets', $amount, 'استرجاع تذكرة — التحدّي اتلغى', $war);

                $member->forceFill(['refunded_at' => now()])->save();
            }

            $war->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

            // إشعار المنضمّين عبر مركز الإشعارات (2.8) + Toast في الشاشة
            foreach ($war->members()->where('user_id', '!=', $war->owner_id)->with('user')->get() as $member) {
                if (! $member->user) {
                    continue;
                }

                Notifier::send(
                    $member->user,
                    'focus_war',
                    'اتلغى تحدّي تركيز كنت منضمًّا له',
                    $member->refunded_at
                        ? 'رجعت لك تذكرة الانضمام، ودقائق تركيزك محفوظة كما هي.'
                        : 'وقتك كان خلص فعلًا، فدقائق تركيزك اتسجّلت كاملة.',
                    route('challenges.focus.index'),
                );
            }

            return $refundables->count();
        });
    }

    // ------------------------------------------------------------------ الإتمام

    /**
     * تسجيل الدقائق لمَن انقضى وقته.
     * العدّاد يكمل حتى لو أُغلقت الشاشة — دعمًا للتركيز الأوفلاين (15.3-ب).
     */
    public function settleDue(User $user): int
    {
        $members = FocusWarMember::query()
            ->with('focusWar')
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->where('ends_at', '<=', now())
            ->get();

        $added = 0;

        foreach ($members as $member) {
            $minutes = (int) ($member->focusWar?->duration_minutes ?? 0);

            $member->forceFill(['completed_at' => now(), 'minutes_awarded' => $minutes])->save();
            $added += $minutes;
        }

        if ($added > 0) {
            $this->stats->addFocusMinutes($user, $added);
            // شارة 24 ساعة تركيز تراكميّة (15.3)
            $this->badges->evaluate($user);
        }

        return $added;
    }

    // ------------------------------------------------------------------ استعلام

    public function activeOwnedCount(User $user): int
    {
        return FocusWar::query()
            ->where('owner_id', $user->id)
            ->where('status', 'active')
            ->count();
    }

    /** كلّ التحديات النشطة — «اللي صاحبها لسّه مقفلهاش» (15.3) */
    public function activeBoard(User $viewer): Collection
    {
        return FocusWar::query()
            ->with(['owner', 'members.user'])
            ->where('status', 'active')
            ->latest('id')
            ->get()
            ->map(fn (FocusWar $war) => [
                'war' => $war,
                'joiners' => $war->members->where('user_id', '!=', $war->owner_id)->values(),
                'joined' => $war->members->contains('user_id', $viewer->id),
                'mine' => (int) $war->owner_id === (int) $viewer->id,
            ]);
    }

    public function focusMinutes(User $user): int
    {
        return (int) $this->stats->of($user)->focus_minutes;
    }
}
