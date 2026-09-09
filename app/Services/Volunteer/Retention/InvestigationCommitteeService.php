<?php

namespace App\Services\Volunteer\Retention;

use App\Models\Currency;
use App\Models\InvestigationCase;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\OffboardingService;
use App\Services\Notifications\Notifier;
use App\Services\Volunteer\Escalation\FlowLedger;
use App\Services\Volunteer\Escalation\HandlerChain;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * لجنة التحقيق — تشكيلها وانعقادها وقرارها (23-0.2-4).
 *
 * أربع محطّات لا أكثر: **تفعيل** المسودّة الآليّة (`CommitteePath::refer`) في
 * ملفٍّ حقيقيّ بمقعدَيه · **ميتينج** خلال 72 ساعة (وإعادة 72 ساعة) · **قرار
 * اللجنة** (فرصة/توصية إقصاء) · **قرار القمّة** البشريّ (إقصاء/رفض التوصية).
 *
 * ⚠️ لا خصمَ ولا فرصةَ تقع هنا مباشرةً: كلٌّ منهما يمرّ بمساره القائم
 * والمختبَر بالفعل — `SuspensionService::grantChance()` للفرصة،
 * `OffboardingService::open('exclusion', …)` للإقصاء — فلا يتفرّع مصدر
 * الحقيقة لأثرٍ ماليّ واحد إلى مسارين.
 */
class InvestigationCommitteeService
{
    public function __construct(
        private readonly HandlerChain $chain,
        private readonly SuspensionService $suspension,
    ) {}

    // ------------------------------------------------------------------ الطابور

    /** المسودّات المفتوحة بلا ملفٍّ مفعَّل — «يحتاج قرارك» عند مشرف عام التطوّع */
    public function queue(): Collection
    {
        return DB::table(CommitteePath::TABLE)
            ->where(CommitteePath::TABLE.'.status', 'open')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('investigation_cases')
                    ->whereColumn('investigation_cases.referral_id', CommitteePath::TABLE.'.id');
            })
            ->orderBy('opened_at')
            ->get();
    }

    // ------------------------------------------------------------------ التفعيل

    /** تفعيل بضغطة واحدة (23-0.2-4-1) — ملفٌّ حقيقيّ بمقعدَيه ولقطة قضيّته */
    public function activate(object $referral, User $actor): InvestigationCase
    {
        $existing = InvestigationCase::query()->where('referral_id', $referral->id)->first();

        if ($existing) {
            return $existing;
        }

        $user = User::query()->findOrFail($referral->user_id);
        $suspensionId = DB::table(SuspensionService::TABLE)
            ->where('referral_id', $referral->id)
            ->value('id');

        $case = DB::transaction(function () use ($referral, $user, $suspensionId, $actor) {
            $case = InvestigationCase::create([
                'referral_id' => $referral->id,
                'suspension_id' => $suspensionId,
                'user_id' => $user->id,
                'status' => 'open',
                'activated_by' => $actor->id,
                'activated_at' => now(),
                'dossier_snapshot' => $this->buildDossier($user),
            ]);

            $case->forceFill([
                'seat_upline_id' => $this->uplineSeatFor($user)?->id,
                'seat_dept_id' => $this->deptSeatFor($user)?->id,
            ])->save();

            return $case->refresh();
        });

        AuditTrail::log($actor, 'investigation.activate', $case, [], [
            'referral_id' => $referral->id,
            'user_id' => $user->id,
            'seat_upline_id' => $case->seat_upline_id,
            'seat_dept_id' => $case->seat_dept_id,
        ]);

        $this->notifySeats($case);

        return $case;
    }

    /**
     * ملفّ القضيّة يتجمّع آليًّا قبل الميتينج (23-0.2-4-4): معاملات آخر نافذة
     * (90 يومًا افتراضًا) + مهامّه المسلَّمة لأبلاينه عند التعليق — **لقطةٌ
     * لا استعلامٌ حيّ**، فلا يتغيّر ملفّ القضيّة بعد فتحه.
     */
    private function buildDossier(User $user): array
    {
        $windowDays = (int) setting('volunteer_investigation.dossier_window_days', 90);
        $currencyIds = Currency::query()->whereIn('code', [LedgerService::REP, FlowLedger::VXP])->pluck('id', 'code');
        $codeByCurrencyId = $currencyIds->flip();

        $transactions = Transaction::query()
            ->where('user_id', $user->id)
            ->whereIn('currency_id', $currencyIds->values())
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->orderByDesc('id')
            ->get(['id', 'currency_id', 'amount', 'source', 'reason', 'created_at'])
            ->map(fn (Transaction $row) => [
                'currency' => $codeByCurrencyId[$row->currency_id] ?? null,
                'amount' => (float) $row->amount,
                'source' => $row->source,
                'reason' => $row->reason,
                'at' => $row->created_at?->toDateTimeString(),
            ])->all();

        return [
            'window_days' => $windowDays,
            'compiled_at' => now()->toDateTimeString(),
            'transactions' => $transactions,
        ];
    }

    // ------------------------------------------------------------------ المقعدان

    /**
     * «الأبلاين المباشر … أو مَن وصله التصعيد وقت الانقطاع» (23-0.2-4-2).
     *
     * ⚠️ لا `HandlerChain::firstHandlerFor()`: هذا يقرأ عضويّة **نشِطة** لصاحبه
     * أوّلًا، والمعلَّق عضويّته `suspended` بالتعريف — فيعود `null` دائمًا.
     * والصحيح قراءة **خريطة التغطية المجمَّدة** التي كتبها `SuspensionService::apply()`
     * لحظة التعليق نفسها (`uplineUserOf()` لكلّ عضويّة) — وهي عين الأبلاين المباشر.
     */
    private function uplineSeatFor(User $user): ?User
    {
        return $this->suspension->coverFor($user);
    }

    /**
     * مقعد قسم المتطوّعين: توازن أحمال بسيط (الأقلّ اختيارًا مؤخّرًا أوّلًا)
     * بين أعضاء مسار «قسم» برتبة تيم ليدر فأعلى — واستبعاد تنازع المصالح
     * (علاقة أبلاين/داونلاين في أيّ كيان) والشخص نفسه.
     */
    private function deptSeatFor(User $suspended): ?User
    {
        $minRankPositionKey = (string) setting('volunteer_investigation.min_seat_position', 'team_leader');
        $minRank = (int) (Position::query()->where('key', $minRankPositionKey)->value('rank') ?? 2);

        $candidates = User::query()
            ->whereHas('memberships', function ($q) use ($minRank) {
                $q->where('status', 'active')
                    ->whereHas('entity.track', fn ($t) => $t->where('key', 'department'))
                    ->whereHas('position', fn ($p) => $p->where('rank', '>=', $minRank)->where('is_honorary', false));
            })
            ->get();

        $lastAssigned = InvestigationCase::query()
            ->whereNotNull('seat_dept_id')
            ->selectRaw('seat_dept_id, MAX(activated_at) as last_at')
            ->groupBy('seat_dept_id')
            ->pluck('last_at', 'seat_dept_id');

        return $candidates
            ->reject(fn (User $u) => (int) $u->id === (int) $suspended->id)
            ->reject(fn (User $u) => $this->relatedByLineRegardlessOfStatus($u, $suspended))
            ->sortBy(fn (User $u) => (string) ($lastAssigned[$u->id] ?? '1970-01-01 00:00:00'))
            ->values()
            ->first();
    }

    /**
     * تنازع المصالح مع الشخص **المعلَّق نفسه** (23-0.2-4-3).
     *
     * ⚠️ لا `HandlerChain::relatedByLine()` هنا: هي تقرأ عضويّات **نشِطة**
     * وحدها، وعضويّات المعلَّق كلّها `suspended` بحكم درجته — فتعود دائمًا
     * `false` ولا تكتشف تنازعًا حقيقيًّا قائمًا. فنكرّر المنطق نفسه هنا بحالتَي
     * `CHAIN_STATUSES` (نشِط/معلَّق) كي تبقى العلاقة الهيكليّة مرئيّة رغم التعليق.
     */
    private function relatedByLineRegardlessOfStatus(User $a, User $b): bool
    {
        return $this->isAncestorRegardlessOfStatus($a, $b) || $this->isAncestorRegardlessOfStatus($b, $a);
    }

    private function isAncestorRegardlessOfStatus(User $ancestor, User $user): bool
    {
        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->whereIn('status', HandlerChain::CHAIN_STATUSES)
            ->get();

        foreach ($memberships as $membership) {
            $cursor = $membership;
            $guard = 0;

            while ($cursor && $cursor->upline_id && $guard++ < 20) {
                $cursor = Membership::query()->find($cursor->upline_id);

                if ($cursor && (int) $cursor->user_id === (int) $ancestor->id) {
                    return true;
                }
            }
        }

        return false;
    }

    /** تجاوز اختيار المقعد بالكود — لمشرف عام التطوّع وحده (23-0.2-4-2) */
    public function overrideSeat(InvestigationCase $case, string $slot, User $candidate, User $actor): InvestigationCase
    {
        if (! in_array($slot, ['upline', 'dept'], true)) {
            throw ValidationException::withMessages(['slot' => setting('volunteer_investigation.committee.override_slot', 'مقعدٌ غير معروف.')]);
        }

        $column = $slot === 'upline' ? 'seat_upline_id' : 'seat_dept_id';

        $case->forceFill([$column => $candidate->id, 'seat_override_by' => $actor->id])->save();

        AuditTrail::log($actor, 'investigation.override_seat', $case, [], ['slot' => $slot, 'user_id' => $candidate->id]);

        return $case->refresh();
    }

    private function notifySeats(InvestigationCase $case): void
    {
        foreach ([$case->seatUpline, $case->seatDept] as $seat) {
            if (! $seat) {
                continue;
            }

            Notifier::send(
                $seat,
                'account',
                (string) setting('volunteer_investigation.notify.seat_title', 'اتعيّنت في لجنة تحقيق'),
                (string) setting('volunteer_investigation.notify.seat_body', 'هتسمعوا من العضو المعلَّق في ميتينج قريب، وقراركم بعده: فرصة أو توصية إقصاء.'),
                null,
                'volunteer',
            );
        }
    }

    // ------------------------------------------------------------------ الميتينج

    /**
     * جدولة الميتينج الأوّل — خلال 72 ساعة من التعليق افتراضًا (23-0.2-4-5).
     *
     * ⭐ «الميتينج فعاليّة بكود حضور — والانعقاد والحضور موثَّقان آليًّا بنظام
     * الفعاليّات القائم»: صفٌّ حقيقيّ في `meetings` (`audience='specific'`،
     * جمهورٌ اسميّ محصورٌ في المعلَّق ومقعدَي اللجنة وحدهم — لا الكيان كلّه)
     * بكود حضورٍ عشوائيّ، فتعمل عليه شاشة الاجتماع والتسجيل القائمتان فعلًا
     * بلا كودٍ جديد. لا يمسّ هذا شرط `recordVerdict()` — مقعدا اللجنة
     * يسجّلان قرارهما كما كانا دائمًا، فهذا توثيقٌ للانعقاد لا قفلٌ جديد عليه.
     */
    public function scheduleMeeting(InvestigationCase $case, Carbon $at, User $actor): InvestigationCase
    {
        if ($case->meeting) {
            $case->meeting->forceFill(['scheduled_at' => $at])->save();
        } else {
            $meeting = Meeting::create([
                'title' => strtr((string) setting('volunteer_investigation.meeting.title', 'ميتينج لجنة تحقيق: :p1'), [':p1' => (string) $case->user->name]),
                'audience' => 'specific',
                'owner_id' => $actor->id,
                'scheduled_at' => $at,
                'status' => 'scheduled',
                'attendance_code' => Str::upper(Str::random(6)),
            ]);

            $meeting->invitees()->sync(
                collect([$case->user_id, $case->seat_upline_id, $case->seat_dept_id])->filter()->unique()
            );

            $case->forceFill(['meeting_id' => $meeting->id])->save();
        }

        $case->forceFill(['meeting_scheduled_at' => $at])->save();

        AuditTrail::log($actor, 'investigation.schedule_meeting', $case, [], ['at' => $at->toDateTimeString()]);

        return $case->refresh();
    }

    /** إعادة جدولة واحدة فقط عند الغياب — الثانية «مضيّ غيابيّ» (23-0.2-4-6) */
    public function reschedule(InvestigationCase $case, Carbon $at, User $actor): InvestigationCase
    {
        $limit = (int) setting('volunteer_investigation.reschedule_limit', 1);

        if ($case->reschedule_count >= $limit) {
            $case->forceFill(['absent_in_person' => true])->save();

            return $case->refresh();
        }

        $case->meeting?->forceFill(['scheduled_at' => $at])->save();

        $case->forceFill([
            'meeting_scheduled_at' => $at,
            'reschedule_count' => $case->reschedule_count + 1,
        ])->save();

        AuditTrail::log($actor, 'investigation.reschedule_meeting', $case, [], ['at' => $at->toDateTimeString()]);

        return $case->refresh();
    }

    // ------------------------------------------------------------------ القرار

    public const VERDICTS = ['chance', 'recommend_dismissal'];

    /**
     * قرار الميتينج (23-0.2-4-7): «فرصة» تُنفَّذ فورًا عبر المسار القائم
     * وتُغلق القضيّة، و«توصية إقصاء» تُرفَع لمشرف عام التطوّع وحده.
     */
    public function recordVerdict(InvestigationCase $case, string $verdict, string $reason, User $actor): InvestigationCase
    {
        if (! in_array($verdict, self::VERDICTS, true)) {
            throw ValidationException::withMessages(['verdict' => setting('volunteer_investigation.committee.verdict_unknown', 'قرارٌ غير معروف.')]);
        }

        if ($case->status !== 'open') {
            throw ValidationException::withMessages(['status' => setting('volunteer_investigation.committee.not_open', 'الملفّ مقفول لتسجيل قرارٍ جديد.')]);
        }

        if (! $case->hasSeat($actor) && ! $actor->allows('investigations.approve')) {
            throw ValidationException::withMessages(['actor' => setting('volunteer_investigation.committee.not_a_seat', 'مقعدا اللجنة وحدهما يسجّلان قرار الميتينج.')]);
        }

        DB::transaction(function () use ($case, $verdict, $reason, $actor) {
            $case->forceFill([
                'verdict' => $verdict,
                'verdict_reason' => $reason,
                'verdict_by' => $actor->id,
                'verdict_at' => now(),
                'status' => $verdict === 'chance' ? 'closed' : 'recommendation_raised',
            ]);

            if ($verdict === 'chance') {
                $this->suspension->grantChance($case->user, $actor, $reason);
                $case->forceFill(['closed_at' => now(), 'closed_by' => $actor->id]);
            }

            $case->save();
        });

        AuditTrail::log($actor, 'investigation.verdict', $case, [], ['verdict' => $verdict]);

        return $case->refresh();
    }

    public const DECISIONS = ['dismiss', 'reject_recommendation'];

    /**
     * القرار البشريّ النهائيّ (23-0.2-4-7-ب) — لمشرف عام التطوّع وحده،
     * ومعه دائمًا مبرّرٌ مكتوب.
     */
    public function decide(InvestigationCase $case, string $decision, string $reason, User $gm): InvestigationCase
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw ValidationException::withMessages(['decision' => setting('volunteer_investigation.committee.decision_unknown', 'قرارٌ غير معروف.')]);
        }

        if ($case->status !== 'recommendation_raised') {
            throw ValidationException::withMessages(['status' => setting('volunteer_investigation.committee.no_recommendation', 'لا توصية إقصاء مرفوعة على هذا الملفّ.')]);
        }

        DB::transaction(function () use ($case, $decision, $reason, $gm) {
            if ($decision === 'dismiss') {
                $offboarding = OffboardingService::open($case->user, 'exclusion', $reason, $gm);
                $case->forceFill(['offboarding_id' => $offboarding->id]);
            } else {
                $this->suspension->grantChance($case->user, $gm, $reason);
            }

            $case->forceFill([
                'decision' => $decision,
                'decision_reason' => $reason,
                'decision_by' => $gm->id,
                'decision_at' => now(),
                'status' => 'decision_issued',
            ])->save();
        });

        AuditTrail::log($gm, 'investigation.decide', $case, [], ['decision' => $decision]);

        return $case->refresh();
    }

    /** أرشفة الملفّ — مرجعٌ واحدٌ لأيّ طعن أو مراجعة لاحقة (23-0.2-4-7) */
    public function archive(InvestigationCase $case, User $actor): InvestigationCase
    {
        if ($case->status !== 'decision_issued') {
            throw ValidationException::withMessages(['status' => setting('volunteer_investigation.committee.not_decided', 'القرار البشريّ لم يصدر بعد على هذا الملفّ.')]);
        }

        $case->forceFill(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $actor->id])->save();

        AuditTrail::log($actor, 'investigation.archive', $case, [], []);

        return $case->refresh();
    }
}
