<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\SettingsCatalog;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Support\Scope\ScopeFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الإدارة المركزيّة للتطوّع (24.2 · 13.4-ك): مرجعٌ واحد لأرقام منظومة
 * التطوّع بدل تفرّقها بين عشر شاشات — بحث وفلترة و«حفظ الكلّ» و«Reset للتاب».
 *
 * كلّ تاب يعرض ما هو **حقيقيّ** فقط: مفتاحٌ مزروع وله قارئٌ في الكود (2.13)
 * — لا وعدًا بحقلٍ لا قاعدة له. حيث لا إعداد حقيقيّ بعد (تاب «النصوص
 * والمحتوى» كاملًا، وأجزاءٌ من VXP والتقييم) تظهر حالة «فارغة» الرسميّة
 * (24.2) بدل حقلٍ مختلَق. معاينة الأثر وتصدير/استيراد JSON: مؤجّلان —
 * موثّقان لا مبنيّان جزئيًّا (Override لكيان وسجلّ التدقيق مبنيّان هنا).
 */
class VolunteerSettingsHubController extends Controller
{
    private const TAB_ORDER = [
        'rep', 'vxp', 'evaluations', 'kudos', 'objections',
        'promotion', 'behavior', 'windows', 'texts',
    ];

    public function index(Request $request): View
    {
        $tabs = [];

        foreach (self::TAB_ORDER as $tab) {
            $tabs[$tab] = [
                'label' => self::label($tab),
                'rows' => SettingsWriter::rowsFor(self::keys($tab)),
            ];
        }

        $canManage = $request->user()->allows('volunteer_central_settings.manage');

        return view('admin.volunteer.settings-hub', [
            'tabs' => $tabs,
            'lastChange' => AuditTrail::latest('settings.update', 1)->first(),
            'canManage' => $canManage,
            // ⭐ Override وسجلّ التدقيق فعلٌ إداريّ — لا داعي لجلبهما لمن يملك عرضًا فقط
            'entities' => $canManage
                ? Entity::query()
                    ->tap(fn ($q) => app(ScopeFilter::class)->apply($q, $request->user(), 'volunteer_central_settings.manage', null, 'id'))
                    ->orderBy('name_ar')->get(['id', 'name_ar'])
                : collect(),
            'auditLog' => $canManage ? AuditTrail::latest('settings.', 20) : collect(),
        ]);
    }

    /** Override لكيان بعينه — يعلو القيمة العامّة داخل الكيان وحده (2.13-هـ) */
    public function saveOverride(Request $request): RedirectResponse
    {
        abort_unless($request->user()->allows('volunteer_central_settings.manage'), 403);

        $data = $request->validate([
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'key' => ['required', 'string'],
            'value' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ]);

        // ⭐ نفس حارس Reset — Override مقصور على مفاتيح الهَب نفسها (2.13)
        abort_unless(in_array($data['key'], self::allKeys(), true), 404);

        $entity = Entity::findOrFail($data['entity_id']);
        SettingsWriter::override($data['key'], $entity, $data['value'], $request->user());

        AuditTrail::log($request->user(), 'settings.override_reason', $entity, [], ['key' => $data['key'], 'reason' => $data['reason']]);

        return back()->with('status', (string) setting('admin.volunteer.settings_hub.override_ok', 'اتحفظ الـOverride للكيان ✓'));
    }

    public function dropOverride(Request $request): RedirectResponse
    {
        abort_unless($request->user()->allows('volunteer_central_settings.manage'), 403);

        $data = $request->validate([
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'key' => ['required', 'string'],
        ]);

        abort_unless(in_array($data['key'], self::allKeys(), true), 404);

        SettingsWriter::dropOverride($data['key'], Entity::findOrFail($data['entity_id']), $request->user());

        return back()->with('status', (string) setting('admin.volunteer.settings_hub.override_drop_ok', 'اتشال الـOverride ✓'));
    }

    /** «حفظ الكلّ» (24.2) — فورمٌ واحد يجمع كلّ التابات التسعة، الظاهر منها والمطويّ */
    public function saveAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()->allows('volunteer_central_settings.manage'), 403);

        $data = $request->validate(['settings' => ['sometimes', 'array']]);
        SettingsWriter::putMany($data['settings'] ?? [], $request->user());

        return back()->with('status', (string) setting('admin.volunteer.settings_hub.save_ok', 'اتحفظ ✓'));
    }

    /** حفظ تاب واحد فقط — احتياطيّ خلف الكواليس، الواجهة تستعمل «حفظ الكلّ» */
    public function saveTab(Request $request, string $tab): RedirectResponse
    {
        abort_unless(in_array($tab, self::TAB_ORDER, true), 404);
        abort_unless($request->user()->allows('volunteer_central_settings.manage'), 403);

        $data = $request->validate(['settings' => ['sometimes', 'array']]);
        $scoped = array_intersect_key($data['settings'] ?? [], array_flip(self::keys($tab)));

        SettingsWriter::putMany($scoped, $request->user());

        return back()->with('status', (string) setting('admin.volunteer.settings_hub.save_ok', 'اتحفظ ✓'));
    }

    public function resetTab(Request $request, string $tab): RedirectResponse
    {
        abort_unless(in_array($tab, self::TAB_ORDER, true), 404);
        abort_unless($request->user()->allows('volunteer_central_settings.manage'), 403);

        SettingsWriter::resetKeys(self::keys($tab), $request->user());

        return back()->with('status', (string) setting('admin.volunteer.settings_hub.reset_tab_ok', 'رجع التاب للافتراضيّ ✓'));
    }

    public function resetField(Request $request): RedirectResponse
    {
        abort_unless($request->user()->allows('volunteer_central_settings.manage'), 403);

        $data = $request->validate(['key' => ['required', 'string']]);

        // ⭐ Reset مقصور على مفاتيح الهَب نفسها — لا يفتح بابًا لإعادة أيّ مفتاح في المنصّة (2.13)
        abort_unless(in_array($data['key'], self::allKeys(), true), 404);

        SettingsWriter::reset($data['key'], $request->user());

        return back()->with('status', (string) setting('admin.volunteer.settings_hub.reset_field_ok', 'رجع الحقل للافتراضيّ ✓'));
    }

    private static function allKeys(): array
    {
        return array_merge(...array_map(fn (string $tab) => self::keys($tab), self::TAB_ORDER));
    }

    private static function label(string $tab): string
    {
        return match ($tab) {
            'rep' => (string) setting('admin.volunteer.settings_hub.tab_rep', 'Rep'),
            'vxp' => (string) setting('admin.volunteer.settings_hub.tab_vxp', 'VXP'),
            'evaluations' => (string) setting('admin.volunteer.settings_hub.tab_evaluations', 'التقييم'),
            'kudos' => (string) setting('admin.volunteer.settings_hub.tab_kudos', 'Kudos ونادي +9.5'),
            'objections' => (string) setting('admin.volunteer.settings_hub.tab_objections', 'الاعتراضات'),
            'promotion' => (string) setting('admin.volunteer.settings_hub.tab_promotion', 'الترقّي والشواغر والأوفبوردنج'),
            'behavior' => (string) setting('admin.volunteer.settings_hub.tab_behavior', 'السلوك'),
            'windows' => (string) setting('admin.volunteer.settings_hub.tab_windows', 'النوافذ والمهل'),
            'texts' => (string) setting('admin.volunteer.settings_hub.tab_texts', 'النصوص والمحتوى'),
        };
    }

    /**
     * مفاتيح كلّ تاب — بعضها مجموعة كتالوج كاملة، وبعضها انتقاءٌ عرضيّ صريح
     * (SettingsCatalog::pick) حين لا تطابق المجموعات الموجودة اسم التاب
     * الدستوريّ (VXP · النوافذ · السلوك · الاعتراضات كلّها انتقاءٌ جزئيّ حقيقيّ
     * من مصادر متعدّدة لا مجموعة واحدة).
     */
    private static function keys(string $tab): array
    {
        return match ($tab) {
            'rep' => array_keys(SettingsCatalog::group('volunteer_rep')),
            // ⭐ الشريحة المحفوظة للأب هي البند الوحيد المزروع من VXP (24.2 التاب 2)؛
            // معامل الجودة وقيد مجموع الأبناء وسقوف الانشغال أرقامٌ دستوريّة لا قارئ لها بعد.
            'vxp' => ['workflow.vxp.parent_min_share_percent'],
            'evaluations' => array_keys(SettingsCatalog::group('performance')),
            'kudos' => array_keys(SettingsCatalog::group('kudos')),
            // ⭐ «اعتراض واحد لكلّ معاملة» تحكّمٌ برمجيّ في ObjectionService لا إعداد (2.13-أ حدود المسح)
            'objections' => [
                'rep.objection.window_days',
                'rep.objection.sla_hours',
                'volunteer_rep.objection_service.status_label_1',
                'volunteer_rep.objection_service.status_label_2',
                'volunteer_rep.objection_service.status_label_3',
                'volunteer_rep.objection_service.status_label_4',
                'volunteer_rep.objection_service.status_label_5',
            ],
            'promotion' => [
                ...array_keys(SettingsCatalog::group('volunteer_org')),
                ...array_keys(SettingsCatalog::group('volunteer_offboarding')),
            ],
            'behavior' => [
                'rep.behavior.monthly_cap_per_granter',
                'rep.behavior.justification_min_chars',
                'rep.behavior.attachment_enabled',
                'rep.behavior.show_granter_count_in_team_health',
                'rep.behavior.severe_approval_window_hours',
            ],
            'windows' => [
                'workflow.escalation.window_hours',
                'workflow.escalation.top_window_hours',
                'workflow.escalation.max_attempts',
                'workflow.activity_window.start',
                'workflow.activity_window.end',
                'workflow.contribution.owner_review_hours',
                'workflow.checkpoint.response_hours',
                'workflow.blocked.max_days',
            ],
            // ⭐ لم يُبنَ بعد — حالة «فارغة» الرسميّة (24.2) بدل حقلٍ مختلَق
            'texts' => [],
            default => [],
        };
    }
}
