<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Developers\WebhookEventCatalog;
use App\Services\Developers\WebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * تسجيل/تدوير/إيقاف الويب-هوكس وإعادة الإرسال اليدويّة (12.15-ب) — إجراءات
 * تاب «Webhooks» في شاشة المطوّرين، بنفس نمط `ApiKeyController` (تاب API).
 *
 * ⛔ **السرّ الكامل يظهر مرّة واحدة فقط** لحظة الإنشاء/التدوير عبر
 * `session()->flash('plain_webhook_secret', …)` — نفس أسلوب `plain_api_key`
 * حرفيًّا (12.15-ج).
 */
class WebhookController extends Controller
{
    public function store(Request $request, WebhookService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(WebhookEventCatalog::EVENT_KEYS)],
        ]);

        $result = $service->create($data['name'], $data['url'], $data['events'], $request->user());

        return back()
            ->with('status', (string) setting('developers.admin.webhook_created_ok', 'اتسجّل الويب-هوك ✓'))
            ->with('plain_webhook_secret', $result['plain_secret'])
            ->with('plain_webhook_secret_name', $result['record']->name);
    }

    public function pause(Request $request, Webhook $webhook, WebhookService $service): RedirectResponse
    {
        $service->pause($webhook, $request->user());

        return back()->with('status', (string) setting('developers.admin.webhook_paused_ok', 'اتوقّف الويب-هوك ✓'));
    }

    public function resume(Request $request, Webhook $webhook, WebhookService $service): RedirectResponse
    {
        $service->resume($webhook, $request->user());

        return back()->with('status', (string) setting('developers.admin.webhook_resumed_ok', 'اشتغل الويب-هوك تاني ✓'));
    }

    public function rotateSecret(Request $request, Webhook $webhook, WebhookService $service): RedirectResponse
    {
        $result = $service->rotateSecret($webhook, $request->user());

        return back()
            ->with('status', (string) setting('developers.admin.webhook_rotated_ok', 'اتدوّر سرّ الويب-هوك ✓'))
            ->with('plain_webhook_secret', $result['plain_secret'])
            ->with('plain_webhook_secret_name', $result['record']->name);
    }

    public function destroy(Request $request, Webhook $webhook, WebhookService $service): RedirectResponse
    {
        $service->delete($webhook, $request->user());

        return back()->with('status', (string) setting('developers.admin.webhook_deleted_ok', 'اتحذف الويب-هوك ✓'));
    }

    /** إعادة إرسال يدويّة لمحاولة فاشلة/مُستنفَدة (12.15-ب) — تعيد تصفير عدّاد المحاولات لدورةٍ جديدة */
    public function retry(WebhookDelivery $delivery): RedirectResponse
    {
        $delivery->fill([
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => null,
        ])->save();

        DeliverWebhookJob::dispatch($delivery->id);

        return back()->with('status', (string) setting('developers.admin.webhook_retry_ok', 'هتتبعت المحاولة تاني ✓'));
    }

    /** «اختبار» — حمولة تجريبيّة فوريّة بلا انتظار حدثٍ حقيقيّ (12.15-ب) */
    public function test(Webhook $webhook): RedirectResponse
    {
        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_key' => 'webhook.test',
            'payload' => [
                'test' => true,
                'webhook_id' => $webhook->id,
                'sent_at' => now()->toIso8601String(),
            ],
            'status' => 'pending',
        ]);

        DeliverWebhookJob::dispatch($delivery->id);

        return back()->with('status', (string) setting('developers.admin.webhook_test_ok', 'اتبعتت حمولة تجريبيّة ✓'));
    }
}
