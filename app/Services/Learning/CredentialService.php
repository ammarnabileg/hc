<?php

namespace App\Services\Learning;

use App\Models\Course;
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
    /**
     * حالة الامتحان النهائيّ للتدريب.
     *
     * @return array{exists:bool,state:string,label:string,unlocked:bool,condition:string,url:?string}
     */
    public function courseExam(User $user, Course $course, int $percent): array
    {
        $exam = DB::table('exams')
            ->where('examable_type', Course::class)
            ->where('examable_id', $course->id)
            ->where('is_active', true)
            ->first();

        $required = (int) setting('learning.exam.unlock_percent', 100);
        $unlocked = $percent >= $required;
        $condition = setting('learning.exam.unlock_condition').' '.$required.'%';

        if (! $exam) {
            return [
                'exists' => false,
                'state' => 'idle',
                'label' => setting('learning.exam.none_label'),
                'unlocked' => false,
                'condition' => $condition,
                'url' => null,
            ];
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
            'url' => $unlocked ? $this->examUrl($exam->id) : null,
        ];
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
