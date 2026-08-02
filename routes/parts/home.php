<?php

use App\Http\Controllers\Admin\PositiveMessageController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| الواجهة العامّة + الرسائل الإيجابيّة + السفراء (21.1 · 21.2 · 2.6-ب · 7.6.1)
|--------------------------------------------------------------------------
| «/» صفحة هبوط **مفهرسة** تعمل للزائر بلا تسجيل — وهي أوّل حلقة نموّ.
| وهذا الملفّ يُحمَّل بعد routes/web.php فيغلب المسار المؤقّت هناك.
| ومسارات الأدمن كلّها محروسة بصلاحيّتها (12.2.1).
*/

// ==================================================== أ) الصفحة الرئيسيّة العامّة
Route::get('/', [HomeController::class, 'index'])->name('home');

// لوحة متصدّري السفراء — عامّة ليكون اللقب مكانةً تُرى (7.6.1)
Route::get('/ambassadors', [HomeController::class, 'ambassadors'])->name('ambassadors.index');

// ==================================================== ب) تذكرة الرسالة المفاجئة (2.6-ب)
Route::middleware('auth')
    ->post('/positive/ticket', [HomeController::class, 'claimTicket'])->name('positive.ticket');

// ==================================================== ج) إدارة مكتبة الرسائل الإيجابيّة
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {

    Route::middleware('permission:positive_messages.list,positive_messages.view')
        ->get('/positive-messages', [PositiveMessageController::class, 'index'])->name('positive.index');

    Route::middleware('permission:positive_messages.create')
        ->post('/positive-messages', [PositiveMessageController::class, 'store'])->name('positive.store');

    Route::middleware('permission:positive_messages.edit')->group(function () {
        Route::put('/positive-messages/{message}', [PositiveMessageController::class, 'update'])->name('positive.update');
        Route::post('/positive-messages/{message}/toggle', [PositiveMessageController::class, 'toggle'])->name('positive.toggle');
        Route::post('/positive-messages/preview', [PositiveMessageController::class, 'preview'])->name('positive.preview');
    });

    Route::middleware('permission:positive_messages.manage')->group(function () {
        Route::post('/positive-messages/settings', [PositiveMessageController::class, 'saveSettings'])->name('positive.settings');
        Route::post('/positive-messages/settings/reset', [PositiveMessageController::class, 'resetSettings'])->name('positive.settings.reset');
    });

    Route::middleware('permission:positive_messages.delete')
        ->delete('/positive-messages/{message}', [PositiveMessageController::class, 'destroy'])->name('positive.destroy');
});
