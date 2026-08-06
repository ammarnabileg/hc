<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\DevelopersController;
use App\Http\Controllers\Admin\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 🧩 المطوّرين — API و Webhooks (12.15 · v5.5 قسمٌ جديد، سجلّ القرارات 25)
|--------------------------------------------------------------------------
| أمر المالك المباشر: دروب-داون بتابين — API وWebhooks، كلاهما مبنيّ الآن
| كاملًا (12.15-أ · 12.15-ب) فوق نفس الراوت `?tab=` بلا إعادة هيكلة.
|
| ⚠️ لا `permission:integrations.manage` أو `webhooks.manage` على مسارٍ
| ماليّ: `integrations.manage` owner-only ولا شيء ماليّ يُبنى هنا (كما
| وثّق العميل الأوّل). أمّا `webhooks.manage` فمُستخدَمة فعلًا — نصّها
| الحرفيّ «إعادة إرسال المحاولات الفاشلة وتدوير الأسرار» يطابق تمامًا
| ما تحته من مسارَي `rotate-secret` و`retry` و`test` (12.15-ب).
*/

Route::middleware(['auth', 'admin.panel'])->prefix('admin/developers')->name('admin.developers.')->group(function () {
    Route::get('/', [DevelopersController::class, 'index'])
        ->middleware('permission:'.implode(',', DevelopersController::GATE_KEYS))->name('index');

    // تاب API — إنشاء/تدوير/إبطال مفتاح (12.15-أ)
    Route::post('/api-keys', [ApiKeyController::class, 'store'])
        ->middleware('permission:integrations.create')->name('api-keys.store');
    Route::post('/api-keys/{apiKey}/rotate', [ApiKeyController::class, 'rotate'])
        ->middleware('permission:integrations.edit')->name('api-keys.rotate');
    Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'revoke'])
        ->middleware('permission:integrations.delete')->name('api-keys.revoke');

    // تاب Webhooks — تسجيل/إيقاف/استئناف/تدوير/حذف/إعادة إرسال/اختبار (12.15-ب)
    Route::post('/webhooks', [WebhookController::class, 'store'])
        ->middleware('permission:webhooks.create')->name('webhooks.store');
    Route::post('/webhooks/{webhook}/pause', [WebhookController::class, 'pause'])
        ->middleware('permission:webhooks.edit')->name('webhooks.pause');
    Route::post('/webhooks/{webhook}/resume', [WebhookController::class, 'resume'])
        ->middleware('permission:webhooks.edit')->name('webhooks.resume');
    Route::post('/webhooks/{webhook}/rotate-secret', [WebhookController::class, 'rotateSecret'])
        ->middleware('permission:webhooks.manage')->name('webhooks.rotate-secret');
    Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy'])
        ->middleware('permission:webhooks.delete')->name('webhooks.destroy');
    Route::post('/webhooks/{webhook}/test', [WebhookController::class, 'test'])
        ->middleware('permission:webhooks.manage')->name('webhooks.test');
    Route::post('/webhook-deliveries/{delivery}/retry', [WebhookController::class, 'retry'])
        ->middleware('permission:webhooks.manage')->name('webhook-deliveries.retry');
});
