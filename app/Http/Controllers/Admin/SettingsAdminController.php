<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAudience;
use App\Models\AuditLog;
use App\Models\MaintenanceWindow;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\System\CountryDataSync;
use App\Services\Admin\System\MaintenanceService;
use App\Services\Admin\System\SettingsRegistry;
use App\Services\Features\FeatureRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ⭐ الإعدادات والنظام: **صفحة واحدة بتابات جانبيّة** (2.15-د) لا عناصر متفرّقة في السايد بار.
 *
 * وكلّ قواعد 2.13-و مطبَّقة هنا: نمط المفتاح `المجال.الميزة.المفتاح` · بحث موحّد
 * بمسار العنصر وتظليل مؤقّت · تصدير/استيراد JSON · مثال قيمة حيّ · حفظ تلقائيّ
 * بـ«تم الحفظ» جنب كلّ حقل · Placeholder بالافتراضيّ وزرّ Reset · Audit بالـHover.
 */
class SettingsAdminController extends Controller
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly MaintenanceService $maintenance,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tabs = $this->registry->tabsFor($user);
        $tab = $request->string('tab')->toString();

        if (! array_key_exists($tab, $tabs)) {
            $tab = (string) array_key_first($tabs);
        }

        $search = $request->string('q')->toString();
        $highlight = $request->string('key')->toString();

        /*
         | ⭐ الشاشة تُصيَّر **رؤوس كروتٍ فقط**: اسم المجموعة ووصفها وعدد مفاتيحها
         | (عدّةُ SQL لا تصييرُ صفوف). ولا تُجلَب الحقول إلّا لدفعة المجموعة التي
         | تُفتَح — تحميلٌ كسول كما تنصّ 2.15-ب، بلا حذف مفتاح ولا إخفائه (2.13).
         | وكان التاب يُبنى كاملًا هنا: تابّ التطوّع 3,353 مفتاحًا في صفحةٍ واحدة.
         */
        $counts = $this->registry->countsForTab($tab, $user, $search);

        // المجموعة التي تُفتَح أوّلًا: مجموعةُ المفتاح القادم من البحث، وإلّا الأولى
        $openGroup = $highlight !== ''
            ? (Setting::query()->where('key', $highlight)->value('group') ?: null)
            : null;

        if ($openGroup === null || ! array_key_exists($openGroup, $counts)) {
            $openGroup = array_key_first($counts);
            $highlight = '';
        }

        // ودفعةُ المفتاح المطلوب هي التي تُفتَح — لا الأولى دائمًا
        $openOffset = ($openGroup !== null && $highlight !== '')
            ? $this->registry->batchStartOfKey($openGroup, $highlight, $user, $search)
            : 0;

        return view('admin.settings.index', [
            'tabs' => $tabs,
            'tab' => $tab,
            'search' => $search,
            'counts' => $counts,
            'openGroup' => $openGroup,
            'openOffset' => $openOffset,
            'batch' => $this->registry->batchSize(),
            'openRows' => $openGroup === null
                ? collect()
                : $this->registry->pageOfGroup($tab, $openGroup, $user, $search, $openOffset),
            'registry' => $this->registry,
            'highlight' => $highlight,
            'maintenance' => $this->maintenance,
            'window' => $this->maintenance->current(),
            'windows' => MaintenanceWindow::query()->latest('id')->limit((int) setting('maintenance.windows_history_limit', 10))->get(),
            'logs' => $tab === 'audit'
                ? AuditLog::query()->with('user')->latest('id')->paginate((int) setting('audit.per_page', 50))
                : null,
            // 12.7-د: جدول الدول + فروق النسخة الجديدة — تحميلٌ كسول للتاب (2.15-أ-10)
            'countries' => $tab === 'countries' ? $this->countriesScreen($request) : null,
            // 24.3: مفاتيح المزايا — كذلك كسولةً، فجدولُها واستعلاماتُه لا يُحمَّلان
            // في كلّ تابٍ آخر
            'features' => $tab === 'features' ? $this->featuresScreen($request) : null,
        ]);
    }

    /**
     * ⭐ **دفعة مفاتيح مجموعة** — نقطةُ التحميل الكسول التي تبني عليها الشاشة.
     *
     * تُنادى عند فتح كارت المجموعة، ثمّ عند كلّ «حمّل المزيد». وتردّ **قطعة
     * HTML** لا JSON: الحقل هنا ليس قيمةً بل صفٌّ كامل بقواعد 2.13-و (المفتاح
     * ظاهرًا · الافتراضيّ مرساةً · ↺ · مثال حيّ · Audit)، وبناؤه في الخادم
     * يبقيه مصدرًا واحدًا لا نسختين تفترقان — واحدةٌ في Blade وأخرى في JS.
     */
    public function groupBatch(Request $request): JsonResponse
    {
        $user = $request->user();
        $tabs = $this->registry->tabsFor($user);
        $tab = $request->string('tab')->toString();

        if (! array_key_exists($tab, $tabs)) {
            return response()->json([
                'message' => (string) setting('settings.batch.error.unknown_tab', 'التاب ده مش موجود — حدّث الصفحة وجرّب تاني.'),
            ], 404);
        }

        $group = $request->string('group')->toString();
        $search = $request->string('q')->toString();
        $offset = max(0, (int) $request->integer('offset'));
        $batch = $this->registry->batchSize();

        $rows = $this->registry->pageOfGroup($tab, $group, $user, $search, $offset, $batch);

        if ($rows->isEmpty() && $offset === 0) {
            return response()->json([
                'message' => (string) setting('settings.batch.error.unknown_group', 'المجموعة دي مش في التاب ده — حدّث الصفحة.'),
            ], 404);
        }

        return response()->json([
            'html' => view('admin.settings.partials.group-fields', [
                'rows' => $rows,
                'registry' => $this->registry,
                'endpoint' => route('admin.settings.field'),
            ])->render(),
            'count' => $rows->count(),
            'next' => $offset + $rows->count(),
        ]);
    }

    /**
     * 🖥️ **مفاتيح المزايا** (24.3) — مادّة الشاشة كاملةً.
     *
     * وفلاترها بأسماء خاصّة (`fq`/`fgroup`/`fstatus`) حتّى لا تصطدم ببحث
     * الإعدادات الموحّد الذي يستعمل `q` في نفس الصفحة.
     *
     * @return array<string, mixed>
     */
    private function featuresScreen(Request $request): array
    {
        $registry = app(FeatureRegistry::class);

        $filters = [
            'q' => trim($request->string('fq')->toString()),
            'group' => $request->string('fgroup')->toString(),
            'status' => $request->string('fstatus')->toString(),
        ];

        $rows = $registry->rows($filters);

        return [
            'filters' => $filters,
            'rows' => $rows,
            'groups' => $registry->groupLabels(),
            'paused' => $registry->pausedCount(),
            'long_outages' => $registry->longOutages(),
            'roles' => Role::query()->orderBy('id')->get(['id', 'name_ar']),
            'segments' => AdAudience::query()->whereNull('archived_at')->orderBy('id')->get(['id', 'name']),
            // أسماء مَن بدّلوا — استعلامٌ واحد بدل استعلامٍ لكلّ صفّ
            'people' => User::query()->whereIn('id', $rows->pluck('last_toggled_by')->filter()->unique())
                ->pluck('name', 'id')->all(),
            'visibility_labels' => [
                'none' => (string) setting('features.ui.visibility.none', 'لا أحد'),
                'admins' => (string) setting('features.ui.visibility.admins', 'الأدمن فقط'),
                'roles' => (string) setting('features.ui.visibility.roles', 'أدوار محدّدة'),
            ],
            'labels' => Setting::query()->where('group', 'features')->pluck('label_ar', 'key')->all(),
        ];
    }

    /**
     * بيانات تاب «بيانات الدول» (12.7-د): الجدول + **فروق النسخة قبل الدمج**.
     *
     * ثلاثة فلاتر ظاهرة فقط، وبأسماء خاصّة بها (`cq`) حتّى لا تصطدم ببحث
     * الإعدادات الموحّد الذي يستعمل `q` في نفس الصفحة.
     *
     * @return array<string, mixed>
     */
    private function countriesScreen(Request $request): array
    {
        $sync = app(CountryDataSync::class);
        $snapshot = $sync->latest();

        $filters = [
            'q' => trim($request->string('cq')->toString()),
            'status' => $request->string('cstatus')->toString(),
            'with_users' => $request->boolean('cusers'),
        ];

        return [
            'filters' => $filters,
            'rows' => $sync->table($filters),
            'snapshot' => $snapshot,
            // الفروق لا تُحسَب إلّا بعد فحصٍ صريح — فلا يفاجئ الشاشةَ حسابٌ ثقيل
            'diff' => $snapshot && $snapshot->status === 'checked' ? $sync->diff($snapshot) : null,
            'changes' => CountryDataSync::changes(),
        ];
    }

    /** حفظ تلقائيّ لحقل واحد — والردّ يحمل رسالة «تم الحفظ ✓» لتظهر جنب الحقل */
    public function saveField(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['nullable'],
        ]);

        $setting = Setting::query()->where('key', $data['key'])->firstOrFail();
        $result = $this->registry->save($setting, $data['value'], $request->user());

        return response()->json($result + [
            'example' => $this->registry->liveExample($setting->refresh()),
            'audit' => $this->registry->lastChange($setting),
        ], $result['saved'] ? 200 : 422);
    }

    public function resetField(Request $request): JsonResponse
    {
        $setting = Setting::query()->where('key', $request->string('key')->toString())->firstOrFail();
        $result = $this->registry->reset($setting, $request->user());

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    /** «تراجع عن آخر تغيير» — قيمة سابقة واحدة تكفي، بلا سجلّات متضخّمة */
    public function undoField(Request $request): JsonResponse
    {
        $setting = Setting::query()->where('key', $request->string('key')->toString())->firstOrFail();
        $result = $this->registry->undo($setting, $request->user());

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    /** Audit بالـHover: آخر تغيير فقط + رابط بروفايل المحرّر */
    /**
     * آخر تعديل على إعدادٍ بعينه — نقطة XHR لا صفحة.
     *
     * والمفتاح الغائب كان يخرج **404 فارغة**: الواجهة تصمت، وصاحب الشاشة لا
     * يعرف أوقعَ خطأٌ أم لا يوجد سجلّ. و2.17-ب تشترط «ماذا حدث + ماذا تفعل»
     * في كلّ خطأ — والنقطة التي تخدم واجهةً ليست مستثناة منها.
     */
    public function audit(Request $request): JsonResponse
    {
        $key = trim($request->string('key')->toString());

        if ($key === '') {
            return response()->json([
                'message' => (string) setting(
                    'settings.audit.error.missing_key',
                    'مافيش مفتاح إعداد في الطلب — افتح السجلّ من جنب الحقل نفسه.',
                ),
            ], 422);
        }

        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return response()->json([
                'message' => (string) setting(
                    'settings.audit.error.unknown_key',
                    'الإعداد ده مش موجود — يمكن يكون اتشال، حدّث الصفحة وجرّب تاني.',
                ),
            ], 404);
        }

        return response()->json($this->registry->lastChange($setting) ?? ['at' => (string) setting('settings.admin.audit_empty', 'مافيش تعديل مسجَّل')]);
    }

    /** بحث موحّد داخل كلّ الإعدادات — والنتيجة بمسارها الكامل */
    public function search(Request $request): JsonResponse
    {
        return response()->json([
            'results' => $this->registry->search($request->string('q')->toString(), $request->user()),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $payload = $this->registry->export($request->user());

        return response()->streamDownload(
            fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'settings-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:2048'],
        ]);

        $payload = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($payload)) {
            return back()->withErrors(['file' => (string) setting('settings.admin.import_denied', 'الملفّ مش JSON صالح — صدّر نسخة وقارن الشكل.')]);
        }

        $result = $this->registry->import($payload, $request->user());

        return back()->with('status', strtr((string) setting('settings.admin.import_ok', 'اتطبّق :applied إعداد · اتخطّى :skipped'), [
            ':applied' => (string) $result['applied'],
            ':skipped' => (string) $result['skipped'],
        ]));
    }
}
