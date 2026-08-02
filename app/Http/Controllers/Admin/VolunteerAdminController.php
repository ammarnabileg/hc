<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Membership;
use App\Models\Offboarding;
use App\Models\Position;
use App\Models\Setting;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\CapacityReport;
use App\Services\Admin\Volunteer\CertificateEligibility;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Services\Admin\Volunteer\VolunteerAnalytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الإدارة المركزيّة للتطوّع (13.4-ك · 24.2).
 *
 * كانت في الدستور سطرًا واحدًا فصارت شاشات: لوحة تطوّع بأعدادها وتنبيهاتها،
 * ومعها **صفحة التطوّع التعريفيّة يُدار محتواها بالكامل** (تعديل/إضافة/حذف)،
 * وشهادات التطوّع بشرطَي استحقاقها، وتحليلات تُقرأ للقرار لا للزينة.
 */
class VolunteerAdminController extends Controller
{
    /** لوحة التطوّع: أعداد · تسكينات · مهامّ · تنبيهات + محتوى صفحة التطوّع */
    public function index(Request $request): View
    {
        $days = VolunteerAnalytics::rangeDays((int) $request->integer('days'));

        return view('admin.volunteer.index', [
            'kpis' => VolunteerAnalytics::kpis($days),
            'days' => $days,
            'recentPlacements' => Membership::query()
                ->with(['user:id,name,code', 'entity:id,name_ar', 'position'])
                ->where('status', 'active')
                ->latest('started_at')
                ->limit(6)
                ->get(),
            'alerts' => [
                'overflows' => CapacityReport::overflows()->count(),
                'unhealthy' => CapacityReport::unhealthy()->count(),
                'pendingExits' => Offboarding::query()->whereNull('completed_at')->count(),
                'pendingCertificates' => CertificateEligibility::pending(200)->count(),
            ],
            'loads' => VolunteerAnalytics::loads()->take(5),
            'page' => SettingsWriter::groupRows('volunteer_page'),
            'blocks' => self::blocks(),
            'blockTypes' => self::BLOCK_TYPES,
        ]);
    }

    /** أنواع كتل صفحة التطوّع التعريفيّة (13.4-أ) */
    public const BLOCK_TYPES = [
        'faq' => 'سؤال شائع',
        'story' => 'قصّة متطوّع',
        'impact' => 'إبراز الأثر',
        'section' => 'قسم حرّ',
    ];

    /** حفظ حقول صفحة التطوّع (العنوان · الميثاق · الإحصائيّات …) */
    public function savePage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓ — محتوى صفحة التطوّع اتحدّث.');
    }

    /** إضافة/تعديل كتلة محتوى — نفس المسار لأنّ الفرق مفتاح واحد */
    public function saveBlock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'index' => ['nullable', 'integer', 'min:0'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(self::BLOCK_TYPES))],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $blocks = self::blocks();
        $block = ['type' => $data['type'], 'title' => $data['title'], 'body' => $data['body']];

        $index = $data['index'] ?? null;

        if ($index !== null && isset($blocks[$index])) {
            $blocks[$index] = $block;
            $message = 'اتحفظ ✓ — الكتلة اتعدّلت.';
        } else {
            $blocks[] = $block;
            $message = 'اتحفظ ✓ — الكتلة اتضافت.';
        }

        SettingsWriter::put('volunteer_page.blocks', array_values($blocks), $request->user());

        return back()->with('status', $message);
    }

    /** حذف كتلة */
    public function deleteBlock(Request $request): RedirectResponse
    {
        $index = (int) $request->integer('index');
        $blocks = self::blocks();

        if (! isset($blocks[$index])) {
            return back()->with('status', 'الكتلة مش موجودة — يمكن اتحذفت قبل كده.');
        }

        unset($blocks[$index]);
        SettingsWriter::put('volunteer_page.blocks', array_values($blocks), $request->user());

        return back()->with('status', 'اتحذفت ✓');
    }

    // ------------------------------------------------------------ الشهادات (13.4-ع)

    public function certificates(Request $request): View
    {
        $typeIds = CertificateType::query()
            ->whereIn('key', array_keys(CertificateEligibility::TYPES))
            ->pluck('id', 'key');

        return view('admin.volunteer.certificates', [
            'types' => CertificateEligibility::enabledTypes(),
            'settings' => SettingsWriter::groupRows('volunteer_cert'),
            'minDays' => CertificateEligibility::minDays(),
            'pending' => CertificateEligibility::pending(20),
            'issued' => Certificate::query()
                ->with(['user:id,name,code'])
                ->whereIn('certificate_type_id', $typeIds->values())
                ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
                ->latest('issued_at')
                ->limit(30)
                ->get(),
            'positions' => Position::query()->where('is_honorary', false)->orderBy('rank')->get(),
        ]);
    }

    public function saveCertificateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.volunteer_cert.min_days_in_position' => ['nullable', 'integer', 'min:0'],
        ]);

        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓ — شروط شهادات التطوّع اتحدّثت.');
    }

    /** إصدار شهادة بوزشن — يحترم شرطَي المدّة وRep */
    public function issueCertificate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'membership_id' => ['required', 'integer', 'exists:memberships,id'],
        ]);

        $membership = Membership::with(['user', 'entity', 'position'])->findOrFail($data['membership_id']);
        $result = CertificateEligibility::issueForMembership($membership, $request->user());

        return back()->with('status', $result['issued']
            ? 'صدرت الشهادة ✓ — واحتفال ذروة في انتظار صاحبها.'
            : 'ما صدرتش: '.$result['reason']);
    }

    /** الإصدار التلقائيّ لكلّ مستحقّ — واحتفال ذروة لكلّ صاحب شهادة (13.4-ع-د) */
    public function autoIssueCertificates(Request $request): RedirectResponse
    {
        if (! setting('volunteer_cert.auto_issue', true)) {
            return back()->with('status', 'الإصدار التلقائيّ موقوف من الإعدادات — فعّله الأوّل.');
        }

        $count = CertificateEligibility::autoIssue($request->user());

        return back()->with('status', $count
            ? 'صدرت '.$count.' شهادة ✓'
            : 'مفيش مستحقّين جدد دلوقتي.');
    }

    /** ⭐ الإلغاء للتزوير المثبَت وحده */
    public function revokeCertificate(Request $request, Certificate $certificate): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'fraud_confirmed' => ['accepted'],
        ]);

        CertificateEligibility::revoke($certificate, $data['reason'], $request->user());

        return back()->with('status', 'اتلغت الشهادة وسُجِّل السبب — والإقصاء وحده لا يُلغي شهادة عن عمل حقيقيّ.');
    }

    // ------------------------------------------------------------ التحليلات

    public function analytics(Request $request): View
    {
        $days = VolunteerAnalytics::rangeDays((int) $request->integer('days'));

        return view('admin.volunteer.analytics', [
            'days' => $days,
            'kpis' => VolunteerAnalytics::kpis($days),
            'attrition' => VolunteerAnalytics::attrition($days),
            'loads' => VolunteerAnalytics::loads(),
            'health' => VolunteerAnalytics::entityHealth(),
            'capacity' => CapacityReport::rows(),
            'granters' => VolunteerAnalytics::granterWatch($days),
            'settings' => SettingsWriter::groupRows('volunteer_analytics'),
        ]);
    }

    public function saveAnalyticsSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);
        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }

    /** ↺ Reset لتاب كامل */
    public function resetGroup(Request $request, string $group): RedirectResponse
    {
        $allowed = ['volunteer_page', 'volunteer_cert', 'volunteer_analytics', 'volunteer_org', 'volunteer_rep', 'volunteer_offboarding'];

        abort_unless(in_array($group, $allowed, true), 404);

        $count = SettingsWriter::resetGroup($group, $request->user());
        AuditTrail::log($request->user(), 'settings.reset_group', null, [], ['group' => $group, 'count' => $count]);

        return back()->with('status', 'رجعت '.$count.' قيمة للافتراضيّ ✓');
    }

    /** @return array<int,array{type:string,title:string,body:string}> */
    private static function blocks(): array
    {
        $raw = Setting::query()->where('key', 'volunteer_page.blocks')->value('value');
        $blocks = json_decode((string) $raw, true);

        return is_array($blocks) ? $blocks : [];
    }
}
