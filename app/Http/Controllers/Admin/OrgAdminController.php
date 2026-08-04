<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Track;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\CapacityReport;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Support\Access\AccessEngine;
use App\Support\Scope\ScopeFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الهيكل والبوزشنز والسعة (13.4-ف · 24.2).
 *
 * السعة هنا **غير مانعة إطلاقًا**: مؤشّرات وتنبيهات فقط، لا تُوقِف تسكينًا
 * ولا ترقيةً ولا نقلًا — غايتها توازن الأحمال ودعم القرار لا تعطيل العمل.
 */
class OrgAdminController extends Controller
{
    public function index(Request $request): View
    {
        $trackId = (int) $request->integer('track');

        /*
         | ⭐ النطاق إلزاميّ مع كلّ صلاحيّة (12.2.1-ب): الكيانات المعروضة كيانات
         | صاحب الشاشة وفروعُها — لا شجرة المنصّة كلّها لمن نطاقه قسمٌ واحد.
         | و`id` هنا هو عمود الكيان لأنّ الجدول جدولُ كيانات.
         */
        $entities = Entity::query()
            ->tap(fn ($q) => app(ScopeFilter::class)->apply($q, $request->user(), 'org_chart.view', null, 'id'))
            ->with('track')
            ->when($trackId, fn ($q) => $q->where('track_id', $trackId))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('name_ar', 'like', '%'.$term.'%'))
            ->orderBy('parent_id')
            ->orderBy('name_ar')
            ->get();

        return view('admin.volunteer.org', [
            'tracks' => Track::query()->orderBy('id')->get(),
            'entities' => $entities,
            'positions' => Position::query()->orderBy('rank')->get(),
            'capacity' => CapacityReport::rows($trackId ?: null, $request->user()),
            'spans' => CapacityReport::spanRows($request->user()),
            'unhealthy' => CapacityReport::unhealthy($request->user()),
            'overflows' => CapacityReport::overflows($request->user()),
            'settings' => SettingsWriter::groupRows('volunteer_org'),
            'filters' => ['track' => $trackId, 'q' => $request->string('q')->toString()],
            'caseFileOpener' => (string) setting('volunteer.org.case_file_opener_position', 'volunteer_gm'),
            'canOpenCaseFile' => $this->canOpenCaseFile($request),
            'members' => Membership::query()
                ->tap(fn ($q) => app(ScopeFilter::class)->apply($q, $request->user(), 'org_chart.view', 'user_id', 'entity_id'))
                ->with(['user:id,name,code', 'entity:id,name_ar', 'position'])
                ->where('status', 'active')
                ->when($trackId, fn ($q) => $q->whereHas('entity', fn ($e) => $e->where('track_id', $trackId)))
                ->limit((int) setting('volunteer.org.members_limit', 50))
                ->get(),
        ]);
    }

    // ------------------------------------------------------------ الكيانات

    public function saveEntity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:entities,id'],
            'track_id' => ['required', 'integer', 'exists:tracks,id'],
            'parent_id' => ['nullable', 'integer', 'exists:entities,id'],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'member_cap' => ['nullable', 'integer', 'min:1'],
        ]);

        $track = Track::findOrFail($data['track_id']);

        // ⭐ الملفّ المؤقّت يفتحه ويُنهيه مشرف عام التطوّع وحده (23-0.2)
        if ($track->is_temporary && ! $this->canOpenCaseFile($request)) {
            return back()->with('status', (string) setting('volunteer_org.admin.save_entity_msg', 'فتح الملفّ المؤقّت لمشرف عام التطوّع وحده — كلّمه يفتحه لك.'));
        }

        $entity = isset($data['id']) ? Entity::findOrFail($data['id']) : new Entity;
        $old = $entity->exists ? $entity->only(['name_ar', 'parent_id', 'member_cap']) : [];

        $entity->fill([
            'track_id' => $data['track_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'description' => $data['description'] ?? null,
            'member_cap' => $data['member_cap'] ?? null,
        ]);

        if (! $entity->exists) {
            $entity->status = 'active';
            $entity->opened_at = now();
        }

        $entity->save();

        AuditTrail::log($request->user(), 'entity.save', $entity, $old, $entity->only(['name_ar', 'parent_id', 'member_cap']));

        return back()->with('status', (string) setting('volunteer_org.admin.save_entity_ok', 'اتحفظ ✓'));
    }

    /** أرشفة كيان — والملفّ المؤقّت يُنهيه مشرف عام التطوّع وحده */
    public function archiveEntity(Request $request, Entity $entity): RedirectResponse
    {
        $entity->loadMissing('track');

        if ($entity->track?->is_temporary && ! $this->canOpenCaseFile($request)) {
            return back()->with('status', (string) setting('volunteer_org.admin.archive_entity_msg', 'إنهاء الملفّ المؤقّت لمشرف عام التطوّع وحده.'));
        }

        $entity->forceFill(['status' => 'archived', 'closed_at' => now()])->save();

        AuditTrail::log($request->user(), 'entity.archive', $entity);

        return back()->with('status', (string) setting('volunteer_org.admin.archive_entity_ok', 'اتأرشف الكيان ✓ — وعضويّاته تُقفَل بمسار الأوفبوردنج.'));
    }

    // ------------------------------------------------------------ البوزشنز والسعة

    /**
     * نطاق الإشراف لكلّ بوزشن (أدنى/افتراضيّ/أقصى) + سقف الانشغال.
     * التجاوز **تنبيه فقط** — بلا منع ولا مبرّر إلزاميّ (13.4-ف-ب).
     */
    public function savePositions(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*.span_min' => ['nullable', 'integer', 'min:0'],
            'positions.*.span_default' => ['nullable', 'integer', 'min:0'],
            'positions.*.span_max' => ['nullable', 'integer', 'min:0'],
            'positions.*.task_load_cap' => ['nullable', 'integer', 'min:0'],
            'positions.*.is_active' => ['nullable', 'boolean'],
        ]);

        foreach ($data['positions'] as $id => $row) {
            $position = Position::find($id);

            if (! $position) {
                continue;
            }

            $old = $position->only(['span_min', 'span_default', 'span_max', 'task_load_cap']);

            $position->forceFill([
                'span_min' => $row['span_min'] ?? null,
                'span_default' => $row['span_default'] ?? null,
                'span_max' => $row['span_max'] ?? null,
                'task_load_cap' => $row['task_load_cap'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ])->save();

            AuditTrail::log($request->user(), 'position.span_update', $position, $old, $row);
        }

        return back()->with('status', (string) setting('volunteer_org.admin.save_positions_ok', 'اتحفظ ✓ — والسعة مؤشّرات لا موانع.'));
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);
        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', (string) setting('volunteer_org.admin.save_settings_ok', 'اتحفظ ✓'));
    }

    /** Override لكلّ كيان: عتبة/سعة تخصّ كيانًا بعينه دون سواه (2.13-هـ) */
    public function saveOverride(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'key' => ['required', 'string'],
            'value' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ]);

        $entity = Entity::findOrFail($data['entity_id']);
        SettingsWriter::override($data['key'], $entity, $data['value'], $request->user());

        AuditTrail::log($request->user(), 'settings.override_reason', $entity, [], ['key' => $data['key'], 'reason' => $data['reason']]);

        return back()->with('status', (string) setting('volunteer_org.admin.save_override_ok', 'اتحفظ الـOverride للكيان ✓'));
    }

    public function dropOverride(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'key' => ['required', 'string'],
        ]);

        SettingsWriter::dropOverride($data['key'], Entity::findOrFail($data['entity_id']), $request->user());

        return back()->with('status', (string) setting('volunteer_org.admin.drop_override_ok', 'رجع الكيان للقيمة العامّة ✓'));
    }

    /** تقرير السعة: أكثر الأقسام تخمةً وأكثرها فراغًا — مادّة قرار */
    public function capacityReport(Request $request): View
    {
        return view('admin.volunteer.capacity', [
            // تقرير السعة داخل نطاق صاحب الشاشة (12.2.1-ب)
            'rows' => CapacityReport::rows((int) $request->integer('track') ?: null, $request->user()),
            'spans' => CapacityReport::spanRows($request->user()),
            'tracks' => Track::query()->orderBy('id')->get(),
            'filters' => ['track' => (int) $request->integer('track')],
        ]);
    }

    /** صلاحيّة مشرف عام التطوّع على الملفّات المؤقّتة */
    private function canOpenCaseFile(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        $positionKey = (string) setting('volunteer.org.case_file_opener_position', 'volunteer_gm');

        $holdsPosition = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('position', fn ($q) => $q->where('key', $positionKey))
            ->exists();

        // الأدمن العامّ يعلو طبقة التطوّع (12.2.1-ز-5)
        return $holdsPosition || app(AccessEngine::class)->allows($user, 'case_files.archive');
    }
}
