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
        $items = $this->liveQuery()
            ->limit((int) setting('announcements.feed.max_items', 200))
            ->get()
            ->filter(fn (Announcement $a) => $this->matches($a, $user));

        // سلسلة الـOnboarding تُقيَّم بعد الاستهداف: خطوةٌ واحدة في وقتها لا دفعة (12.6-أ)
        return $this->gateOnboarding($items, $user)->values();
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
        return $this->isLive($announcement)
            && $this->matches($announcement, $user)
            && $this->onboardingStepIsDue($announcement, $user);
    }

    /** المنشور حيّ: منشور · حان موعده · لم تنتهِ صلاحيّته (يُؤرشَف تلقائيًّا). */
    public function isLive(Announcement $announcement): bool
    {
        return $announcement->status === (string) setting('announcements.status.published', 'published')
            // قالب التكرار مصدرُ دورات لا دورة — فلا يُبَثّ بنفسه أبدًا (12.6-أ)
            && blank($announcement->recurrence)
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

    /**
     * سلسلة Onboarding متدرّجة (12.6-أ): خطوةٌ تظهر حين يحين وقتها **وحين تُقرأ
     * التي قبلها** — فالمستخدم الجديد يتلقّى رحلةً مرتّبة لا كومةً في يومه الأوّل.
     *
     * @param  Collection<int, Announcement>  $items
     * @return Collection<int, Announcement>
     */
    private function gateOnboarding(Collection $items, User $user): Collection
    {
        [$steps, $rest] = $items->partition(fn (Announcement $a) => $a->onboarding_step !== null);

        if ($steps->isEmpty()) {
            return $rest;
        }

        if (! setting('announcements.onboarding.enabled', true)) {
            return $rest;
        }

        $ordered = $steps->sortBy('onboarding_step')->values();
        $readIds = $this->readAnnouncementIds($user, $ordered);
        $allowed = collect();

        foreach ($ordered as $step) {
            if (! $this->onboardingStepIsDue($step, $user)) {
                break;
            }

            $allowed->push($step);

            // الخطوة التالية تنتظر قراءة هذه — تسلسلٌ حقيقيّ لا ترتيبُ عرض
            if (! in_array($step->id, $readIds, true)) {
                break;
            }
        }

        // الترتيب المنصوص عليه يبقى كما هو: المثبَّت أعلى ثمّ الأحدث (13.2)
        return $rest->merge($allowed)
            ->sortBy([
                fn (Announcement $a, Announcement $b) => (int) $b->is_pinned <=> (int) $a->is_pinned,
                fn (Announcement $a, Announcement $b) => ($b->created_at <=> $a->created_at),
            ]);
    }

    /** هل حان وقت خطوة السلسلة لهذا المستخدم؟ (مهلة الأيّام + قراءة ما قبلها) */
    private function onboardingStepIsDue(Announcement $announcement, User $user): bool
    {
        if ($announcement->onboarding_step === null) {
            return true;
        }

        if (! setting('announcements.onboarding.enabled', true)) {
            return false;
        }

        $joinedAt = $user->created_at ?? now();

        if ($joinedAt->copy()->addDays((int) $announcement->onboarding_delay_days)->isFuture()) {
            return false;
        }

        $earlier = Announcement::query()
            ->whereNotNull('onboarding_step')
            ->where('onboarding_step', '<', (int) $announcement->onboarding_step)
            ->where('status', (string) setting('announcements.status.published', 'published'))
            ->get()
            ->filter(fn (Announcement $a) => $this->matches($a, $user));

        if ($earlier->isEmpty()) {
            return true;
        }

        $readIds = $this->readAnnouncementIds($user, $earlier);

        return $earlier->every(fn (Announcement $a) => in_array($a->id, $readIds, true));
    }

    /** @return array<int, int> */
    private function readAnnouncementIds(User $user, Collection $announcements): array
    {
        return AnnouncementRead::query()
            ->where('user_id', $user->id)
            ->whereIn('announcement_id', $announcements->pluck('id'))
            ->whereNotNull('read_at')
            ->pluck('announcement_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function liveQuery()
    {
        $now = now();

        return Announcement::query()
            ->where('status', (string) setting('announcements.status.published', 'published'))
            // قالب التكرار لا يُبَثّ — دوراته المولَّدة هي التي تصل الناس (12.6-أ)
            ->whereNull('recurrence')
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
