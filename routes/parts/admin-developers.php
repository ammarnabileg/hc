<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\DevelopersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 🧩 المطوّرين — API و Webhooks (12.15 · v5.5 قسمٌ جديد، سجلّ القرارات 25)
|--------------------------------------------------------------------------
| أمر المالك المباشر: دروب-داون بتابين — API (مبنيّ كاملًا هنا) وWebhooks
| (سقالته فقط، تُبنى لاحقًا فوق `?tab=webhooks` بلا إعادة هيكلة — 12.15-ب).
|
| ⚠️ لا `permission:integrations.manage` على أيّ مسارٍ هنا: هذا المفتاح
| owner-only لعمليّاتٍ ذات أثرٍ ماليّ (بوّابة الدفع) ولا شيء من هذا القبيل
| يُبنى في هذه الدفعة — التوثيق الصريح في `_STATUS.md`.
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
});
