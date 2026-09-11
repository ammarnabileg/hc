<?php

namespace App\Http\Controllers\AdminScreens;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingPost;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\AdminScreens\MeetingsMirror;
use App\Services\AdminScreens\ScreenSettings;
use App\Services\Volunteer\Meetings\AttendanceService;
use App\Services\Volunteer\Meetings\MeetingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * شاشة اجتماعات التطوّع في لوحة الإدارة (24.2-أوّلًا).
 *
 * ما تفعله هذه الشاشة ولا تفعله شاشة المتطوّع: نظرةٌ عرضيّة على الأقسام كلّها
 * (جدولًا أو تقويمًا)، ومنح حضور استثنائيّ بسببٍ مسجَّل، وتصدير الحضور، وأثرٌ
 * في سجلّ التدقيق لكلّ فعلٍ إداريّ.
 *
 * ⭐ [2026-09-11] وما زادت عليه بأمر 24.2-أوّلًا — **+ اجتماع** · **إدارة
 * الكود/الأسئلة** · **رفع المحضر والمرفقات والتسجيل** · **تثبيت بوست** ·
 * **إلغاء بسبب** — كلّه يستدعي `MeetingManager` نفسها التي تستدعيها لوحة
 * التطوّع، كما كان الإنهاء يستدعي `AttendanceService` نفسها. **نستدعي ولا
 * نكرّر**: بابان بعقدٍ واحد لا عقدان لبابين.
 */
class MeetingsAdminController extends Controller
{
    public function __construct(
        private readonly MeetingsMirror $mirror,
        private readonly AttendanceService $attendance,
        private readonly MeetingManager $manager,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);

        // ⭐ تبديل (تقويم / جدول) — 24.2-أوّلًا، بنفس اصطلاح `view=` في شاشة الفعاليّات
        $view = $request->string('view')->toString() === 'calendar' ? 'calendar' : 'table';
        $month = $this->mirror->calendarMonth($request->string('month')->toString() ?: null);

        $meetings = $this->mirror->paginate($user, $filters);

        return view('admin.meetings-admin.index', [
            'meetings' => $meetings,
            'counts' => $this->mirror->attendanceCounts($meetings->items()),
            'attachmentCounts' => $this->mirror->attachmentCounts($meetings->items()),
            'posts' => $this->mirror->postsFor($meetings->items()),
            'stats' => $this->mirror->stats($user, $filters),
            'filters' => $filters,
            'statuses' => MeetingsMirror::statuses(),
            'entities' => $this->mirror->entities(),
            'attendance' => $this->attendance,
            'view' => $view,
            'month' => $month,
            'calendarMeetings' => $view === 'calendar' ? $this->mirror->calendar($user, $filters, $month) : collect(),
            'settings' => ScreenSettings::rows(ScreenSettings::SCREEN_MEETINGS, $user),
        ]);
    }

    /**
     * ⭐ **+ اجتماع** من لوحة الإدارة (24.2-أوّلًا).
     *
     * والفعل **هو فعل لوحة التطوّع نفسه**: نفس `MeetingManager::create()` بنفس
     * قواعد التحقّق ونفس قصّ الجمهور بالنطاق ونفس إشعار الجمهور — فلا يوجد
     * «اجتماع أنشأته اللوحة» يختلف عن «اجتماع أنشأه المتطوّع».
     *
     * والفرق الوحيد المشروع هو **مدى الكيان**: مسؤول اللوحة بنطاق `ALL` يُنشئ
     * لأيّ قسم، وهو ما يقوله نطاقُ صلاحيّته أصلًا لا اصطلاحٌ جديد هنا.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(
            $this->manager->creationRules(),
            [],
            $this->manager->creationAttributes(),
        );

        $meeting = $this->manager->create(
            $request->user(),
            $data,
            (array) $request->input('questions', []),
            (array) $request->file('attachments', []),
            $request->boolean('restricted'),
        );

        AuditTrail::log($request->user(), 'meetings.create', $meeting, [], [
            'audience' => $meeting->audience,
            'entity_id' => $meeting->entity_id,
        ]);

        return back()->with('status', (string) setting('meetings.screen.store_ok', 'اتعمل الاجتماع ✓ وابعتنا إشعارًا لجمهوره.'));
    }

    /** ⭐ رفع المحضر والمرفقات والتسجيل — نفس خدمة لوحة التطوّع (24.2-أوّلًا) */
    public function minutes(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'minutes' => ['nullable', 'string', 'max:20000'],
            'recording_url' => ['nullable', 'url', 'max:500'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:8192'],
        ], [], [
            'minutes' => (string) setting('meetings.admin.minutes_msg', 'المحضر'),
            'recording_url' => (string) setting('meetings.admin.minutes_msg_2', 'رابط التسجيل'),
        ]);

        $result = $this->manager->saveMinutes(
            $meeting,
            $request->user(),
            $data['minutes'] ?? null,
            $data['recording_url'] ?? null,
            (array) $request->file('attachments', []),
            $request->boolean('restricted'),
        );

        if ($result['ok']) {
            AuditTrail::log($request->user(), 'meeting_minutes.manage', $meeting, [], ['has_recording' => filled($meeting->recording_url)]);
        }

        return back()->with($result['ok'] ? 'status' : 'problem', $result['message']);
    }

    /** ⭐ إدارة الكود/الأسئلة — نفس خدمة لوحة التطوّع (13.4-ن-ب · 24.2-أوّلًا) */
    public function questions(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'attendance_code' => ['nullable', 'string', 'max:32'],
            'questions' => ['nullable', 'array'],
            'questions.*.prompt' => ['nullable', 'string', 'max:300'],
            'questions.*.options' => ['nullable', 'string', 'max:500'],
            'questions.*.correct_answer' => ['nullable', 'string', 'max:120'],
        ]);

        $result = $this->manager->saveCodeAndQuestions(
            $meeting,
            $request->user(),
            $data['attendance_code'] ?? null,
            (array) $request->input('questions', []),
            $request->filled('attendance_code'),
        );

        AuditTrail::log($request->user(), 'meetings.manage', $meeting, [], ['code_changed' => $request->filled('attendance_code')]);

        return back()->with('status', $result['message']);
    }

    /** ⭐ تثبيت بوست أعلى نقاش الاجتماع — والبوست لا بدّ أن يكون بوستَ هذا الاجتماع */
    public function pin(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'post_id' => ['required', 'integer', 'exists:meeting_posts,id'],
        ], [], ['post_id' => (string) setting('meetings.admin.pin_msg', 'البوست')]);

        $post = MeetingPost::query()
            ->where('meeting_id', $meeting->id)
            ->findOrFail($data['post_id']);

        $result = $this->manager->togglePin($post);

        AuditTrail::log($request->user(), 'meeting_posts.manage', $meeting, [], ['post_id' => $post->id, 'pinned' => $post->is_pinned]);

        return back()->with('status', $result['message']);
    }

    /** ⭐ إلغاء بسبب — والسبب إلزاميّ، والملغى لا تُفتَح له نافذة حضور أبدًا */
    public function cancel(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['reason' => (string) setting('meetings.admin.cancel_msg', 'سبب الإلغاء')]);

        $result = $this->manager->cancel($meeting, $request->user(), $data['reason']);

        if ($result['ok']) {
            AuditTrail::log($request->user(), 'meetings.delete', $meeting, [], ['reason' => $data['reason']]);
        }

        return back()->with($result['ok'] ? 'status' : 'problem', $result['message']);
    }

    /** لوحة جانبيّة: حضور اجتماع بعينه — تفاصيل بلا صفحة جديدة (2.15-أ-8) */
    public function show(Request $request, Meeting $meeting): View
    {
        return view('admin.meetings-admin.show', [
            'meeting' => $meeting->load(['entity:id,name_ar', 'owner:id,name,code']),
            'attendees' => $this->mirror->attendees($meeting),
            'attachments' => $this->manager->attachments($meeting),
            'statuses' => MeetingsMirror::statuses(),
            'attendance' => $this->attendance,
        ]);
    }

    /** إنهاء الاجتماع من اللوحة — يفتح نافذة تسجيل الحضور بعدد ساعات مضبوط */
    public function end(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'window_hours' => ['required', 'integer', 'min:1', 'max:'.$this->attendance->maxWindowHours()],
            'minutes' => ['nullable', 'string'],
        ], [], ['window_hours' => (string) setting('meetings.admin.end_msg', 'عدد ساعات نافذة التسجيل')]);

        $result = $this->attendance->end($meeting, $request->user(), (int) $data['window_hours'], $data['minutes'] ?? null);

        AuditTrail::log($request->user(), 'meetings.manage', $meeting, [], ['window_hours' => $data['window_hours']]);

        return $result['ok']
            ? back()->with('status', $result['message'])
            : back()->with('problem', strtr((string) setting('meetings.admin.end_msg_2', ':a1 جرّب تاني، ولو فضل الخطأ راجع حالة الاجتماع.'), [':a1' => (string) ($result['message'])]));
    }

    /** منح حضور استثنائيّ — بسببٍ إلزاميّ ومسجَّل في الصفّ نفسه */
    public function grant(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['user_id' => (string) setting('meetings.admin.grant_msg', 'العضو'), 'reason' => (string) setting('meetings.admin.grant_msg_2', 'سبب المنح')]);

        $member = User::query()->findOrFail($data['user_id']);
        $result = $this->mirror->grantExceptional($meeting, $member, $data['reason'], $request->user());

        if ($result['ok']) {
            AuditTrail::log($request->user(), 'meeting_attendance.manage', $meeting, [], [
                'user_id' => $member->id,
                'reason' => $data['reason'],
            ]);
        }

        return $result['ok']
            ? back()->with('status', $result['message'])
            : back()->with('problem', $result['message']);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->mirror->exportRows($request->user(), $this->filters($request));

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            if ($rows !== []) {
                fputcsv($handle, array_keys($rows[0]));

                foreach ($rows as $row) {
                    fputcsv($handle, array_values($row));
                }
            }

            fclose($handle);
        }, 'meetings-attendance-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);

        ScreenSettings::putMany(ScreenSettings::SCREEN_MEETINGS, $data['settings'], $request->user());

        return back()->with('status', (string) setting('meetings.admin.save_settings_ok', 'اتحفظ ✓'));
    }

    public function resetSettings(Request $request): RedirectResponse
    {
        $count = ScreenSettings::resetScreen(ScreenSettings::SCREEN_MEETINGS, $request->user());

        return back()->with('status', strtr((string) setting('meetings.admin.reset_settings_ok', 'رجعت :a1 قيمة للافتراضيّ ✓'), [':a1' => (string) ($count)]));
    }

    /** @return array<string,string> */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->toString(),
            'entity' => $request->string('entity')->toString(),
            'status' => $request->string('status')->toString(),
            'window' => $request->string('window')->toString(),
            'no_minutes' => $request->string('no_minutes')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];
    }
}
