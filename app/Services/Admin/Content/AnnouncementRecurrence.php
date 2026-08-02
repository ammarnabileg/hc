<?php

namespace App\Services\Admin\Content;

use App\Models\Announcement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * الجدولة المتكرّرة للمنشورات (12.6-أ · 24.3).
 *
 * لماذا **نسخةٌ جديدة** في كلّ دورة بدل «إعادة نشر» نفس الصفّ؟ لأنّ القراءة
 * والإقرار والتصويت مربوطة برقم المنشور: لو أعدنا نشر الصفّ نفسه لظهر «مقروء»
 * لمن قرأه المرّة الماضية، ولصار الإقرار المكافَأ مرّةً واحدة **مقفولًا للأبد**.
 * فالدورة الجديدة منشورٌ جديد يحمل أثر أبيه في `recurrence_parent_id`.
 *
 * والقالب الأب **لا يُنشَر بنفسه**: حالته `scheduled` وهو مصدرُ الدورات لا دورة.
 */
class AnnouncementRecurrence
{
    /** الترددات المتاحة — من الإعدادات فلا نصّ محروق (2.13). */
    public static function frequencies(): array
    {
        $list = setting('announcements.recurrence.frequencies', [
            'daily' => 'يوميًّا',
            'weekly' => 'أسبوعيًّا',
            'monthly' => 'شهريًّا',
        ]);

        return is_array($list) ? $list : [];
    }

    /** القوالب المستحقّة الآن — أساس الأمر و«معاينة بلا تنفيذ». */
    public function due(?CarbonImmutable $now = null): Collection
    {
        $now = $now ?? CarbonImmutable::now();

        return Announcement::query()
            ->whereNotNull('recurrence')
            ->whereIn('recurrence', array_keys(self::frequencies()))
            ->whereNull('recurrence_parent_id')
            ->get()
            ->filter(fn (Announcement $template) => $this->isDue($template, $now))
            ->values();
    }

    /**
     * توليد الدورات المستحقّة.
     *
     * @return array{generated: int, ended: int}
     */
    public function run(?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();
        $generated = 0;
        $ended = 0;

        foreach ($this->due($now) as $template) {
            $this->publishOccurrence($template, $now);
            $generated++;
        }

        // القوالب التي تجاوزت مدّاها: يُطفأ تكرارها ولا يُحذف شيء (2.11-د)
        $expired = Announcement::query()
            ->whereNotNull('recurrence')
            ->whereNull('recurrence_parent_id')
            ->whereNotNull('recurrence_until')
            ->where('recurrence_until', '<=', $now)
            ->get();

        foreach ($expired as $template) {
            $template->update(['recurrence' => null]);
            $ended++;
        }

        return ['generated' => $generated, 'ended' => $ended];
    }

    /** موعد الدورة التالية — يُعرَض للأدمن في الجدول فيعرف متى يخرج التالي. */
    public function nextRunAt(Announcement $template, ?CarbonImmutable $now = null): ?CarbonImmutable
    {
        $now = $now ?? CarbonImmutable::now();

        if (blank($template->recurrence) || ! isset(self::frequencies()[$template->recurrence])) {
            return null;
        }

        $next = $this->nextDueAt($template);

        if ($template->recurrence_until && $next->greaterThan(CarbonImmutable::parse($template->recurrence_until))) {
            return null;
        }

        return $next->lessThan($now) ? $now : $next;
    }

    // ------------------------------------------------------------------ داخليّ

    private function isDue(Announcement $template, CarbonImmutable $now): bool
    {
        if ($template->recurrence_until && CarbonImmutable::parse($template->recurrence_until)->lessThanOrEqualTo($now)) {
            return false;
        }

        return $this->nextDueAt($template)->lessThanOrEqualTo($now);
    }

    /**
     * أوّل دورة تخرج **في موعد البدء نفسه** لا بعد فترةٍ منه — وإلّا ضاعت
     * الدورة الأولى بصمت وظنّ الأدمن أنّ الجدولة لا تعمل.
     */
    private function nextDueAt(Announcement $template): CarbonImmutable
    {
        if ($template->recurrence_last_at) {
            return $this->advance(CarbonImmutable::parse($template->recurrence_last_at), (string) $template->recurrence);
        }

        return CarbonImmutable::parse($template->scheduled_at ?? $template->created_at ?? now());
    }

    private function advance(CarbonImmutable $from, string $frequency): CarbonImmutable
    {
        return match ($frequency) {
            'daily' => $from->addDay(),
            'weekly' => $from->addWeek(),
            'monthly' => $from->addMonthNoOverflow(),
            default => $from->addDay(),
        };
    }

    private function publishOccurrence(Announcement $template, CarbonImmutable $now): Announcement
    {
        $days = (int) setting('announcements.auto_archive.days', 30);

        $occurrence = $template->replicate([
            'created_at', 'updated_at', 'recurrence', 'recurrence_until', 'recurrence_last_at',
        ]);

        $occurrence->recurrence = null;
        $occurrence->recurrence_until = null;
        $occurrence->recurrence_last_at = null;
        $occurrence->recurrence_parent_id = $template->id;
        $occurrence->status = (string) setting('announcements.status.published', 'published');
        $occurrence->scheduled_at = $now;
        $occurrence->expires_at = $days > 0 ? $now->addDays($days) : null;
        // التثبيت لا يورَّث: سقف المثبَّت ثلاثة، ودورةٌ يوميّة تكنسه في أيّام (24.3)
        $occurrence->is_pinned = false;
        $occurrence->save();

        $template->update(['recurrence_last_at' => $now]);

        return $occurrence;
    }
}
