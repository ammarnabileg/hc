<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Currency;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Certificates\CertificateIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * الامتحان (24.5 · 4.2): **شاشة تركيز بلا سايد بار** — سؤال واحد في المرّة،
 * عدّاد تنازليّ، حفظ تدريجيّ، **والتصحيح والدرجة في الخادم حصرًا**.
 */
class ExamController extends Controller
{
    public function __construct(private readonly CertificateIssuer $issuer) {}

    /** بوب-أب ما قبل البدء: المدّة · المحاولات · السعر بالكوينز والرصيد قبل/بعد (24.5) */
    public function start(Exam $exam): View|RedirectResponse
    {
        $user = request()->user();
        $running = $this->runningAttempt($exam, $user);

        if ($running) {
            return redirect()->route('exams.take', $exam);
        }

        $used = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'expired'])
            ->count();

        $price = (float) $exam->price_coins;
        $balance = $user->balance($this->currencyCode());

        return view('exams.start', [
            'exam' => $exam,
            'attemptsUsed' => $used,
            'attemptsLeft' => max(0, $exam->attempts_allowed - $used),
            'price' => $price,
            'balance' => $balance,
            'balanceAfter' => $balance - $price,
            'affordable' => $balance >= $price,
            'cooldownUntil' => $this->cooldownUntil($exam, $user),
            'expiringCertificates' => $this->certificatesThatWillExpire($exam, $user),
        ]);
    }

    /** بدء المحاولة فعليًّا: خصم الكوينز وإنهاء الشهادة التأهيليّة القديمة (13.4-ق) */
    public function begin(Exam $exam, Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($this->runningAttempt($exam, $user)) {
            return redirect()->route('exams.take', $exam);
        }

        if (! $exam->is_active) {
            return back()->with('status', (string) setting('exams.messages.closed', 'الامتحان ده مقفول دلوقتي.'));
        }

        $used = ExamAttempt::query()
            ->where('exam_id', $exam->id)->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'expired'])->count();

        if ($used >= $exam->attempts_allowed) {
            return back()->with('status', (string) setting('exams.messages.no_attempts_left', 'خلصت محاولاتك في الامتحان ده.'));
        }

        if ($this->cooldownUntil($exam, $user)) {
            return back()->with('status', (string) setting('exams.messages.cooldown', 'لسّه بدري على المحاولة الجاية — استنّى شويّة وراجع الدروس.'));
        }

        $price = (float) $exam->price_coins;

        if ($price > 0 && $user->balance($this->currencyCode()) < $price) {
            return back()->with('status', (string) setting('exams.messages.insufficient_balance', 'رصيدك مايكفّيش لدخول الامتحان — اشحن محفظتك وارجع.'));
        }

        $attempt = DB::transaction(function () use ($exam, $user, $price) {
            if ($price > 0) {
                $this->charge($user, $exam, $price);
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

        return redirect()->route('exams.take', ['exam' => $exam, 'q' => 1])
            ->with('status', (string) setting('exams.messages.started', 'بالتوفيق — ركّز وخُد وقتك.'))
            ->with('attempt_id', $attempt->id);
    }

    /** شاشة الحلّ: سؤال واحد في المرّة + شريط تقدّم + شاشة مراجعة قبل التسليم (24.5) */
    public function take(Exam $exam, Request $request): View|RedirectResponse
    {
        $user = $request->user();
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
        $attempt = $this->runningAttempt($exam, $request->user());

        if (! $attempt) {
            return response()->json(['saved' => false], 409);
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

    private function currencyCode(): string
    {
        return (string) setting('exams.wallet.currency_code', 'coins');
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

    /** خصم كوينز امتحان المسار وتسجيله في الجدول الموحّد للمعاملات (19) */
    private function charge(User $user, Exam $exam, float $price): void
    {
        $currency = Currency::query()->where('code', $this->currencyCode())->first();

        if (! $currency) {
            return;
        }

        $wallet = WalletBalance::query()->firstOrCreate(
            ['user_id' => $user->id, 'currency_id' => $currency->id],
            ['balance' => 0],
        );

        $wallet->balance = (float) $wallet->balance - $price;
        $wallet->lifetime_spent = (float) $wallet->lifetime_spent + $price;
        $wallet->save();

        Transaction::create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'amount' => -$price,
            'balance_after' => $wallet->balance,
            'layer' => 'training',
            'source' => 'academy',
            'reason' => (string) setting('exams.wallet.charge_reason', 'دخول امتحان').' — '.$exam->title_ar,
            'reference_type' => $exam->getMorphClass(),
            'reference_id' => $exam->id,
        ]);
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

    private function isQualifyingExam(Exam $exam): bool
    {
        $qualifyingId = (int) setting('volunteer.qualifying.course_id', 0);

        return $qualifyingId > 0
            && $exam->examable_type === 'App\Models\Course'
            && (int) $exam->examable_id === $qualifyingId;
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

        $this->issuer->issue(
            user: $attempt->user,
            typeKey: $typeKey,
            subject: $subject,
            data: ['certificate_name' => $subject->name_ar ?? $exam->title_ar],
        );
    }
}
