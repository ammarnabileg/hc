<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * نقطة التكامل مع مجال الامتحانات والشهادات (يبنيه مجال آخر).
 *
 * لماذا طبقة وسيطة؟ لأنّ شارتَي «الامتحان» و«الشهادة» مطلوبتان على كروت تدريباتي
 * قبل أن يجهز ذلك المجال، فنقرأ حالتهما من جداولهما، ونربط بأسماء المسارات
 * محميّةً بـRoute::has فلا تنكسر الشاشة إن لم تكن مسجَّلة بعد.
 */
class CredentialService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * حالة الامتحان النهائيّ للتدريب.
     *
     * ⭐ **والإتاحة جزءٌ من الحالة لا زينةٌ فوقها** (5 · 24.5): كان البلوك يقرأ
     * التقدّم وحده، فيعرض [ادخل الامتحان] والتدريب خارج نافذته — والخادم يردّ
     * (`EnsureExamWithinAvailability`). زرٌّ يَعِد بما لا يقع.
     *
     * و24.5 (صفحة التدريب) لا يريده مخفيًّا أيضًا: «**الدروس المقفولة تظهر بقفل
     * وسببٍ مكتوب** … لا مخفيّة. أسفلها **بلوك الامتحان النهائيّ بحالته وشرط
     * فتحه**» — فيبقى ظاهرًا بحالته، ويحمل `locked` و`lock_reason` بدل الزرّ.
     *
     * والسبب من `AvailabilityService` نفسها بساعة المستخدم، وبنفس صياغة الحارس
     * — فلا يقول له البلوك شيئًا ويقول له الخادم شيئًا آخر.
     *
     * @param  array{open:bool,state:string,reason:?string}|null  $availability
     *                                                                          حالة الإتاحة المحسوبة سلفًا (تجنّبًا لإعادة الحساب في القوائم)؛
     *                                                                          وإن لم تُمرَّر قرأتها الخدمة بنفسها فلا يسقط الحارس بالنسيان.
     * @return array{exists:bool,state:string,label:string,unlocked:bool,condition:string,url:?string,locked:bool,lock_reason:?string,locked_label:string}
     */
    public function courseExam(User $user, Course $course, int $percent, ?array $availability = null): array
    {
        $exam = DB::table('exams')
            ->where('examable_type', Course::class)
            ->where('examable_id', $course->id)
            ->where('is_active', true)
            ->first();

        $required = (int) setting('learning.exam.unlock_percent', 100);
        $unlocked = $percent >= $required;
        $condition = setting('learning.exam.unlock_condition').' '.$required.'%';

        $availability ??= $this->availability->forCourse(
            $course,
            Enrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->first(),
            $user,
        );

        $locked = ! ($availability['open'] ?? true);
        $lockReason = $locked
            ? trim((string) setting('exams.messages.course_locked').' '.(string) ($availability['reason'] ?? ''))
            : null;

        $lock = [
            'locked' => $locked,
            'lock_reason' => $lockReason,
            'locked_label' => (string) setting('learning.exam.locked_badge'),
        ];

        if (! $exam) {
            return [
                'exists' => false,
                'state' => 'idle',
                'label' => setting('learning.exam.none_label'),
                'unlocked' => false,
                'condition' => $condition,
                'url' => null,
            ] + $lock;
        }

        $passed = DB::table('exam_attempts')
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->where('passed', true)
            ->exists();

        $attempted = DB::table('exam_attempts')
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->exists();

        return [
            'exists' => true,
            'state' => $passed ? 'ok' : ($attempted ? 'warn' : 'idle'),
            'label' => $passed
                ? setting('learning.exam.passed_label')
                : ($attempted ? setting('learning.exam.attempted_label') : setting('learning.exam.pending_label')),
            'unlocked' => $unlocked,
            'condition' => $condition,
            // ⭐ الرابط يسقط مع القفل: الوعد لا يُكتَب إلّا حين يقع
            'url' => $unlocked && ! $locked ? $this->examUrl($exam->id) : null,
        ] + $lock;
    }

    /**
     * شارة الشهادة — والمصدر الواحد هو جدول الشهادات نفسه (24.5 — شهاداتي).
     *
     * @return array{exists:bool,state:string,label:string,url:?string}
     */
    public function certificateBadge(User $user, Course|LearningPath $subject): array
    {
        $row = DB::table('certificates')
            ->where('user_id', $user->id)
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->id)
            ->orderByDesc('issued_at')
            ->first();

        if (! $row) {
            return [
                'exists' => false,
                'state' => 'idle',
                'label' => setting('learning.certificate.none_label'),
                'url' => null,
            ];
        }

        return [
            'exists' => true,
            'state' => $row->status === 'valid' ? 'honor' : 'idle',
            'label' => $row->status === 'valid'
                ? setting('learning.certificate.issued_label')
                : setting('learning.certificate.inactive_label'),
            'url' => Route::has('learning.certificates') ? route('learning.certificates') : null,
        ];
    }

    /**
     * ⭐ امتحان شهادة المسار وسعره — **من صفّ الامتحان وحده** (12.4-أ · 2.13).
     *
     * كان يرتدّ إلى إعدادٍ عامّ حين لا يجد صفًّا، فيُعرَض للمتدرّب سعرٌ (150)
     * لا يعرفه الأدمن الذي يرى في شاشته صفرًا — سعرٌ بمصدرين متعارضين وخللٌ
     * ماليّ مباشر. فلا ارتداد بعد اليوم: بلا صفّ امتحان لا سعر ولا امتحان.
     */
    public function pathExam(LearningPath $path): array
    {
        $exam = DB::table('exams')
            ->where('examable_type', LearningPath::class)
            ->where('examable_id', $path->id)
            ->where('is_active', true)
            ->first();

        return [
            'exists' => (bool) $exam,
            'price' => (int) ($exam->price_coins ?? 0),
            'url' => $exam ? $this->examUrl($exam->id) : null,
        ];
    }

    private function examUrl(int|string $examId): ?string
    {
        return Route::has('exams.start') ? route('exams.start', ['exam' => $examId]) : null;
    }
}
