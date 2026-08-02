<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateAccreditation;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Services\Admin\Content\CertificateBulkIssuer;
use App\Services\Admin\Content\ContentAudit;
use App\Services\Admin\Content\TemplateDesigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    ) {}

    public function index(Request $request): View
    {
        // تحميل كسول للتابات: لا نجهّز إلّا بيانات التاب المفتوح (2.15-د)
        $tab = $request->string('tab')->toString() ?: 'accreditations';
        $tab = in_array($tab, ['accreditations', 'types', 'issue', 'ledger'], true) ? $tab : 'accreditations';

        return view('admin.certificates.index', array_merge([
            'tab' => $tab,
            'tabs' => $this->tabs(),
        ], match ($tab) {
            'accreditations' => $this->accreditationsData(),
            'types' => $this->typesData(),
            'issue' => $this->issueData(),
            default => $this->ledgerData($request),
        }));
    }

    // ============================================================ 1) الاعتمادات

    public function storeAccreditation(Request $request): RedirectResponse
    {
        $data = $this->accreditationRules($request);

        $accreditation = CertificateAccreditation::create($data + ['is_platform' => false]);
        $this->audit->record($accreditation, 'accreditation.created', [], $data);

        return back()->with('status', 'اتضاف الاعتماد ✓');
    }

    public function updateAccreditation(Request $request, CertificateAccreditation $accreditation): RedirectResponse
    {
        $accreditation->update($this->accreditationRules($request));
        $this->audit->record($accreditation, 'accreditation.updated', [], []);

        return back()->with('status', 'اتحفظ ✓');
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
            return back()->with('status', 'فيه أنواع شهادات مربوطة بالاعتماد ده — فكّها الأوّل.');
        }

        $this->audit->record($accreditation, 'accreditation.deleted', ['name_ar' => $accreditation->name_ar], []);
        $accreditation->delete();

        return back()->with('status', 'اتشال الاعتماد ✓');
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

        return back()->with('status', 'اتضاف نوع الشهادة — وتصميمه الافتراضيّ جاهز ✓');
    }

    public function updateType(Request $request, CertificateType $type): RedirectResponse
    {
        $type->update($this->typeRules($request));
        $this->audit->record($type, 'certificate_type.updated', [], []);

        return back()->with('status', 'اتحفظ ✓');
    }

    public function destroyType(CertificateType $type): RedirectResponse
    {
        if (Certificate::query()->where('certificate_type_id', $type->id)->exists()) {
            return back()->with('status', 'فيه شهادات صادرة بالنوع ده — عطّله بدل ما تشيله.');
        }

        $this->audit->record($type, 'certificate_type.deleted', ['name_ar' => $type->name_ar], []);
        $type->delete();

        return back()->with('status', 'اتشال النوع ✓');
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

        return back()->with('status', 'اتظبطت اللغات على كلّ الأنواع ✓');
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
        [$type, $codes, $language] = $this->issueInput($request);

        $templates = $this->designer->templatesFor($type);
        $template = $templates[$language] ?? $templates['ar'];

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
        [$type, $codes, $language] = $this->issueInput($request);

        $result = $this->issuer->issueBatch($codes, $type, $language, $request->user());

        $message = 'اتصدرت '.$result['issued'].' شهادة ✓';

        if ($result['skipped'] > 0) {
            $message .= ' — و'.$result['skipped'].' اتخطّيناها لأنّها صدرت قبل كده.';
        }

        if ($result['failed'] !== []) {
            $message .= ' وفيه '.count($result['failed']).' كود مش موجود.';
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

        return back()->with('status', 'اتلغت الشهادة، والسبب متسجّل ✓');
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
            ? 'اتصدرت نسخة مصحّحة بكود '.$fresh->code.' ✓'
            : 'مقدرناش نعيد الإصدار — راجع نوع الشهادة.');
    }

    /** تصدير/طباعة جماعيّة بالنوع أو بأكواد الأشخاص (12.5-د). */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->issuer->ledger($request->all())->getCollection();
        $filename = 'certificates-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['كود الشهادة', 'صاحبها', 'كوده', 'النوع', 'المصدر', 'تاريخ الإصدار', 'الحالة']);

            foreach ($rows as $certificate) {
                fputcsv($out, [
                    $certificate->code,
                    $certificate->user?->name,
                    $certificate->user?->code,
                    $certificate->certificate_type?->name_ar,
                    $certificate->source,
                    $certificate->issued_at?->format('Y-m-d'),
                    CertificateBulkIssuer::STATUSES[$certificate->status] ?? $certificate->status,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array{0: CertificateType, 1: array<int, string>, 2: string} */
    private function issueInput(Request $request): array
    {
        $data = $request->validate([
            'certificate_type_id' => ['required', 'integer', 'exists:certificate_types,id'],
            'codes' => ['required', 'string'],
            'language' => ['nullable', 'string', 'in:ar,en'],
        ]);

        $type = CertificateType::query()->findOrFail($data['certificate_type_id']);
        $language = $data['language'] ?? ($type->lang_ar_enabled ? 'ar' : 'en');

        return [$type, $this->issuer->parseCodes($data['codes']), $language];
    }

    /** @return array<string, mixed> */
    private function accreditationsData(): array
    {
        $accreditations = CertificateAccreditation::query()->orderByDesc('is_platform')->orderBy('id')->get();

        $typeCounts = CertificateType::query()
            ->selectRaw('accreditation_id, count(*) as total')
            ->groupBy('accreditation_id')
            ->pluck('total', 'accreditation_id');

        $issuedCounts = DB::table('certificates')
            ->join('certificate_types', 'certificate_types.id', '=', 'certificates.certificate_type_id')
            ->selectRaw('certificate_types.accreditation_id as accreditation_id, count(*) as total')
            ->groupBy('certificate_types.accreditation_id')
            ->pluck('total', 'accreditation_id');

        return compact('accreditations', 'typeCounts', 'issuedCounts');
    }

    /** @return array<string, mixed> */
    private function typesData(): array
    {
        $types = CertificateType::query()->with('accreditation')->orderBy('id')->get();

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
        ];
    }

    /** @return array<string, mixed> */
    private function issueData(): array
    {
        return [
            'types' => CertificateType::query()->where('is_active', true)->orderBy('name_ar')->get(),
            'batchLimit' => (int) setting('certificates.issue.batch_limit', 200),
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
            'certificates' => $this->issuer->ledger($filters),
            'filters' => $filters,
            'types' => CertificateType::query()->orderBy('name_ar')->get(),
            'statuses' => CertificateBulkIssuer::STATUSES,
            'sources' => (array) setting('certificates.sources', ['manual' => 'يدويّ', 'auto' => 'تلقائيّ', 'import' => 'مستورد']),
            'revokeReasons' => (array) setting('certificates.revoke.reasons', ['تزوير مثبَت', 'بيانات خاطئة', 'طلب صاحبها']),
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
            'is_active' => ['nullable', 'boolean'],
            'bindings' => ['nullable', 'array'],
            'bindings.*.table' => ['required_with:bindings', 'string'],
            'bindings.*.column' => ['required_with:bindings', 'string'],
        ]);

        // الربط بقاعدة البيانات لا يُقبَل خارج القائمة البيضاء — واجهة حسّاسة (24.1)
        $data['bindings'] = json_encode(
            collect($data['bindings'] ?? [])
                ->filter(fn ($b) => $this->designer->bindingAllowed((string) $b['table'], (string) $b['column']))
                ->map(fn ($b) => ['table' => $b['table'], 'column' => $b['column']])
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
