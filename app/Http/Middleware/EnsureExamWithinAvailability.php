<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\Learning\AvailabilityService;
use App\Services\Learning\TimezoneDetector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ⭐ **حاجز الإتاحة على الامتحان** (الدستور 5 · 4.2 · 8).
 *
 * القسم 5 يقول: «**عدة فترات إتاحة للتدريب الواحد** … المتدرب يوصل للتدريب فقط
 * أثناء إحدى هذه الفترات» و«**أوقات تشغيل يومية لكل تدريب** … خارج الساعات دي
 * التدريب **مقفول** حتى لو فترة الإتاحة سارية» — **بالتوقيت المحلّيّ للمستخدم**.
 *
 * والحاجز كان مفروضًا على الدروس وحدها (`ProgressService::outline`)، بينما
 * **الامتحان النهائيّ للتدريب جزءٌ من التدريب** ومسـاراته كلّها مفتوحة: كان
 * `GET /exams/{exam}/start` و`POST …/begin` و`GET …/take` و`POST …/answer`
 * و`POST …/submit` تردّ **200 والتدريب مقفول**. وليست الثغرة في الشاشة: الامتحان
 * **يُصدر الشهادة** (8 · شرط الإصدار = اجتياز الامتحان)، فتنتهي بشهادةٍ صدرت
 * خارج نافذة الإتاحة أصلًا — دليل تشغيل: امتحان تدريبٍ نافذته 05:00→07:00
 * دخله صاحبه الساعة 17:2x فنجح فصدرت له `CRS-2026-000001`.
 *
 * ⚠️ **ولا يُقفَل الباب في وجه من يستحقّه** — ثلاثة استثناءات مقصودة:
 *  1) **المحاولة الجارية**: بابٌ فُتِح داخل النافذة ودُفِعت تذكرته، فلا يُسحَب من
 *     صاحبه في منتصفه لأنّ الساعة دقّت — يكمل ويسلّم، وعدّاد المدّة يحدّه أصلًا.
 *  2) **شاشة النتيجة** (`exams/attempts/{attempt}/result`): سجلٌّ للقراءة لا
 *     يفتح بابًا ولا يُصحّح ولا يُصدر شيئًا، وقفلُه يخفي عن صاحبه نتيجة محاولةٍ
 *     دفع ثمنها. ولذلك لا `exam` في مساره — والحاجز يمرّره صراحةً.
 *  3) **امتحان شهادة المسار**: `examable` مسارٌ لا تدريب، ولا نافذةَ إتاحةٍ
 *     للمسار في المخطّط أصلًا (الفترات والساعات أعمدةٌ على `courses`) — فلا
 *     يُقاس عليه، ويبقى بابه ودفعُه بالكوينز كما هما.
 *
 * والقرار كلّه **على الخادم**: لا يكفي أن تخفي صفحة التدريب زرّ الامتحان.
 */
class EnsureExamWithinAvailability
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly TimezoneDetector $timezones,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $exam = $request->route('exam');

        // بلا امتحانٍ في المسار (شاشة النتيجة) أو بلا جلسة ⟵ ليس هذا موضع الحاجز
        if (! $user instanceof User || ! $exam instanceof Exam) {
            return $next($request);
        }

        $course = $exam->examable;

        if (! $course instanceof Course) {
            return $next($request);
        }

        if ($this->hasRunningAttempt($exam, $user)) {
            return $next($request);
        }

        // مكان المستخدم يتبعه أوّلًا بأوّل، فالنافذة تُقاس بساعته هو لا بساعة الخادم (5)
        $this->timezones->sync($request, $user);

        $enrollment = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        $state = $this->availability->forCourse($course, $enrollment, $user);

        if ($state['open']) {
            return $next($request);
        }

        return $this->deny($request, $course, $enrollment, $state);
    }

    /** محاولةٌ بدأت فعلًا ولم تُسلَّم بعد — حقٌّ مكتسَبٌ لا يُنقَض */
    private function hasRunningAttempt(Exam $exam, User $user): bool
    {
        return ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->exists();
    }

    /**
     * الردّ: **ماذا حدث + متى يفتح** (2.17-ج) — والسبب يأتي من خدمة الإتاحة
     * نفسها بساعة المستخدم، فلا يختلف نصّ الامتحان عن نصّ صفحة التدريب.
     *
     * @param  array{open:bool,state:string,reason:?string}  $state
     */
    private function deny(Request $request, Course $course, ?Enrollment $enrollment, array $state): Response
    {
        $message = trim(
            (string) setting('exams.messages.course_locked', 'الامتحان جزء من التدريب، والتدريب مقفول دلوقتي.')
            .' '.(string) ($state['reason'] ?? '')
        );

        if ($request->expectsJson()) {
            return response()->json([
                'saved' => false,
                'locked' => true,
                'message' => $message,
            ], 403);
        }

        return $enrollment
            ? redirect()->route('learning.course', $course)->with('status', $message)
            : redirect()->route('learning.courses')->with('status', $message);
    }
}
