<?php

namespace App\Services\Gamification\Wars;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\User;
use App\Models\WarMatch;
use App\Models\WarReadiness;
use App\Models\WarUserStat;
use App\Services\Gamification\WalletGateway;
use App\Services\Gamification\Wars\Exceptions\WarRuleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * الاستعداد والمواجهة (15.0 · 15.1 · 15.2-1).
 *
 * ⭐ **القفل الذرّيّ:** بدء المواجهة يقفل **الطرفين معًا** داخل معاملة واحدة
 * بـ`lockForUpdate` على صفَّي الاستعداد (وهما فريدان لكلّ مستخدم)، ثمّ يحذفهما.
 * فلو حاول شخصان تحدّي نفس اللاعب في نفس اللحظة، ينتظر الثاني حتى تُغلَق
 * المعاملة الأولى فيجد صفّ الاستعداد قد اختفى ⟵ يُرفَض. بلا هذا القفل يدخل
 * لاعبٌ واحد مواجهتين ويُخصَم منه مرّتين.
 */
class MatchmakingService
{
    public function __construct(
        private readonly WarRules $rules,
        private readonly WarQuestionFunnel $funnel,
        private readonly WalletGateway $wallet,
        private readonly WarStats $stats,
    ) {}

    // ------------------------------------------------------------ الاستعداد

    public function readinessOf(User $user): ?WarReadiness
    {
        return WarReadiness::query()->where('user_id', $user->id)->first();
    }

    /**
     * ضغط «استعداد» — والاستعداد **حصريّ**: نوع واحد في اللحظة الواحدة (15.0).
     *
     * @throws WarRuleException
     */
    public function ready(User $user, Challenge $challenge): WarReadiness
    {
        if (! $challenge->is_active) {
            throw new WarRuleException('الحرب دي موقوفة دلوقتي — جرّب ساحة تانية.');
        }

        if ($this->runningMatchOf($user)) {
            throw new WarRuleException('عندك مواجهة شغّالة دلوقتي — كمّلها الأوّل.');
        }

        $gate = $this->rules->readyTickets($challenge);
        $balance = $this->wallet->balance($user, 'tickets');

        // بوّابة ≥ 12 تذكرة (15.2-4) — لأنّ إلغاء الاستعداد وسط حرب يكلّف 12
        if ($balance < $gate) {
            throw new WarRuleException(
                "الاستعداد محتاج {$gate} تذكرة على الأقلّ ورصيدك ".(int) $balance.' — اشحن وارجع، الساحة مستنّياك.',
                $gate - $balance,
            );
        }

        if (! $this->funnel->isBankReady($challenge)) {
            throw new WarRuleException('بنك أسئلة الحرب دي لسّه مش جاهز — جرّب ساحة تانية دلوقتي.');
        }

        return WarReadiness::updateOrCreate(
            ['user_id' => $user->id],
            [
                'challenge_id' => $challenge->id,
                'war_type' => $this->rules->typeOf($challenge),
                'ready_at' => now(),
            ],
        );
    }

    /** إلغاء الاستعداد من الشريط العائم — من أيّ صفحة (15.0) */
    public function cancelReady(User $user): void
    {
        WarReadiness::query()->where('user_id', $user->id)->delete();
    }

    // ------------------------------------------------------------ القائمة

    /**
     * «المحاربون الجاهزون» — حتى 10 عشوائيًّا بشروط 15.1:
     * ضاغط استعداد + غير مشغول + رصيده ≥ العتبة + لم يخسر 3 متتالية.
     *
     * @return Collection<int, array{user:User,wins:int,losses:int}>
     */
    public function fighters(User $me, Challenge $challenge): Collection
    {
        $gate = $this->rules->readyTickets($challenge);
        $lossLimit = $this->rules->lossStreakLimit($challenge);

        $candidates = WarReadiness::query()
            ->with('user')
            ->where('challenge_id', $challenge->id)
            ->where('user_id', '!=', $me->id)
            ->get();

        // المشغولون في مواجهة جارية يخرجون من القائمة فورًا (تتحدّث لحظيًّا)
        $busy = ChallengeParticipation::query()
            ->whereNotNull('war_match_id')
            ->where('status', 'running')
            ->whereIn('user_id', $candidates->pluck('user_id'))
            ->pluck('user_id')
            ->all();

        $stats = WarUserStat::query()
            ->whereIn('user_id', $candidates->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        return $candidates
            ->reject(fn (WarReadiness $r) => in_array($r->user_id, $busy, true))
            ->reject(fn (WarReadiness $r) => ! $r->user)
            ->reject(fn (WarReadiness $r) => $this->wallet->balance($r->user, 'tickets') < $gate)
            // قاعدة 3 خسارات: يختفي من القائمة ولا يظهر معطّلًا (2.15-أ-7)
            ->reject(fn (WarReadiness $r) => (int) ($stats[$r->user_id]->loss_streak ?? 0) >= $lossLimit)
            ->shuffle()
            ->take($this->rules->maxVisibleFighters($challenge))
            ->map(fn (WarReadiness $r) => [
                'user' => $r->user,
                'wins' => (int) ($stats[$r->user_id]->wins ?? 0),
                'losses' => (int) ($stats[$r->user_id]->losses ?? 0),
            ])
            ->values();
    }

    // ------------------------------------------------------------ المواجهة

    /** المواجهة الجارية لمستخدم — الاستئناف يعتمد عليها (15.2-7) */
    public function runningMatchOf(User $user): ?WarMatch
    {
        return WarMatch::query()
            ->where('status', 'running')
            ->where(fn ($q) => $q->where('challenger_id', $user->id)->orWhere('opponent_id', $user->id))
            ->latest('id')
            ->first();
    }

    /**
     * ⭐ بدء المواجهة بقفل ذرّيّ على الطرفين (15.2-1).
     *
     * @throws WarRuleException
     */
    public function start(User $challenger, User $opponent, Challenge $challenge): WarMatch
    {
        if ($challenger->id === $opponent->id) {
            throw new WarRuleException('ما ينفعش تتحدّى نفسك 🙂');
        }

        $gate = $this->rules->readyTickets($challenge);
        $questions = $this->funnel->draw($challenge);

        if ($questions === []) {
            throw new WarRuleException('بنك أسئلة الحرب دي فاضي — بلّغ الإدارة وجرّب ساحة تانية.');
        }

        return DB::transaction(function () use ($challenger, $opponent, $challenge, $gate, $questions) {
            // ترتيب القفل ثابت (بالمعرّف تصاعديًّا) منعًا للتشابك (Deadlock)
            $ids = [$challenger->id, $opponent->id];
            sort($ids);

            $locked = WarReadiness::query()
                ->whereIn('user_id', $ids)
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            foreach ($ids as $id) {
                if (! $locked->has($id)) {
                    throw new WarRuleException('المحارب ده دخل مواجهة تانية دلوقتي — اختار غيره.');
                }

                if ((int) $locked[$id]->challenge_id !== (int) $challenge->id) {
                    throw new WarRuleException('المحارب ده استعدّ لحرب تانية — اختار غيره.');
                }
            }

            // البوّابة تُتحقَّق للطرفين لحظة البدء لا لحظة الاستعداد (15.2-4)
            foreach ([$challenger, $opponent] as $side) {
                if ($this->wallet->balance($side, 'tickets') < $gate) {
                    throw new WarRuleException('واحد من الطرفين رصيده نزل تحت شرط الدخول — المواجهة اتلغت.');
                }
            }

            $busy = ChallengeParticipation::query()
                ->whereNotNull('war_match_id')
                ->where('status', 'running')
                ->whereIn('user_id', $ids)
                ->lockForUpdate()
                ->exists();

            if ($busy) {
                throw new WarRuleException('المحارب ده دخل مواجهة تانية دلوقتي — اختار غيره.');
            }

            $match = WarMatch::create([
                'challenge_id' => $challenge->id,
                'war_type' => $this->rules->typeOf($challenge),
                'challenger_id' => $challenger->id,
                'opponent_id' => $opponent->id,
                'questions' => $questions,
                'status' => 'running',
                'started_at' => now(),
            ]);

            foreach ([$challenger, $opponent] as $side) {
                ChallengeParticipation::create([
                    'challenge_id' => $challenge->id,
                    'war_match_id' => $match->id,
                    'user_id' => $side->id,
                    'started_at' => now(),
                    'status' => 'running',
                    'score' => 0,
                    'reached_index' => 0,
                    'progress' => ['answers' => [], 'index' => 0],
                ]);
            }

            // خروجهما من البركة: الاستعداد يُحذَف فلا يتحدّاهما أحد (15.2-1)
            WarReadiness::query()->whereIn('user_id', $ids)->delete();

            // الإعدادات تُقفَل أثناء حرب نشطة (12.10-ج)
            if (! $challenge->settings_locked) {
                $challenge->forceFill(['settings_locked' => true])->save();
            }

            return $match;
        });
    }
}
