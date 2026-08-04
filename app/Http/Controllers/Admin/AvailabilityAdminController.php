<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseAvailabilityPeriod;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Services\Learning\AvailabilityService;
use App\Services\Learning\UserClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * إدارة الإتاحة الزمنيّة للتدريبات (الدستور 5 · 12.4).
 *
 * سؤال واحد للشاشة (2.15-أ-1): **متى يُفتَح هذا التدريب ومتى يُقفَل؟**
 * — قائمة التدريبات بحالتها الآن، وبانل جانبيّ لفتراته ونافذته اليوميّة.
 *
 * ⭐ وكلّ ما يظهر هنا للأدمن مكتوبٌ بجوار كلّ صفّ **بأيّ توقيت يُقاس**: النافذة
 * اليوميّة تُقاس بساعة كلّ متدرّب لا بساعة الخادم، وهذا ليس تفصيلًا بل جوهر (5).
 */
class AvailabilityAdminController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly UserClock $clock,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $filter = in_array($request->query('state'), ['scheduled', 'always'], true)
            ? (string) $request->query('state')
            : '';

        $courses = Course::query()
            ->when($search !== '', fn ($q) => $q->where('name_ar', 'like', '%'.$search.'%'))
            ->when($filter === 'scheduled', fn ($q) => $q->where(
                fn ($inner) => $inner->whereNotNull('daily_open_at')
                    ->orWhereIn('id', CourseAvailabilityPeriod::query()->select('course_id'))
            ))
            ->when($filter === 'always', fn ($q) => $q->whereNull('daily_open_at')
                ->whereNotIn('id', CourseAvailabilityPeriod::query()->select('course_id')))
            ->orderBy('name_ar')
            ->paginate((int) setting('availability.admin.per_page', 20))
            ->withQueryString();

        $selected = $this->selected($request, $courses);

        return view('admin.availability.index', [
            'courses' => $courses,
            'rows' => $courses->map(fn (Course $course) => $this->row($course))->all(),
            'selected' => $selected,
            'periods' => $selected ? $this->availability->periods($selected) : collect(),
            'daily' => $selected ? $this->availability->dailyWindow($selected) : null,
            'state' => $selected ? $this->availability->forCourse($selected, null, $request->user()) : null,
            'adminTimezone' => $this->clock->timezoneFor($request->user()),
            'filters' => ['q' => $search, 'state' => $filter],
            'settings' => SettingsWriter::groupRows('availability'),
            'maxPeriods' => (int) setting('availability.admin.max_periods', 24),
        ]);
    }

    /** إضافة فترة إتاحة — والتواريخ المقلوبة تُرفَض برسالة تقول ماذا يفعل (2.17) */
    public function storePeriod(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ], [
            'ends_on.after_or_equal' => (string) setting('availability.admin.store_period_must', 'تاريخ النهاية لازم يكون بعد تاريخ البداية أو نفسه.'),
        ], [
            'starts_on' => (string) setting('availability.admin.store_period_msg', 'تاريخ البداية'),
            'ends_on' => (string) setting('availability.admin.store_period_msg_2', 'تاريخ النهاية'),
        ]);

        $count = CourseAvailabilityPeriod::query()->where('course_id', $course->id)->count();

        if ($count >= (int) setting('availability.admin.max_periods', 24)) {
            return back()->with('status', (string) setting('availability.admin.store_period_msg_3', 'وصلت أقصى عدد فترات لهذا التدريب — احذف فترة قديمة الأوّل.'));
        }

        $period = CourseAvailabilityPeriod::create([
            'course_id' => $course->id,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'is_active' => true,
        ]);

        AuditTrail::log($request->user(), 'availability.period.created', $period, [], $data);

        return $this->backToCourse($course, (string) setting('availability.admin.store_period_ok', 'اتضافت الفترة ✓'));
    }

    /** تفعيل/تعطيل فترة — التعطيل لا يحذف التاريخ فيبقى الأثر مقروءًا */
    public function togglePeriod(Request $request, CourseAvailabilityPeriod $period): RedirectResponse
    {
        $period->forceFill(['is_active' => ! $period->is_active])->save();

        AuditTrail::log($request->user(), 'availability.period.toggled', $period, [], [
            'is_active' => $period->is_active,
        ]);

        return $this->backToCourse($period->course, $period->is_active ? (string) setting('availability.admin.toggle_period_ok', 'اتفعّلت الفترة ✓') : (string) setting('availability.admin.toggle_period_ok_2', 'اتوقفت الفترة ✓'));
    }

    public function destroyPeriod(Request $request, CourseAvailabilityPeriod $period): RedirectResponse
    {
        $course = $period->course;

        AuditTrail::log($request->user(), 'availability.period.deleted', $period, $period->only(['starts_on', 'ends_on']), []);
        $period->delete();

        return $this->backToCourse($course, (string) setting('availability.admin.destroy_period_ok', 'اتحذفت الفترة ✓'));
    }

    /**
     * أوقات التشغيل اليوميّة — وتفريغ الحقلين يعني «مفتوح طول اليوم» لا «مقفول».
     */
    public function saveDaily(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'daily_open_at' => ['nullable', 'date_format:H:i'],
            'daily_close_at' => ['nullable', 'date_format:H:i', 'required_with:daily_open_at'],
        ], [], [
            'daily_open_at' => (string) setting('availability.admin.save_daily_msg', 'وقت الفتح اليوميّ'),
            'daily_close_at' => (string) setting('availability.admin.save_daily_msg_2', 'وقت الغلق اليوميّ'),
        ]);

        $old = $course->only(['daily_open_at', 'daily_close_at']);

        $course->forceFill([
            'daily_open_at' => $data['daily_open_at'] ?: null,
            'daily_close_at' => $data['daily_open_at'] ? ($data['daily_close_at'] ?: null) : null,
        ])->save();

        AuditTrail::log($request->user(), 'availability.daily.saved', $course, $old, $course->only(['daily_open_at', 'daily_close_at']));

        return $this->backToCourse($course, (string) setting('availability.admin.save_daily_ok', 'اتحفظ ✓'));
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);

        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', (string) setting('availability.admin.save_settings_ok', 'اتحفظ ✓'));
    }

    public function resetSettings(Request $request): RedirectResponse
    {
        $count = SettingsWriter::resetGroup('availability', $request->user());

        return back()->with('status', strtr((string) setting('availability.admin.reset_settings_ok', 'رجعت :a1 قيمة للافتراضيّ ✓'), [':a1' => (string) ($count)]));
    }

    // ------------------------------------------------------------------ داخليّ

    private function selected(Request $request, mixed $courses): ?Course
    {
        $id = (int) $request->integer('course');

        return $id ? Course::find($id) : $courses->first();
    }

    /** صفّ الجدول: حالة التدريب الآن + عدد فتراته + نافذته اليوميّة */
    private function row(Course $course): array
    {
        $periods = $this->availability->periods($course);
        $daily = $this->availability->dailyWindow($course);

        return [
            'course' => $course,
            'periods_count' => $periods->count(),
            'daily' => $daily,
            'unrestricted' => $periods->isEmpty() && $daily === null,
        ];
    }

    private function backToCourse(?Course $course, string $status): RedirectResponse
    {
        return redirect()
            ->route('admin.availability.index', $course ? ['course' => $course->id] : [])
            ->with('status', $status);
    }
}
