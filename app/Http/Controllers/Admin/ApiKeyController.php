<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\Developers\ApiKeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * إنشاء/تدوير/إبطال مفاتيح API (12.15-أ) — إجراءات تاب «API» في شاشة المطوّرين.
 *
 * ⛔ **المفتاح الكامل يظهر مرّة واحدة فقط** لحظة الإنشاء/التدوير عبر
 * `session()->flash('plain_api_key', …)` — يُعرَض في الطلب التالي فقط ثمّ
 * يُمحى تلقائيًّا (آليّة الفلاش القياسيّة في لارافيل)، ولا يُخزَّن في القاعدة
 * إطلاقًا بعد هذه اللحظة (12.15-ج).
 */
class ApiKeyController extends Controller
{
    public function store(Request $request, ApiKeyService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => [Rule::in(ApiKeyService::SCOPES)],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $service->create(
            $data['name'],
            $data['scopes'],
            $request->user(),
            $data['rate_limit_per_minute'] ?? null,
            isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
        );

        return back()
            ->with('status', (string) setting('developers.admin.created_ok', 'اتنشأ المفتاح ✓'))
            ->with('plain_api_key', $result['plain_key'])
            ->with('plain_api_key_name', $result['record']->name);
    }

    public function rotate(Request $request, ApiKey $apiKey, ApiKeyService $service): RedirectResponse
    {
        $result = $service->rotate($apiKey, $request->user());

        return back()
            ->with('status', (string) setting('developers.admin.rotated_ok', 'اتدوّر المفتاح ✓'))
            ->with('plain_api_key', $result['plain_key'])
            ->with('plain_api_key_name', $result['record']->name);
    }

    public function revoke(Request $request, ApiKey $apiKey, ApiKeyService $service): RedirectResponse
    {
        $service->revoke($apiKey, $request->user());

        return back()->with('status', (string) setting('developers.admin.revoked_ok', 'اتبطَّل المفتاح ✓'));
    }
}
