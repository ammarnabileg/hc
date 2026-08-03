<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Gamification\EconomyLedger;
use App\Services\Gamification\EconomyRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * الامتحان (24.5 · 4.2): **شاشة تركيز بلا سايد بار** — سؤال واحد في المرّة،
 * عدّاد تنازليّ، حفظ تدريجيّ، **والتصحيح والدرجة في الخادم حصرًا**.
 *
 * ⭐ عملتان لا واحدة (4.2 · 7.1 · 16):
 *  - **الامتحان النهائيّ للتدريب** يكلّف **تذكرة** تُخصَم **بمجرّد الدخول**
 *    (جاوب أو ما جاوبش) — وقيمتها من جدول «أوجه الصرف» في لوحة الإدارة.
 *  - **امتحان شهادة المسار** وحده **مدفوع بالكوينز** بسعرٍ يحدّده الأدمن لكلّ مسار.
 */
class ExamController extends Controller
{
    /** مفتاح وجه الصرف في جدول «أوجه الصرف» بلوحة الإدارة (12.10) */
    private const EXAM_RULE = 'course.exam';

    public function __construct(
        private readonly CertificateIssuer $issuer,
        private readonly EconomyRules $rules,
        private readonly EconomyLedger $economy,
    ) {}

    /** بوب-أب ما قبل البدء: المدّة · المحاولات · التكلفة بعملتها والرصيد قبل/بعد (24.5) */
    public function start(Exam $exam): View|RedirectResponse
    {
        $user = request()->user();

        if ($denial = $this->enrollmentDenial($exam, $user)) {
            return $denial;
        }

        $running = $this->runningAttempt($exam, $user);

        if ($running) {
            return redirect()->route('exams.take', $exam);
        }

        $used = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'expired'])
            ->count();

        $cost = $this->cost($exam);
        $balance = $this->economy->balance($user, $cost['currency']);
        $limit = $this->attemptLimit($exam);

        return view('exams.start', [
            'exam' => $exam,
            'attemptsUsed' => $used,
            // ⭐ `null` = **بلا حدّ** (4.2): «كل دخول = تذكرة» — والتذكرة هي الحدّ
            'attemptsLeft' => $limit > 0 ? max(0, $limit - $used) : null,
            'attemptsLimit' => $limit,
            'price' => $cost['amount'],
            'currencyLabel' => $cost['label'],
            'balance' => $balance,
            'balanceAfter' => $balance - $cost['amount'],
            'affordable' => $balance >= $cost['amount'],
            // التذاكر تُكتسَب ولا تُشحَن (7.1) — فزرّ الشحن لامتحان الكوينز وحده
            'canTopup' => ! $this->isCourseExam($exam),
            'shortMessage' => $this->insufficientMessage($exam),
            'cooldownUntil' => $this->cooldownUntil($exam, $user),
            'expiringCertificates' => $this->certificatesThatWillExpire($exam, $user),
        ]);
    }

    /** بدء المحاولة فعليًّا: خصم التكلفة وإنهاء الشهادة التأهيليّة القديمة (13.4-ق) */
    public function begin(Exam $exam, Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($denial = $this->enrollmentDenial($exam, $user)) {
            return $denial;
        }

        if ($this->runningAttempt($exam, $user)) {
            return redirect()->route('exams.take', $exam);
        }

        if (! $exam->is_active) {
            return back()->with('status', (string) setting('exams.messages.closed', 'الامتحان ده مقفول دلوقتي.'));
        }

        $used = ExamAttempt::query()
            ->where('exam_id', $exam->id)->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'expired'])->count();

        $limit = $this->attemptLimit($exam);

        if ($limit > 0 && $used >= $limit) {
            return back()->with('status', (string) setting('exams.messages.no_attempts_left', 'خلصت محاولاتك في الامتحان ده.'));
        }

        if ($this->cooldownUntil($exam, $user)) {
            return back()->with('status', (string) setting('exams.messages.cooldown', 'لسّه بدري على المحاولة الجاية — استنّى شويّة وراجع الدروس.'));
        }

        $cost = $this->cost($exam);

        if ($cost['amount'] > 0 && $this->economy->balance($user, $cost['currency']) < $cost['amount']) {
            return back()->with('status', $this->insufficientMessage($exam));
        }

        $attempt = DB::transaction(function () use ($exam, $user, $cost) {
            // ⭐ الخصم **بمجرّد الدخول** — سواء جاوب أو ما جاوبش (4.2)
            if ($cost['amount'] > 0 && ! $this->charge($user, $exam, $cost)) {
                return null;
            }

            // ⭐ 13.4-ق: بمجرّد دخوله الامتحان تنتقل شهادته القديمة من نوعه إلى «منتهية» — ولا تُمسَح
            foreach ($this->expiringTypeKeys($exam) as $typeKey) {
                $this->issuer->expireForNewerExam($user, $typeKey);
            }

            return ExamAttempt::create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'exam_version' => $exam->version ?? 1,
                'started_at' => now(),
                'answers' => [],
                'status' => 'in_progress',
            ]);
        });

        // فشل الخصم في اللحظة الأخيرة (سباق على نفس الرصيد) ⟵ لا محاولة ولا خصم
        if (! $attempt) {
            return back()->with('status', $this->insufficientMessage($exam));
        }

        return redirect()->route('exams.take', ['exam' => $exam, 'q' => 1])
            ->with('status', (string) setting('exams.messages.started', 'بالتوفيق — ركّز وخُد وقتك.'))
            ->with('attempt_id', $attempt->id);
    }

    /** شاشة الحلّ: سؤال واحد في المرّة + شريط تقدّم + شاشة مراجعة قبل التسليم (24.5) */
    public function take(Exam $exam, Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($denial = $this->enrollmentDenial($exam, $user)) {
            return $denial;
        }

        $attempt = $this->runningAttempt($exam, $user);

        if (! $attempt) {
            return redirect()->route('exams.start', $exam);
        }

        if ($this->secondsLeft($attempt) <= 0) {
            $this->finish($attempt, 'expired');

            return redirect()->route('exams.result', $attempt);
        }

        $questions = $this->questions($exam);
        $total = $questions->count();
        $answers = (array) ($attempt->answers ?? []);
        $requested = $request->query('q', '1');
        $review = $requested === 'review' || $total === 0;
        $index = $review ? $total : min(max(1, (int) $requested), max(1, $total));

        return view('exams.take', [
            'exam' => $exam,
            'attempt' => $attempt,
            'questions' => $questions,
            'question' => $review ? null : $questions[$index - 1],
            'index' => $index,
            'total' => $total,
            'answers' => $answers,
            'answered' => count(array_filter($answers, fn ($a) => $a !== null && $a !== '')),
            'review' => $review,
            'secondsLeft' => $this->secondsLeft($attempt),
        ]);
    }

    /** حفظ تدريجيّ: لو الشبكة اتقطعت «إجاباتك محفوظة» ويُستأنف من مكانه (2.17-ب) */
    public function answer(Exam $exam, Request $request): JsonResponse
    {
        if ($this->enrollmentDenial($exam, $request->user())) {
            return response()->json(['saved' => false], 403);
        }

        $attempt = $this->runningAttempt($exam, $request->user());

        if (! $attempt) {
            return response()->json(['saved' => false], 409);
        }

        // انتهى الوقت وهو يكتب: نسلّم تلقائيًّا ونخبره بوضوح بدل الحفظ في الفراغ (24.5)
        if ($this->secondsLeft($attempt) <= 0) {
            $this->finish($attempt, 'expired');

            return response()->json([
                'saved' => false,
                'expired' => true,
                'message' => (string) setting('exams.messages.time_up', 'خلص الوقت — سلّمنا إجاباتك تلقائيًّا.'),
                'redirect' => route('exams.result', $attempt),
            ], 409);
        }

        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'value' => ['nullable', 'string', 'max:2000'],
        ]);

        $question = ExamQuestion::query()
            ->where('exam_id', $exam->id)
            ->where('id', $validated['question_id'])
            ->first();

        if (! $question) {
            return response()->json(['saved' => false], 404);
        }

        $answers = (array) ($attempt->answers ?? []);
        $answers[(string) $question->id] = $validated['value'];
        $attempt->update(['answers' => $answers]);

        return response()->json([
            'saved' => true,
            'message' => (string) setting('exams.messages.autosaved', 'اتحفظ ✓'),
            'seconds_left' => $this->secondsLeft($attempt),
        ]);
    }

    /** التسليم — والتصحيح في الخادم حصرًا (لا يُقبَل أيّ حساب من المتصفّح) */
    public function submit(Exam $exam, Request $request): RedirectResponse
    {
        if ($denial = $this->enrollmentDenial($exam, $request->user())) {
            return $denial;
        }

        $attempt = $this->runningAttempt($exam, $request->user());

        if (! $attempt) {
            return redirect()->route('exams.start', $exam);
        }

        $answers = (array) ($attempt->answers ?? []);

        foreach ((array) $request->input('answers', []) as $questionId => $value) {
            $answers[(string) (int) $questionId] = is_string($value) ? $value : null;
        }

        $attempt->update(['answers' => $answers]);

        $timedOut = $this->secondsLeft($attempt) <= 0;
        $this->finish($attempt, $timedOut ? 'expired' : 'submitted');

        return redirect()->route('exams.result', $attempt);
    }

    /** شاشة النتيجة: نجح/رسب · الدرجة · ما يفتحه النجاح · واحتفال ذروة عند إصدار الشهادة */
    public function result(ExamAttempt $attempt, Request $request): View|RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        if ($attempt->status === 'in_progress') {
            return redirect()->route('exams.take', $attempt->exam);
        }

        $certificate = $this->certificateFor($attempt);

        return view('exams.result', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'certificate' => $certificate,
            'celebrate' => $certificate ? $this->issuer->claimCelebration($certificate) : false,
            'timedOut' => $attempt->status === 'expired',
        ]);
    }

    // ------------------------------------------------------------ الداخل

    /**
     * ⭐ حارس التسجيل — الصلاحيّة وحدها لا تكفي (4.2 · 8).
     *
     * «الامتحان النهائيّ **للتدريب**» امتحانُ تدريبٍ بعينه، واجتيازه **يُصدر
     * الشهادة** (8). فمن لم يُسجَّل في التدريب أصلًا كان بوسعه دفع تذكرةٍ
     * والحصول على شهادةٍ **بلا أيّ تعلّم** — وذلك خرق 4.2 و8 معًا، وهدمٌ
     * لحجّية الوثيقة التي بُني عليها القسم 8.1 كلّه.
     *
     * والحارس محصورٌ في امتحان التدريب: امتحان شهادة المسار له بابه ودفعُه.
     */
    private function enrollmentDenial(Exam $exam, User $user): ?RedirectResponse
    {
        if (! $this->isCourseExam($exam) || $this->isEnrolled($exam, $user)) {
            return null;
        }

        $message = (string) setting(
            'exams.messages.not_enrolled',
            'الامتحان ده لتدريبٍ لسّه ما سجّلتش فيه — ابدأ التدريب الأوّل وهيتفتحلك.',
        );

        $course = $exam->examable;

        return $course instanceof Course
            ? redirect()->route('learning.course', $course)->with('status', $message)
            : redirect()->route('learning.courses')->with('status', $message);
    }

    /** التسجيل في التدريب صاحب الامتحان — من جدول التسجيلات لا من الجلسة */
    private function isEnrolled(Exam $exam, User $user): bool
    {
        return Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', (int) $exam->examable_id)
            ->exists();
    }

    /**
     * ⭐ تكلفة دخول الامتحان بعملتها (4.2 · 7.1 · 16).
     *
     * امتحان **التدريب** بالتذاكر — تذكرة واحدة افتراضًا من جدول «أوجه الصرف»
     * (`xp_rules.spend` ⟵ `course.exam`) لا رقمًا محروقًا؛ وامتحان **شهادة
     * المسار** وحده بالكوينز بسعره المحفوظ لكلّ مسار.
     *
     * @return array{currency:string,amount:float,label:string}
     */
    private function cost(Exam $exam): array
    {
        if ($this->isCourseExam($exam)) {
            $currency = $this->rules->spendCurrency(self::EXAM_RULE, (string) setting('exams.wallet.tickets_currency_code', 'tickets'));
            $amount = $this->rules->spendCost(self::EXAM_RULE, (float) setting('exams.tickets.course_exam', 1));

            return ['currency' => $currency, 'amount' => $amount, 'label' => $this->economy->label($currency)];
        }

        $currency = (string) setting('exams.wallet.currency_code', 'coins');

        return [
            'currency' => $currency,
            'amount' => (float) $exam->price_coins,
            'label' => $this->economy->label($currency),
        ];
    }

    /** الامتحان النهائيّ للتدريب — وهو وحده الذي يُدفَع بالتذاكر (4.2) */
    private function isCourseExam(Exam $exam): bool
    {
        return $exam->examable_type === (new Course)->getMorphClass();
    }

    /**
     * رسالة نقص الرصيد: ماذا حدث + ماذا تفعل (2.17-ج).
     * والتذاكر تُكتسَب بالتعلّم والستريك لا بالشحن — فالرسالة تختلف بالعملة.
     */
    private function insufficientMessage(Exam $exam): string
    {
        return $this->isCourseExam($exam)
            ? (string) setting('exams.messages.insufficient_tickets', 'محتاج تذكرة عشان تدخل الامتحان — كمّل درسًا أو أكمل ستريكك وهترجع تلاقيها.')
            : (string) setting('exams.messages.insufficient_balance', 'رصيدك مايكفّيش لدخول الامتحان — اشحن محفظتك وارجع.');
    }

    private function runningAttempt(Exam $exam, User $user): ?ExamAttempt
    {
        return ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();
    }

    private function questions(Exam $exam)
    {
        return ExamQuestion::query()
            ->where('exam_id', $exam->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->take((int) setting('exams.questions.max', 20))
            ->get();
    }

    private function secondsLeft(ExamAttempt $attempt): int
    {
        $endsAt = $attempt->started_at->copy()->addMinutes($attempt->exam->duration_minutes);

        return max(0, (int) now()->diffInSeconds($endsAt, false));
    }

    /**
     * ⭐ سقف المحاولات — و**صفرٌ يعني بلا حدّ** (4.2).
     *
     * نصّ 4.2 حرفيًّا: «**يكلّف تذكرة واحدة** تُخصَم **بمجرد الدخول** (سواء جاوب
     * أو ما جاوبش)، و**بدون مدة انتظار** (لا يوجد الـ 20 ثانية هنا). **كل دخول =
     * تذكرة**». فالنصّ لا يعرف سقفًا للمحاولات أصلًا، والذي يحكم الدخول هو
     * **التذكرة وحدها**: من ملك تذكرةً دخل، ومن لم يملكها لم يدخل. وكان المخطّط
     * يفتتح كلّ امتحانٍ بـ`attempts_allowed = 1` و`retry_cooldown_hours = 24`،
     * فمن رسب مُنِع من الإعادة أربعًا وعشرين ساعة أو مُنِع منها إلى الأبد — وهو
     * عكس النصّ لا تفصيلًا فيه.
     *
     * والسقف والانتظار **يبقيان مفتاحين قابلين لضبط المالك** (2.13) في تاب
     * «التقييم» من فورم التدريب؛ الملغى هو **الافتراضيّ المخالف** لا إمكانيّة
     * الضبط. والافتراضيّ الآن: بلا سقفٍ وبلا انتظار.
     *
     * ⚠️ ولا يُقاس على هذا **13.4-ق**: «مرفوض ⛔ … **الاستثناء من إعادة الامتحان**
     * لأيّ سببٍ كان» نصٌّ يمنع **إعفاء** العائد من دخول الامتحان من جديد (فهو
     * بوّابة قائمة الانتظار)، لا نصٌّ يمنع **إعادة المحاولة بعد الرسوب** — بل
     * نصّ الرسوب نفسه هناك يقول: «محاولتك الجاية متاحة [حسب قواعد الامتحان]».
     */
    private function attemptLimit(Exam $exam): int
    {
        return max(0, (int) $exam->attempts_allowed);
    }

    /** مدّة الانتظار بعد الرسوب — والافتراضيّ **صفر** تنفيذًا لـ«بدون مدة انتظار» (4.2) */
    private function cooldownUntil(Exam $exam, User $user): ?Carbon
    {
        $hours = (int) $exam->retry_cooldown_hours;

        if ($hours <= 0) {
            return null;
        }

        $last = ExamAttempt::query()
            ->where('exam_id', $exam->id)->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'expired'])
            ->latest('submitted_at')->first();

        if (! $last || ! $last->submitted_at || $last->passed) {
            return null;
        }

        $until = $last->submitted_at->copy()->addHours($hours);

        return $until->isFuture() ? $until : null;
    }

    /**
     * خصم تكلفة الدخول وتسجيلها في الجدول الموحّد للمعاملات (19).
     * والخصم يمرّ بدفتر الأستاذ وحده — فلا رصيد يتغيّر بلا سطرٍ يشرحه.
     *
     * @param  array{currency:string,amount:float,label:string}  $cost
     */
    private function charge(User $user, Exam $exam, array $cost): bool
    {
        return $this->economy->charge(
            user: $user,
            currencyCode: $cost['currency'],
            amount: $cost['amount'],
            source: 'academy',
            reference: $exam,
            reason: (string) setting('exams.wallet.charge_reason', 'دخول امتحان').' — '.$exam->title_ar,
        );
    }

    /**
     * أنواع الشهادات التي تنتهي بدخول امتحانٍ أحدث (13.4-ق).
     * **والأثر محصورٌ في الشهادة التأهيليّة وحدها** — أمّا شهادات البوزشن والخبرة
     * والتدريبات والفعاليّات فتبقى سارية ولا تُمسّ.
     */
    private function expiringTypeKeys(Exam $exam): array
    {
        $qualifyingType = (string) setting('exams.qualifying_certificate_type', 'qualifying');

        if ($this->isQualifyingExam($exam)) {
            return [$qualifyingType];
        }

        if (! $exam->requires_retake_on_version_change) {
            return [];
        }

        $key = $this->certificateTypeKey($exam);

        return $key ? [$key] : [];
    }

    /**
     * ⭐ التأهيليّ **مسارٌ لا كورس** — حسمًا لتعارض 13.4-ب مع 13.4-ق:
     * 13.4-ب هو **نصّ التعريف الحاكم** («مسار واحد شامل كورسات» و«الشهادة على
     * المسار فقط · لا شهادات لكورساته»)، و13.4-ق يصف الشهادة نفسها لا آليّة
     * ربطها. وآليّة 13.4-ق تبقى كما هي بحرفها: التأهيليّة وحدها تصير «منتهية».
     */
    private function isQualifyingExam(Exam $exam): bool
    {
        $qualifyingPathId = (int) setting('volunteer.qualifying.path_id', 0);

        return $qualifyingPathId > 0
            && $exam->examable_type === 'App\Models\LearningPath'
            && (int) $exam->examable_id === $qualifyingPathId;
    }

    /** نوع الشهادة التي يفتحها هذا الامتحان — من الإعدادات لا من الكود (2.13) */
    private function certificateTypeKey(Exam $exam): ?string
    {
        if ($this->isQualifyingExam($exam)) {
            return (string) setting('exams.qualifying_certificate_type', 'qualifying');
        }

        $map = setting('exams.certificate_type_map', [
            'App\Models\Course' => 'course',
            'App\Models\LearningPath' => 'path',
        ]);

        return is_array($map) ? ($map[$exam->examable_type] ?? null) : null;
    }

    /** الشهادة التي فتحها هذا الامتحان بعينه — للربط في شاشة النتيجة */
    private function certificateFor(ExamAttempt $attempt): ?Certificate
    {
        $exam = $attempt->exam;

        if (! $exam->examable_type || ! $exam->examable_id) {
            return null;
        }

        return Certificate::query()
            ->where('user_id', $attempt->user_id)
            ->where('subject_type', $exam->examable_type)
            ->where('subject_id', $exam->examable_id)
            ->latest('id')
            ->first();
    }

    /** لعرضها في بوب-أب ما قبل البدء بصراحة قبل الضغط على [أنا جاهز] */
    private function certificatesThatWillExpire(Exam $exam, User $user)
    {
        $keys = $this->expiringTypeKeys($exam);

        if ($keys === []) {
            return collect();
        }

        return Certificate::query()
            ->where('user_id', $user->id)
            ->where('status', 'valid')
            ->whereHas('certificate_type', fn ($q) => $q->whereIn('key', $keys))
            ->get();
    }

    /** التصحيح في الخادم حصرًا: الدرجة موزونة بأوزان الأسئلة */
    private function finish(ExamAttempt $attempt, string $status): void
    {
        if ($attempt->status !== 'in_progress') {
            return;
        }

        $exam = $attempt->exam;
        $questions = $this->questions($exam);
        $answers = (array) ($attempt->answers ?? []);

        $totalWeight = 0;
        $earned = 0;

        foreach ($questions as $question) {
            $totalWeight += $question->weight;

            if ($this->isCorrect($question, $answers[(string) $question->id] ?? null)) {
                $earned += $question->weight;
            }
        }

        $score = $totalWeight > 0 ? round(($earned / $totalWeight) * 100, 2) : 0.0;
        $passed = $score >= $exam->pass_score;

        $attempt->update([
            'submitted_at' => now(),
            'score' => $score,
            'passed' => $passed,
            'status' => $status,
        ]);

        if ($passed) {
            $this->issueCertificate($attempt);
        }
    }

    /** المقارنة تتحمّل فروق المسافات والتشكيل البسيطة، والاختيار بمطابقة تامّة */
    private function isCorrect(ExamQuestion $question, ?string $answer): bool
    {
        if ($answer === null || trim($answer) === '' || $question->correct_answer === null) {
            return false;
        }

        $normalize = fn (string $value) => trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return match ($question->type) {
            'number' => is_numeric($answer) && is_numeric($question->correct_answer)
                && abs((float) $answer - (float) $question->correct_answer) < 0.000001,
            'text' => mb_strtolower($normalize($answer)) === mb_strtolower($normalize($question->correct_answer)),
            default => $normalize($answer) === $normalize($question->correct_answer),
        };
    }

    /** الإصدار التلقائيّ عند استيفاء الشرط (8): اجتياز الامتحان بالدرجة المحدّدة */
    private function issueCertificate(ExamAttempt $attempt): void
    {
        $exam = $attempt->exam;
        $subject = $exam->examable;

        if (! $subject) {
            return;
        }

        $typeKey = $this->certificateTypeKey($exam);

        if (! $typeKey) {
            return;
        }

        /*
         | ⭐ اسم الشهادة **لا اسم العرض** (8 · 3): لكلّ تدريب اسمان، والذي
         | يُكتَب على الوثيقة هو `cert_name_*`. ولا نمرّر الاسم من هنا أصلًا —
         | المُصدِر يقرؤه من الكيان بلغة النسخة، فيبقى القرار في مكانٍ واحد
         | ولا يخرج مسارُ إصدارٍ باسمٍ ومسارٌ آخر باسمٍ مختلف.
         */
        $this->issuer->issue(
            user: $attempt->user,
            typeKey: $typeKey,
            subject: $subject,
        );
    }
}
