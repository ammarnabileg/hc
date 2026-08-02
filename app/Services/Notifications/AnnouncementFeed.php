<?php

namespace App\Services\Notifications;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * فيد التعليمات (الدستور 13.2 · 24.5): قناة بثّ اتّجاه واحد بلا ردود.
 *
 * لماذا الفلترة في PHP لا في SQL؟ لأنّ الاستهداف (audience) شرائح متداخلة
 * (مسار/تدريب/دور/مستخدم) وتقييمها في JSON عبر قواعد بيانات مختلفة هشّ،
 * وعدد المنشورات الحيّة صغير بطبعه ومحكوم بسقف من الإعدادات.
 */
class AnnouncementFeed
{
    /** كاش سياق المستخدم داخل الطلب الواحد: user_id => tokens */
    private array $context = [];

    /** المنشورات الحيّة التي تخصّ هذا المستخدم — المثبَّت أعلى القائمة دائمًا. */
    public function for(User $user): Collection
    {
        return $this->liveQuery()
            ->limit((int) setting('announcements.feed.max_items', 200))
            ->get()
            ->filter(fn (Announcement $a) => $this->matches($a, $user))
            ->values();
    }

    /** سجلّات القراءة/الإقرار/التفاعل لهذا المستخدم مفهرسةً برقم المنشور. */
    public function readsFor(User $user, Collection $announcements): Collection
    {
        if ($announcements->isEmpty()) {
            return collect();
        }

        return AnnouncementRead::query()
            ->where('user_id', $user->id)
            ->whereIn('announcement_id', $announcements->pluck('id'))
            ->get()
            ->keyBy('announcement_id');
    }

    /** عدّاد غير المقروء — يظهر في رأس الصفحة وفي السايد بار. */
    public function unreadCount(User $user): int
    {
        $items = $this->for($user);
        $read = $this->readsFor($user, $items);

        return $items->reject(fn (Announcement $a) => (bool) ($read[$a->id]->read_at ?? null))->count();
    }

    /**
     * أوّل منشور حرج لم يُقَرّ بعد — يفتح بوب-أب الإقرار الإلزاميّ قبل المتابعة.
     */
    public function pendingAcknowledge(User $user): ?Announcement
    {
        $items = $this->for($user)->where('requires_acknowledge', true);
        $read = $this->readsFor($user, $items);

        return $items->first(fn (Announcement $a) => ! ($read[$a->id]->acknowledged_at ?? null));
    }

    /** هل يرى هذا المستخدمُ هذا المنشورَ الآن؟ (حياة المنشور + شريحة الاستهداف) */
    public function isVisibleTo(Announcement $announcement, User $user): bool
    {
        return $this->isLive($announcement) && $this->matches($announcement, $user);
    }

    /** المنشور حيّ: منشور · حان موعده · لم تنتهِ صلاحيّته (يُؤرشَف تلقائيًّا). */
    public function isLive(Announcement $announcement): bool
    {
        return $announcement->status === (string) setting('announcements.status.published', 'published')
            && (! $announcement->scheduled_at || ! $announcement->scheduled_at->isFuture())
            && (! $announcement->expires_at || $announcement->expires_at->isFuture());
    }

    /**
     * تطابق شريحة الاستهداف (13.2): الكلّ / مسار / تدريب / دور / مستخدم بعينه.
     *
     * الشكل المقبول: قاعدة واحدة {"type":"role","keys":[...]} أو قائمة قواعد
     * تُجمَع باتّحاد — و«بلا استهداف» يعني الكلّ.
     */
    public function matches(Announcement $announcement, User $user): bool
    {
        $audience = $announcement->audience;

        if (blank($audience)) {
            return true;
        }

        $rules = array_is_list($audience) ? $audience : [$audience];

        foreach ($rules as $rule) {
            if (is_array($rule) && $this->ruleMatches($rule, $user)) {
                return true;
            }
        }

        return false;
    }

    /** أنواع المنشورات كفلتر ظاهر (النوع) — من الإعدادات لا من الكود. */
    public static function types(): array
    {
        $types = setting('announcements.types', [
            'pinned' => 'مثبَّت',
            'critical' => 'يحتاج إقرار',
            'general' => 'عامّ',
        ]);

        return is_array($types) ? $types : [];
    }

    /** نوع المنشور المشتقّ من خصائصه — بلا عمود إضافيّ في الجدول. */
    public static function typeOf(Announcement $announcement): string
    {
        return match (true) {
            (bool) $announcement->is_pinned => 'pinned',
            (bool) $announcement->requires_acknowledge => 'critical',
            default => 'general',
        };
    }

    // ------------------------------------------------------------------ داخليّ

    private function liveQuery()
    {
        $now = now();

        return Announcement::query()
            ->where('status', (string) setting('announcements.status.published', 'published'))
            ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->orderByDesc('is_pinned')   // المثبَّت أعلى القائمة (13.2)
            ->orderByDesc('created_at');
    }

    private function ruleMatches(array $rule, User $user): bool
    {
        $type = (string) ($rule['type'] ?? 'all');
        $ids = array_map('intval', (array) ($rule['ids'] ?? []));
        $keys = array_map('strval', (array) ($rule['keys'] ?? []));
        $ctx = $this->contextFor($user);

        return match ($type) {
            'all' => true,
            'user' => in_array($user->id, $ids, true),
            'role' => (bool) array_intersect($keys, $ctx['roles']),
            'course', 'training' => (bool) array_intersect($ids, $ctx['courses']),
            'path', 'track' => (bool) array_intersect($ids, $ctx['paths']),
            default => false,
        };
    }

    /** @return array{roles: array<int,string>, courses: array<int,int>, paths: array<int,int>} */
    private function contextFor(User $user): array
    {
        if (isset($this->context[$user->id])) {
            return $this->context[$user->id];
        }

        $courses = $user->enrollments()->pluck('course_id')->map(fn ($id) => (int) $id)->all();

        $paths = $courses === [] ? [] : DB::table('course_learning_path')
            ->whereIn('course_id', $courses)
            ->pluck('learning_path_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->context[$user->id] = [
            'roles' => $user->roles()->pluck('key')->map(fn ($key) => (string) $key)->all(),
            'courses' => $courses,
            'paths' => $paths,
        ];
    }
}
