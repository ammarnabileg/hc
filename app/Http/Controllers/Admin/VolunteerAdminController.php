<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Membership;
use App\Models\Offboarding;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\CapacityReport;
use App\Services\Admin\Volunteer\CertificateEligibility;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Services\Admin\Volunteer\VolunteerAnalytics;
use App\Services\Volunteer\Org\HonoraryElement;
use App\Support\Scope\ScopeFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            // النطاق إلزاميّ مع كلّ صلاحيّة (12.2.1-ب) — التسكينات المعروضة نطاقُه هو
            'recentPlacements' => Membership::query()
                ->tap(fn ($q) => app(ScopeFilter::class)->apply($q, $request->user(), 'memberships.list', 'user_id', 'entity_id'))
                ->with(['user:id,name,code', 'entity:id,name_ar', 'position'])
                ->where('status', 'active')
                ->latest('started_at')
                ->limit((int) setting('volunteer.admin.recent_placements', 6))
                ->get(),
            'alerts' => [
                'overflows' => CapacityReport::overflows($request->user())->count(),
                'unhealthy' => CapacityReport::unhealthy($request->user())->count(),
                'pendingExits' => Offboarding::query()->whereNull('completed_at')->count(),
                'pendingCertificates' => CertificateEligibility::pending((int) setting('volunteer_cert.pending_scan_limit', 200), $request->user())->count(),
            ],
            'loads' => VolunteerAnalytics::loads()->take((int) setting('volunteer.admin.loads_preview', 5)),
            'page' => SettingsWriter::groupRows('volunteer_page'),
            'blocks' => self::blocks(),
            'blockTypes' => self::blockTypes(),
            // 🔒 العنصر الشرفيّ: مجموعته تُحمَّل لمالك المنصّة وحده — والباقي لا يرى الحقول أصلًا
            'honorary' => $request->user()->isPlatformOwner() ? SettingsWriter::groupRows('volunteer_honorary') : [],
            'honoraryPlaces' => HonoraryElement::PLACES,
            'honoraryFrames' => HonoraryElement::FRAMES,
            'honoraryAccounts' => $request->user()->isPlatformOwner() ? self::honoraryAccounts() : collect(),
        ]);
    }

    /**
     * حفظ إعدادات العنصر الشرفيّ (13.4-ص-د) — **لمالك المنصّة وحده 🔒 مع Audit**.
     * والحارس هنا في الخادم لا في إخفاء الحقل وحده، فالإخفاء تجربةٌ لا حماية.
     */
    public function saveHonorary(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isPlatformOwner(), 403);

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.volunteer\\.honorary\\.user_id' => ['nullable', 'integer', 'min:0'],
            'settings.volunteer\\.honorary\\.frame_style' => ['nullable', 'string', 'in:'.implode(',', array_keys(HonoraryElement::FRAMES))],
        ]);

        $settings = $data['settings'];

        // أماكن الظهور تصل كصناديق اختيار — تُخزَّن JSON بمفاتيح مقفولة لا حرّة
        if ($request->has('places')) {
            $places = [];

            foreach (array_keys(HonoraryElement::PLACES) as $place) {
                $places[$place] = (bool) $request->input('places.'.$place, false);
            }

            $settings['volunteer.honorary.places'] = $places;
        }

        SettingsWriter::putMany($settings, $request->user());
        AuditTrail::log($request->user(), 'honorary.settings.update', null, [], ['keys' => array_keys($settings)]);

        return back()->with('status', (string) setting('volunteer.admin.save_honorary_ok', 'اتحفظ ✓ — إعدادات العنصر الشرفيّ اتحدّثت.'));
    }

    /** مفاتيح أنواع كتل صفحة التطوّع (13.4-أ) — مفاتيح داخليّة لا نصوصًا */
    public const BLOCK_TYPE_KEYS = ['faq', 'story', 'impact', 'section'];

    /**
     * أسماء أنواع الكتل كما تُعرَض — ميثودٌ لا `const` كي يحرّرها المالك (2.13-أ).
     *
     * @return array<string, string>
     */
    public static function blockTypes(): array
    {
        return [
            'faq' => (string) setting('volunteer.admin.block_type_faq', 'سؤال شائع'),
            'story' => (string) setting('volunteer.admin.block_type_story', 'قصّة متطوّع'),
            'impact' => (string) setting('volunteer.admin.block_type_impact', 'إبراز الأثر'),
            'section' => (string) setting('volunteer.admin.block_type_section', 'قسم حرّ'),
        ];
    }

    /** حفظ حقول صفحة التطوّع (العنوان · الميثاق · الإحصائيّات …) */
    public function savePage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', (string) setting('volunteer.admin.save_page_ok', 'اتحفظ ✓ — محتوى صفحة التطوّع اتحدّث.'));
    }

    /** إضافة/تعديل كتلة محتوى — نفس المسار لأنّ الفرق مفتاح واحد */
    public function saveBlock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'index' => ['nullable', 'integer', 'min:0'],
            'type' => ['required', 'string', 'in:'.implode(',', self::BLOCK_TYPE_KEYS)],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $blocks = self::blocks();
        $block = ['type' => $data['type'], 'title' => $data['title'], 'body' => $data['body']];

        $index = $data['index'] ?? null;

        if ($index !== null && isset($blocks[$index])) {
            $blocks[$index] = $block;
            $message = (string) setting('volunteer.admin.save_block_ok', 'اتحفظ ✓ — الكتلة اتعدّلت.');
        } else {
            $blocks[] = $block;
            $message = (string) setting('volunteer.admin.save_block_ok_2', 'اتحفظ ✓ — الكتلة اتضافت.');
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
            return back()->with('status', (string) setting('volunteer.admin.delete_block_denied', 'الكتلة مش موجودة — يمكن اتحذفت قبل كده.'));
        }

        unset($blocks[$index]);
        SettingsWriter::put('volunteer_page.blocks', array_values($blocks), $request->user());

        return back()->with('status', (string) setting('volunteer.admin.delete_block_ok', 'اتحذفت ✓'));
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
            // المستحقّون والصادر — كلاهما داخل نطاق صاحب الشاشة (12.2.1-ب)
            'pending' => CertificateEligibility::pending((int) setting('volunteer_cert.pending_rows', 20), $request->user()),
            'issued' => Certificate::query()
                ->tap(fn ($q) => app(ScopeFilter::class)->apply($q, $request->user(), 'volunteer_certificates.view'))
                ->with(['user:id,name,code'])
                ->whereIn('certificate_type_id', $typeIds->values())
                ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
                ->latest('issued_at')
                ->limit((int) setting('volunteer_cert.issued_rows', 30))
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

        return back()->with('status', (string) setting('volunteer.admin.save_certificate_settings_ok', 'اتحفظ ✓ — شروط شهادات التطوّع اتحدّثت.'));
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
            ? (string) setting('volunteer.admin.issue_certificate_ok', 'صدرت الشهادة ✓ — واحتفال ذروة في انتظار صاحبها.')
            : strtr((string) setting('volunteer.admin.issue_certificate_msg', 'ما صدرتش: :a1'), [':a1' => (string) ($result['reason'])]));
    }

    /** الإصدار التلقائيّ لكلّ مستحقّ — واحتفال ذروة لكلّ صاحب شهادة (13.4-ع-د) */
    public function autoIssueCertificates(Request $request): RedirectResponse
    {
        if (! setting('volunteer_cert.auto_issue', true)) {
            return back()->with('status', (string) setting('volunteer.admin.auto_issue_certificates_msg', 'الإصدار التلقائيّ موقوف من الإعدادات — فعّله الأوّل.'));
        }

        $count = CertificateEligibility::autoIssue($request->user());

        return back()->with('status', $count
            ? strtr((string) setting('volunteer.admin.auto_issue_certificates_ok', 'صدرت :a1 شهادة ✓'), [':a1' => (string) ($count)])
            : (string) setting('volunteer.admin.auto_issue_certificates_empty', 'مفيش مستحقّين جدد دلوقتي.'));
    }

    /** ⭐ الإلغاء للتزوير المثبَت وحده */
    public function revokeCertificate(Request $request, Certificate $certificate): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'fraud_confirmed' => ['accepted'],
        ]);

        CertificateEligibility::revoke($certificate, $data['reason'], $request->user());

        return back()->with('status', (string) setting('volunteer.admin.revoke_certificate_msg', 'اتلغت الشهادة وسُجِّل السبب — والإقصاء وحده لا يُلغي شهادة عن عمل حقيقيّ.'));
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
            'capacity' => CapacityReport::rows(null, $request->user()),
            'granters' => VolunteerAnalytics::granterWatch($days),
            'settings' => SettingsWriter::groupRows('volunteer_analytics'),
        ]);
    }

    public function saveAnalyticsSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);
        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', (string) setting('volunteer.admin.save_analytics_settings_ok', 'اتحفظ ✓'));
    }

    /** ↺ Reset لتاب كامل */
    public function resetGroup(Request $request, string $group): RedirectResponse
    {
        $allowed = ['volunteer_page', 'volunteer_cert', 'volunteer_analytics', 'volunteer_org', 'volunteer_rep', 'volunteer_offboarding'];

        // 🔒 مجموعة العنصر الشرفيّ لمالك المنصّة وحده (13.4-ص-د)
        if ($group === 'volunteer_honorary') {
            abort_unless($request->user()->isPlatformOwner(), 403);
            $allowed[] = 'volunteer_honorary';
        }

        abort_unless(in_array($group, $allowed, true), 404);

        $count = SettingsWriter::resetGroup($group, $request->user());
        AuditTrail::log($request->user(), 'settings.reset_group', null, [], ['group' => $group, 'count' => $count]);

        return back()->with('status', strtr((string) setting('volunteer.admin.reset_group_ok', 'رجعت :a1 قيمة للافتراضيّ ✓'), [':a1' => (string) ($count)]));
    }

    /**
     * الحسابات المرشَّحة للعنصر الشرفيّ: مالك المنصّة ومَن يحمل بوزشنًا شرفيًّا —
     * قائمةٌ قصيرة مقصودة، فالعنصر **واحد** لا قائمة اختيار مفتوحة (13.4-ص).
     *
     * @return Collection<int, User>
     */
    private static function honoraryAccounts(): Collection
    {
        return User::query()
            ->where(fn ($q) => $q
                ->whereHas('roles', fn ($r) => $r->where('key', (string) config('access.owner_role')))
                ->orWhereHas('memberships', fn ($m) => $m->whereHas('position', fn ($p) => $p->where('is_honorary', true))))
            ->orderBy('name')
            ->limit((int) setting('volunteer.honorary.accounts_limit', 20))
            ->get(['id', 'name', 'code']);
    }

    /** @return array<int,array{type:string,title:string,body:string}> */
    private static function blocks(): array
    {
        $raw = Setting::query()->where('key', 'volunteer_page.blocks')->value('value');
        $blocks = json_decode((string) $raw, true);

        return is_array($blocks) ? $blocks : [];
    }
}
