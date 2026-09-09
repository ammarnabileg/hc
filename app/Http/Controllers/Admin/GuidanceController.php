<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAudience;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\Course;
use App\Models\HelpArticle;
use App\Models\LearningPath;
use App\Models\Role;
use App\Models\User;
use App\Services\Account\ComplaintService;
use App\Services\Admin\AudienceSegments;
use App\Services\Admin\Content\AnnouncementRecurrence;
use App\Services\Admin\Content\GuidanceComposer;
use App\Services\Notifications\AnnouncementMailer;
use App\Services\Notifications\AnnouncementPersonalizer;
use App\Services\Notifications\AnnouncementPoll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * التوجيه والدعم (12.6 · 24.3) — دروب-داون بأربع صفحات:
 * **التعليمات · الإشعارات · دليل المستخدم · الشكاوى**.
 */
class GuidanceController extends Controller
{
    public function __construct(private readonly GuidanceComposer $guidance) {}

    // ============================================================== أ) التعليمات

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => $request->string('status')->toString(),
            'pinned' => $request->boolean('pinned'),
        ];

        $announcements = $this->guidance->announcements($filters);
        $recurrence = app(AnnouncementRecurrence::class);

        return view('admin.guidance.index', [
            'announcements' => $announcements,
            'stats' => $this->guidance->readStats($announcements->getCollection()),
            'filters' => $filters,
            'statuses' => GuidanceComposer::statuses(),
            'tabs' => $this->tabs('announcements'),
            'audiences' => $this->audienceOptions(),
            // الجدولة المتكرّرة: الترددات + موعد الدورة القادمة لكلّ قالب (12.6-أ)
            'frequencies' => AnnouncementRecurrence::frequencies(),
            'nextRuns' => $announcements->getCollection()->mapWithKeys(
                fn (Announcement $a) => [$a->id => $recurrence->nextRunAt($a)],
            ),
            // وسوم التخصيص الديناميكيّ كما تُعرَض في المحرّر (12.6-أ)
            'tokens' => AnnouncementPersonalizer::tokens(),
            // ⭐ القنوات الموحّدة من مكان واحد: تاب · Toast/إشعار · بريد (12.6-أ)
            'channels' => AnnouncementMailer::channels(),
            'emailStats' => $this->guidance->emailDeliveryStats($announcements->getCollection()),
        ]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $announcement = $this->guidance->saveAnnouncement(null, $this->announcementRules($request));

        return redirect()
            ->route('admin.guidance.index')
            ->with('status', strtr((string) setting('guidance.admin.store_announcement_ok', 'اتحفظ المنشور «:a1» ✓'), [':a1' => (string) ($announcement->title)]));
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->guidance->saveAnnouncement($announcement, $this->announcementRules($request));

        return back()->with('status', (string) setting('guidance.admin.update_announcement_ok', 'اتحفظ ✓'));
    }

    public function duplicateAnnouncement(Announcement $announcement): RedirectResponse
    {
        $this->guidance->duplicateAnnouncement($announcement);

        return back()->with('status', (string) setting('guidance.admin.duplicate_announcement_ok', 'اتعملت نسخة كمسودّة ✓'));
    }

    public function archiveAnnouncement(Announcement $announcement): RedirectResponse
    {
        $this->guidance->archiveAnnouncement($announcement);

        return back()->with('status', (string) setting('guidance.admin.archive_announcement_ok', 'اتأرشف المنشور ✓'));
    }

    /** تحليلات عميقة: نسبة القراءة ومَن قرأ ومَن أقرّ + **أفضل توقيت** (12.6-أ). */
    public function analytics(Request $request, Announcement $announcement, AnnouncementPoll $poll): View
    {
        return view('admin.guidance.analytics', [
            'announcement' => $announcement,
            'readers' => $this->guidance->readers($announcement),
            'stats' => $this->guidance->readStats(collect([$announcement]))[$announcement->id] ?? [],
            'bestTime' => $this->guidance->bestTime(),
            // نتيجة الاستطلاع للأدمن سلطةٌ بصلاحيّتها (`announcement_polls.view`)،
            // لا تسريبٌ للمستخدم — ومَن لا يملكها لا يرى البلوك أصلًا (2.15-أ-7)
            'poll' => $poll->has($announcement) && $request->user()?->can('announcement_polls.view')
                ? ['options' => $poll->options($announcement), 'tally' => $poll->tally($announcement), 'public' => (bool) $announcement->poll_results_public, 'closed' => $poll->isClosed($announcement)]
                : null,
        ]);
    }

    /** تصدير التحليلات CSV (12.6-أ) — مَن قرأ ومَن أقرّ واختياره في الاستطلاع. */
    public function exportAnalytics(Request $request, Announcement $announcement): StreamedResponse
    {
        $rows = $this->guidance->analyticsExportRows(
            $announcement,
            (bool) $request->user()?->can('announcement_polls.export'),
        );
        $name = 'announcement-'.$announcement->id.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // BOM ليفتح إكسل العربيّة سليمةً بلا خطوة يدويّة
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * ⭐ معاينة على الأجهزة قبل النشر (12.6-أ · 24.3): موبايل ⇄ ديسكتوب.
     *
     * والمعاينة تمرّ بنفس مسار العرض الحقيقيّ — التخصيص الديناميكيّ والاستطلاع
     * كما يراهما القارئ — وإلّا كانت «معاينة» لشيءٍ آخر غير المنشور.
     */
    public function preview(Request $request, Announcement $announcement, AnnouncementPoll $poll, AnnouncementPersonalizer $personalizer): View
    {
        $devices = (array) setting('announcements.preview.devices', ['mobile' => 'موبايل', 'desktop' => 'ديسكتوب']);
        $device = $request->string('device')->toString();
        $device = array_key_exists($device, $devices) ? $device : (string) array_key_first($devices);

        return view('admin.guidance.preview', [
            'announcement' => $personalizer->apply($announcement, $request->user()),
            'raw' => $announcement,
            'poll' => $poll->viewModel($announcement, $request->user()),
            'device' => $device,
            'devices' => $devices,
            'width' => (int) setting('announcements.preview.mobile_width', 390),
            'tokens' => AnnouncementPersonalizer::tokens(),
        ]);
    }

    // ============================================================== ب) الإشعارات

    public function notifications(): View
    {
        return view('admin.guidance.notifications', [
            'types' => $this->guidance->notificationTypes(),
            'grouping' => $this->guidance->groupingPreview(),
            'tabs' => $this->tabs('notifications'),
            'audiences' => $this->audienceOptions(),
            'channels' => (array) setting('notifications.channels', ['bell' => 'الجرس', 'toast' => 'Toast', 'email' => 'بريد']),
            'rateLimit' => (int) setting('notifications.rate_limit.per_user_per_day', 3),
            // ⭐ حالة حدّ الهدوء كما يُطبَّق فعلًا لا كما يُوعَد به (12.6-ب)
            'quietLimit' => $this->guidance->quietLimitState(),
        ]);
    }

    /** إرسال إشعار يدويّ لجمهور محدَّد — **برابط أو بدون** (12.6-ب). */
    public function sendNotification(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'body' => ['nullable', 'string', 'max:1000'],
            'url' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:32'],
            'audience_type' => ['required', 'string', 'in:all,role,course,path,user,segment'],
            'audience_ids' => ['nullable', 'array'],
            'audience_ids.*' => ['integer'],
            'audience_keys' => ['nullable', 'array'],
        ]);

        $sent = $this->guidance->sendManualNotification($data);

        return back()->with('status', strtr((string) setting('guidance.admin.send_notification_ok', 'اتبعت الإشعار لـ:a1 مستخدم ✓'), [':a1' => (string) ($sent)]));
    }

    /** ⭐ حفظ مصفوفة النوع × القناة (24.3) — كانت لا تُحفَظ أصلًا */
    public function saveNotificationMatrix(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'matrix' => ['required', 'array'],
            'matrix.*' => ['array'],
            'matrix.*.*' => ['nullable'],
        ]);

        $this->guidance->saveNotificationMatrix($data['matrix'], $request->user());

        return back()->with('status', (string) setting('guidance.admin.save_matrix_ok', 'اتحفظت مصفوفة القنوات ✓'));
    }

    // ============================================================== ج) دليل المستخدم

    public function help(Request $request): View
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'category' => $request->string('category')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $articles = $this->guidance->articles($filters);

        return view('admin.guidance.help', [
            'articles' => $articles,
            'rates' => $articles->getCollection()->mapWithKeys(
                fn (HelpArticle $a) => [$a->id => $this->guidance->helpfulRate($a)],
            ),
            'filters' => $filters,
            'categories' => $this->guidance->articleCategories(),
            'tabs' => $this->tabs('help'),
        ]);
    }

    public function storeArticle(Request $request): RedirectResponse
    {
        $this->guidance->saveArticle(null, $this->articleRules($request));

        return back()->with('status', (string) setting('guidance.admin.store_article_ok', 'اتضاف الدليل ✓'));
    }

    public function updateArticle(Request $request, HelpArticle $article): RedirectResponse
    {
        $this->guidance->saveArticle($article, $this->articleRules($request));

        return back()->with('status', (string) setting('guidance.admin.update_article_ok', 'اتحفظ ✓'));
    }

    public function destroyArticle(HelpArticle $article): RedirectResponse
    {
        $article->delete();

        return back()->with('status', (string) setting('guidance.admin.destroy_article_ok', 'اتشال الدليل ✓'));
    }

    // ============================================================== د) الشكاوى

    public function complaints(Request $request): View
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => $request->string('status')->toString(),
            'category' => $request->string('category')->toString(),
        ];

        $complaints = $this->guidance->complaints($filters);

        return view('admin.guidance.complaints', [
            'complaints' => $complaints,
            'sla' => $complaints->getCollection()->mapWithKeys(
                fn (Complaint $c) => [$c->id => $this->guidance->slaState($c)],
            ),
            // اسم المعيَّن له من خريطة جاهزة — لأنّ `assigned_to` عمودٌ واسمُ علاقة معًا
            'assigneeNames' => $this->assigneeNames(),
            'filters' => $filters,
            'statuses' => GuidanceComposer::complaintStatuses(),
            'reasons' => $this->guidance->complaintReasons(),
            'assignees' => $this->assignees(),
            'closeReasons' => (array) setting('complaints.close_reasons', ['اتحلّت', 'مكرّرة', 'خارج نطاقنا']),
            'tabs' => $this->tabs('complaints'),
        ]);
    }

    /** بانل تفاصيل الشكوى: النصّ الكامل + سجلّ المراسلات الداخليّ (24.3). */
    public function showComplaint(Complaint $complaint): View
    {
        return view('admin.guidance.complaint', [
            'complaint' => $complaint->load('user'),
            'assigneeNames' => $this->assigneeNames(),
            'messages' => ComplaintMessage::query()->with('user')->where('complaint_id', $complaint->id)->oldest('id')->get(),
            'statuses' => GuidanceComposer::complaintStatuses(),
            'assignees' => $this->assignees(),
            'closeReasons' => (array) setting('complaints.close_reasons', ['اتحلّت', 'مكرّرة', 'خارج نطاقنا']),
        ]);
    }

    public function assignComplaint(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'integer', 'exists:users,id']]);

        $this->guidance->assign($complaint, $data['assigned_to'] ?? null, $request->user());

        return back()->with('status', (string) setting('guidance.admin.assign_complaint_ok', 'اتظبط الإسناد ✓'));
    }

    /** ردّ داخليّ (للفريق) أو خارجيّ (يوصل للمستخدم إشعارًا) — 24.3. */
    public function replyComplaint(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.(int) setting('complaints.reply.max_chars', 2000)],
            'is_internal' => ['nullable', 'boolean'],
            // ⭐ `answered` حالةٌ حقيقيّة في الدورة — وكانت محجوبةً هنا فصارت كودًا ميّتًا (11)
            'status' => ['nullable', 'string', Rule::in(array_keys(GuidanceComposer::complaintStatuses()))],
        ]);

        $this->guidance->reply(
            $complaint,
            $request->user(),
            $data['body'],
            (bool) ($data['is_internal'] ?? false),
            $data['status'] ?? null,
        );

        return back()->with('status', ($data['is_internal'] ?? false) ? (string) setting('guidance.admin.reply_complaint_ok', 'اتسجّلت ملاحظة داخليّة ✓') : (string) setting('guidance.admin.reply_complaint_ok_2', 'اتبعت الردّ ✓'));
    }

    /** الإغلاق بسبب موثّق (24.3). */
    public function closeComplaint(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:2', 'max:255']]);

        $this->guidance->close($complaint, $data['reason'], $request->user());

        return back()->with('status', (string) setting('guidance.admin.close_complaint_ok', 'اتقفلت الشكوى، والسبب متسجّل ✓'));
    }

    /**
     * شاشة تحرير أسباب الشكوى — **إضافة/تعديل/حذف من لوحة الأدمن** (11).
     * كان الموجود دروب-داون فلترة فقط، فلم تكن القائمة قابلة للإدارة أصلًا.
     */
    public function complaintReasons(): View
    {
        return view('admin.guidance.complaint-reasons', [
            'reasons' => $this->guidance->complaintReasons(),
            'defaults' => ComplaintService::defaultReasons(),
            'inUse' => Complaint::query()
                ->selectRaw('category, count(*) as total')
                ->whereNotNull('category')
                ->groupBy('category')
                ->pluck('total', 'category')
                ->all(),
            'tabs' => $this->tabs('complaints'),
        ]);
    }

    public function updateComplaintReasons(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reasons' => ['required', 'array', 'min:1'],
            'reasons.*' => ['nullable', 'string', 'max:48'],
        ], [
            'reasons.required' => (string) setting('guidance.admin.update_complaint_reasons_msg', 'سيب سببًا واحدًا على الأقلّ — الفورم محتاج قائمة يختار منها.'),
            'reasons.*.max' => (string) setting('guidance.admin.update_complaint_reasons_msg_2', 'السبب طويل — خلّيه في كلمات.'),
        ]);

        $saved = $this->guidance->saveComplaintReasons($data['reasons'], $request->user());

        return back()->with('status', strtr((string) setting('guidance.admin.update_complaint_reasons_ok', 'اتحفظت الأسباب ✓ (:a1)'), [':a1' => (string) (count($saved))]));
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<int, array{key: string, label: string, url: string}> */
    private function tabs(string $current): array
    {
        return [
            ['key' => 'announcements', 'label' => (string) setting('guidance.admin.tabs_msg', 'التعليمات'), 'url' => route('admin.guidance.index')],
            ['key' => 'notifications', 'label' => (string) setting('guidance.admin.tabs_msg_2', 'الإشعارات'), 'url' => route('admin.guidance.notifications')],
            ['key' => 'help', 'label' => (string) setting('guidance.admin.tabs_msg_3', 'دليل المستخدم'), 'url' => route('admin.guidance.help')],
            ['key' => 'complaints', 'label' => (string) setting('guidance.admin.tabs_msg_4', 'الشكاوى'), 'url' => route('admin.guidance.complaints')],
        ];
    }

    /** @return array<string, mixed> */
    private function audienceOptions(): array
    {
        return [
            'roles' => Role::query()->orderBy('id')->get(['id', 'key', 'name_ar']),
            'courses' => Course::query()->orderByDesc('id')->limit((int) setting('announcements.audience.picker_limit', 30))->get(['id', 'name_ar']),
            'paths' => LearningPath::query()->orderBy('sort_order')->get(['id', 'name_ar']),
            // ⭐ الشرائح المحفوظة (12.13): يستهدفها المحرّر بدل إعادة بناء الفلاتر،
            // والمؤرشفة لا تُعرَض — شريحةٌ خارج الخدمة لا تُخاطَب.
            'segments' => AdAudience::query()
                ->where('kind', AudienceSegments::KIND)
                ->whereNull('archived_at')
                ->orderByDesc('id')
                ->limit((int) setting('announcements.audience.picker_limit', 30))
                ->get(['id', 'name', 'segment_type', 'size']),
        ];
    }

    /** @return array<int, string> */
    private function assigneeNames(): array
    {
        return $this->assignees()->pluck('name', 'id')->all();
    }

    private function assignees()
    {
        return User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->limit((int) setting('complaints.assignees.limit', 50))
            ->get(['id', 'name', 'code']);
    }

    /** @return array<string, mixed> */
    private function announcementRules(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'body' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:32'],
            'media_path' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:64'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            // استهداف بشرائح (12.6-أ)
            'audience_type' => ['required', 'string', 'in:all,role,course,path,user,segment'],
            'audience_ids' => ['nullable', 'array'],
            'audience_ids.*' => ['integer'],
            'audience_keys' => ['nullable', 'array'],
            // التفاعل مسموح أو ممنوع لكلّ منشور
            'reactions_enabled' => ['nullable', 'boolean'],
            // إقرار «قرأتُ وفهمت» بـXP — بسقف مرّة واحدة لكلّ منشور
            'requires_acknowledge' => ['nullable', 'boolean'],
            'acknowledge_xp' => ['nullable', 'integer', 'min:0', 'max:'.(int) setting('announcements.acknowledge.max_xp', 500)],
            // السقف نفسه الذي يفرضه المُقِرّ خادميًّا — فلا يقبل الفورم رقمًا يُبتَر بصمت
            'acknowledge_tickets' => ['nullable', 'integer', 'min:0', 'max:'.(int) setting('announcements.acknowledge.max_tickets', 20)],
            // ⭐ القنوات الموحّدة من مكان واحد (12.6-أ): تاب · Toast/إشعار · بريد
            'show_in_feed' => ['nullable', 'boolean'],
            'push_to_notifications' => ['nullable', 'boolean'],
            'email_enabled' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'status' => ['required', 'string', 'in:draft,scheduled,published,archived'],

            // ⭐ استطلاع داخل المنشور — والنتيجة **عامّة أو مخفيّة** (12.6-أ)
            'poll_question' => ['nullable', 'string', 'max:190'],
            'poll_options' => ['nullable', 'array', 'max:'.(int) setting('announcements.poll.max_options', 6)],
            'poll_options.*' => ['nullable', 'string', 'max:120'],
            'poll_results_public' => ['nullable', 'boolean'],
            'poll_closes_at' => ['nullable', 'date'],

            // ⭐ جدولة متكرّرة + سلسلة Onboarding متدرّجة (12.6-أ)
            'recurrence' => ['nullable', 'string', Rule::in(array_keys(AnnouncementRecurrence::frequencies()))],
            'recurrence_until' => ['nullable', 'date'],
            'onboarding_step' => ['nullable', 'integer', 'min:1', 'max:'.(int) setting('announcements.onboarding.max_steps', 12)],
            'onboarding_delay_days' => ['nullable', 'integer', 'min:0', 'max:'.(int) setting('announcements.onboarding.max_delay_days', 365)],
        ], [
            'poll_options.max' => (string) setting('guidance.admin.announcement_rules_msg', 'خيارات الاستطلاع كتيرة — قلّلها عشان القرار يبقى سهل.'),
            'onboarding_step.max' => (string) setting('guidance.admin.announcement_rules_msg_2', 'السلسلة طويلة — خلّيها خطوات معدودة يقدر المستخدم يكمّلها.'),
        ]);

        // بناء الاستطلاع صلاحيّةٌ مستقلّة في المصفوفة (`announcement_polls.create`):
        // فمن لا يملكها لا يرى حقوله **ولا تُقبَل منه** لو أرسلها يدويًّا (12.2.1).
        if (! $request->user()?->can('announcement_polls.create')) {
            unset($data['poll_question'], $data['poll_options'], $data['poll_results_public'], $data['poll_closes_at']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function articleRules(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'title_en' => ['nullable', 'string', 'max:190'],
            'category' => ['nullable', 'string', 'max:64'],
            'body' => ['nullable', 'string'],
            'tags' => ['nullable'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
        ]);
    }
}
