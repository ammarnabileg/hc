<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CountrySourceSnapshot;
use App\Models\Governorate;
use App\Services\Admin\System\CountryDataSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 12.7-د «بيانات الدول»: عرض/تحديث مصدر `dr5hn` مع **فحص فروق النسخة قبل
 * الدمج بلا فقد** — والشاشة تابٌ داخل «الإعدادات والنظام» لا صفحةً منفصلة
 * (2.15-د: صفحة واحدة بتابات جانبيّة).
 */
class CountriesController extends Controller
{
    public function __construct(private readonly CountryDataSync $sync) {}

    /** استيراد نسخة من المصدر — الفعل الرئيسيّ الوحيد في الشاشة (2.15-أ-2). */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:'.(int) setting('countries.import.max_kb', 8192)],
        ], [
            'file.required' => 'اختر ملفّ النسخة الأوّل — من غيره مافيش حاجة نفحصها.',
            'file.max' => 'الملفّ أكبر من الحدّ المسموح — اقسمه أو ارفع نسخة أصغر.',
        ]);

        $payload = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($payload)) {
            return $this->back('الملفّ مش JSON صالح — صدّر النسخة تاني من المصدر وارفعها.', error: true);
        }

        try {
            $snapshot = $this->sync->import($payload, $request->user());
        } catch (RuntimeException $exception) {
            return $this->back($exception->getMessage(), error: true);
        }

        $this->sync->check($snapshot);

        return $this->back('اتحفظت النسخة واتفحصت ✓ — راجع الفروق قبل الدمج.');
    }

    /** فحص التحديثات: يعيد حساب الفروق على آخر نسخة (24.3). */
    public function check(): RedirectResponse
    {
        $snapshot = $this->sync->latest();

        if (! $snapshot) {
            return $this->back('مافيش نسخة مرفوعة لسّه — ارفع نسخة المصدر الأوّل.', error: true);
        }

        $this->sync->check($snapshot);

        return $this->back('اتفحصت الفروق ✓');
    }

    /**
     * ⭐ «فحص المصدر الآن»: **يجلب من الشبكة ⇐ يبني الفروق ⇐ يقف** — ولا يدمج
     * حرفًا (12.7-د: «فحص فروق النسخة الجديدة **قبل** الدمج»).
     *
     * وفشل الشبكة يظهر بسببه للمالك ولا يُبتلَع، **واللقطة الأخيرة الناجحة
     * تبقى كما هي** فلا تضيع فروقٌ لم يقرّر فيها بعد.
     */
    public function checkSource(Request $request): RedirectResponse
    {
        $check = $this->sync->checkSource($request->user(), 'manual');

        return $this->back($check->message, error: ! $check->succeeded());
    }

    /**
     * الدمج — و`dry_run` يعرض ما سيقع بلا كتابة (24.3 · 2.11-ط).
     * والفروق تُعرَض للمالك **ليقرّر قبل التنفيذ**: لا يُدمَج إلّا ما اختاره.
     */
    public function merge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'snapshot_id' => ['required', 'integer', 'exists:country_source_snapshots,id'],
            'keys' => ['nullable', 'array'],
            'keys.*' => ['string', 'max:190'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $snapshot = CountrySourceSnapshot::query()->findOrFail($data['snapshot_id']);
        $dryRun = (bool) ($data['dry_run'] ?? false);

        try {
            $report = $this->sync->merge($snapshot, $data['keys'] ?? [], $dryRun, $request->user());
        } catch (RuntimeException $exception) {
            return $this->back($exception->getMessage(), error: true);
        }

        $summary = 'مضاف '.$report['added'].' · معدَّل '.$report['updated'].' · مخفيّ '.$report['hidden']
            .' · محميّ من الحذف '.count($report['protected']);

        return $this->back(($dryRun ? 'معاينة (بلا كتابة): ' : 'اتحفظ ✓ ').$summary)
            ->with('countries_report', $report);
    }

    /** تعديل الاسم العربيّ يدويًّا وإظهار/إخفاء الدولة (24.3). */
    public function updateCountry(Request $request, Country $country): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $country->update([
            'name_ar' => $data['name_ar'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            // إظهارٌ يدويّ يمحو أثر إخفاء الدمج — فلا يُعاد إخفاؤها بلا سبب
            'sync_hidden_at' => ($data['is_active'] ?? false) ? null : $country->sync_hidden_at,
        ]);

        return $this->back('اتحفظ ✓');
    }

    /**
     * تعديل اسم المحافظة — **بلا إخفاء**: المحافظة لا تُخفى أبدًا (قاعدة المالك)،
     * وإخفاؤها يُسقطها من قوائم الاختيار فيصير ارتباط المستخدم بها بلا معنى.
     */
    public function updateGovernorate(Request $request, Governorate $governorate): RedirectResponse
    {
        $data = $request->validate(['name_ar' => ['required', 'string', 'max:120']]);

        $governorate->update(['name_ar' => $data['name_ar']]);

        return $this->back('اتحفظ ✓');
    }

    /** تصدير بيانات الدول بنفس شكل النسخة — فيصلح مدخلًا للفحص لاحقًا. */
    public function export(): StreamedResponse
    {
        $governorates = Governorate::query()->get()->groupBy('country_id');

        $payload = [
            'version' => now()->format('Y-m-d'),
            'countries' => Country::query()->orderBy('iso2')->get()->map(fn (Country $country) => [
                'iso2' => $country->iso2,
                'name_ar' => $country->name_ar,
                'name_en' => $country->name_en,
                'phone_code' => $country->phone_code,
                'timezone' => $country->timezone,
                'governorates' => ($governorates[$country->id] ?? collect())
                    ->map(fn (Governorate $g) => ['name_ar' => $g->name_ar, 'name_en' => $g->name_en])
                    ->values(),
            ])->values(),
        ];

        return response()->streamDownload(
            fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'countries-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    // ------------------------------------------------------------------ داخليّ

    private function back(string $message, bool $error = false): RedirectResponse
    {
        // الرجوع لتاب الدول نفسه — فلا يتوه الأدمن في تابٍ آخر بعد فعله (2.17)
        return redirect()
            ->route('admin.settings.index', ['tab' => 'countries'])
            ->with($error ? 'error' : 'status', $message);
    }
}
