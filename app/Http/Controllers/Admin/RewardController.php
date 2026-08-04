<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\RewardGrantService;
use App\Services\Admin\Volunteer\SettingsWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * إدارة المكافآت (12.9 · 24.3).
 *
 * فورم أوّلًا ثم **جدول معاينة قبل التنفيذ** (رصيد قبل/بعد) ثمّ تأكيد بملخّص.
 * ⭐ **الخصم يقدر ينزل تحت الصفر** — مسموح صراحةً، والأكواد الخاطئة تُستبعَد
 * بتنبيه ظاهر لا بفشلٍ صامت.
 */
class RewardController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.gamification.rewards.index', [
            'currencies' => $this->currencies(),
            'reasons' => (array) setting('rewards.reasons', []),
            'reasonsWithReference' => (array) setting('rewards.reasons_requiring_reference', []),
            'reasonsStrict' => (array) setting('rewards.reasons_requiring_reference_strict', []),
            'notes' => (array) setting('rewards.notes', []),
            'segments' => (array) setting('rewards.segments', []),
            'settings' => SettingsWriter::groupRows('rewards'),
            'ledger' => RewardGrantService::ledger($request->only(['code', 'currency', 'direction']), $request->user()),
            'audit' => AuditTrail::latest('manual_rewards', 10),
            'tab' => $request->string('tab')->toString() ?: 'form',
            'preview' => null,
            'cards' => [],
            'old' => [],
        ]);
    }

    /** «تحقّق من الأكواد» ⟵ جدول المعاينة بأسماء أصحابها والرصيد قبل/بعد */
    public function preview(Request $request): View
    {
        $data = $request->validate([
            'codes' => ['required', 'string'],
            'currency' => ['required', 'string'],
            'direction' => ['required', 'string', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0'],
            'per_code' => ['nullable', 'array'],
            'reason' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes_key' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $preview = RewardGrantService::preview(
            $data['codes'], $data['currency'], (float) $data['amount'],
            $data['direction'], $data['per_code'] ?? [],
        );

        return view('admin.gamification.rewards.index', [
            'currencies' => $this->currencies(),
            'reasons' => (array) setting('rewards.reasons', []),
            'reasonsWithReference' => (array) setting('rewards.reasons_requiring_reference', []),
            'reasonsStrict' => (array) setting('rewards.reasons_requiring_reference_strict', []),
            'notes' => (array) setting('rewards.notes', []),
            'segments' => (array) setting('rewards.segments', []),
            'settings' => SettingsWriter::groupRows('rewards'),
            'ledger' => RewardGrantService::ledger([]),
            'audit' => AuditTrail::latest('manual_rewards', 10),
            'tab' => 'form',
            'preview' => $preview,
            'cards' => [],
            'old' => $data,
        ]);
    }

    /** التنفيذ بعد التأكيد النهائيّ */
    public function grant(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'codes' => ['required', 'string'],
            'currency' => ['required', 'string'],
            'direction' => ['required', 'string', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0'],
            'per_code' => ['nullable', 'array'],
            'reason' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes_key' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
        ]);

        // «تصحيح خطأ تقنيّ» يجعل مرجع المعاملة الأصليّة إلزاميًّا (12.9)
        $strict = (array) setting('rewards.reasons_requiring_reference_strict', []);

        if (in_array($data['reason'], $strict, true) && empty($data['reference'])) {
            return back()->withInput()->with('status', (string) setting('rewards.admin.grant_must', 'السبب ده لازم معاه مرجع المعاملة الأصليّة — اكتبه وجرّب تاني.'));
        }

        $preview = RewardGrantService::preview(
            $data['codes'], $data['currency'], (float) $data['amount'],
            $data['direction'], $data['per_code'] ?? [],
        );

        if ($preview['rows']->isEmpty()) {
            return back()->withInput()->with('status', (string) setting('rewards.admin.grant_empty', 'مفيش ولا كود صحيح — راجع الأكواد وجرّب تاني.'));
        }

        $result = RewardGrantService::execute(
            $preview, $data['currency'], $data['reason'],
            $data['reference'] ?? null,
            trim(((array) setting('rewards.notes', []))[$data['notes_key'] ?? ''] ?? '').' '.($data['notes'] ?? ''),
            $request->user(),
        );

        session()->flash('status', strtr((string) setting('rewards.admin.grant_ok', 'اتنفّذ ✓ — :a1 مستخدم بإجماليّ :a2'), [':a1' => (string) ($result['granted']), ':a2' => (string) ($result['total'])]));

        return view('admin.gamification.rewards.index', [
            'currencies' => $this->currencies(),
            'reasons' => (array) setting('rewards.reasons', []),
            'reasonsWithReference' => (array) setting('rewards.reasons_requiring_reference', []),
            'reasonsStrict' => $strict,
            'notes' => (array) setting('rewards.notes', []),
            'segments' => (array) setting('rewards.segments', []),
            'settings' => SettingsWriter::groupRows('rewards'),
            'ledger' => RewardGrantService::ledger([]),
            'audit' => AuditTrail::latest('manual_rewards', 10),
            'tab' => 'form',
            'preview' => null,
            'cards' => $result['cards'],
            'old' => [],
        ]);
    }

    /** استهداف بشريحة بدل الأكواد اليدويّة — دفعات كبيرة */
    public function segment(Request $request): RedirectResponse
    {
        $data = $request->validate(['segment' => ['required', 'string']]);

        return back()
            ->withInput(['codes' => RewardGrantService::segmentCodes($data['segment'])])
            ->with('status', (string) setting('rewards.admin.segment_msg', 'اتحمّلت أكواد الشريحة في الحقل — راجعها قبل التنفيذ.'));
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);
        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', (string) setting('rewards.admin.save_settings_ok', 'اتحفظ ✓'));
    }

    private function currencies()
    {
        $allowed = (array) setting('rewards.currencies', ['xp', 'coins', 'tickets']);

        return Currency::query()->whereIn('code', $allowed)->get();
    }
}
