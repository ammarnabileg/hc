<?php

namespace App\Services\Volunteer\Org;

use App\Models\Currency;
use App\Models\Membership;
use App\Models\PromotionDecision;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\BehaviorLedger;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Volunteer\Goals\LeadershipService;
use App\Services\Volunteer\People\PositionRoleAssigner;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ سلّم الترقية الفوريّ (القسم 0 · 23-0.2): **لا فترة شغور أصلًا** — لحظة
 * شغور أيّ بوزشن (حتى مستوى سوبرفايزر) يُحسَب السلّم من **الداونلاين
 * المباشر** ويُصعَّد الفائز فورًا وتلقائيًّا، بشلّال معاييرَ لا يُطبَّع ولا
 * يُقارَب — أوّل معيارٍ يظهر فيه فرقٌ يحسم:
 *
 *  1. Rep المكتسَب خلال 90 يومًا من **سجلّ المعاملات الخام** (`amount`) لا
 *     الرقم المسقوف المعروض — فمن بلغ السقف نصف الشهر يستمرّ مكتسَبه بالسجلّ.
 *  2. Rep الحاليّ — لقطة الشهر الجاري.
 *  3. مؤشّر القيادة (Leadership Pulse) — ويُتخطّى لِمَن لا داونلاين له
 *     رياضيًّا (الكوردنيتورز، رتبة 1).
 *  4. VXP المكتسَب خلال 90 يومًا (فرق الرصيد لا نسبة).
 *  5. نفس المقياس بنوافذ متناقصة (إعداد — القاعدة الذهبيّة 2.13): أوّل
 *     نافذة يظهر فيها فرقٌ تحسم فورًا.
 *  6. تعادلٌ كاملٌ في كلّ المعايير والنوافذ ⟵ قرار بشريّ موثَّق
 *     (`PromotionDecision`) — الدايركتور، أو مشرف عام التطوّع إن كان
 *     الشاغر بوزشن الدايركتور نفسه.
 *
 * وبوزشنات **سوبرفايزر فأقلّ** تُثبَّت فورًا بلا اعتماد؛ **الدايركتور** يُعيَّن
 * الفائز فيه «قائم بأعمال» (`is_acting`) بكامل صلاحيّات البوزشن حتى الاعتماد
 * البشريّ (`confirmActing`/`rejectActing`) — ودرجاته المكتسَبة في الفترة
 * تُحسَب له عاديًّا حتى لو رُدَّ الاعتماد، لأنّها مجرّد صفوف Rep/VXP عاديّة.
 *
 * ⛔ **مشرف عام المسار** (رتبة 5) مستثنًى من الترقية الفوريّة — «قاعدة
 * نهائيّة» (23-0.2): شغوره يرفع دايركتورات المسار **مؤقّتًا** لعضويّة
 * مشرف عام التطوّع نفسه (`escalateTrackVacancy`)، ثمّ يملؤه هو بأحد
 * مسارين — كودٌ مباشر، أو مرشّح السلّم بمعاينة كاملة — ولا تصعيد إلّا
 * بموافقته النهائيّة (`resolveTrackVacancy`). **ومشرف عام التطوّع نفسه**
 * (رتبة 6) خارج هذه الخدمة كليًّا — لا نصّ يحكم شغوره.
 *
 * والشغور **الثانويّ** الناتج عن ترقية الفائز نفسه (بوزشنه القديم يصير
 * شاغرًا بدوره) يُملأ بنفس المنطق **تكراريًّا** — بحارس عمقٍ يمنع أيّ حلقة
 * لا نهائيّة في بياناتٍ فاسدة.
 */
class PromotionLadder
{
    /** أقصى عمق تكرار — سلّمٌ ستّ درجات فلا حاجة لأكثر، والباقي حارسٌ فقط */
    private const MAX_DEPTH = 10;

    /** رتبة الدايركتور — الوحيدة التي تحتاج اعتمادًا بشريًّا («قائم بأعمال») */
    private const APPROVAL_RANK = 4;

    /** مشرف عام المسار — استثناءٌ منصوص: تصعيدٌ مؤقّت لمشرف عام التطوّع لا ترقية فوريّة */
    private const TRACK_SUPERVISOR_RANK = 5;

    /** مشرف عام التطوّع — أعلى السلّم، خارج هذه الخدمة كليًّا (لا نصّ يحكم شغوره) */
    private const GM_RANK = 6;

    private const VXP = 'vxp';

    /**
     * ترتيب المرشّحين (الداونلاين المباشر) لعضويّةٍ شاغرة — بلا تنفيذ، للمعاينة
     * وللاستخدام الداخليّ معًا.
     *
     * @param  array<int, int>  $excludeUserIds
     * @return array{candidates: array<int, array<string, mixed>>, winner: ?array<string, mixed>, tie: bool}
     */
    public function rank(Membership $vacated, array $excludeUserIds = []): array
    {
        $candidates = Membership::query()
            ->where('upline_id', $vacated->id)
            ->where('status', 'active')
            ->whereNotIn('user_id', $excludeUserIds ?: [0])
            ->with(['user', 'position'])
            ->get();

        if ($candidates->isEmpty()) {
            return ['candidates' => [], 'winner' => null, 'tie' => false];
        }

        $userIds = $candidates->pluck('user_id')->all();
        $windows = $this->tiebreakWindows();

        $rep90 = $this->ledgerSums($userIds, BehaviorLedger::REP, 90);
        $vxp90 = $this->ledgerSums($userIds, self::VXP, 90);
        $vxpWindows = [];

        foreach ($windows as $days) {
            $vxpWindows[$days] = $this->ledgerSums($userIds, self::VXP, $days);
        }

        $leadership = app(LeadershipService::class);

        $scored = $candidates->map(function (Membership $membership) use ($rep90, $vxp90, $vxpWindows, $leadership) {
            // من لا داونلاين له (كوردنيتور) يُتخطّى معيار مؤشّر القيادة رياضيًّا — لا صفرٌ يُحاسَب به ظلمًا
            $isCoordinator = (int) ($membership->position?->rank ?? 0) === 1;
            $pulse = $isCoordinator ? 0.0 : (float) (($leadership->receivedSummary($membership->user)['average']) ?? 0.0);

            $windowsScored = [];
            foreach ($vxpWindows as $days => $sums) {
                $windowsScored[$days] = (float) ($sums[$membership->user_id] ?? 0.0);
            }

            return [
                'membership' => $membership,
                'user' => $membership->user,
                'rep_90d' => (float) ($rep90[$membership->user_id] ?? 0.0),
                'rep_current' => (float) Integrations::balance($membership->user, BehaviorLedger::REP),
                'leadership' => $pulse,
                'vxp_90d' => (float) ($vxp90[$membership->user_id] ?? 0.0),
                'vxp_windows' => $windowsScored,
            ];
        });

        $sorted = $scored->sort(fn (array $a, array $b) => $this->compare($a, $b, $windows))->values();

        $tie = $sorted->count() > 1 && $this->compare($sorted[0], $sorted[1], $windows) === 0;
        $winner = $tie ? null : $sorted->first();

        return ['candidates' => $sorted->all(), 'winner' => $winner, 'tie' => $tie];
    }

    /**
     * ⭐ نقطة الدخول: عضويّة انتهت للتوّ — املأ شغورها فورًا («لا فترة شغور»).
     * تُستدعى من كلّ مسارٍ يُنهي عضويّة (أوفبوردنج اليوم، ونقلٌ/تعليقٌ لاحقًا).
     *
     * @param  array<int, int>  $excludeUserIds
     * @return array<string, mixed>|null
     */
    public function fillVacancy(Membership $vacated, ?User $actor = null, array $excludeUserIds = [], int $depth = 0): ?array
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        $vacated->loadMissing('position');
        $position = $vacated->position;

        if (! $position || $position->is_honorary) {
            return null;
        }

        $rank = (int) $position->rank;

        if ($rank >= self::GM_RANK) {
            return null;
        }

        if ($rank === self::TRACK_SUPERVISOR_RANK) {
            return $this->escalateTrackVacancy($vacated, $actor);
        }

        $result = $this->rank($vacated, $excludeUserIds);

        if ($result['tie']) {
            $this->recordDecision($vacated, $result['candidates'], 'tie');

            return ['outcome' => 'tie', 'vacated_membership_id' => $vacated->id];
        }

        $winnerRow = $result['winner'];

        if (! $winnerRow) {
            return null;
        }

        return DB::transaction(function () use ($vacated, $winnerRow, $rank, $actor, $depth) {
            /** @var Membership $winnerOldMembership */
            $winnerOldMembership = $winnerRow['membership'];
            $winner = $winnerRow['user'];
            $isActing = $rank === self::APPROVAL_RANK;
            $assigner = app(PositionRoleAssigner::class);

            $newMembership = Membership::create([
                'user_id' => $winner->id,
                'entity_id' => $vacated->entity_id,
                'position_id' => $vacated->position_id,
                'upline_id' => $vacated->upline_id,
                'is_primary' => true,
                'is_acting' => $isActing,
                'started_at' => now(),
                'status' => 'active',
            ]);

            $assigner->grant($newMembership, $actor?->id);

            // بقيّة الداونلاين المباشر تتبع الفائز في بوزشنه الجديد فورًا
            Membership::query()
                ->where('upline_id', $vacated->id)
                ->where('id', '!=', $winnerOldMembership->id)
                ->update(['upline_id' => $newMembership->id]);

            // بوزشن الفائز القديم شغَرَ بدوره — والسحب بعد النقل لا قبله (13.4-س)
            $winnerOldMembership->forceFill(['status' => 'ended', 'ended_at' => now(), 'end_reason' => 'promotion'])->save();
            $assigner->revoke($winnerOldMembership);

            AuditTrail::log($actor, 'promotion_ladder.auto_promote', $newMembership, [], [
                'from_membership_id' => $winnerOldMembership->id,
                'vacated_membership_id' => $vacated->id,
                'is_acting' => $isActing,
            ]);

            Integrations::notify(
                $winner, 'volunteer',
                $isActing
                    ? (string) setting('volunteer.promotion_ladder.notify_acting_title', 'اتصعّدت «قائم بأعمال» 🎖️')
                    : (string) setting('volunteer.promotion_ladder.notify_title', 'مبروك الترقية 🎖️'),
                $isActing
                    ? (string) setting('volunteer.promotion_ladder.notify_acting_body', 'سلّم الترقية رشّحك — بكامل صلاحيّات البوزشن لحين الاعتماد.')
                    : (string) setting('volunteer.promotion_ladder.notify_body', 'سلّم الترقية رشّحك واستلمت البوزشن فورًا.'),
                null, 'volunteer',
            );

            // الشغور الثانويّ (بوزشن الفائز القديم) يُملأ فورًا كذلك — بلا فترة شغور
            $this->fillVacancy($winnerOldMembership, $actor, [], $depth + 1);

            return [
                'outcome' => $isActing ? 'acting' : 'promoted',
                'membership_id' => $newMembership->id,
                'user_id' => $winner->id,
                'vacated_membership_id' => $vacated->id,
            ];
        });
    }

    /** ⭐ اعتماد «القائم بأعمال» — تثبيتٌ نهائيّ بلا تغيير آخر (23-0.2) */
    public function confirmActing(Membership $membership, User $approver): Membership
    {
        $membership->forceFill(['is_acting' => false])->save();

        AuditTrail::log($approver, 'promotion_ladder.confirm', $membership, ['is_acting' => true], ['is_acting' => false]);

        Integrations::notify(
            $membership->user, 'volunteer',
            (string) setting('volunteer.promotion_ladder.notify_confirmed_title', 'اتثبّتّ في البوزشن ✓'),
            (string) setting('volunteer.promotion_ladder.notify_confirmed_body', 'الاعتماد وصل — البوزشن بتاعك ثابت دلوقتي.'),
            null, 'volunteer',
        );

        return $membership->fresh();
    }

    /**
     * ⭐ ردّ الاعتماد — بمبرّر مكتوب (`promotion_ladder.reject`)، وسلّم الترقية
     * يُعاد حسابه لنفس الشاغر **باستثناء المرشّح المردود** — فلا يبقى شغورٌ
     * بلا محاولة ثانية.
     */
    public function rejectActing(Membership $membership, User $approver, string $reason): void
    {
        $rejectedUserId = $membership->user_id;

        $membership->forceFill(['status' => 'ended', 'ended_at' => now(), 'end_reason' => 'acting_rejected'])->save();
        app(PositionRoleAssigner::class)->revoke($membership);

        AuditTrail::log($approver, 'promotion_ladder.reject', $membership, [], ['reason' => $reason]);

        Integrations::notify(
            $membership->user, 'volunteer',
            (string) setting('volunteer.promotion_ladder.notify_rejected_title', 'رُدّ اعتماد القائم بأعمال'),
            $reason,
            null, 'volunteer',
        );

        $this->fillVacancy($membership, $approver, [$rejectedUserId]);
    }

    /**
     * ⭐ حسم تعادلٍ كامل — قرار الدايركتور (أو مشرف عام التطوّع للشاغر
     * دايركتور) بمبرّر مكتوب، ثمّ الترقية تسري بنفس منطق `fillVacancy`.
     */
    public function decideTie(PromotionDecision $decision, User $winnerUser, User $decidedBy, string $reason): ?array
    {
        $vacated = $decision->vacatedMembership;

        if (! $vacated) {
            return null;
        }

        $decision->forceFill([
            'status' => 'decided',
            'decided_by' => $decidedBy->id,
            'decision_user_id' => $winnerUser->id,
            'reason' => $reason,
            'decided_at' => now(),
        ])->save();

        // نستثني كلّ المرشّحين المتعادلين إلّا الفائز المختار — فيُختار هو حتمًا
        $excludeUserIds = array_values(array_diff((array) $decision->candidate_user_ids, [$winnerUser->id]));

        AuditTrail::log($decidedBy, 'promotion_ladder.decide_tie', $decision, [], ['winner_user_id' => $winnerUser->id, 'reason' => $reason]);

        return $this->fillVacancy($vacated, $decidedBy, $excludeUserIds);
    }

    /**
     * ⭐ شغور مشرف عام المسار (رتبة 5 — استثناءٌ منصوص، 23-0.2): **لا ترقية
     * فوريّة**. دايركتورات المسار يرفعون **مؤقّتًا** لعضويّة مشرف عام
     * التطوّع نفسه — فيقوم هو بدور مشرف المسار حتى الملء — ويُسجَّل قرارٌ
     * بمرشّحي السلّم (دايركتورات المسار) لمعاينته، دون تصعيدٍ آليّ لأيٍّ منهم.
     */
    public function escalateTrackVacancy(Membership $vacated, ?User $actor = null): ?array
    {
        $gmMembership = $this->activeGmMembership();

        if (! $gmMembership) {
            return null;
        }

        Membership::query()
            ->where('upline_id', $vacated->id)
            ->where('status', 'active')
            ->update(['upline_id' => $gmMembership->id]);

        $preview = $this->rank($vacated);
        $decision = $this->recordDecision($vacated, $preview['candidates'], 'track_vacancy');

        AuditTrail::log($actor, 'promotion_ladder.track_escalated', $decision, [], [
            'vacated_membership_id' => $vacated->id,
            'gm_membership_id' => $gmMembership->id,
        ]);

        if ($gmMembership->user) {
            Integrations::notify(
                $gmMembership->user, 'volunteer',
                (string) setting('volunteer.promotion_ladder.notify_track_vacancy_title', 'شغور مشرف مسار — بانتظار ملئك'),
                (string) setting('volunteer.promotion_ladder.notify_track_vacancy_body', 'رفعتُ دايركتورات المسار إليك مؤقّتًا — املأه بكودٍ مباشر أو مرشّح السلّم.'),
                null, 'volunteer',
            );
        }

        return ['outcome' => 'track_escalated', 'vacated_membership_id' => $vacated->id, 'decision_id' => $decision->id];
    }

    /**
     * ⭐ ملء شغور مشرف عام المسار — قرار مشرف عام التطوّع النهائيّ وحده
     * (23-0.2): مرشّحٌ من معاينة السلّم، أو أيّ شخصٍ آخر بكودٍ مباشر —
     * ولا فرق هنا بين المسارين، فكلاهما «موافقته النهائيّة» شرطًا وحيدًا.
     * ولو كان الفائز دايركتورًا حاليًّا في المسار، شغوره القديم يُملأ تكراريًّا.
     */
    public function resolveTrackVacancy(PromotionDecision $decision, User $winnerUser, User $decidedBy, string $reason): ?array
    {
        $vacated = $decision->vacatedMembership;

        if (! $vacated) {
            return null;
        }

        $vacated->loadMissing('entity');
        $trackId = $vacated->entity?->track_id;

        $decision->forceFill([
            'status' => 'decided',
            'decided_by' => $decidedBy->id,
            'decision_user_id' => $winnerUser->id,
            'reason' => $reason,
            'decided_at' => now(),
        ])->save();

        return DB::transaction(function () use ($vacated, $trackId, $winnerUser, $decidedBy) {
            $assigner = app(PositionRoleAssigner::class);

            $newMembership = Membership::create([
                'user_id' => $winnerUser->id,
                'entity_id' => $vacated->entity_id,
                'position_id' => $vacated->position_id,
                'upline_id' => $vacated->upline_id,
                'is_primary' => true,
                'is_acting' => false,
                'started_at' => now(),
                'status' => 'active',
            ]);

            $assigner->grant($newMembership, $decidedBy->id);

            // دايركتورات المسار المرفوعون مؤقّتًا لمشرف عام التطوّع يتبعون البوزشن الجديد الآن
            $gmMembership = $this->activeGmMembership();

            if ($gmMembership) {
                Membership::query()
                    ->where('upline_id', $gmMembership->id)
                    ->where('status', 'active')
                    ->whereHas('position', fn ($q) => $q->where('key', 'director'))
                    ->when($trackId, fn ($q) => $q->whereHas('entity', fn ($e) => $e->where('track_id', $trackId)))
                    ->update(['upline_id' => $newMembership->id]);
            }

            // الفائز إن كان دايركتورًا حاليًّا في نفس المسار: بوزشنه القديم يشغر بدوره ويُملأ تكراريًّا
            $winnerOldMembership = Membership::query()
                ->where('user_id', $winnerUser->id)
                ->where('status', 'active')
                ->where('id', '!=', $newMembership->id)
                ->whereHas('position', fn ($q) => $q->where('key', 'director'))
                ->when($trackId, fn ($q) => $q->whereHas('entity', fn ($e) => $e->where('track_id', $trackId)))
                ->first();

            if ($winnerOldMembership) {
                $winnerOldMembership->forceFill(['status' => 'ended', 'ended_at' => now(), 'end_reason' => 'promotion'])->save();
                $assigner->revoke($winnerOldMembership);
                $this->fillVacancy($winnerOldMembership, $decidedBy);
            }

            AuditTrail::log($decidedBy, 'promotion_ladder.track_resolved', $newMembership, [], [
                'vacated_membership_id' => $vacated->id,
                'winner_user_id' => $winnerUser->id,
            ]);

            Integrations::notify(
                $winnerUser, 'volunteer',
                (string) setting('volunteer.promotion_ladder.notify_title', 'مبروك الترقية 🎖️'),
                (string) setting('volunteer.promotion_ladder.notify_body', 'سلّم الترقية رشّحك واستلمت البوزشن فورًا.'),
                null, 'volunteer',
            );

            return [
                'outcome' => 'promoted',
                'membership_id' => $newMembership->id,
                'user_id' => $winnerUser->id,
                'vacated_membership_id' => $vacated->id,
            ];
        });
    }

    // ------------------------------------------------------------------ داخليّ

    private function activeGmMembership(): ?Membership
    {
        return Membership::query()
            ->where('status', 'active')
            ->whereHas('position', fn ($q) => $q->where('key', 'volunteer_gm'))
            ->with('user')
            ->first();
    }

    /** نوافذ كسر التعادل المتناقصة — إعداد لا رقمٌ محروق (القاعدة الذهبيّة 2.13) */
    private function tiebreakWindows(): array
    {
        $windows = setting('volunteer.promotion_ladder.tiebreak_windows_days', [30, 21, 10, 7, 3, 1]);
        $windows = is_array($windows) ? $windows : [30, 21, 10, 7, 3, 1];

        return array_values(array_map('intval', $windows));
    }

    /** مقارنة مرشّحَين بشلّال المعايير — أوّل فرقٍ يحسم، وصفرٌ يعني تعادلًا كاملًا حتى الآن */
    private function compare(array $a, array $b, array $windows): int
    {
        foreach (['rep_90d', 'rep_current', 'leadership', 'vxp_90d'] as $key) {
            $cmp = $b[$key] <=> $a[$key];

            if ($cmp !== 0) {
                return $cmp;
            }
        }

        foreach ($windows as $days) {
            $cmp = ($b['vxp_windows'][$days] ?? 0.0) <=> ($a['vxp_windows'][$days] ?? 0.0);

            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return 0;
    }

    private function recordDecision(Membership $vacated, array $candidates, string $kind): PromotionDecision
    {
        return PromotionDecision::create([
            'vacated_membership_id' => $vacated->id,
            'entity_id' => $vacated->entity_id,
            'position_id' => $vacated->position_id,
            'kind' => $kind,
            'candidate_user_ids' => array_map(fn (array $row) => $row['user']->id, $candidates),
            'status' => 'awaiting_decision',
        ]);
    }

    /**
     * مجموع معاملات عملةٍ خلال نافذة — من **سجلّ المعاملات الخام** (`amount`)
     * لا الرصيد المسقوف المعروض، فالتصفير/السقف الشهريّ لا يمحو المكتسَب
     * الحقيقيّ. نفس نمط `CommitteePath::cumulativeEarned` معمَّمًا للعملة والنافذة.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, float>
     */
    private function ledgerSums(array $userIds, string $currencyCode, int $days): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if ($userIds === []) {
            return [];
        }

        $currencyId = Currency::query()->where('code', $currencyCode)->value('id');

        if (! $currencyId) {
            return array_fill_keys($userIds, 0.0);
        }

        $totals = Transaction::query()
            ->whereIn('user_id', $userIds)
            ->where('currency_id', $currencyId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('user_id, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $out = [];

        foreach ($userIds as $id) {
            $out[$id] = round((float) ($totals[$id] ?? 0), 2);
        }

        return $out;
    }
}
