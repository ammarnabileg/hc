<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BehaviorTransaction;
use App\Models\BehaviorViolation;
use App\Models\Membership;
use App\Models\RepRule;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\BehaviorLedger;
use App\Services\Admin\Volunteer\RepRuleWriter;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Support\Scope\ScopeFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * ضبط Rep (13.4-ن · 13.4-ك).
 *
 * ⭐ **كلّ قيم `rep_rules` قابلة للتعديل من هنا** — المهامّ والاجتماعات
 * والأكاديمية ومؤشّر القيادة والسلوك والحدود — ولكلّ قيمة زرّ Reset
 * لافتراضيّها المنصوص، فلا يبقى في المنصّة رقمُ Rep محروق.
 */
class RepAdminController extends Controller
{
    public function index(Request $request): View
    {
        $rules = RepRule::query()->orderBy('group')->orderBy('key')->get();

        return view('admin.volunteer.rep', [
            'groups' => RepRuleWriter::GROUPS,
            'rules' => $rules->groupBy('group'),
            'defaults' => RepRuleWriter::DEFAULTS,
            'violations' => BehaviorViolation::query()->orderBy('code')->get(),
            'settings' => SettingsWriter::groupRows('volunteer_rep'),
            'monthlyCap' => BehaviorLedger::monthlyCap(),
            'myQuota' => BehaviorLedger::remainingQuota($request->user()),
            // النطاق إلزاميّ مع كلّ صلاحيّة (12.2.1-ب) — حركات Rep لمن هم في نطاقه
            'recent' => BehaviorTransaction::query()
                ->tap(fn ($q) => app(ScopeFilter::class)->apply($q, $request->user(), 'rep_transactions.view'))
                ->with(['user:id,name,code', 'granted_by:id,name,code', 'behavior_violation'])
                ->latest('id')
                ->limit((int) setting('rep.admin.recent_rows', 15))
                ->get(),
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    // ------------------------------------------------------------ قيم Rep

    /** حفظ قيم مجموعة كاملة — ينعكس فورًا على `rep_rule()` */
    public function saveRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*' => ['required', 'numeric', 'between:-100,100'],
        ]);

        foreach ($data['rules'] as $key => $value) {
            RepRuleWriter::put((string) $key, (float) $value, $request->user());
        }

        return back()->with('status', 'اتحفظ ✓ — القيم الجديدة سارية من دلوقتي.');
    }

    /** ↺ Reset لقيمة واحدة */
    public function resetRule(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string']]);
        RepRuleWriter::reset($data['key'], $request->user());

        return back()->with('status', 'رجعت للافتراضيّ ✓');
    }

    /** ↺ Reset لمجموعة كاملة */
    public function resetGroup(Request $request): RedirectResponse
    {
        $data = $request->validate(['group' => ['required', 'string', 'in:'.implode(',', array_keys(RepRuleWriter::GROUPS))]]);
        $count = RepRuleWriter::resetGroup($data['group'], $request->user());

        return back()->with('status', 'رجعت '.$count.' قيمة للافتراضيّ ✓');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);
        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }

    // ------------------------------------------------------------ المخالفات المكوَّدة

    /** قائمة المخالفات يحدّدها الأدمن — لا نصّ حرّ ليبقى التصنيف قابلًا للتحليل */
    public function saveViolation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:behavior_violations,id'],
            'code' => ['required', 'string', 'max:32'],
            'label_ar' => ['required', 'string', 'max:180'],
            'default_value' => ['required', 'numeric', 'between:-10,0'],
            'requires_higher_approval' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $violation = isset($data['id']) ? BehaviorViolation::findOrFail($data['id']) : new BehaviorViolation;
        $old = $violation->exists ? $violation->only(['label_ar', 'default_value']) : [];

        $violation->fill([
            'code' => mb_strtoupper($data['code']),
            'label_ar' => $data['label_ar'],
            'default_value' => (float) $data['default_value'],
            'requires_higher_approval' => (bool) ($data['requires_higher_approval'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        AuditTrail::log($request->user(), 'behavior_violation.save', $violation, $old, $violation->only(['code', 'label_ar', 'default_value']));

        return back()->with('status', 'اتحفظ ✓');
    }

    // ------------------------------------------------------------ معاملة السلوك

    /** معاينة الأثر قبل الحفظ: «Rep ينزل من كذا إلى كذا» */
    public function previewBehavior(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'violation_id' => ['required', 'integer', 'exists:behavior_violations,id'],
        ]);

        $user = User::query()->where('code', mb_strtoupper($data['code']))->first();

        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'الكود ده مش موجود — راجع الكود وجرّب تاني.'], 422);
        }

        $violation = BehaviorViolation::findOrFail($data['violation_id']);
        $preview = BehaviorLedger::preview($user, (float) $violation->default_value);

        return response()->json([
            'ok' => true,
            'name' => $user->name,
            'preview' => $preview,
            'remaining' => BehaviorLedger::remainingQuota($request->user()),
            'needs_approval' => (bool) $violation->requires_higher_approval,
        ]);
    }

    /** تسجيل معاملة سلوك — بمبرّر إلزاميّ وسقف شهريّ للمانح */
    public function recordBehavior(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'violation_id' => ['required', 'integer', 'exists:behavior_violations,id'],
            'justification' => ['required', 'string', 'max:1000'],
            'attachment_path' => ['nullable', 'string', 'max:255'],
        ]);

        $target = User::query()->where('code', mb_strtoupper($data['code']))->first();

        if (! $target) {
            return back()->withInput()->with('status', 'الكود ده مش موجود — راجع الكود وجرّب تاني.');
        }

        $membership = Membership::query()
            ->where('user_id', $target->id)
            ->where('status', 'active')
            ->first();

        try {
            $record = BehaviorLedger::record(
                $request->user(), $target,
                BehaviorViolation::findOrFail($data['violation_id']),
                $data['justification'], $data['attachment_path'] ?? null, $membership,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('status', $e->getMessage());
        }

        return back()->with('status', $record->status === 'pending_approval'
            ? 'اتسجّلت وبتنتظر موافقة المستوى الأعلى (نافذة '.setting('rep.behavior.severe_approval_window_hours', 24).' ساعة).'
            : 'اتسجّلت وطُبِّقت ✓ — والعضو وصله إشعار بالنوع والمبرّر.');
    }

    public function approveBehavior(Request $request, BehaviorTransaction $behaviorTransaction): RedirectResponse
    {
        BehaviorLedger::approve($request->user(), $behaviorTransaction);

        return back()->with('status', 'اتعمدت المعاملة وطُبِّقت ✓');
    }
}
