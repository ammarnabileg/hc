<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use App\Models\TerminalCommandLog;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Developers\ApiEndpointCatalog;
use App\Services\Developers\ApiKeyService;
use App\Services\Developers\WebhookEventCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 🧩 المطوّرين — API وWebhooks والطرفيّة (12.15 · v5.5/v5.6، سجلّ القرارات 25).
 *
 * دروب-داون بثلاث صفحات: **API** (12.15-أ) و**Webhooks** (12.15-ب) و**الطرفيّة**
 * (12.15-هـ، مستحدَثةٌ بأمر المالك 2026-08-06) — الثلاثة فوق نفس الراوت بـ`?tab=`.
 *
 * ⭐ **كلّ تابٍّ يُحرَس بصلاحيّته هو لا بباب الشاشة العامّ وحده** — تاب `api`
 * يتطلّب `integrations.view` وتاب `webhooks` يتطلّب `webhooks.view`، على نمط
 * `StatsController::tabsFor()` (12.2.1-أ). أمّا تاب `terminal` **فلا صلاحيّة
 * له إطلاقًا** — الدستور صريح: «مالك المنصّة حصرًا — لا صلاحيّة تُمنَح لأيّ
 * دورٍ آخر، ولا استثناء»، فحارسه `isPlatformOwner()` مباشرةً لا `TAB_PERMISSIONS`.
 */
class DevelopersController extends Controller
{
    public const TAB_KEYS = ['api', 'webhooks', 'terminal'];

    /** باب الشاشة بسعة التابات معًا — ومَن لا يملك أيًّا منها لا يفتح الباب أصلًا */
    public const GATE_KEYS = ['integrations.view', 'integrations.list', 'webhooks.view', 'webhooks.list'];

    /** صلاحيّة كلّ تابّ على حدة (12.2.1-أ) — `terminal` عمدًا غائبٌ هنا (owner-only بلا صلاحيّة) */
    private const TAB_PERMISSIONS = [
        'api' => 'integrations.view',
        'webhooks' => 'webhooks.view',
    ];

    /** عناوين التابات — ميثودٌ لا `const` لأنّ الثابت لا يقبل `setting()` (2.13-أ) */
    public static function tabs(): array
    {
        return [
            'api' => (string) setting('developers.admin.tab_api', 'API'),
            'webhooks' => (string) setting('developers.admin.tab_webhooks', 'Webhooks'),
            'terminal' => (string) setting('developers.admin.tab_terminal', 'الطرفيّة'),
        ];
    }

    public function index(Request $request, ApiKeyService $service): View
    {
        $tab = $request->string('tab')->toString() ?: 'api';

        abort_unless(in_array($tab, self::TAB_KEYS, true), 404);

        // تاب الطرفيّة owner-only بلا مفتاح صلاحيّة (12.15-هـ) — التابان الآخران بمفتاحيهما المعتادَين
        if ($tab === 'terminal') {
            abort_unless($request->user()?->isPlatformOwner(), 403);
        } else {
            abort_unless($request->user()?->can(self::TAB_PERMISSIONS[$tab]), 403);
        }

        return view('admin.developers.index', [
            'tab' => $tab,
            'tabs' => self::tabs(),
            'data' => $this->dataFor($tab, $service),
        ]);
    }

    private function dataFor(string $tab, ApiKeyService $service): array
    {
        return match ($tab) {
            'api' => [
                'keys' => ApiKey::query()->with(['created_by:id,name,code'])->latest('id')->get(),
                'scopeOptions' => ApiKeyService::SCOPES,
                'scopeLabels' => ApiKeyService::scopeLabels(),
                'defaultRateLimit' => (int) setting('developers.api.default_rate_limit', 60),
                'catalog' => ApiEndpointCatalog::forDisplay(),
                // آخر 100 سجلّ عبر كلّ المفاتيح معًا — أحدث ما جرى أوّلًا (12.15-أ)
                'usageLogs' => ApiRequestLog::query()
                    ->with('apiKey:id,name')
                    ->latest('id')
                    ->limit((int) setting('developers.api.log_retention_count', 100))
                    ->get(),
                'plainKey' => session('plain_api_key'),
            ],
            'webhooks' => [
                'webhooks' => Webhook::query()->with(['created_by:id,name,code'])->latest('id')->get(),
                'eventOptions' => WebhookEventCatalog::EVENT_KEYS,
                'eventLabels' => WebhookEventCatalog::labels(),
                // آخر 100 محاولة عبر كلّ الويب-هوكس معًا — أحدث ما جرى أوّلًا (12.15-ب)
                'deliveries' => WebhookDelivery::query()
                    ->with('webhook:id,name')
                    ->latest('id')
                    ->limit((int) setting('developers.webhooks.log_retention_count', 100))
                    ->get(),
                'plainSecret' => session('plain_webhook_secret'),
                'plainSecretName' => session('plain_webhook_secret_name'),
            ],
            'terminal' => [
                // آخر 200 أمر — أحدث ما نُفِّذ أوّلًا (12.15-هـ · 12.15-و)
                'logs' => TerminalCommandLog::query()
                    ->with('user:id,name,code')
                    ->latest('id')
                    ->limit((int) setting('developers.terminal.log_retention_count', 200))
                    ->get(),
                'enabled' => (bool) setting('developers.terminal.enabled', true),
            ],
            default => [],
        };
    }
}
