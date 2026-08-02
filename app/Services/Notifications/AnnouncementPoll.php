<?php

namespace App\Services\Notifications;

use App\Models\Announcement;
use App\Models\AnnouncementPollVote;
use App\Models\User;

/**
 * استطلاع داخل المنشور (12.6-أ · 24.3) — بنوعيه: **عامّ النتيجة** و**مخفيّ النتيجة**.
 *
 * ⛔ **قاعدة حاكمة (2.9 — ممنوع Dark Patterns):** المخفيّ يبقى مخفيًّا **فعلًا**
 * لا شكليًّا. لذلك الحساب نفسه لا يقع أصلًا حين تكون النتيجة محجوبة: الدالّة
 * `resultsFor()` ترجع `null` فلا يصل عددٌ واحد إلى الـHTML ولا إلى الـJSON —
 * لا صفّ مخفيّ بـ`display:none` ولا `data-` تحمل الأرقام ليقرأها فضوليّ.
 *
 * ومتى تُكشَف؟ حين يختار الأدمن «عامّة»، أو حين **يُغلَق الاستطلاع** (بموعد
 * إغلاقه أو بانتهاء المنشور/أرشفته) — فالوعد «تشوفها بعد الإغلاق» يُوفى.
 */
class AnnouncementPoll
{
    /** هل لهذا المنشور استطلاع أصلًا؟ */
    public function has(Announcement $announcement): bool
    {
        return trim((string) $announcement->poll_question) !== '' && $this->options($announcement) !== [];
    }

    /** @return array<int, string> */
    public function options(Announcement $announcement): array
    {
        $options = $announcement->poll_options;

        if (! is_array($options)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($option) => trim((string) $option),
            $options,
        ), fn (string $option) => $option !== ''));
    }

    /** الاستطلاع مقفول: بلغ موعد إغلاقه أو انتهى المنشور/أُرشف. */
    public function isClosed(Announcement $announcement): bool
    {
        if ($announcement->poll_closes_at && ! $announcement->poll_closes_at->isFuture()) {
            return true;
        }

        if ($announcement->expires_at && ! $announcement->expires_at->isFuture()) {
            return true;
        }

        return $announcement->status === (string) setting('announcements.status.archived', 'archived');
    }

    /** هل يجوز لهذا المستخدم رؤية الأرقام الآن؟ (عامّة · أو بعد الإغلاق) */
    public function resultsVisible(Announcement $announcement): bool
    {
        return (bool) $announcement->poll_results_public || $this->isClosed($announcement);
    }

    /**
     * الأرقام **أو لا شيء**: `null` تعني «لا تحسب ولا ترسل» لا «أرسِل واخفِ».
     *
     * @return array{counts: array<int, int>, total: int}|null
     */
    public function resultsFor(Announcement $announcement): ?array
    {
        if (! $this->has($announcement) || ! $this->resultsVisible($announcement)) {
            return null;
        }

        return $this->tally($announcement);
    }

    /**
     * الأرقام للأدمن في شاشة التحليلات — سلطةٌ مقيَّدة بالصلاحيّة على المسار،
     * لا تسريبٌ للمستخدم (12.6-أ: «تحليلات عميقة» حقٌّ للأدمن وحده).
     *
     * @return array{counts: array<int, int>, total: int}
     */
    public function tally(Announcement $announcement): array
    {
        $counts = array_fill(0, count($this->options($announcement)), 0);

        $rows = AnnouncementPollVote::query()
            ->where('announcement_id', $announcement->id)
            ->selectRaw('option_index, count(*) as total')
            ->groupBy('option_index')
            ->pluck('total', 'option_index');

        foreach ($rows as $index => $total) {
            if (array_key_exists((int) $index, $counts)) {
                $counts[(int) $index] = (int) $total;
            }
        }

        return ['counts' => $counts, 'total' => array_sum($counts)];
    }

    /** اختيار هذا المستخدم — يعرفه هو دائمًا حتى لو النتيجة مخفيّة. */
    public function choiceOf(Announcement $announcement, User $user): ?int
    {
        $vote = AnnouncementPollVote::query()
            ->where('announcement_id', $announcement->id)
            ->where('user_id', $user->id)
            ->first();

        return $vote?->option_index;
    }

    /**
     * تصويت — صوتٌ واحد لكلّ مستخدم، وتبديل الخيار قبل الإغلاق مسموح
     * (تصحيح خطأٍ حقٌّ لا حيلة)، وبعد الإغلاق مرفوض.
     */
    public function vote(Announcement $announcement, User $user, int $optionIndex): void
    {
        AnnouncementPollVote::updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['option_index' => $optionIndex],
        );
    }

    /**
     * ما يُسلَّم للواجهة: السؤال والخيارات واختيار صاحب الشاشة —
     * والنتائج **فقط** حين يجوز كشفها.
     *
     * @return array<string, mixed>|null
     */
    public function viewModel(Announcement $announcement, User $user): ?array
    {
        if (! $this->has($announcement)) {
            return null;
        }

        $results = $this->resultsFor($announcement);

        return [
            'question' => (string) $announcement->poll_question,
            'options' => $this->options($announcement),
            'choice' => $this->choiceOf($announcement, $user),
            'closed' => $this->isClosed($announcement),
            'results' => $results,
            // ما يُقال للمستخدم بصراحة حين تكون النتيجة محجوبة (2.17 — لا غموض)
            'hidden_notice' => $results === null
                ? (string) setting('announcements.poll.hidden_notice', 'النتيجة مخفيّة لحدّ ما الاستطلاع يقفل.')
                : null,
        ];
    }
}
