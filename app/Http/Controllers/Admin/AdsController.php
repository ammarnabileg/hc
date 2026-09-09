<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAudience;
use App\Models\AdAudienceExport;
use App\Models\Setting;
use App\Services\Admin\System\SettingsRegistry;
use App\Services\Ads\AudienceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * الإعلان المدفوع: إعادة الاستهداف والجمهور المشابه (21.3).
 *
 * ⭐ **الشرائح تُبنى من قاعدة بياناتنا** لا من البكسل وحده — لأنّنا نعرف ما لا تعرفه
 *    المنصّات الإعلانيّة. و**التصدير مشفَّر SHA-256** فالبيانات الخام لا تغادر خوادمنا أبدًا.
 * 🔒 ومعرّفات البكسل ومفاتيح الـConversions API لمالك المنصّة وحده.
 */
class AdsController extends Controller
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly AudienceResolver $audiences,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.articles.ads', [
            'audiences' => AdAudience::query()->latest('id')->paginate((int) setting('ads.audiences.per_page', 20)),
            'exports' => AdAudienceExport::query()->with('ad_audience')->latest('id')->limit((int) setting('ads.exports.rows', 10))->get(),
            'rules' => $this->rules(),
            // 🔒 المفاتيح لا تُعرَض لغير مالك المنصّة
            'pixelSettings' => $request->user()->isPlatformOwner()
                ? Setting::query()->where('key', 'like', 'ads.%')->orderBy('key')->get()
                : Setting::query()->where('key', 'like', 'ads.%')->where('is_owner_only', false)->orderBy('key')->get(),
            'registry' => $this->registry,
        ]);
    }

    /** الشروط المعتمَدة للشرائح — قائمة مقفولة لا شروط حرّة (21.3-ب) */
    public function rules(): array
    {
        return AudienceResolver::rules();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'kind' => ['required', 'in:retargeting,lookalike_source'],
            'rule' => ['required', 'string'],
            'ttl_days' => ['required', 'integer', 'min:1', 'max:365'],
            'refresh_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        abort_unless(array_key_exists($data['rule'], $this->rules()), 422, (string) setting('ads.admin.store_denied', 'الشرط ده مش من القائمة المعتمَدة.'));

        AdAudience::create([
            'name' => $data['name'],
            'kind' => $data['kind'],
            'rule' => ['key' => $data['rule']],
            'ttl_days' => $data['ttl_days'],
            'refresh_hours' => $data['refresh_hours'],
            'is_active' => true,
        ]);

        return back()->with('status', (string) setting('ads.admin.store_ok', 'الشريحة اتحفظت ✓'));
    }

    /**
     * ⭐ التصدير حسّاس لأنّه يُخرِج شريحةً من المنصّة — ولذلك:
     *  - لمالك المنصّة وحده،
     *  - **والبريد والهاتف يُشفَّران SHA-256 قبل الكتابة**، فالمطابقة بالبصمة لا بالبيانات.
     */
    public function export(Request $request, AdAudience $audience): RedirectResponse
    {
        abort_unless($request->user()->isPlatformOwner(), 403, (string) setting('ads.admin.export_msg', 'تصدير الشرائح لمالك المنصّة وحده.'));

        $users = $this->resolve($audience);
        $lines = ['email_sha256,phone_sha256'];

        foreach ($users as $user) {
            // ⭐ من لا هاتف له يخرج بخانة فارغة لا ببصمة النصّ الفارغ — فلا يُطابِق أحدًا (21.3)
            $digits = preg_replace('/\D+/', '', (string) $user->phone);
            $lines[] = hash('sha256', mb_strtolower(trim((string) $user->email)))
                .','.($digits !== '' ? hash('sha256', $digits) : '');
        }

        $path = 'exports/audiences/'.$audience->id.'-'.now()->format('Ymd-His').'.csv';
        Storage::disk('local')->put($path, implode("\n", $lines));

        AdAudienceExport::create([
            'ad_audience_id' => $audience->id,
            'exported_by' => $request->user()->id,
            'rows' => count($lines) - 1,
            'file_path' => $path,
            'hash_algo' => 'sha256',
        ]);

        $audience->update(['last_built_at' => now(), 'size' => count($lines) - 1]);

        return back()->with('status', strtr((string) setting('ads.admin.export_ok', 'اتصدّرت :a1 صفّ مشفَّرة SHA-256 ✓'), [':a1' => (string) ((count($lines) - 1))]));
    }

    public function saveSetting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'starts_with:ads.'],
            'value' => ['nullable'],
        ]);

        $setting = Setting::query()->where('key', $data['key'])->firstOrFail();
        $result = $this->registry->save($setting, $data['value'], $request->user());

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    /**
     * بناء الشريحة من بياناتنا — **شرط كلٍّ منفَّذٌ فعلًا** في `AudienceResolver` (21.3-ب).
     * ⭐ ومَن لم يوافق صراحةً على غرض الإعلان يُستبعَد فعليًّا لا شكليًّا (21.3-د).
     */
    private function resolve(AdAudience $audience)
    {
        return $this->audiences->resolve($audience);
    }
}
