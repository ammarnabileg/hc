<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Developers\ApiEndpointCatalog;
use App\Services\Developers\ApiKeyService;
use App\Services\Developers\WebhookEventCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 🧩 المطوّرين — API و Webhooks (12.15 · v5.5 قسمٌ جديد، سجلّ القرارات 25).
 *
 * دروب-داون بصفحتين كما أمر المالك حرفيًّا: **API** (12.15-أ) و**Webhooks**
 * (12.15-ب) — كلاهما مبنيّ كاملًا الآن فوق نفس الراوت بـ`?tab=`.
 *
 * ⭐ **كلّ تابٍّ يُحرَس بصلاحيّته هو لا بباب الشاشة العامّ وحده** — تاب `api`
 * يتطلّب `integrations.view` تحديدًا وتاب `webhooks` يتطلّب `webhooks.view`،
 * على نمط `StatsController::tabsFor()` (12.2.1-أ) لا `GamificationController`
 * الذي يكتفي بباب الشاشة الجامع — فمَن يملك إحدى الصلاحيّتين فقط لا يُخيَّر
 * افتراضيًّا في تابٍّ لا يملكه.
 */
class DevelopersController extends Controller
{
    public const TAB_KEYS = ['api', 'webhooks'];

    /** باب الشاشة بسعة تابيها معًا — ومَن لا يملك أيًّا منهما لا يفتح الباب أصلًا */
    public const GATE_KEYS = ['integrations.view', 'integrations.list', 'webhooks.view', 'webhooks.list'];

    /** صلاحيّة كلّ تابّ على حدة (12.2.1-أ) */
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
        ];
    }

    public function index(Request $request, ApiKeyService $service): View
    {
        $tab = $request->string('tab')->toString() ?: 'api';

        abort_unless(in_array($tab, self::TAB_KEYS, true), 404);
        abort_unless($request->user()?->can(self::TAB_PERMISSIONS[$tab]), 403);

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
            default => [],
        };
    }
}
