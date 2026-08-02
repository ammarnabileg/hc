<?php

namespace App\Services\Notifications;

use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\User;

/**
 * التخصيص الديناميكيّ في نصّ المنشور (12.6-أ): «مرحبًا [اسم]» + إدراج اسم
 * التدريب والديدلاين تلقائيًّا.
 *
 * قاعدتان تحكمان التنفيذ:
 * 1) **لا يبقى وسمٌ ظاهرًا للمستخدم أبدًا** — الوسم بلا قيمة يُستبدَل ببديلٍ
 *    مهذّب من الإعدادات، فلا يقرأ أحدٌ «مرحبًا [اسم]» حرفيًّا.
 * 2) **الاستبدال وقت العرض لا وقت الحفظ** — فالمنشور الواحد يخاطب كلّ قارئ
 *    باسمه هو، ويبقى نصّه الأصليّ قابلًا للتحرير في اللوحة كما كتبه الأدمن.
 */
class AnnouncementPersonalizer
{
    /** كاش قيم المستخدم داخل الطلب الواحد — المنشورات كثيرة والقارئ واحد. */
    private array $cache = [];

    /** استبدال الوسوم في نصّ حرّ. */
    public function render(?string $text, ?User $user): string
    {
        $text = (string) $text;

        if ($text === '' || ! str_contains($text, '[')) {
            return $text;
        }

        return strtr($text, $this->values($user));
    }

    /** نسخةٌ من المنشور بنصوصٍ مخاطِبة لهذا القارئ — بلا حفظٍ في القاعدة. */
    public function apply(Announcement $announcement, ?User $user): Announcement
    {
        $copy = clone $announcement;
        $copy->title = $this->render($announcement->title, $user);
        $copy->body = $this->render($announcement->body, $user);
        $copy->cta_label = $this->render($announcement->cta_label, $user);
        $copy->poll_question = $this->render($announcement->poll_question, $user);

        return $copy;
    }

    /**
     * الوسوم المتاحة كما تُعرَض للأدمن في المحرّر — من الإعدادات لا من الكود.
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        $tokens = setting('announcements.personalization.tokens', [
            '[اسم]' => 'الاسم الأوّل للقارئ',
            '[الاسم]' => 'الاسم الكامل للقارئ',
            '[الكود]' => 'كود المستخدم',
            '[التدريب]' => 'اسم أحدث تدريب نشط',
            '[الديدلاين]' => 'ديدلاين ذلك التدريب',
        ]);

        return is_array($tokens) ? $tokens : [];
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<string, string> */
    private function values(?User $user): array
    {
        $cacheKey = $user?->id ?? 0;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $fallbackName = (string) setting('announcements.personalization.fallback_name', 'صاحبنا');
        $fallbackCourse = (string) setting('announcements.personalization.fallback_course', 'تدريبك');
        $fallbackDeadline = (string) setting('announcements.personalization.fallback_deadline', 'الموعد المحدَّد');

        $enrollment = $user ? $this->latestEnrollment($user) : null;

        return $this->cache[$cacheKey] = [
            '[اسم]' => $user?->shortName() ?: $fallbackName,
            '[الاسم]' => $user?->name ?: $fallbackName,
            '[الكود]' => (string) ($user?->code ?? ''),
            '[التدريب]' => $enrollment?->course?->name_ar ?: $fallbackCourse,
            '[الديدلاين]' => $enrollment?->deadline_at?->format('Y-m-d') ?: $fallbackDeadline,
        ];
    }

    private function latestEnrollment(User $user): ?Enrollment
    {
        return Enrollment::query()
            ->with('course:id,name_ar')
            ->where('user_id', $user->id)
            ->where('status', (string) setting('announcements.personalization.enrollment_status', 'active'))
            ->orderByRaw('deadline_at is null')
            ->orderBy('deadline_at')
            ->first();
    }
}
