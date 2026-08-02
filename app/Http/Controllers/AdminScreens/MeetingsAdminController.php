<?php

namespace App\Http\Controllers\AdminScreens;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\AdminScreens\MeetingsMirror;
use App\Services\AdminScreens\ScreenSettings;
use App\Services\Volunteer\Meetings\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * مرآة اجتماعات التطوّع في لوحة الإدارة (24.2-أوّلًا).
 *
 * ما تفعله هذه الشاشة ولا تفعله شاشة المتطوّع: نظرةٌ عرضيّة على الأقسام كلّها،
 * وإنهاءٌ إداريّ يفتح نافذة الحضور، ومنح حضور استثنائيّ بسببٍ مسجَّل، وتصدير
 * الحضور. والمنطق نفسه منطق لوحة التطوّع — نستدعيه ولا نكرّره.
 */
class MeetingsAdminController extends Controller
{
    public function __construct(
        private readonly MeetingsMirror $mirror,
        private readonly AttendanceService $attendance,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);
        $meetings = $this->mirror->paginate($user, $filters);

        return view('admin.meetings-admin.index', [
            'meetings' => $meetings,
            'counts' => $this->mirror->attendanceCounts($meetings->items()),
            'stats' => $this->mirror->stats($user, $filters),
            'filters' => $filters,
            'statuses' => MeetingsMirror::STATUSES,
            'entities' => $this->mirror->entities(),
            'attendance' => $this->attendance,
            'settings' => ScreenSettings::rows(ScreenSettings::GROUP_MEETINGS, $user),
        ]);
    }

    /** لوحة جانبيّة: حضور اجتماع بعينه — تفاصيل بلا صفحة جديدة (2.15-أ-8) */
    public function show(Request $request, Meeting $meeting): View
    {
        return view('admin.meetings-admin.show', [
            'meeting' => $meeting->load(['entity:id,name_ar', 'owner:id,name,code']),
            'attendees' => $this->mirror->attendees($meeting),
            'attendance' => $this->attendance,
        ]);
    }

    /** إنهاء الاجتماع من اللوحة — يفتح نافذة تسجيل الحضور بعدد ساعات مضبوط */
    public function end(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'window_hours' => ['required', 'integer', 'min:1', 'max:'.$this->attendance->maxWindowHours()],
            'minutes' => ['nullable', 'string'],
        ], [], ['window_hours' => 'عدد ساعات نافذة التسجيل']);

        $result = $this->attendance->end($meeting, $request->user(), (int) $data['window_hours'], $data['minutes'] ?? null);

        AuditTrail::log($request->user(), 'meetings.manage', $meeting, [], ['window_hours' => $data['window_hours']]);

        return $result['ok']
            ? back()->with('status', $result['message'])
            : back()->with('problem', $result['message'].' جرّب تاني، ولو فضل الخطأ راجع حالة الاجتماع.');
    }

    /** منح حضور استثنائيّ — بسببٍ إلزاميّ ومسجَّل في الصفّ نفسه */
    public function grant(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['user_id' => 'العضو', 'reason' => 'سبب المنح']);

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

        ScreenSettings::putMany(ScreenSettings::GROUP_MEETINGS, $data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }

    public function resetSettings(Request $request): RedirectResponse
    {
        $count = ScreenSettings::resetGroup(ScreenSettings::GROUP_MEETINGS, $request->user());

        return back()->with('status', 'رجعت '.$count.' قيمة للافتراضيّ ✓');
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
