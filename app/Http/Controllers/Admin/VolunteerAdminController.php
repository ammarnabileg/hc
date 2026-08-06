<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Offboarding;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Track;
use App\Models\User;
use App\Services\Admin\Content\CertificateBulkIssuer;
use App\Services\Admin\Content\TemplateDesigner;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'honoraryPlaces' => HonoraryElement::placeLabels(),
            'honoraryFrames' => HonoraryElement::frameLabels(),
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
            'settings.volunteer\\.honorary\\.frame_style' => ['nullable', 'string', 'in:'.implode(',', HonoraryElement::FRAME_KEYS)],
        ]);

        $settings = $data['settings'];

        // أماكن الظهور تصل كصناديق اختيار — تُخزَّن JSON بمفاتيح مقفولة لا حرّة
        if ($request->has('places')) {
            $places = [];

            foreach (HonoraryElement::PLACE_KEYS as $place) {
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

    /**
     * شاشة شهادات التطوّع (13.4-ع · 24.2): تابان — **القوالب** (شبكة الأنواع
     * الأربعة، تحرير عبر مصمّم القوالب المشترك 12.5-ب بلا نظام موازٍ) و**السجلّ
     * الصادر** (جدول مفلتَر بالتمرير التدريجيّ 13.1، وفيه أيضًا «مستحقّ ولم تُصدَر»).
     */
    public function certificates(Request $request): View
    {
        $tab = $request->string('tab')->toString() ?: 'ledger';
        $tab = in_array($tab, ['templates', 'ledger'], true) ? $tab : 'ledger';

        return view('admin.volunteer.certificates', array_merge([
            'tab' => $tab,
            'types' => CertificateEligibility::enabledTypes(),
            'settings' => SettingsWriter::groupRows('volunteer_cert'),
            'minDays' => CertificateEligibility::minDays(),
        ], $tab === 'templates' ? $this->certificateTemplatesData() : $this->certificateLedgerData($request)));
    }

    /** ⭐ تمرير تدريجيّ (13.1 · قرار §25): شريحة إضافيّة لسجلّ الشهادات الصادرة بلا ترقيم صفحات */
    public function certificatesMore(Request $request): View
    {
        $filters = $this->certificateFilters($request);
        $perPage = max(1, (int) setting('volunteer_cert.ledger_per_page', 20));
        $offset = max(0, (int) $request->integer('offset'));

        $result = CertificateEligibility::ledger($filters, $request->user(), $offset, $perPage);

        return view('admin.volunteer.certificates.partials.ledger-rows-fragment', [
            'issued' => $result['items'],
            'nextOffset' => $offset + $perPage,
            'hasMore' => $offset + $result['items']->count() < $result['total'],
            'pageSize' => $perPage,
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

    /**
     * ⭐ إصدار يدويّ — **تقدير استثنائيّة وحدها** (13.4-ع-4): مستفيدٌ بالكود
     * ومبرّرٌ إلزاميّ، ومتكرّرة بطبيعتها فلا تُحجَب بشهادةٍ سابقة لنفس الشخص.
     */
    public function issueAppreciationCertificate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $user = User::query()->where('code', $data['code'])->first();

        if (! $user) {
            return back()->with('status', (string) setting('volunteer.admin.appreciation_no_user', 'مفيش مستخدم بالكود ده.'));
        }

        $result = CertificateEligibility::issueAppreciation($user, $data['reason'], $request->user());

        return back()->with('status', $result['issued']
            ? (string) setting('volunteer.admin.appreciation_ok', 'صدرت شهادة التقدير ✓')
            : strtr((string) setting('volunteer.admin.appreciation_fail', 'ما صدرتش: :a1'), [':a1' => (string) ($result['reason'])]));
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

    /** ⭐ تفعيل/إيقاف نوعٍ من الأربعة — من كرت القالب نفسه لا الإعدادات وحدها فقط (24.2) */
    public function toggleCertificateType(Request $request, string $type): RedirectResponse
    {
        abort_unless(in_array($type, CertificateEligibility::TYPE_KEYS, true), 404);

        $flags = (array) setting('volunteer_cert.types', []);
        $flags[$type] = ! (bool) ($flags[$type] ?? true);

        SettingsWriter::putMany(['volunteer_cert.types' => $flags], $request->user());

        return back()->with('status', (string) setting('volunteer.admin.certificate_type_toggled', 'اتحدّثت حالة النوع ✓'));
    }

    /** تصدير سجلّ شهادات التطوّع المفلتَر CSV (24.2 «تصدير السجلّ») */
    public function exportCertificates(Request $request): StreamedResponse
    {
        $filters = $this->certificateFilters($request);
        $result = CertificateEligibility::ledger($filters, $request->user(), 0, (int) setting('volunteer_cert.export_limit', 5000));
        $filename = 'volunteer-certificates-'.now()->format('Ymd-His').'.csv';
        $statuses = CertificateBulkIssuer::statuses();

        return response()->streamDownload(function () use ($result, $statuses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                (string) setting('volunteer.admin.export_col_code', 'كود الشهادة'),
                (string) setting('volunteer.admin.export_col_user', 'المستفيد'),
                (string) setting('volunteer.admin.export_col_type', 'النوع'),
                (string) setting('volunteer.admin.export_col_position', 'البوزشن/الكيان'),
                (string) setting('volunteer.admin.export_col_period', 'المدّة من–إلى'),
                (string) setting('volunteer.admin.export_col_team', 'نطاق المسؤوليّة'),
                (string) setting('volunteer.admin.export_col_issued', 'تاريخ الإصدار'),
                (string) setting('volunteer.admin.export_col_lang', 'اللغة'),
                (string) setting('volunteer.admin.export_col_status', 'الحالة'),
            ]);

            foreach ($result['items'] as $certificate) {
                $summary = CertificateEligibility::rowSummary($certificate);

                fputcsv($out, [
                    $certificate->code,
                    trim(($certificate->user?->name ?? '').' ('.($certificate->user?->code ?? '').')'),
                    $certificate->certificate_type?->name_ar,
                    $summary['position_entity'],
                    $summary['period'],
                    $summary['team_size'] !== null ? (string) $summary['team_size'] : '',
                    $certificate->issued_at?->format('Y-m-d'),
                    $certificate->language,
                    $statuses[$certificate->status] ?? $certificate->status,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // -------------------------------------------------------- شهادات التطوّع: داخليّ

    /** @return array<string, mixed> */
    private function certificateTemplatesData(): array
    {
        $types = CertificateType::query()->whereIn('key', CertificateEligibility::TYPE_KEYS)->get()->keyBy('key');
        $labels = CertificateEligibility::types();
        $designer = app(TemplateDesigner::class);

        $cards = [];

        foreach (CertificateEligibility::TYPE_KEYS as $key) {
            $type = $types[$key] ?? null;

            $cards[] = [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'type' => $type,
                'templates' => $type ? $designer->templatesFor($type) : null,
            ];
        }

        return ['cards' => $cards];
    }

    /** @return array<string, mixed> */
    private function certificateLedgerData(Request $request): array
    {
        $view = $request->string('view')->toString() === 'pending' ? 'pending' : 'ledger';
        $filters = $this->certificateFilters($request);
        $data = ['view' => $view, 'filters' => $filters, 'filterOptions' => $this->certificateFilterOptions()];

        if ($view === 'pending') {
            // المستحقّون والصادر — كلاهما داخل نطاق صاحب الشاشة (12.2.1-ب)
            return $data + ['pending' => CertificateEligibility::pending((int) setting('volunteer_cert.pending_rows', 20), $request->user())];
        }

        $perPage = max(1, (int) setting('volunteer_cert.ledger_per_page', 20));
        $result = CertificateEligibility::ledger($filters, $request->user(), 0, $perPage);

        return $data + [
            'issued' => $result['items'],
            'total' => $result['total'],
            'nextOffset' => $perPage,
            'hasMore' => $result['items']->count() < $result['total'],
            'pageSize' => $perPage,
        ];
    }

    /** @return array<string, mixed> */
    private function certificateFilters(Request $request): array
    {
        return [
            'q' => trim($request->string('q')->toString()),
            'type' => $request->string('type')->toString(),
            'entity_id' => $request->integer('entity_id'),
            'track_id' => $request->integer('track_id'),
            'position_id' => $request->integer('position_id'),
            'language' => $request->string('language')->toString(),
            'status' => $request->string('status')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];
    }

    /** @return array<string, mixed> */
    private function certificateFilterOptions(): array
    {
        return [
            'types' => CertificateEligibility::types(),
            'tracks' => Track::query()->orderBy('name_ar')->get(['id', 'name_ar']),
            'entities' => Entity::query()->where('status', 'active')->orderBy('name_ar')->get(['id', 'name_ar', 'track_id']),
            'positions' => Position::query()->where('is_honorary', false)->orderBy('rank')->get(['id', 'name_ar']),
        ];
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
