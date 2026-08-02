<?php

namespace App\Services\Admin\Content;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\HelpArticle;
use App\Models\Setting;
use App\Models\User;
use App\Services\Account\ComplaintService;
use App\Services\Notifications\AnnouncementFeed;
use App\Services\Notifications\Notifier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * التوجيه والدعم (12.6 · 24.3): التعليمات · الإشعارات · دليل المستخدم · الشكاوى.
 *
 * القاعدة الحاكمة في التعليمات: **الإقرار يُكافَأ مرّة واحدة لكلّ منشور** —
 * والسقف يُفرَض على الخادم في `AnnouncementAcknowledger`، وهنا نضبط قيمته فقط.
 */
class GuidanceComposer
{
    public function __construct(private readonly ContentAudit $audit) {}

    /** حالات المنشور (24.3) */
    public const STATUSES = ['draft' => 'مسودّة', 'scheduled' => 'مجدول', 'published' => 'منشور', 'archived' => 'مؤرشف'];

    // ============================================================== التعليمات

    /** @param  array<string, mixed>  $filters */
    public function announcements(array $filters = []): LengthAwarePaginator
    {
        $query = Announcement::query()->latest('id');

        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->where('title', 'like', '%'.$q.'%');
        }

        if (($status = (string) ($filters['status'] ?? '')) !== '' && isset(self::STATUSES[$status])) {
            $query->where('status', $status);
        }

        if (! empty($filters['pinned'])) {
            $query->where('is_pinned', true);
        }

        return $query->paginate((int) setting('announcements.admin.per_page', 15))->withQueryString();
    }

    /**
     * تحليلات القراءة والإقرار لكلّ منشور (12.6-أ): نسبة القراءة ومَن قرأ ومَن أقرّ.
     *
     * @return array<int, array{reads: int, acks: int, rate: int}>
     */
    public function readStats(Collection $announcements): array
    {
        $audience = max(1, $this->audienceSize());

        $rows = AnnouncementRead::query()
            ->whereIn('announcement_id', $announcements->pluck('id'))
            ->selectRaw('announcement_id, count(read_at) as reads, count(acknowledged_at) as acks')
            ->groupBy('announcement_id')
            ->get()
            ->keyBy('announcement_id');

        $stats = [];

        foreach ($announcements as $announcement) {
            $reads = (int) ($rows[$announcement->id]->reads ?? 0);
            $stats[$announcement->id] = [
                'reads' => $reads,
                'acks' => (int) ($rows[$announcement->id]->acks ?? 0),
                'rate' => min(100, (int) round(($reads / $audience) * 100)),
            ];
        }

        return $stats;
    }

    /** مَن قرأ ومَن أقرّ بالتفصيل — تاب «تحليلات عميقة». */
    public function readers(Announcement $announcement): Collection
    {
        return AnnouncementRead::query()
            ->with('user')
            ->where('announcement_id', $announcement->id)
            ->latest('read_at')
            ->limit((int) setting('announcements.analytics.max_rows', 100))
            ->get();
    }

    /** @param  array<string, mixed>  $data */
    public function saveAnnouncement(?Announcement $announcement, array $data): Announcement
    {
        $isNew = $announcement === null;
        $status = isset(self::STATUSES[$data['status'] ?? '']) ? (string) $data['status'] : 'draft';

        $payload = [
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'type' => $data['type'] ?? null,
            'media_path' => $data['media_path'] ?? ($announcement->media_path ?? null),
            'cta_label' => $data['cta_label'] ?? null,
            'cta_url' => $data['cta_url'] ?? null,
            // استهداف بشرائح: الكلّ / مسار / تدريب / دور / مستخدم بعينه (13.2)
            'audience' => $this->audienceRule($data),
            // التفاعل مسموح أو ممنوع **لكلّ منشور** على حدة (12.6-أ)
            'reactions_enabled' => (bool) ($data['reactions_enabled'] ?? setting('announcements.reactions.default_on', false)),
            'requires_acknowledge' => (bool) ($data['requires_acknowledge'] ?? false),
            // ⭐ XP الإقرار — بسقف مرّة واحدة لكلّ منشور (12.6-أ)
            'acknowledge_xp' => (int) ($data['acknowledge_xp'] ?? setting('announcements.acknowledge.default_xp', 0)),
            'acknowledge_tickets' => (int) ($data['acknowledge_tickets'] ?? setting('announcements.acknowledge.default_tickets', 0)),
            'push_to_notifications' => (bool) ($data['push_to_notifications'] ?? false),
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'scheduled_at' => $status === 'scheduled' ? ($data['scheduled_at'] ?? null) : ($data['scheduled_at'] ?? null),
            // أرشفة تلقائيّة بعد مدّة من الإعدادات (12.6-أ)
            'expires_at' => $data['expires_at'] ?? $this->defaultExpiry($status),
            'status' => $status,
        ];

        if ($isNew) {
            $payload['created_by'] = auth()->id();
            $announcement = Announcement::create($payload);
        } else {
            $announcement->update($payload);
        }

        $this->enforcePinLimit($announcement);
        $this->audit->record($announcement, $isNew ? 'announcement.created' : 'announcement.updated', [], ['status' => $status]);

        if ($status === 'published' && $announcement->push_to_notifications) {
            $this->pushToBell($announcement);
        }

        return $announcement->refresh();
    }

    /** مكتبة المنشورات: نسخ منشور سابق لإعادة إرساله (12.6-أ). */
    public function duplicateAnnouncement(Announcement $announcement): Announcement
    {
        $copy = $announcement->replicate(['created_at', 'updated_at']);
        $copy->title = $announcement->title.(string) setting('announcements.duplicate.suffix', ' — نسخة');
        $copy->status = 'draft';
        $copy->is_pinned = false;
        $copy->created_by = auth()->id();
        $copy->save();

        return $copy;
    }

    public function archiveAnnouncement(Announcement $announcement): Announcement
    {
        $announcement->update(['status' => 'archived', 'is_pinned' => false, 'expires_at' => now()]);
        $this->audit->record($announcement, 'announcement.archived', [], []);

        return $announcement;
    }

    // ============================================================== الإشعارات

    /**
     * إرسال إشعار يدويّ لجمهور محدَّد — **برابط أو بدون** (12.6-ب).
     *
     * @param  array<string, mixed>  $data
     */
    public function sendManualNotification(array $data): int
    {
        $users = $this->resolveAudience($data);
        $url = trim((string) ($data['url'] ?? '')) ?: null;

        Notifier::sendMany(
            users: $users,
            category: (string) ($data['category'] ?? setting('notifications.manual.default_category', 'admin')),
            title: (string) $data['title'],
            body: $data['body'] ?? null,
            url: $url,
        );

        return $users->count();
    }

    /**
     * أنواع الإشعارات ومصفوفتها — قابلة للتوسّع من الإعدادات بلا تعديل كود (2.8).
     *
     * @return array<string, string>
     */
    public function notificationTypes(): array
    {
        $types = setting('notifications.types', [
            'account' => 'قبول الحساب',
            'certificate' => 'إصدار شهادة',
            'exam' => 'نتيجة امتحان',
            'announcement' => 'رسالة إداريّة',
            'wallet' => 'طلب سحب أو شحن',
            'order' => 'اكتمال طلب',
        ]);

        return is_array($types) ? $types : [];
    }

    /**
     * ⭐ أثر التجميع الفعليّ (12.6-ب): كم إشعارًا **اندمج فعلًا** في إشعارٍ واحد.
     *
     * كان هذا عدّاد عرضٍ يحصي ما وصل في نافذة زمنيّة **بلا أن يدمج شيئًا** —
     * فتَعِد الشاشة الأدمن بحمايةٍ غير موجودة. الآن الدمج يقع في `Notifier`
     * ويُخزَّن في `group_count`، وهذه قراءةٌ له لا وعدٌ عنه.
     */
    public function groupingPreview(): Collection
    {
        $window = (int) setting('notifications.grouping.window_minutes', 15);

        return DB::table('app_notifications')
            ->where('created_at', '>=', now()->subMinutes($window * (int) setting('notifications.grouping.report_windows', 96)))
            ->where('group_count', '>', 1)
            ->selectRaw('category, sum(group_count) as total, count(*) as rows')
            ->groupBy('category')
            ->get();
    }

    /**
     * حالة حدّ الهدوء كما تُطبَّق فعلًا (12.6-ب): الحدّ اليوميّ · الفئات المستثناة
     * · وكم إشعارًا تجمّع اليوم بسببه — لتقول الشاشة ما يجري لا ما نتمنّاه.
     *
     * @return array<string, mixed>
     */
    public function quietLimitState(): array
    {
        $digestCategory = (string) setting('notifications.digest.category', 'digest');

        return [
            'limit' => (int) setting('notifications.rate_limit.per_user_per_day', 3),
            'exempt' => Notifier::quietExemptCategories(),
            'deferred_today' => (int) DB::table('app_notifications')
                ->where('category', $digestCategory)
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('group_count'),
        ];
    }

    // ============================================================== دليل المستخدم

    /** @param  array<string, mixed>  $filters */
    public function articles(array $filters = []): LengthAwarePaginator
    {
        $query = HelpArticle::query()->latest('id');

        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->where(fn ($i) => $i->where('title', 'like', '%'.$q.'%')->orWhere('body', 'like', '%'.$q.'%'));
        }

        if (($category = (string) ($filters['category'] ?? '')) !== '') {
            $query->where('category', $category);
        }

        if (($status = (string) ($filters['status'] ?? '')) !== '') {
            $query->where('status', $status);
        }

        return $query->paginate((int) setting('help.admin.per_page', 12))->withQueryString();
    }

    /** @param  array<string, mixed>  $data */
    public function saveArticle(?HelpArticle $article, array $data): HelpArticle
    {
        $payload = [
            'title' => $data['title'],
            'title_en' => $data['title_en'] ?? null,
            'category' => $data['category'] ?? null,
            'body' => $data['body'] ?? null,
            'tags' => $this->tags($data['tags'] ?? []),
            'status' => in_array($data['status'] ?? 'draft', ['draft', 'published', 'archived'], true) ? (string) ($data['status'] ?? 'draft') : 'draft',
        ];

        if ($article) {
            $article->update($payload);

            return $article;
        }

        $payload['slug'] = $this->uniqueSlug($payload['title']);

        return HelpArticle::create($payload);
    }

    public function articleCategories(): Collection
    {
        return collect((array) setting('help.categories', ['البداية', 'التدريبات', 'الشهادات', 'المحفظة', 'الحساب']))
            ->merge(HelpArticle::query()->whereNotNull('category')->distinct()->pluck('category'))
            ->unique()
            ->values();
    }

    /** «هل كان مفيدًا؟» — النسبة تُحسَب من تقييمات المستخدمين (12.6-ج). */
    public function helpfulRate(HelpArticle $article): int
    {
        $total = (int) $article->helpful_yes + (int) $article->helpful_no;

        return $total > 0 ? (int) round(((int) $article->helpful_yes / $total) * 100) : 0;
    }

    // ============================================================== الشكاوى

    /**
     * حالات طابور الشكاوى (11 · 24.3) — **من المصدر الواحد** `ComplaintService`.
     *
     * كانت هنا قائمةٌ ثانية تسمّي `open` «جديدة» بينما يسمّيها المستخدم «مفتوحة»،
     * و**تُسقِط `answered` كلّيًّا** فلا يقدر الأدمن على تقديم الحالة بردّه،
     * فتبقى التذكرة «مفتوحة» بعد الردّ وعدّاد «تمّ الردّ» صفرٌ دائمًا.
     *
     * @return array<string, string>
     */
    public static function complaintStatuses(): array
    {
        return ComplaintService::statusLabels();
    }

    /** @param  array<string, mixed>  $filters */
    public function complaints(array $filters = []): LengthAwarePaginator
    {
        $query = Complaint::query()->with('user')->latest('id');

        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->where(fn ($i) => $i->where('title', 'like', '%'.$q.'%')
                ->orWhere('body', 'like', '%'.$q.'%')
                ->orWhere('number', 'like', '%'.$q.'%'));
        }

        if (($status = (string) ($filters['status'] ?? '')) !== '' && isset(self::complaintStatuses()[$status])) {
            $query->where('status', $status);
        }

        if (($category = (string) ($filters['category'] ?? '')) !== '') {
            $query->where('category', $category);
        }

        return $query->paginate((int) setting('complaints.admin.per_page', 15))->withQueryString();
    }

    /**
     * أسباب الشكوى الثمانية المعتمَدة — قائمة إعدادات لا كود (11 · 24.3).
     * ومصدرها **نفس** المفتاح الذي يقرؤه فورم المستخدم، وإلّا كان تحرير الأدمن بلا أثر.
     */
    public function complaintReasons(): Collection
    {
        return collect(ComplaintService::categories());
    }

    /**
     * حفظ قائمة الأسباب من لوحة الأدمن — إضافة/تعديل/حذف (11).
     *
     * @param  array<int, string>  $reasons
     * @return array<int, string>
     */
    public function saveComplaintReasons(array $reasons, ?User $actor = null): array
    {
        $clean = collect($reasons)
            ->map(fn ($reason) => trim((string) $reason))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $before = ComplaintService::categories();

        $setting = Setting::updateOrCreate(
            ['key' => ComplaintService::REASONS_KEY],
            [
                'group' => 'complaints',
                'label_ar' => 'أسباب الشكاوى والمقترحات',
                'type' => 'json',
                'default_value' => json_encode(ComplaintService::defaultReasons(), JSON_UNESCAPED_UNICODE),
                'value' => json_encode($clean, JSON_UNESCAPED_UNICODE),
            ],
        );

        Cache::forget('settings');

        $this->audit->record(
            $setting,
            'complaints.reasons.updated',
            ['reasons' => $before],
            ['reasons' => $clean],
            $actor,
        );

        return $clean;
    }

    /** ردّ **داخليّ** (ملاحظة للفريق) أو **خارجيّ** (يصل للمستخدم إشعارًا) — 24.3. */
    public function reply(Complaint $complaint, User $actor, string $body, bool $internal, ?string $status = null): ComplaintMessage
    {
        $message = ComplaintMessage::create([
            'complaint_id' => $complaint->id,
            'user_id' => $actor->id,
            'body' => $body,
            'is_internal' => $internal,
        ]);

        // ⭐ الردّ الخارجيّ **يقدّم الحالة** إلى «تمّ الردّ» ما لم يختر الأدمن غيرها (11)
        // — وإلّا بقيت التذكرة «مفتوحة» بعد الردّ وصار العدّاد صفرًا دائمًا.
        $next = $status && isset(self::complaintStatuses()[$status])
            ? $status
            : (! $internal && $complaint->status !== 'closed' ? 'answered' : null);

        if ($next !== null && $next !== $complaint->status) {
            $complaint->update(['status' => $next]);
        }

        if (! $internal && setting('complaints.notify.on_reply', true)) {
            Notifier::send(
                user: $complaint->user,
                category: 'complaint',
                title: (string) setting('complaints.notify.reply_title', 'وصلك ردّ على رسالتك'),
                body: Str::limit($body, (int) setting('complaints.notify.body_limit', 120)),
            );
        }

        $this->audit->record($complaint, 'complaint.replied', [], ['internal' => $internal], $actor);

        return $message;
    }

    /** الإغلاق بسبب موثّق (24.3). */
    public function close(Complaint $complaint, string $reason, ?User $actor = null): Complaint
    {
        $complaint->update([
            'status' => 'closed',
            'close_reason' => mb_substr($reason, 0, 255),
            'closed_at' => now(),
        ]);

        if (setting('complaints.notify.on_close', true)) {
            Notifier::send(
                user: $complaint->user,
                category: 'complaint',
                title: (string) setting('complaints.notify.close_title', 'قفلنا رسالتك — وشكرًا لوقتك'),
                body: $reason,
            );
        }

        $this->audit->record($complaint, 'complaint.closed', [], ['reason' => $reason], $actor);

        return $complaint->refresh();
    }

    public function assign(Complaint $complaint, ?int $userId, ?User $actor = null): Complaint
    {
        $complaint->update([
            'assigned_to' => $userId,
            'status' => $complaint->status === 'open' ? 'in_review' : $complaint->status,
        ]);

        $this->audit->record($complaint, 'complaint.assigned', [], ['assigned_to' => $userId], $actor);

        return $complaint->refresh();
    }

    /** عمر الشكوى مقابل SLA الردّ — يتلوّن عند التأخّر (24.3). */
    public function slaState(Complaint $complaint): string
    {
        if ($complaint->status === 'closed') {
            return 'idle';
        }

        $hours = $complaint->created_at?->diffInHours(now()) ?? 0;
        $sla = (int) setting('complaints.sla.reply_hours', 48);

        return match (true) {
            $hours >= $sla => 'danger',
            $hours >= $sla * 0.75 => 'warn',
            default => 'ok',
        };
    }

    // ------------------------------------------------------------------ داخليّ

    /** @param  array<string, mixed>  $data */
    private function audienceRule(array $data): array
    {
        $type = (string) ($data['audience_type'] ?? 'all');

        return match ($type) {
            'role' => ['type' => 'role', 'keys' => array_values(array_filter((array) ($data['audience_keys'] ?? [])))],
            'course' => ['type' => 'course', 'ids' => array_map('intval', (array) ($data['audience_ids'] ?? []))],
            'path' => ['type' => 'path', 'ids' => array_map('intval', (array) ($data['audience_ids'] ?? []))],
            'user' => ['type' => 'user', 'ids' => array_map('intval', (array) ($data['audience_ids'] ?? []))],
            default => ['type' => 'all'],
        };
    }

    /** @return Collection<int, User> */
    private function resolveAudience(array $data): Collection
    {
        $rule = $this->audienceRule($data);
        $feed = app(AnnouncementFeed::class);

        $announcement = new Announcement(['audience' => $rule]);

        return User::query()
            ->where('status', 'active')
            ->limit((int) setting('notifications.manual.max_recipients', 2000))
            ->get()
            ->filter(fn (User $user) => $feed->matches($announcement, $user))
            ->values();
    }

    private function pushToBell(Announcement $announcement): void
    {
        $feed = app(AnnouncementFeed::class);

        $users = User::query()
            ->where('status', 'active')
            ->limit((int) setting('announcements.push.max_recipients', 2000))
            ->get()
            ->filter(fn (User $user) => $feed->matches($announcement, $user));

        Notifier::sendMany(
            users: $users,
            category: 'announcement',
            title: $announcement->title,
            body: Str::limit((string) $announcement->body, (int) setting('announcements.push.body_limit', 120)),
            url: Route::has('announcements.index') ? route('announcements.index') : null,
        );
    }

    /** أقصى عدد منشورات مثبَّتة (افتراضيًّا 3) — الأقدم يُفكّ تثبيته (24.3). */
    private function enforcePinLimit(Announcement $announcement): void
    {
        if (! $announcement->is_pinned) {
            return;
        }

        $max = (int) setting('announcements.pinned.max', 3);

        Announcement::query()
            ->where('is_pinned', true)
            ->where('id', '!=', $announcement->id)
            ->orderByDesc('id')
            ->skip(max(0, $max - 1))
            ->take(PHP_INT_MAX)
            ->get()
            ->each(fn (Announcement $old) => $old->update(['is_pinned' => false]));
    }

    private function defaultExpiry(string $status): ?string
    {
        $days = (int) setting('announcements.auto_archive.days', 30);

        return $status === 'published' && $days > 0 ? now()->addDays($days)->toDateTimeString() : null;
    }

    private function audienceSize(): int
    {
        return (int) User::query()->where('status', 'active')->count();
    }

    /** @return array<int, string> */
    private function tags(mixed $tags): array
    {
        $list = is_string($tags) ? explode(',', $tags) : (array) $tags;

        return collect($list)->map(fn ($t) => trim((string) $t))->filter()->unique()->values()->all();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;
        $i = 1;

        while (HelpArticle::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
