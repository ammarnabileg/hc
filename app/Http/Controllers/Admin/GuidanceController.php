<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\Course;
use App\Models\HelpArticle;
use App\Models\LearningPath;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\Content\GuidanceComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

        return view('admin.guidance.index', [
            'announcements' => $announcements,
            'stats' => $this->guidance->readStats($announcements->getCollection()),
            'filters' => $filters,
            'statuses' => GuidanceComposer::STATUSES,
            'tabs' => $this->tabs('announcements'),
            'audiences' => $this->audienceOptions(),
        ]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $announcement = $this->guidance->saveAnnouncement(null, $this->announcementRules($request));

        return redirect()
            ->route('admin.guidance.index')
            ->with('status', 'اتحفظ المنشور «'.$announcement->title.'» ✓');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->guidance->saveAnnouncement($announcement, $this->announcementRules($request));

        return back()->with('status', 'اتحفظ ✓');
    }

    public function duplicateAnnouncement(Announcement $announcement): RedirectResponse
    {
        $this->guidance->duplicateAnnouncement($announcement);

        return back()->with('status', 'اتعملت نسخة كمسودّة ✓');
    }

    public function archiveAnnouncement(Announcement $announcement): RedirectResponse
    {
        $this->guidance->archiveAnnouncement($announcement);

        return back()->with('status', 'اتأرشف المنشور ✓');
    }

    /** تحليلات عميقة: نسبة القراءة ومَن قرأ ومَن أقرّ (12.6-أ). */
    public function analytics(Announcement $announcement): View
    {
        return view('admin.guidance.analytics', [
            'announcement' => $announcement,
            'readers' => $this->guidance->readers($announcement),
            'stats' => $this->guidance->readStats(collect([$announcement]))[$announcement->id] ?? [],
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
            'audience_type' => ['required', 'string', 'in:all,role,course,path,user'],
            'audience_ids' => ['nullable', 'array'],
            'audience_ids.*' => ['integer'],
            'audience_keys' => ['nullable', 'array'],
        ]);

        $sent = $this->guidance->sendManualNotification($data);

        return back()->with('status', 'اتبعت الإشعار لـ'.$sent.' مستخدم ✓');
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

        return back()->with('status', 'اتضاف الدليل ✓');
    }

    public function updateArticle(Request $request, HelpArticle $article): RedirectResponse
    {
        $this->guidance->saveArticle($article, $this->articleRules($request));

        return back()->with('status', 'اتحفظ ✓');
    }

    public function destroyArticle(HelpArticle $article): RedirectResponse
    {
        $article->delete();

        return back()->with('status', 'اتشال الدليل ✓');
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
            'statuses' => GuidanceComposer::COMPLAINT_STATUSES,
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
            'statuses' => GuidanceComposer::COMPLAINT_STATUSES,
            'assignees' => $this->assignees(),
            'closeReasons' => (array) setting('complaints.close_reasons', ['اتحلّت', 'مكرّرة', 'خارج نطاقنا']),
        ]);
    }

    public function assignComplaint(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'integer', 'exists:users,id']]);

        $this->guidance->assign($complaint, $data['assigned_to'] ?? null, $request->user());

        return back()->with('status', 'اتظبط الإسناد ✓');
    }

    /** ردّ داخليّ (للفريق) أو خارجيّ (يوصل للمستخدم إشعارًا) — 24.3. */
    public function replyComplaint(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.(int) setting('complaints.reply.max_chars', 2000)],
            'is_internal' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:open,in_review,closed'],
        ]);

        $this->guidance->reply(
            $complaint,
            $request->user(),
            $data['body'],
            (bool) ($data['is_internal'] ?? false),
            $data['status'] ?? null,
        );

        return back()->with('status', ($data['is_internal'] ?? false) ? 'اتسجّلت ملاحظة داخليّة ✓' : 'اتبعت الردّ ✓');
    }

    /** الإغلاق بسبب موثّق (24.3). */
    public function closeComplaint(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:2', 'max:255']]);

        $this->guidance->close($complaint, $data['reason'], $request->user());

        return back()->with('status', 'اتقفلت الشكوى، والسبب متسجّل ✓');
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<int, array{key: string, label: string, url: string}> */
    private function tabs(string $current): array
    {
        return [
            ['key' => 'announcements', 'label' => 'التعليمات', 'url' => route('admin.guidance.index')],
            ['key' => 'notifications', 'label' => 'الإشعارات', 'url' => route('admin.guidance.notifications')],
            ['key' => 'help', 'label' => 'دليل المستخدم', 'url' => route('admin.guidance.help')],
            ['key' => 'complaints', 'label' => 'الشكاوى', 'url' => route('admin.guidance.complaints')],
        ];
    }

    /** @return array<string, mixed> */
    private function audienceOptions(): array
    {
        return [
            'roles' => Role::query()->orderBy('id')->get(['id', 'key', 'name_ar']),
            'courses' => Course::query()->orderByDesc('id')->limit((int) setting('announcements.audience.picker_limit', 30))->get(['id', 'name_ar']),
            'paths' => LearningPath::query()->orderBy('sort_order')->get(['id', 'name_ar']),
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
        return $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'body' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:32'],
            'media_path' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:64'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            // استهداف بشرائح (12.6-أ)
            'audience_type' => ['required', 'string', 'in:all,role,course,path,user'],
            'audience_ids' => ['nullable', 'array'],
            'audience_ids.*' => ['integer'],
            'audience_keys' => ['nullable', 'array'],
            // التفاعل مسموح أو ممنوع لكلّ منشور
            'reactions_enabled' => ['nullable', 'boolean'],
            // إقرار «قرأتُ وفهمت» بـXP — بسقف مرّة واحدة لكلّ منشور
            'requires_acknowledge' => ['nullable', 'boolean'],
            'acknowledge_xp' => ['nullable', 'integer', 'min:0', 'max:'.(int) setting('announcements.acknowledge.max_xp', 500)],
            'acknowledge_tickets' => ['nullable', 'integer', 'min:0'],
            'push_to_notifications' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'status' => ['required', 'string', 'in:draft,scheduled,published,archived'],
        ]);
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
