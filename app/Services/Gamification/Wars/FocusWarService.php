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
    /**
     * دلو المصدر في دفتر الأستاذ — **مفتاحٌ لا جملة** (24.2 «بحث بالمصدر/الـKey»).
     * والجملة العربيّة مكانها `reason` وهو عمود «السبب» في تاب المعاملات (19.2).
     */
    private const LEDGER_SOURCE = 'challenge';

    public function __construct(
        private readonly WarRules $rules,
        private readonly WalletGateway $wallet,
        private readonly WarStats $stats,
        private readonly BadgeService $badges,
    ) {}

    /**
     * ⭐ **رسالةُ «الرصيد لا يكفي» — صياغةٌ واحدة لكلّ بابَي كلّ عمليّة.**
     *
     * لكلّ عمليّةٍ هنا **فحصان**: فحصٌ مسبَق يعطي رسالةً مفيدة قبل أن نبدأ،
     * وحارسٌ بعد الخصم يمسك ما ينزلق بين الفحص والكتابة (سباقٌ أو قاعُ العملة).
     * وكتابة الرسالة مرّتين تعني نصَّين يتباعدان مع الزمن ونصًّا محروقًا جديدًا
     * (2.13) — فالصياغة هنا **مرّةً واحدة** يقرؤها البابان.
     */
    private function cannotAfford(string $action, float $needed, float $balance): WarRuleException
    {
        $message = match ($action) {
            'create' => strtr(setting('gamification_wars.focus_war_service.cannot_afford_1', 'إنشاء التحدّي بـ:p1 تذاكر ورصيدك :p2 — اشحن وابدأ.'), [':p1' => (string) ((int) $needed), ':p2' => (string) ((int) $balance)]),
            'join' => strtr(setting('gamification_wars.focus_war_service.cannot_afford_2', 'الانضمام بـ:p1 تذكرة ورصيدك :p2 — اشحن وانضمّ.'), [':p1' => (string) ((int) $needed), ':p2' => (string) ((int) $balance)]),
            default => strtr(setting('gamification_wars.focus_war_service.cannot_afford_3', 'الإلغاء محتاج :p1 تذكرة ترجع للمنضمّين ورصيدك :p2 — وفّر الفرق وارجع ألغِ.'), [':p1' => (string) ((int) $needed), ':p2' => (string) ((int) $balance)]),
        };

        return new WarRuleException($message, max(0.0, $needed - $balance));
    }

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
            throw new WarRuleException(strtr(setting('gamification_wars.focus_war_service.create_1', 'اختار مدّة من المدد المتاحة: :p1 دقيقة.'), [':p1' => (string) (implode(' · ', $durations))]));
        }

        $max = $this->rules->maxActiveFocus($challenge);

        if ($this->activeOwnedCount($user) >= $max) {
            throw new WarRuleException(strtr(setting('gamification_wars.focus_war_service.create_2', 'عندك :p1 تحديات نشطة — اقفل واحدًا قبل ما تضيف جديدًا.'), [':p1' => (string) ($max)]));
        }

        $cost = $this->rules->focusCreateCost($challenge);
        $balance = $this->wallet->balance($user, 'tickets');

        if ($balance < $cost) {
            throw $this->cannotAfford('create', $cost, $balance);
        }

        return DB::transaction(function () use ($user, $challenge, $minutes, $intention, $isGroup, $cost) {
            // رسوم الإنشاء غير قابلة للاسترجاع (15.3) — لذلك تُخصَم مرّة واحدة هنا.
            // ⭐ ونتيجة الخصم **تُقرَأ**: لو رُدَّ (رصيدٌ نزل بعد الفحص) فلا تحدٍّ
            // مجّانيّ — تُرتجَع المعاملة كلّها ويُقال له لماذا.
            if ($cost > 0 && ! $this->wallet->debit($user, 'tickets', $cost, self::LEDGER_SOURCE, setting('gamification_wars.focus_war_service.body_1', 'إنشاء تحدّي تركيز'), $challenge)) {
                throw $this->cannotAfford('create', $cost, $this->wallet->balance($user, 'tickets'));
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
            throw new WarRuleException(setting('gamification_wars.focus_war_service.join_1', 'التحدّي ده اتقفل — اختار واحدًا نشطًا.'));
        }

        if (! $war->is_group) {
            throw new WarRuleException(setting('gamification_wars.focus_war_service.join_2', 'التحدّي ده فرديّ — مش مفتوح للانضمام.'));
        }

        if ((int) $war->owner_id === (int) $user->id) {
            throw new WarRuleException(setting('gamification_wars.focus_war_service.join_3', 'إنت صاحب التحدّي ده أصلًا 🙂'));
        }

        if ($war->members()->where('user_id', $user->id)->exists()) {
            throw new WarRuleException(setting('gamification_wars.focus_war_service.join_4', 'إنت منضمّ للتحدّي ده بالفعل — كمّل تركيزك.'));
        }

        $cost = $this->rules->focusJoinCost();
        $balance = $this->wallet->balance($user, 'tickets');

        if ($balance < $cost) {
            throw $this->cannotAfford('join', $cost, $balance);
        }

        return DB::transaction(function () use ($user, $war, $cost) {
            /*
             | ⭐ تحويل مباشر لصاحب التحدّي — «**مش minting**، فالفارمينج مقفول»
             | (15.3). وكان سطرَين متجاورَين تُهمَل نتيجة أوّلهما: لو رُدَّ الخصم
             | مضت الإضافة **فسُكَّت تذكرة**. فصار نداءً ذرّيًّا واحدًا يخصم أوّلًا
             | ولا يضيف إلّا بعد نجاحه.
             */
            if ($cost > 0) {
                $moved = $this->wallet->transfer(
                    from: $user,
                    to: $war->owner,
                    currencyCode: 'tickets',
                    amount: $cost,
                    source: self::LEDGER_SOURCE,
                    debitReason: setting('gamification_wars.focus_war_service.join_5', 'انضمام لتحدّي تركيز'),
                    creditReason: setting('gamification_wars.focus_war_service.join_6', 'تذكرة انضمام لتحدّيك'),
                    reference: $war,
                );

                if ($moved <= 0) {
                    throw $this->cannotAfford('join', $cost, $this->wallet->balance($user, 'tickets'));
                }
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
     * ⭐ **والقفل الذرّيّ شرطُ صحّةٍ لا تحسين** (15.2-1 حرفيًّا: «**قفل ذرّي
     *    (Atomic Lock):** بمجرد بدء تحدٍّ يُقفَل الطرفان فورًا من البركة
     *    (Transaction) لمنع تحدّيهما من شخصين في نفس اللحظة»).
     *
     * ⚠️ **العطب الذي أُصلِح:** كان فحص الحالة (`status !== 'active'`) وجمعُ
     *    المستحقّين وفحصُ الرصيد **خارج المعاملة**، وصفُّ الحرب **بلا قفل** —
     *    خلافًا لأخيه `WarMatchService::settle()` الذي يقرأ الصفّ داخل المعاملة
     *    بـ`lockForUpdate()`. فطلبَا إلغاءٍ متزامنان (ضغطتان · تبويبتان ·
     *    عاملان) يقرآن `status = active` معًا فيمرّان معًا، فيُستَرجَع لكلّ
     *    منضمٍّ **مرّتان** — أي تذاكر تُسَكّ من العدم، وهو عين ما يمنعه 15.2-6:
     *    «**منع الفارمينج:** الرابح +2 والخاسر −2 (**محصّلة صفرية**)».
     *
     * ⚠️ **وحدود ما يُقاس** (يجب أن يعلنها الحارس وإلّا قُرِئ رقمه تغطيةً كاملة):
     *    السباق نفسه **غير قابل للإثبات في اختبارٍ أحاديّ الخيط** — ولذلك يقيس
     *    `FocusWarCancelRaceTest` **البنية التي تمنعه** لا السباق: يسجّل
     *    استعلامات القاعدة بـ`DB::listen` ويؤكّد أنّ صفّ الحرب يُقرَأ **داخل
     *    معاملةٍ** و**بـ`for update`**. وهذا مقبولٌ ما دام معلَنًا.
     *
     * @throws WarRuleException
     */
    public function cancel(User $actor, FocusWar $war): int
    {
        if ((int) $war->owner_id !== (int) $actor->id) {
            throw new WarRuleException(setting('gamification_wars.focus_war_service.cancel_1', 'التحدّي ده مش بتاعك.'));
        }

        return DB::transaction(function () use ($actor, $war) {
            /*
             | ⭐ **القراءة الحاسمة داخل المعاملة وبقفل الصفّ** — كأخيه
             | `WarMatchService::settle()`. الطلب الثاني ينتظر هنا حتّى تنتهي
             | معاملة الأوّل، فيقرأ `cancelled` ويخرج بلا استرجاعٍ ثانٍ.
             */
            $fresh = FocusWar::query()->whereKey($war->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->status !== 'active') {
                throw new WarRuleException(setting('gamification_wars.focus_war_service.cancel_2', 'التحدّي ده مقفول أصلًا.'));
            }

            /*
             | وجمعُ المستحقّين وفحصُ الرصيد **بعد القفل** لا قبله: قائمةٌ حُسِبت
             | قبل القفل تكون لقطةً قديمة، فيُسترجَع لمن سُدِّد له بالفعل.
             */
            $refundables = $fresh->members()
                ->where('user_id', '!=', $fresh->owner_id)
                ->whereNull('refunded_at')
                ->get()
                ->filter(fn (FocusWarMember $m) => ! $m->isDue() && $m->paid > 0);

            $due = (float) $refundables->sum(fn (FocusWarMember $m) => (float) $m->paid);
            $balance = $this->wallet->balance($actor, 'tickets');

            if ($balance < $due) {
                throw $this->cannotAfford('cancel', $due, $balance);
            }

            $war = $fresh;

            foreach ($refundables as $member) {
                $amount = (float) $member->paid;

                // ⭐ الاسترجاع تحويلٌ ذرّيّ كذلك: لا تصل التذكرة للمنضمّ إلّا
                // إذا خرجت فعلًا من رصيد صاحب التحدّي (15.3 — شرط التغطية)
                $moved = $this->wallet->transfer(
                    from: $actor,
                    to: $member->user,
                    currencyCode: 'tickets',
                    amount: $amount,
                    source: self::LEDGER_SOURCE,
                    debitReason: setting('gamification_wars.focus_war_service.cancel_3', 'استرجاع تذكرة انضمام بعد إلغاء تحدّيك'),
                    creditReason: setting('gamification_wars.focus_war_service.cancel_4', 'استرجاع تذكرة — التحدّي اتلغى'),
                    reference: $war,
                );

                if ($moved <= 0) {
                    throw $this->cannotAfford('cancel', $amount, $this->wallet->balance($actor, 'tickets'));
                }

                $member->forceFill(['refunded_at' => now()])->save();
            }

            $war->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

            // العدّاد يقف لحظة الإلغاء: كلٌّ يحتفظ بما جمّعه **بالفعل** لا بالمدّة كاملة (15.3-2)
            $this->closeMembers($war);

            // إشعار المنضمّين عبر مركز الإشعارات (2.8) + Toast في الشاشة
            foreach ($war->members()->where('user_id', '!=', $war->owner_id)->with('user')->get() as $member) {
                if (! $member->user) {
                    continue;
                }

                Notifier::send(
                    $member->user,
                    'focus_war',
                    setting('gamification_wars.focus_war_service.cancel_5', 'اتلغى تحدّي تركيز كنت منضمًّا له'),
                    $member->refunded_at
                        ? setting('gamification_wars.focus_war_service.cancel_6', 'رجعت لك تذكرة الانضمام، ودقائق تركيزك محفوظة كما هي.')
                        : setting('gamification_wars.focus_war_service.cancel_7', 'وقتك كان خلص فعلًا، فدقائق تركيزك اتسجّلت كاملة.'),
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
     *
     * ⭐ والساعة وحدها لا تكفي: **حالة التحدّي** هي التي تحدّد المستحقّ. فالتحدّي
     * الملغيّ توقّف عدّاده لحظة الإلغاء، ولو سوّينا بـ`ends_at` وحده لأخذ المنضمّ
     * المدّة كاملةً **فوق** استرجاع تذكرته — استفادةٌ مضاعفة وشارةٌ بلا تركيز.
     */
    public function settleDue(User $user): int
    {
        $members = FocusWarMember::query()
            ->with('focusWar')
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->where(function ($query) {
                // انقضت المدّة… أو أُقفل التحدّي قبلها فلا معنى لانتظارها
                $query->where('ends_at', '<=', now())
                    ->orWhereHas('focusWar', fn ($war) => $war->where('status', 'cancelled'));
            })
            ->get();

        $added = 0;

        foreach ($members as $member) {
            $minutes = $this->earnedMinutes($member);

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

    /**
     * الدقائق المستحقّة لعضويّةٍ بحسب **حالة التحدّي** (15.3).
     *
     * - تحدٍّ قائم انقضت مدّته ⟵ المدّة كاملة («استفاد كامل»).
     * - تحدٍّ **ملغيّ** ⟵ ما بين الانضمام ولحظة الإلغاء فقط، مسقوفًا بالمدّة:
     *   «المنضمّون يحتفظون بدقائق التركيز اللي جمّعوها **بالفعل**» — فلا تُسَكّ
     *   دقيقةٌ من العدم بعد توقّف الحرب.
     */
    private function earnedMinutes(FocusWarMember $member): int
    {
        $war = $member->focusWar;
        $duration = (int) ($war?->duration_minutes ?? 0);

        if (! $war || $duration <= 0 || ! $member->joined_at) {
            return 0;
        }

        $stoppedAt = $war->status === 'cancelled' ? $war->cancelled_at : null;

        if (! $stoppedAt) {
            return $member->isDue() ? $duration : 0;
        }

        $accumulated = (int) floor($member->joined_at->diffInMinutes($stoppedAt));

        return max(0, min($duration, $accumulated));
    }

    /**
     * إقفال عضويّات تحدٍّ توقّف: كلٌّ يأخذ ما بذله فعلًا في اللحظة نفسها،
     * فلا تبقى عضويّةٌ «جارية» في حربٍ انتهت ولا يتأخّر حقّ صاحب المجهود.
     */
    private function closeMembers(FocusWar $war): void
    {
        $members = $war->members()->whereNull('completed_at')->with('user')->get();

        foreach ($members as $member) {
            $member->setRelation('focusWar', $war);
            $minutes = $this->earnedMinutes($member);

            $member->forceFill(['completed_at' => now(), 'minutes_awarded' => $minutes])->save();

            if ($minutes > 0 && $member->user) {
                $this->stats->addFocusMinutes($member->user, $minutes);
                $this->badges->evaluate($member->user);
            }
        }
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
