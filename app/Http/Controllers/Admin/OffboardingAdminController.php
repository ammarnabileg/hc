<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offboarding;
use App\Models\Reentry;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\OffboardingService;
use App\Services\Admin\Volunteer\SettingsWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * الأوفبوردنج والعائدون (13.4-س · 13.4-ق).
 *
 * ⭐ قاعدتان لا تُكسران:
 *  - **الإقصاء حصرًا عبر سلّم العتبات** — لا فصل بقرار فرديّ من أبلاين.
 *  - **السبب لا يُنشَر للفريق** — يظهر «انتهت عضويّة فلان» فقط،
 *    والتفصيل في الملاحظات الإداريّة لمن يملك صلاحيّتها.
 */
class OffboardingAdminController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->string('type')->toString();

        $records = Offboarding::query()
            ->with(['user:id,name,code', 'initiated_by:id,name', 'approved_by:id,name'])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->whereHas('user', fn ($u) => $u->where('code', mb_strtoupper($term))->orWhere('name', 'like', '%'.$term.'%')))
            ->latest('id')
            ->limit((int) setting('volunteer.offboarding.admin_rows', 50))
            ->get();

        return view('admin.volunteer.offboarding', [
            'types' => OffboardingService::TYPES,
            'records' => $records,
            'clearance' => OffboardingService::clearanceItems(),
            'settings' => SettingsWriter::groupRows('volunteer_offboarding'),
            'questions' => (array) setting('volunteer.offboarding.exit_interview_questions', []),
            'filters' => ['type' => $type, 'q' => $request->string('q')->toString()],
            'exclusionThreshold' => rep_rule('limit.suspension', -10),
        ]);
    }

    /** شاشة العائدين بحالتها (تبريد · متاح · لا عودة) */
    public function reentries(Request $request): View
    {
        $records = Offboarding::query()
            ->with('user:id,name,code')
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->limit((int) setting('volunteer.offboarding.admin_rows', 50))
            ->get()
            ->map(fn (Offboarding $o) => ['offboarding' => $o] + OffboardingService::reentryState($o));

        return view('admin.volunteer.reentries', [
            'records' => $records,
            'open' => Reentry::query()->with('user:id,name,code')->latest('id')->limit((int) setting('volunteer.offboarding.reentry_rows', 30))->get(),
            'examRequired' => (bool) setting('volunteer.offboarding.reentry_exam_required', true),
            'startsPosition' => (string) setting('volunteer.offboarding.reentry_starts_position', 'coordinator'),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(OffboardingService::TYPES))],
            'reason' => ['nullable', 'string', 'max:1000'],
            'clearance' => ['nullable', 'array'],
        ]);

        $target = User::query()->where('code', mb_strtoupper($data['code']))->first();

        if (! $target) {
            return back()->withInput()->with('status', 'الكود ده مش موجود — راجع الكود وجرّب تاني.');
        }

        try {
            OffboardingService::open($target, $data['type'], $data['reason'] ?? null, $request->user(), $data['clearance'] ?? []);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('status', $e->getMessage());
        }

        return back()->with('status', 'اتفتح ملفّ الإنهاء ✓ — كمّل التصفية الإلزاميّة قبل الإغلاق.');
    }

    /** تحديث التشيك-ليست — لا إنهاء قبل اكتمالها */
    public function saveClearance(Request $request, Offboarding $offboarding): RedirectResponse
    {
        $data = $request->validate(['clearance' => ['nullable', 'array']]);

        $checklist = [];

        foreach (OffboardingService::clearanceItems() as $index => $label) {
            $checklist[] = ['label' => $label, 'done' => (bool) ($data['clearance'][$index] ?? false)];
        }

        $offboarding->forceFill(['clearance_checklist' => $checklist])->save();

        AuditTrail::log($request->user(), 'offboarding.clearance', $offboarding, [], ['done' => collect($checklist)->where('done', true)->count()]);

        return back()->with('status', 'اتحفظ ✓');
    }

    /** مقابلة الخروج — 3 أسئلة تغذّي تقرير أسباب التسرّب */
    public function saveExitInterview(Request $request, Offboarding $offboarding): RedirectResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $offboarding->forceFill([
            'exit_interview_done' => true,
            'exit_interview_notes' => json_encode($data['answers'], JSON_UNESCAPED_UNICODE),
        ])->save();

        AuditTrail::log($request->user(), 'offboarding.exit_interview', $offboarding);

        return back()->with('status', 'اتسجّلت مقابلة الخروج ✓ — شكرًا لوقتك.');
    }

    public function complete(Request $request, Offboarding $offboarding): RedirectResponse
    {
        try {
            OffboardingService::complete($offboarding, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', 'اتقفل الملفّ ✓ — والفريق شاف «انتهت عضويّة فلان» بلا سبب.');
    }

    public function openReentry(Request $request, Offboarding $offboarding): RedirectResponse
    {
        $state = OffboardingService::reentryState($offboarding);

        if ($state['state'] !== 'ok') {
            return back()->with('status', $state['label']);
        }

        OffboardingService::openReentry($offboarding->user()->firstOrFail(), $offboarding, $request->user());

        return back()->with('status', 'اتفتح ملفّ العودة ✓ — والامتحان شرطٌ لدخول قائمة الانتظار الحاليّة.');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);
        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }
}
