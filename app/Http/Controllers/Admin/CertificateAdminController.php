<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateAccreditation;
use App\Models\CertificateReport;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\Event;
use App\Services\Admin\Content\CertificateBulkIssuer;
use App\Services\Admin\Content\ContentAudit;
use App\Services\Admin\Content\TemplateDesigner;
use App\Services\Certificates\CertificateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * إدارة الشهادات (12.5 · 24.1) — أربعة تبويبات بترتيب منطقيّ:
 * **نعرّف** (الاعتمادات) ⟵ **نُعِدّ** (الأنواع والقوالب) ⟵ **نُصدِر** ⟵ **نتابع** (السجلّ).
 *
 * والحالات الثلاث في السجلّ: **سارية · منتهية · ملغاة** — والإلغاء للتزوير المثبَت،
 * أمّا «منتهية» فانتهاء عملٍ لا اتّهام (13.4-ق).
 */
class CertificateAdminController extends Controller
{
    public function __construct(
        private readonly CertificateBulkIssuer $issuer,
        private readonly TemplateDesigner $designer,
        private readonly ContentAudit $audit,
        private readonly CertificateRenderer $renderer,
    ) {}

    public function index(Request $request): View
    {
        // تحميل كسول للتابات: لا نجهّز إلّا بيانات التاب المفتوح (2.15-د)
        $tab = $request->string('tab')->toString() ?: 'accreditations';
        $tab = in_array($tab, ['accreditations', 'types', 'issue', 'ledger', 'verification'], true) ? $tab : 'accreditations';

        return view('admin.certificates.index', array_merge([
            'tab' => $tab,
            'tabs' => $this->tabs(),
        ], match ($tab) {
            'accreditations' => $this->accreditationsData($request),
            'types' => $this->typesData($request),
            'issue' => $this->issueData(),
            'verification' => $this->verificationData($request),
            default => $this->ledgerData($request),
        }));
    }

    // ============================================================ 1) الاعتمادات

    public function storeAccreditation(Request $request): RedirectResponse
    {
        $data = $this->accreditationRules($request);

        $accreditation = CertificateAccreditation::create($data + ['is_platform' => false]);
        $this->audit->record($accreditation, 'accreditation.created', [], $data);

        return back()->with('status', (string) setting('certificates.admin.store_accreditation_ok', 'اتضاف الاعتماد ✓'));
    }

    public function updateAccreditation(Request $request, CertificateAccreditation $accreditation): RedirectResponse
    {
        $accreditation->update($this->accreditationRules($request));
        $this->audit->record($accreditation, 'accreditation.updated', [], []);

        return back()->with('status', (string) setting('certificates.admin.update_accreditation_ok', 'اتحفظ ✓'));
    }

    /** ⭐ اعتماد المنصّة **لا يُحذَف** أبدًا، ولا يُحذَف اعتمادٌ له أنواع مرتبطة (12.5-أ). */
    public function destroyAccreditation(CertificateAccreditation $accreditation): RedirectResponse
    {
        if ($accreditation->is_platform) {
            return back()->with('status', (string) setting(
                'certificates.accreditation.platform_locked_text',
                'اعتماد المنصّة ثابت ولا يتشال — تقدر تعطّله بس.',
            ));
        }

        if (CertificateType::query()->where('accreditation_id', $accreditation->id)->exists()) {
            return back()->with('status', (string) setting('certificates.admin.destroy_accreditation_msg', 'فيه أنواع شهادات مربوطة بالاعتماد ده — فكّها الأوّل.'));
        }

        $this->audit->record($accreditation, 'accreditation.deleted', ['name_ar' => $accreditation->name_ar], []);
        $accreditation->delete();

        return back()->with('status', (string) setting('certificates.admin.destroy_accreditation_ok', 'اتشال الاعتماد ✓'));
    }

    // ============================================================ 2) الأنواع والقوالب

    public function storeType(Request $request): RedirectResponse
    {
        $data = $this->typeRules($request);
        $data['key'] = $this->uniqueKey($data['name_en'] ?: $data['name_ar']);

        $type = CertificateType::create($data);

        // ⭐ تصميم افتراضيّ جاهز لكلّ نوع ولكلّ لغة (12.5-ب)
        $this->designer->templatesFor($type);
        $this->audit->record($type, 'certificate_type.created', [], $data);

        return back()->with('status', (string) setting('certificates.admin.store_type_ok', 'اتضاف نوع الشهادة — وتصميمه الافتراضيّ جاهز ✓'));
    }

    public function updateType(Request $request, CertificateType $type): RedirectResponse
    {
        $type->update($this->typeRules($request));
        $this->audit->record($type, 'certificate_type.updated', [], []);

        return back()->with('status', (string) setting('certificates.admin.update_type_ok', 'اتحفظ ✓'));
    }

    public function destroyType(CertificateType $type): RedirectResponse
    {
        if (Certificate::query()->where('certificate_type_id', $type->id)->exists()) {
            return back()->with('status', (string) setting('certificates.admin.destroy_type_msg', 'فيه شهادات صادرة بالنوع ده — عطّله بدل ما تشيله.'));
        }

        $this->audit->record($type, 'certificate_type.deleted', ['name_ar' => $type->name_ar], []);
        $type->delete();

        return back()->with('status', (string) setting('certificates.admin.destroy_type_ok', 'اتشال النوع ✓'));
    }

    /** ⭐ تفعيل اللغات **مجمَّعًا** لكلّ الأنواع — وإفراديًّا من فورم النوع (12.5-ب). */
    public function bulkLanguages(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lang_ar_enabled' => ['nullable', 'boolean'],
            'lang_en_enabled' => ['nullable', 'boolean'],
        ]);

        CertificateType::query()->update([
            'lang_ar_enabled' => (bool) ($data['lang_ar_enabled'] ?? false),
            'lang_en_enabled' => (bool) ($data['lang_en_enabled'] ?? false),
        ]);

        return back()->with('status', (string) setting('certificates.admin.bulk_languages_ok', 'اتظبطت اللغات على كلّ الأنواع ✓'));
    }

    // ============================================================ 3) الإصدار

    /** «تحقّق من الأكواد» قبل الإصدار: صالح / غير موجود / صدرت له قبل كده (12.5-ج). */
    public function verifyCodes(Request $request): View
    {
        [$type, $codes, $language] = $this->issueInput($request);

        return view('admin.certificates.verified', [
            'type' => $type,
            'language' => $language,
            'rows' => $this->issuer->verify($codes, $type),
            'raw' => $request->string('codes')->toString(),
        ]);
    }

    /** معاينة قبل الإصدار: الشهادات تحت بعضها ببياناتها الحقيقيّة (12.5-ج). */
    public function previewIssue(Request $request): View
    {
        [$type, $codes, $language, $templateId] = $this->issueInput($request);

        // ⭐ [2026-09-10] القالب المختار صراحةً وقت الإصدار — أو الافتراضيّ/الأحدث كما كان (سطر 2406)
        $template = $templateId
            ? CertificateTemplate::query()->where('id', $templateId)->where('certificate_type_id', $type->id)->where('language', $language)->first()
            : null;

        if (! $template) {
            $templates = $this->designer->templatesFor($type);
            $template = $templates[$language] ?? $templates['ar'];
        }

        return view('admin.certificates.preview', [
            'type' => $type,
            'language' => $language,
            'template' => $template,
            'layers' => $this->designer->visibleLayers($this->designer->fromStorage($template->layers)),
            'rows' => $this->issuer->preview($this->issuer->verify($codes, $type), $type, $language),
            'raw' => $request->string('codes')->toString(),
        ]);
    }

    public function issue(Request $request): RedirectResponse
    {
        [$type, $codes, $language, $templateId] = $this->issueInput($request);

        $result = $this->issuer->issueBatch($codes, $type, $language, $request->user(), templateId: $templateId);

        $message = strtr((string) setting('certificates.admin.issue_ok', 'اتصدرت :a1 شهادة ✓'), [':a1' => (string) ($result['issued'])]);

        if ($result['skipped'] > 0) {
            $message .= strtr((string) setting('certificates.admin.issue_msg', ' — و:a1 اتخطّيناها لأنّها صدرت قبل كده.'), [':a1' => (string) ($result['skipped'])]);
        }

        if ($result['failed'] !== []) {
            $message .= strtr((string) setting('certificates.admin.issue_denied', ' وفيه :a1 كود مش موجود.'), [':a1' => (string) (count($result['failed']))]);
        }

        return redirect()
            ->route('admin.certificates.index', ['tab' => 'ledger'])
            ->with('status', $message);
    }

    // ============================================================ 4) السجلّ

    /** الإلغاء بسبب موثّق إلزاميّ + إشعار المستخدم بلباقة (12.5-د). */
    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'notify' => ['nullable', 'boolean'],
        ]);

        $this->issuer->revoke($certificate, $data['reason'], (bool) ($data['notify'] ?? true), $request->user());

        return back()->with('status', (string) setting('certificates.admin.revoke_ok', 'اتلغت الشهادة، والسبب متسجّل ✓'));
    }

    /** إعادة إصدار/تصحيح: يُبطل القديمة ويصدر مصحّحة (12.5-د). */
    public function reissue(Request $request, Certificate $certificate): RedirectResponse
    {
        $data = $request->validate([
            'certificate_name' => ['nullable', 'string', 'max:190'],
        ]);

        $fresh = $this->issuer->reissue(
            $certificate,
            array_filter(['certificate_name' => $data['certificate_name'] ?? null]),
            $request->user(),
        );

        return back()->with('status', $fresh
            ? strtr((string) setting('certificates.admin.reissue_ok', 'اتصدرت نسخة مصحّحة بكود :a1 ✓'), [':a1' => (string) ($fresh->code)])
            : (string) setting('certificates.admin.reissue_msg', 'مقدرناش نعيد الإصدار — راجع نوع الشهادة.'));
    }

    // ============================================================ 5) صفحة التحقّق والبلاغات

    /**
     * ⭐ **مراجعة بلاغ** (24.1): «`pop-box` «مراجعة بلاغ» [التفاصيل + إجراء:
     * تجاهل/إلغاء الشهادة/تصعيد]» — ثلاثة إجراءات لا رابع، ولا دورةَ عملٍ فوقها.
     *
     * و«إلغاء الشهادة» يمرّ من **باب الإلغاء نفسه** لا من باب جانبيّ: النصّ يوجب
     * أن تكون «**صلاحيّة الإلغاء منفصلة عن الإصدار**» (24.1)، فمن لا يملك
     * `certificates.delete` يقدر يتجاهل ويصعّد ولا يقدر يُلغي — والإلغاء يبقى
     * **للتزوير المثبَت وحده** (13.4-ق) بسببٍ موثّق وإشعارٍ لصاحبها.
     */
    public function reviewReport(Request $request, CertificateReport $report): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(CertificateReport::actions())],
            'note' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $certificate = $report->certificate;

        if ($data['action'] === CertificateReport::REVOKED) {
            if (! $request->user()?->can('certificates.delete')) {
                return back()->with('status', (string) setting(
                    'certificates.reports.revoke_forbidden_text',
                    'إلغاء الشهادة صلاحيّة منفصلة — تقدر تتجاهل البلاغ أو تصعّده.',
                ));
            }

            if (! $certificate || $certificate->status !== 'valid') {
                return back()->with('status', (string) setting(
                    'certificates.reports.revoke_unavailable_text',
                    'مفيش شهادة سارية بالكود ده عشان تتلغي.',
                ));
            }

            $this->issuer->revoke(
                $certificate,
                $data['reason'] ?: (string) setting('certificates.reports.default_revoke_reason', 'تزوير مثبَت ببلاغ'),
                true,
                $request->user(),
            );
        }

        $report->update([
            'status' => $data['action'],
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'] ?? null,
        ]);

        // Audit لكلّ مراجعة: مين وامتى وليه (12.5-د)
        $this->audit->record($report, 'certificate_report.reviewed', [], [
            'action' => $data['action'],
            'code' => $report->code,
        ]);

        return back()->with('status', (string) setting('certificates.reports.reviewed_text', 'اتراجع البلاغ ✓'));
    }

    /**
     * ⭐ تصدير/طباعة جماعيّة (24.1 سطر 4676: pop-box «بالنوع/الفعاليّة **أو
     * بأكواد الأشخاص** + الصيغة») — كانت وصلة CSV مباشرة بفلاتر الصفحة
     * الحاليّة فقط، بلا بوب-أب ولا اختيار صيغة ولا فلترة بأكواد الأشخاص، وتصدّر
     * **صفحة السجلّ المعروضة (20) لا كلّ ما طابق** لأنّها كانت تستهلك `ledger()`
     * المُصفَّح. الآن `ledgerForExport()` غير مُصفَّحة (سقفها `certificates.export.row_limit`)،
     * والصيغة CSV أو صورٌ (ZIP) عبر `CertificateRenderer::png()` نفسها — لا مولِّد ثانٍ.
     */
    public function export(Request $request): StreamedResponse
    {
        abort_unless((bool) setting('certificates.export.bulk_enabled', true), 403);

        // «أو» حرفيّة (24.1 سطر 4676): وضعا الفلترة متمانعان — كودٌ ملصوق لا يخلط
        // مع النوع/الفعاليّة، حتى لو بقيت حقول اللوحة الأخرى معبّأةً من اختيارٍ سابق.
        $input = $request->all();

        if ($request->string('mode')->toString() === 'codes') {
            unset($input['type'], $input['event_id']);
        } else {
            unset($input['codes']);
        }

        $rows = $this->issuer->ledgerForExport($input, $request->user());

        return $request->string('format')->toString() === 'images'
            ? $this->exportImages($rows)
            : $this->exportCsv($rows);
    }

    /** @param  Collection<int, Certificate>  $rows */
    private function exportCsv($rows): StreamedResponse
    {
        $filename = 'certificates-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [(string) setting('certificates.admin.export_msg', 'كود الشهادة'), (string) setting('certificates.admin.export_msg_2', 'صاحبها'), (string) setting('certificates.admin.export_msg_3', 'كوده'), (string) setting('certificates.admin.export_msg_4', 'النوع'), (string) setting('certificates.admin.export_msg_5', 'المصدر'), (string) setting('certificates.admin.export_msg_6', 'تاريخ الإصدار'), (string) setting('certificates.admin.export_msg_7', 'الحالة')]);

            foreach ($rows as $certificate) {
                fputcsv($out, [
                    $certificate->code,
                    $certificate->user?->name,
                    $certificate->user?->code,
                    $certificate->certificate_type?->name_ar,
                    $certificate->source,
                    $certificate->issued_at?->format('Y-m-d'),
                    CertificateBulkIssuer::statuses()[$certificate->status] ?? $certificate->status,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param  Collection<int, Certificate>  $rows */
    private function exportImages($rows): StreamedResponse
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'certs-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($rows as $certificate) {
            $zip->addFromString($certificate->code.'.png', $this->renderer->png($certificate));
        }

        $zip->close();

        return response()->streamDownload(function () use ($zipPath) {
            fpassthru(fopen($zipPath, 'r'));
            unlink($zipPath);
        }, 'certificates-'.now()->format('Ymd-His').'.zip', ['Content-Type' => 'application/zip']);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array{0: CertificateType, 1: array<int, string>, 2: string} */
    /** @return array{0: CertificateType, 1: array<int, string>, 2: string, 3: ?int} */
    private function issueInput(Request $request): array
    {
        $data = $request->validate([
            'certificate_type_id' => ['required', 'integer', 'exists:certificate_types,id'],
            'codes' => ['required', 'string'],
            'language' => ['nullable', 'string', 'in:ar,en'],
            // ⭐ [2026-09-10] اختيار القالب وقت الإصدار (سطر 2406) — فاضي = الافتراضيّ/الأحدث كما كان
            'template_id' => ['nullable', 'integer', 'exists:certificate_templates,id'],
        ]);

        $type = CertificateType::query()->findOrFail($data['certificate_type_id']);
        $language = $data['language'] ?? ($type->lang_ar_enabled ? 'ar' : 'en');

        return [$type, $this->issuer->parseCodes($data['codes']), $language, $data['template_id'] ?? null];
    }

    /** @return array<string, mixed> */
    /**
     * ⭐ [2026-09-10] العرض المنصوص (24.1 سطر 4619-4620): **جدول** لا كروت —
     * بحثٌ بالاسم + Chips: الحالة (نشط/معطّل) · مستخدَم/غير مستخدَم.
     */
    private function accreditationsData(Request $request): array
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => $request->string('status')->toString(),
            'used' => $request->string('used')->toString(),
        ];

        $typeCounts = CertificateType::query()
            ->selectRaw('accreditation_id, count(*) as total')
            ->groupBy('accreditation_id')
            ->pluck('total', 'accreditation_id');

        $accreditations = CertificateAccreditation::query()
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name_ar', 'like', '%'.$filters['q'].'%')->orWhere('name_en', 'like', '%'.$filters['q'].'%')))
            ->when($filters['status'] !== '', fn ($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->when($filters['used'] !== '', function ($q) use ($filters, $typeCounts) {
                $ids = $typeCounts->keys()->all();
                $filters['used'] === 'used' ? $q->whereIn('id', $ids) : $q->whereNotIn('id', $ids);
            })
            ->orderByDesc('is_platform')
            ->orderBy('id')
            ->get();

        $issuedCounts = DB::table('certificates')
            ->join('certificate_types', 'certificate_types.id', '=', 'certificates.certificate_type_id')
            ->selectRaw('certificate_types.accreditation_id as accreditation_id, count(*) as total')
            ->groupBy('certificate_types.accreditation_id')
            ->pluck('total', 'accreditation_id');

        return compact('accreditations', 'typeCounts', 'issuedCounts', 'filters');
    }

    /** @return array<string, mixed> */
    private function typesData(Request $request): array
    {
        // ⭐ [2026-09-10] «الضغط ← الأنواع المفلترة» من عمود عدد الأنواع في الاعتمادات (سطر 4620)
        $accreditationId = $request->integer('accreditation_id');

        $types = CertificateType::query()->with('accreditation')
            ->when($accreditationId, fn ($q) => $q->where('accreditation_id', $accreditationId))
            ->orderBy('id')
            ->get();

        $templateCounts = CertificateTemplate::query()
            ->selectRaw('certificate_type_id, count(*) as total')
            ->groupBy('certificate_type_id')
            ->pluck('total', 'certificate_type_id');

        $issued = DB::table('certificates')
            ->selectRaw('certificate_type_id, count(*) as total')
            ->groupBy('certificate_type_id')
            ->pluck('total', 'certificate_type_id');

        return [
            'types' => $types,
            'templateCounts' => $templateCounts,
            'issuedCounts' => $issued,
            'accreditations' => CertificateAccreditation::query()->where('is_active', true)->get(),
            'bindableTables' => $this->designer->bindableTables(),
            'accreditationFilter' => $accreditationId ? CertificateAccreditation::find($accreditationId) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function issueData(): array
    {
        return [
            'types' => CertificateType::query()->where('is_active', true)->orderBy('name_ar')->get(),
            'batchLimit' => (int) setting('certificates.issue.batch_limit', 200),
            // ⭐ [2026-09-10] «قوالب متعدّدة للنوع … اختيار القالب وقت الإصدار» (سطر 2406)
            'templates' => CertificateTemplate::query()->orderByDesc('is_default')->orderByDesc('version')->get(['id', 'certificate_type_id', 'language', 'version', 'is_default']),
        ];
    }

    /** @return array<string, mixed> */
    private function ledgerData(Request $request): array
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'type' => $request->integer('type'),
            'status' => $request->string('status')->toString(),
            'source' => $request->string('source')->toString(),
            'from' => $request->string('from')->toString(),
        ];

        return [
            'certificates' => $this->issuer->ledger($filters, $request->user()),
            'filters' => $filters,
            'types' => CertificateType::query()->orderBy('name_ar')->get(),
            'statuses' => CertificateBulkIssuer::statuses(),
            'sources' => (array) setting('certificates.sources', ['manual' => 'يدويّ', 'auto' => 'تلقائيّ', 'import' => 'مستورد']),
            'revokeReasons' => (array) setting('certificates.revoke.reasons', ['تزوير مثبَت', 'بيانات خاطئة', 'طلب صاحبها']),
            // ⭐ بوب-أب «تصدير/طباعة جماعيّة» (24.1 سطر 4676) — [بالنوع/الفعاليّة أو بأكواد الأشخاص + الصيغة]
            'events' => Event::query()->latest('starts_at')->limit(200)->get(['id', 'title_ar']),
            'exportEnabled' => (bool) setting('certificates.export.bulk_enabled', true),
        ];
    }

    /**
     * جدول البلاغات (24.1): الكود · المبلِّغ · السبب · التاريخ · الحالة · [مراجعة].
     *
     * @return array<string, mixed>
     */
    private function verificationData(Request $request): array
    {
        $status = $request->string('status')->toString();

        $reports = CertificateReport::query()
            ->with(['reporter', 'reviewer', 'certificate.certificate_type'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.ltrim(trim($request->string('q')->toString()), '#').'%';
                $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('reason', 'like', $term));
            })
            // الجديد أوّلًا ثمّ الأحدث — البلاغ الذي لم يُراجَع لا ينزل تحت المراجَع
            ->orderByRaw("case when status = 'new' then 0 else 1 end")
            ->orderByDesc('created_at')
            ->paginate((int) setting('certificates.reports.page_size', 20))
            ->withQueryString();

        return [
            'reports' => $reports,
            'reportFilters' => ['status' => $status, 'q' => $request->string('q')->toString()],
            'reportStatuses' => (array) setting('certificates.reports.statuses', [
                'new' => 'جديد',
                'dismissed' => 'اتجاهل',
                'revoked' => 'اتلغت الشهادة',
                'escalated' => 'اتصعّد',
            ]),
            'reportActions' => (array) setting('certificates.reports.actions', [
                'dismissed' => 'تجاهل',
                'revoked' => 'ألغِ الشهادة',
                'escalated' => 'صعّد',
            ]),
            'revokeReasons' => (array) setting('certificates.revoke.reasons', ['تزوير مثبَت', 'بيانات خاطئة', 'طلب صاحبها']),
            'canRevoke' => (bool) $request->user()?->can('certificates.delete'),
            'newReportsCount' => CertificateReport::query()->where('status', CertificateReport::NEW)->count(),
        ];
    }

    /** @return array<int, array{key: string, label: string, url: string}> */
    private function tabs(): array
    {
        $labels = (array) setting('certificates.tabs', [
            'accreditations' => 'الاعتمادات',
            'types' => 'الأنواع والقوالب',
            'issue' => 'إصدار شهادة',
            'ledger' => 'سجلّ الصادر',
        ]);

        /*
         | ⭐ **صفحة التحقّق** شاشةٌ خامسة في 24.1 لا تابٌ خامس في 12.5: الأخير
         | يعدّ **أربعة تبويبات** (نعرّف ⟵ نُعِدّ ⟵ نُصدِر ⟵ نتابع)، بينما 24.1
         | يفرد لصفحة التحقّق شاشةً بذاتها وفيها **جدول البلاغات**. فنُلحقها هنا
         | بمفتاحها الخاصّ — بلا لمس إعداد التبويبات الأربعة، فلا يفقد تنصيبٌ
         | قائم شاشةً لأنّ قيمة المالك المحفوظة لا تعرف المفتاح الجديد.
         */
        $labels['verification'] ??= (string) setting('certificates.tabs.verification', 'صفحة التحقّق');

        return collect($labels)
            ->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'url' => route('admin.certificates.index', ['tab' => $key]),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function accreditationRules(Request $request): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'verify_note_ar' => ['nullable', 'string', 'max:190'],
            'verify_note_en' => ['nullable', 'string', 'max:190'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function typeRules(Request $request): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            // أهمّ حقل: اختلاف الاعتماد = شهادة مختلفة كليًّا (12.5-ب)
            'accreditation_id' => ['required', 'integer', 'exists:certificate_accreditations,id'],
            'numbering_prefix' => ['nullable', 'string', 'max:16'],
            'numbering_padding' => ['nullable', 'integer', 'min:3', 'max:12'],
            'auto_issue' => ['nullable', 'boolean'],
            'auto_issue_event' => ['nullable', 'string', 'max:64'],
            'lang_ar_enabled' => ['nullable', 'boolean'],
            'lang_en_enabled' => ['nullable', 'boolean'],
            'signature_enabled' => ['nullable', 'boolean'],
            'signature_path' => ['nullable', 'string', 'max:255'],
            'stamp_path' => ['nullable', 'string', 'max:255'],
            'security_elements_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            // كلّ ربط سطر واحد بصيغة «الجدول.العمود»
            'bindings' => ['nullable', 'array'],
            'bindings.*' => ['string', 'max:128'],
        ]);

        // الربط بقاعدة البيانات لا يُقبَل خارج القائمة البيضاء — واجهة حسّاسة (24.1)
        $data['bindings'] = json_encode(
            collect($data['bindings'] ?? [])
                ->map(function (string $pair) {
                    [$table, $column] = array_pad(explode('.', $pair, 2), 2, '');

                    return ['table' => $table, 'column' => $column];
                })
                ->filter(fn (array $b) => $this->designer->bindingAllowed($b['table'], $b['column']))
                ->values()
                ->all(),
            JSON_UNESCAPED_UNICODE,
        );

        foreach (['auto_issue', 'lang_ar_enabled', 'lang_en_enabled', 'signature_enabled', 'is_active'] as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }

        return $data;
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'type';
        $key = $base;
        $i = 1;

        while (CertificateType::query()->where('key', $key)->exists()) {
            $key = $base.'_'.(++$i);
        }

        return mb_substr($key, 0, 64);
    }
}
