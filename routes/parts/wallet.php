<?php

use App\Http\Controllers\GatewayWebhookController;
use App\Http\Controllers\Trainee\TopupController;
use App\Http\Controllers\Trainee\WalletController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| المحفظة والمعاملات وشحن الحساب (19 · 19.5 · 24.5)
|--------------------------------------------------------------------------
| كلّ مسار محكوم بصلاحيّته (12.2.1)، والعنصر الذي لا يملكه المستخدم يُخفى
| من الواجهة أصلًا فلا يصطدم بـ403.
*/

Route::middleware('auth')->prefix('wallet')->name('wallet.')->group(function () {

    // 🖥️ رصيدي وشحن
    Route::get('/', [WalletController::class, 'index'])
        ->middleware('permission:wallet.view')
        ->name('index');

    // 🖥️ التذاكر 🎟️
    Route::get('/tickets', [WalletController::class, 'tickets'])
        ->middleware('permission:wallet.view')
        ->name('tickets');

    // 🖥️ المعاملات والفواتير
    Route::get('/transactions', [WalletController::class, 'transactions'])
        ->middleware('permission:wallet.list')
        ->name('transactions');

    Route::get('/transactions/export', [WalletController::class, 'export'])
        ->middleware('permission:wallet.export')
        ->name('transactions.export');

    // 🖥️ شحن الحساب — صفحة واحدة بتابين
    Route::middleware('permission:topup.create')->group(function () {
        Route::get('/topup', [TopupController::class, 'index'])->name('topup');
        Route::post('/topup/manual', [TopupController::class, 'storeManual'])->name('topup.manual');
        Route::post('/topup/gateway', [TopupController::class, 'startGateway'])->name('topup.gateway');

        // رابط الرجوع من البوّابة: عرضٌ فقط ولا يزيد رصيدًا أبدًا (19.5-ج-2)
        Route::get('/topup/return', [TopupController::class, 'gatewayReturn'])->name('topup.return');
    });

    // 🖥️ طلبات الشحن
    Route::get('/topup/requests', [TopupController::class, 'requests'])
        ->middleware('permission:topup.list')
        ->name('topup.requests');
});

/*
| ويب هوك البوّابة: بلا CSRF وبلا auth — لأنّ المنادي خادم فواتيرك لا متصفّح.
| وحمايته من الهاش الموقَّع وحده (19.5-ج-2)، وكلّ نداء يُسجَّل خامًا.
*/
Route::post('/webhooks/fawaterk', [GatewayWebhookController::class, 'fawaterk'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.fawaterk');
