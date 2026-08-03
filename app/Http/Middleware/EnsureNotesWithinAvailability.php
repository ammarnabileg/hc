<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\AvailabilityService;
use App\Services\Learning\TimezoneDetector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ⭐ **حاجز الإتاحة على ملاحظات التدريب** (الدستور 5 · 3.2).
 *
 * القسم 5 حرفيًّا: «**عدة فترات إتاحة للتدريب الواحد** — مثال: (1→7 يناير)…
 * المتدرب يوصل للتدريب فقط أثناء إحدى هذه الفترات» و«**أوقات تشغيل يومية لكل
 * تدريب** — مثال: من 5:00 ص إلى 7:00 ص… خارج الساعات دي التدريب **مقفول** حتى
 * لو فترة الإتاحة سارية» — والقياس «بالتوقيت المحلّي لدولة المستخدم».
 *
 * والملاحظة ليست شيئًا خارج التدريب: القسم 3.2 يقول «**Text Area واحد مشترك
 * لكل دروس التدريب** — الملاحظات **موحّدة على مستوى التدريب**»، فهي كتابةٌ
 * **داخل** التدريب نفسه. وكان الباب مفتوحًا: `POST /learning/courses/{slug}/notes`
 * يردّ 200 و«اتحفظ ✓» والتدريب مقفول — دليل تشغيل: تدريب نافذته اليوميّة
 * 05:00→07:00 والساعة 21:5x، فحُفظت الملاحظة.
 *
 * ⚠️ **واحتذاءً بـ`EnsureExamWithinAvailability` لا أسلوبًا ثانيًا** — نفس
 * القارئ (`AvailabilityService`) ونفس الساعة (`TimezoneDetector`) ونفس صياغة
 * الردّ (ماذا حدث + متى يفتح — 2.17)، **ولا يُقفَل الباب في وجه من يستحقّه**:
 *  1) **القراءة والتصدير يمرّان**: `GET …/notes/export` سجلٌّ لبيانات المتدرّب
 *     نفسه لا يفتح بابًا ولا يكتب حرفًا — وقياسًا على استثناء «شاشة النتيجة»
 *     في حارس الامتحان، حجبُه يخفي عن صاحبه ما كتبه هو. ولذلك الحاجز على
 *     الكتابة والمسح وحدهما (`save` · `clear`).
 *  2) **التدريب المفتوح يمرّ كما كان** — ولا يمسّ الحاجزُ الحفظَ التلقائيّ (3.2)
 *     ما دامت النافذة سارية.
 *
 * والقرار **على الخادم**: لا يكفي أن تخفي صفحة التدريب مربّع الملاحظات.
 */
class EnsureNotesWithinAvailability
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly TimezoneDetector $timezones,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $course = $request->route('course');

        // بلا تدريبٍ في المسار أو بلا جلسة ⟵ ليس هذا موضع الحاجز
        if (! $user instanceof User || ! $course instanceof Course) {
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

    /**
     * الردّ: **ماذا حدث + متى يفتح** (2.17-ج) — والسبب من خدمة الإتاحة نفسها
     * بساعة المستخدم، فلا يختلف نصّ الملاحظات عن نصّ صفحة التدريب ولا عن نصّ
     * الامتحان. والحفظ التلقائيّ يقرأ `saved:false` فيعرض الرسالة بدل «اتحفظ ✓».
     *
     * @param  array{open:bool,state:string,reason:?string}  $state
     */
    private function deny(Request $request, Course $course, ?Enrollment $enrollment, array $state): Response
    {
        $message = trim(
            (string) setting('learning.notes.course_locked')
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
