<?php

namespace App\Services\Volunteer\People;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Interview;
use App\Models\LearningPath;
use App\Models\Offboarding;
use App\Models\PlacementRequest;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Models\VolunteerPathAward;
use App\Services\Learning\PathService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * رحلة المتطوّع من الميثاق إلى التسكين (13.4-أ · ب · ج · هـ).
 *
 * **العطل الذي تُصلحه:** كانت الرحلة مقطوعة عند منتصفها — لا سطر في التطبيق
 * كلّه يُنشئ `RecruitmentCandidate`، فبين «إتمام التأهيليّ» و«كانبان المرشّحين»
 * فجوةٌ لا يعبرها إلّا السيدر؛ و`qualifying.completed` (1000 XP) معرَّفة بلا
 * مستدعٍ؛ والمتقدّم بلا شاشة حالة يعرف منها أين هو.
 *
 * القواعد المحفوظة بالحرف:
 *  · **التأهيليّ مسارٌ** شامل كورسات (13.4-ب — وهو نصّ التعريف الحاكم)،
 *    لا كورسًا واحدًا، والشهادة على المسار وحده.
 *  · **الميثاق يُوافَق عليه قبل بدء التأهيليّ** (13.4-أ) — لا بعده.
 *  · **1000 XP مرّة واحدة** — والقيد الفريد في `volunteer_path_awards` هو
 *    الحارس الأخير لا شرط `if`.
 *  · **التبريد بعد الخروج يسبق كلّ شيء** (13.4-س · ق): لا زرّ بدء أصلًا قبل موعده.
 *  · **«جدّد استعدادك» بتبريد** يمنع التكرار المتلاحق (13.4-هـ).
 */
class JourneyService
{
    /** محطّات الشريط الدائم (13.4-ج): تأهيليّ ⟵ مبدئيّة ⟵ مقابلة ⟵ نهائيّة ⟵ بدء */
    public const STEPS = ['qualifying', 'shortlist', 'interview', 'final_list', 'started'];

    /** المراحل التي تعني أنّ المرشّح بلغ المحطّة أو تجاوزها */
    private const REACHED = [
        'shortlist' => ['applied', 'screening', 'interview', 'final_list', 'placed'],
        'interview' => ['interview', 'final_list', 'placed'],
        'final_list' => ['final_list', 'placed'],
        'started' => ['placed'],
    ];

    public function __construct(
        private readonly PathService $paths,
        private readonly PeopleBridge $bridge,
        private readonly AuditTrail $audit,
    ) {}

    // ------------------------------------------------------------ المسار التأهيليّ

    /** المسار التأهيليّ المعتمَد — **مسار لا كورس** (13.4-ب)، و0 يعني «لم يُضبَط بعد» */
    public function path(): ?LearningPath
    {
        $id = (int) setting('volunteer.qualifying.path_id', 0);

        return $id > 0 ? LearningPath::query()->find($id) : null;
    }

    /**
     * تقدّم المتقدّم في التأهيليّ بنسبة («باقي القليل» — 13.4-ب).
     *
     * @return array{total:int,completed:int,percent:int,complete:bool}
     */
    public function progress(User $user): array
    {
        $path = $this->path();

        if (! $path) {
            return ['total' => 0, 'completed' => 0, 'percent' => 0, 'complete' => false];
        }

        $row = $this->paths->progress($user, $path);
        $row['complete'] = $row['total'] > 0 && $row['percent'] >= 100;

        return $row;
    }

    /**
     * ⭐ إتمام التأهيليّ: **1000 XP مرّة واحدة** + احتفال ذروة + إشعار.
     * تُستدعى عند كلّ فتحٍ لصفحة التطوّع فلا تتعلّق الميزة بوجود كرون —
     * على غرار مهلة الـ48 ساعة في `PlacementService::expireOverdue()`.
     *
     * @return int ما مُنِح فعلًا في هذه الجولة (0 = مُنِح قبلها أو لم يكتمل بعد)
     */
    public function completeIfDue(User $user): int
    {
        $path = $this->path();

        if (! $path || ! $this->progress($user)['complete']) {
            return 0;
        }

        $amount = $this->bridge->xpRuleValue('qualifying.completed', (int) setting('volunteer.qualifying.xp_reward', 1000));

        try {
            $award = VolunteerPathAward::create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
                'kind' => VolunteerPathAward::QUALIFYING_XP,
                'amount' => $amount,
                'awarded_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return 0; // مُنِحت قبل كده — الحارس الأخير في قاعدة البيانات
        }

        $granted = $this->bridge->awardXp(
            $user, $amount, 'volunteer_qualifying', $path,
            (string) setting('volunteer.qualifying.xp_reason', 'مكافأة إتمام المسار التأهيليّ'),
            'qualifying.completed',
        );

        if ($granted !== $amount) {
            $award->forceFill(['amount' => $granted])->save();
        }

        // احتفال ذروة عند إتمام التأهيليّ (13.4-و · 2.14)
        $this->bridge->celebrate($user, 'qualifying.completed', $path);

        $this->bridge->notify(
            $user, 'recruitment',
            (string) setting('volunteer.qualifying.done_title', 'أتممت المسار التأهيليّ 🎉'),
            (string) setting('volunteer.qualifying.done_body', 'خطوتك الجاية: ادخل قائمة الانتظار المبدئيّة.'),
            route('volunteering.landing'),
        );

        return $granted;
    }

    // ------------------------------------------------------------ المرشّح

    public function candidateOf(User $user): ?RecruitmentCandidate
    {
        return RecruitmentCandidate::query()->where('user_id', $user->id)->latest('id')->first();
    }

    /**
     * ⭐ زرّ «الدخول للمرحلة التالية» ⟵ **قائمة انتظار مبدئيّة** (13.4-ب).
     * هنا وحده يولد المرشّح — فما بين إتمام التأهيليّ ولوحة الكانبان جسرٌ لا فجوة.
     *
     * @throws RuntimeException التبريد · الميثاق · التأهيليّ غير مكتمل · مرشّح قائم
     */
    public function enterPipeline(User $user): RecruitmentCandidate
    {
        if ($existing = $this->candidateOf($user)) {
            if ($existing->stage !== 'rejected') {
                return $existing;
            }
        }

        $gate = $this->startGate($user);

        if (! $gate['open']) {
            throw new RuntimeException($gate['message']);
        }

        if (! $this->progress($user)['complete']) {
            throw new RuntimeException((string) setting('volunteer.journey.incomplete_message',
                'لسّه المسار التأهيليّ مش مكتمل — كمّل اللي فاضل وهتلاقي الزرّ في انتظارك.'));
        }

        // المكافأة قبل الدخول — فمن أتمّ ودخل مباشرةً لا يفوته حقّه
        $this->completeIfDue($user);

        $exit = $this->lastExit($user);

        $candidate = DB::transaction(function () use ($user, $exit) {
            return RecruitmentCandidate::create([
                'user_id' => $user->id,
                'stage' => 'applied',
                'applied_at' => now(),
                'stage_changed_at' => now(),
                'qualifying_score' => $this->qualifyingScore($user),
                'course_scores' => $this->courseScores($user),
                // شارة «عائد» + سجلّه السابق أمام فريق التوظيف (13.4-ق-هـ)
                'is_returning' => (bool) $exit,
                'previous_service_from' => $exit?->created_at,
                'previous_service_to' => $exit?->completed_at,
                'previous_exit_type' => $exit?->type,
            ]);
        });

        $this->audit->record($user, 'candidate.entered_pipeline', $candidate, [], ['stage' => 'applied']);

        $this->bridge->notify(
            $user, 'recruitment',
            (string) setting('volunteer.journey.shortlist_title', 'دخلت قائمة الانتظار المبدئيّة ✓'),
            (string) setting('volunteer.journey.waiting_copy', 'طلبك تحت المراجعة، هنتواصل معاك قريبًا.'),
            route('volunteering.landing'),
        );

        return $candidate;
    }

    /**
     * ⭐ «جدّد استعدادك» (13.4-هـ): يرفعه في القائمة الافتراضيّة **بلا أن يمحو
     * مدّة انتظاره الحقيقيّة** — فالتاريخان مفصولان: `applied_at` للانتظار،
     * و`readiness_renewed_at` للترتيب. وبمهلة تبريد تمنع التكرار المتلاحق.
     *
     * @throws RuntimeException داخل التبريد أو خارج مرحلة الانتظار
     */
    public function renewReadiness(RecruitmentCandidate $candidate): RecruitmentCandidate
    {
        if (! in_array($candidate->stage, ['applied', 'screening', 'interview', 'final_list'], true)) {
            throw new RuntimeException((string) setting('volunteer.journey.renew_not_waiting',
                'التجديد بينفع وأنت في الانتظار بس.'));
        }

        $available = $this->renewAvailableAt($candidate);

        if ($available && $available->isFuture()) {
            throw new RuntimeException(str_replace(
                ':date', $available->translatedFormat('j F Y'),
                (string) setting('volunteer.journey.renew_cooldown_message',
                    'جدّدت استعدادك من فترة قريّبة — تقدر تجدّد تاني يوم :date.'),
            ));
        }

        $candidate->forceFill([
            'renewed_readiness' => true,
            'readiness_renewed_at' => now(),
        ])->save();

        $this->audit->record($candidate->user, 'candidate.readiness_renewed', $candidate, [], [
            'stage' => $candidate->stage,
        ]);

        return $candidate;
    }

    /** متى يُتاح التجديد التالي؟ — `null` يعني متاح الآن */
    public function renewAvailableAt(RecruitmentCandidate $candidate): ?Carbon
    {
        $last = $candidate->readiness_renewed_at;

        if (! $last) {
            return null;
        }

        return $last->copy()->addDays(max(0, (int) setting('volunteer.journey.renew_cooldown_days', 14)));
    }

    public function canRenew(RecruitmentCandidate $candidate): bool
    {
        $at = $this->renewAvailableAt($candidate);

        return in_array($candidate->stage, ['applied', 'screening', 'interview', 'final_list'], true)
            && (! $at || $at->isPast());
    }

    // ------------------------------------------------------------ الميثاق والتبريد

    /** نصّ ميثاق المتطوّع — يُدار من لوحة الإدارة (13.4-أ) */
    public function charterText(): string
    {
        return (string) setting('volunteer_page.charter_text', '');
    }

    public function hasAcceptedCharter(User $user): bool
    {
        return $user->volunteer_charter_accepted_at !== null;
    }

    /** ⭐ الموافقة على الميثاق **قبل** بدء التأهيليّ — ترفع الالتزام (13.4-أ) */
    public function acceptCharter(User $user): void
    {
        if ($this->hasAcceptedCharter($user)) {
            return;
        }

        $user->forceFill(['volunteer_charter_accepted_at' => now()])->saveQuietly();

        $this->audit->record($user, 'volunteer.charter_accepted', $user, [], ['at' => now()->toDateTimeString()]);
    }

    /** آخر خروجٍ مكتمل — منه يُقرأ التبريد وشارة «عائد» */
    public function lastExit(User $user): ?Offboarding
    {
        return Offboarding::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->latest('id')
            ->first();
    }

    /**
     * بوّابة البدء: التبريد بعد الخروج يسبق كلّ شيء (13.4-ق-أ)،
     * ثمّ الميثاق. والمرفوض بوّابته مغلقة برسالة محترمة لا بصمت.
     *
     * @return array{open:bool,state:string,message:string,until:?Carbon,excluded:bool}
     */
    public function startGate(User $user): array
    {
        $exit = Offboarding::query()->where('user_id', $user->id)->latest('id')->first();

        $excluded = $exit?->type === 'exclusion'
            && ! (bool) setting('volunteer.offboarding.exclusion_allows_return', false);

        if ($excluded) {
            return [
                'open' => false,
                'state' => 'danger',
                'excluded' => true,
                'until' => null,
                'message' => (string) setting('volunteer.offboarding.excluded_copy',
                    'العودة بعد الاستبعاد بتحتاج قرارًا من مشرف عام التطوّع. تواصل معنا من صفحة الدعم.'),
            ];
        }

        $until = $exit?->cooldown_until;

        if ($until && now()->lessThan($until)) {
            return [
                'open' => false,
                'state' => 'warn',
                'excluded' => false,
                'until' => $until,
                'message' => str_replace('{date}', $until->translatedFormat('j F Y'),
                    (string) setting('volunteer.offboarding.cooldown_copy',
                        'أهلًا بعودتك 👋 مكانك محفوظ عندنا. تقدر تبدأ من جديد يوم {date}.')),
            ];
        }

        if (! $this->hasAcceptedCharter($user)) {
            return [
                'open' => false,
                'state' => 'idle',
                'excluded' => false,
                'until' => null,
                'message' => (string) setting('volunteer_page.charter_required_message',
                    'اقرأ ميثاق المتطوّع ووافق عليه الأوّل — بعدها يفتح لك المسار التأهيليّ.'),
            ];
        }

        return ['open' => true, 'state' => 'ok', 'excluded' => false, 'until' => null, 'message' => ''];
    }

    // ------------------------------------------------------------ شاشة الحالة (13.4-ج)

    /**
     * شريط التقدّم الدائم + بطاقة «حالتي» + الخطوة الجاية.
     *
     * @return array{steps:list<array{key:string,label:string,done:bool,current:bool}>,
     *               current:string,candidate:?RecruitmentCandidate,next:string,
     *               status_label:string,status_state:string,interview:?Interview,
     *               placement:?PlacementRequest,progress:array}
     */
    public function status(User $user): array
    {
        $candidate = $this->candidateOf($user);
        $progress = $this->progress($user);
        $labels = $this->stepLabels();

        $done = [
            'qualifying' => $candidate !== null || $progress['complete'],
            'shortlist' => $candidate !== null && in_array($candidate->stage, self::REACHED['shortlist'], true),
            'interview' => $candidate !== null && in_array($candidate->stage, self::REACHED['interview'], true),
            'final_list' => $candidate !== null && in_array($candidate->stage, self::REACHED['final_list'], true),
            'started' => $candidate !== null && in_array($candidate->stage, self::REACHED['started'], true),
        ];

        $current = 'qualifying';

        foreach (self::STEPS as $step) {
            if (! $done[$step]) {
                $current = $step;
                break;
            }

            $current = $step;
        }

        $steps = [];

        foreach (self::STEPS as $step) {
            $steps[] = [
                'key' => $step,
                'label' => $labels[$step],
                'done' => $done[$step],
                'current' => $step === $current,
            ];
        }

        return [
            'steps' => $steps,
            'current' => $current,
            'candidate' => $candidate,
            'progress' => $progress,
            'next' => $this->nextStepCopy($current, $candidate, $progress),
            'status_label' => $labels[$current],
            'status_state' => $current === 'started' ? 'ok' : 'warn',
            'interview' => $candidate ? $this->upcomingInterview($candidate) : null,
            'placement' => $candidate ? $this->pendingPlacement($candidate) : null,
        ];
    }

    /** أسماء المحطّات — من الإعدادات لا من الكود (2.13) */
    public function stepLabels(): array
    {
        return [
            'qualifying' => (string) setting('volunteer.journey.step.qualifying', 'المسار التأهيليّ'),
            'shortlist' => (string) setting('volunteer.journey.step.shortlist', 'قائمة مبدئيّة'),
            'interview' => (string) setting('volunteer.journey.step.interview', 'مقابلة'),
            'final_list' => (string) setting('volunteer.journey.step.final_list', 'قائمة نهائيّة'),
            'started' => (string) setting('volunteer.journey.step.started', 'بدء'),
        ];
    }

    /** «الخطوة الجاية» واضحة بعد كلّ مرحلة — ونصوصها قابلة للتعديل من الأدمن (13.4-ج) */
    public function nextStepCopy(string $current, ?RecruitmentCandidate $candidate, array $progress = []): string
    {
        if ($current === 'qualifying' && $candidate === null) {
            $percent = (int) ($progress['percent'] ?? 0);

            // تذكير لطيف لمن بدأ وما كمّلش — بلا إلحاح (13.4-ج)
            return $percent > 0 && $percent < 100
                ? (string) setting('volunteer.journey.reminder_copy', 'باقي القليل على إتمام التأهيليّ — كمّل من حيث وقفت.')
                : (string) setting('volunteer.journey.next.qualifying', 'ابدأ المسار التأهيليّ وكمّله عشان تدخل قائمة الانتظار.');
        }

        return match ($current) {
            'shortlist' => (string) setting('volunteer.journey.next.shortlist', 'طلبك تحت المراجعة، وهنتواصل معاك لتحديد موعد المقابلة.'),
            'interview' => (string) setting('volunteer.journey.next.interview', 'استعدّ لمقابلتك — هتلاقي الرابط والموعد هنا.'),
            'final_list' => (string) setting('volunteer.journey.next.final_list', 'أنت في القائمة النهائيّة — فاضل اختيار القسم المناسب ليك.'),
            'started' => (string) setting('volunteer.journey.next.started', 'أهلًا بيك معانا — لوحة التطوّع بقت متاحة ليك.'),
            default => (string) setting('volunteer.journey.next.qualifying', 'ابدأ المسار التأهيليّ وكمّله عشان تدخل قائمة الانتظار.'),
        };
    }

    /** رابط المقابلة وموعدها — ومعه عدّاد نازل في الصفحة (13.4-ج) */
    public function upcomingInterview(RecruitmentCandidate $candidate): ?Interview
    {
        return Interview::query()
            ->where('recruitment_candidate_id', $candidate->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at')
            ->first();
    }

    /** طلب تسكين معلَّق — لازم يدخل يوافق/يرفض داخل المهلة (13.4-هـ) */
    public function pendingPlacement(RecruitmentCandidate $candidate): ?PlacementRequest
    {
        return PlacementRequest::query()
            ->with(['entity', 'position'])
            ->where('recruitment_candidate_id', $candidate->id)
            ->whereIn('status', ['sent', 'awaiting'])
            ->where('respond_due_at', '>', now())
            ->latest('id')
            ->first();
    }

    // ------------------------------------------------------------ الدرجات

    /** درجة امتحان المسار التأهيليّ — أعلى محاولة ناجحة */
    private function qualifyingScore(User $user): ?float
    {
        $path = $this->path();

        if (! $path) {
            return null;
        }

        $examIds = Exam::query()
            ->where('examable_type', $path->getMorphClass())
            ->where('examable_id', $path->id)
            ->pluck('id');

        if ($examIds->isEmpty()) {
            return null;
        }

        $score = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->max('score');

        return $score === null ? null : (float) $score;
    }

    /** درجات كورسات التأهيليّ — تظهر على بطاقة المرشّح (13.4-د) */
    private function courseScores(User $user): array
    {
        $path = $this->path();

        if (! $path) {
            return [];
        }

        $courseIds = DB::table('course_learning_path')
            ->where('learning_path_id', $path->id)
            ->pluck('course_id');

        if ($courseIds->isEmpty()) {
            return [];
        }

        $exams = Exam::query()
            ->where('examable_type', 'App\Models\Course')
            ->whereIn('examable_id', $courseIds)
            ->pluck('examable_id', 'id');

        if ($exams->isEmpty()) {
            return [];
        }

        $rows = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('exam_id', $exams->keys())
            ->get(['exam_id', 'score']);

        $out = [];

        foreach ($rows as $row) {
            $courseId = (int) ($exams[$row->exam_id] ?? 0);

            if ($courseId === 0) {
                continue;
            }

            $out[$courseId] = max((float) ($out[$courseId] ?? 0), (float) $row->score);
        }

        return $out;
    }
}
