<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Carbon;

/**
 * الإتاحة والجدولة (الدستور 5).
 *
 * لماذا هنا؟ لأنّ «مقفول» في هذه المنصّة ليس إخفاءً: التدريب المنتهية إتاحته
 * يظهر بحالته وسببه المكتوب (24.5 — تدريباتي)، فنحتاج مصدرًا واحدًا للسبب
 * تستعمله الكروت وصفحة التدريب وصفحة الدرس معًا بلا تكرار.
 */
class AvailabilityService
{
    /**
     * حالة إتاحة التدريب لهذا المستخدم.
     *
     * @return array{open:bool,state:string,reason:?string}
     */
    public function forCourse(Course $course, ?Enrollment $enrollment = null): array
    {
        // لم يُنشَر بعد — رماديّ لا أحمر: «غير نشط» وليست حالةً سيّئة (2.16)
        if ($course->status !== setting('learning.course.published_status', 'published')) {
            return $this->closed('idle', setting('learning.lock.unpublished_reason'));
        }

        $scheduled = $course->scheduled_at ?? $course->published_at;

        if ($scheduled instanceof Carbon && $scheduled->isFuture()) {
            return $this->closed('idle', setting('learning.lock.scheduled_reason').' — '.$scheduled->translatedFormat('j F Y'));
        }

        if ($enrollment && $this->isExpired($enrollment)) {
            return $this->closed('danger', setting('learning.lock.expired_reason'));
        }

        return ['open' => true, 'state' => 'ok', 'reason' => null];
    }

    /**
     * انتهت إتاحة التسجيل: إمّا وُسِم كذلك في الجدول، أو فات موعده النهائيّ
     * دون إكمال. والمكتمل لا ينتهي أبدًا — الوصول للمُنجَز دائم (20).
     */
    public function isExpired(Enrollment $enrollment): bool
    {
        if ($enrollment->status === 'expired') {
            return true;
        }

        if ($enrollment->status === 'completed') {
            return false;
        }

        return $enrollment->deadline_at instanceof Carbon && $enrollment->deadline_at->isPast();
    }

    /** @return array{open:bool,state:string,reason:?string} */
    private function closed(string $state, ?string $reason): array
    {
        return ['open' => false, 'state' => $state, 'reason' => $reason];
    }
}
