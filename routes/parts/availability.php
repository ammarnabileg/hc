<?php

use App\Http\Controllers\Admin\AvailabilityAdminController;
use App\Http\Controllers\Trainee\TimezoneController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| الإتاحة الزمنيّة والتوقيت (الدستور 5)
|--------------------------------------------------------------------------
| مساران لا ثالث لهما: توقيت المستخدم (كشفٌ تلقائيّ + تعديل يدويّ)، وشاشة
| إدارة فترات الإتاحة وأوقات التشغيل اليوميّة. والصلاحيّة إلزاميّة على كلّ
| مسار إداريّ (12.2.1)، أمّا توقيت المستخدم فملكه هو فلا يحرسه إلّا `auth`.
*/

Route::middleware('auth')->group(function () {
    // نداء صامت من الصفحة: «أنا في هذه المنطقة الآن» — الخادم يتحقّق ثمّ يكتب
    Route::post('/me/timezone/detect', [TimezoneController::class, 'detect'])->name('timezone.detect');

    // الاختيار اليدويّ يعلو الكشف التلقائيّ ولا يُدهَس (5)
    Route::post('/me/timezone', [TimezoneController::class, 'update'])->name('timezone.update');
});

Route::middleware(['auth', 'admin.panel'])->prefix('admin')->name('admin.')->group(function () {

    Route::middleware('permission:courses.list,courses.view')
        ->get('/availability', [AvailabilityAdminController::class, 'index'])->name('availability.index');

    Route::middleware('permission:courses.edit')->group(function () {
        Route::post('/availability/{course}/periods', [AvailabilityAdminController::class, 'storePeriod'])
            ->name('availability.periods.store');

        Route::post('/availability/periods/{period}/toggle', [AvailabilityAdminController::class, 'togglePeriod'])
            ->name('availability.periods.toggle');

        Route::delete('/availability/periods/{period}', [AvailabilityAdminController::class, 'destroyPeriod'])
            ->name('availability.periods.destroy');

        Route::post('/availability/{course}/daily', [AvailabilityAdminController::class, 'saveDaily'])
            ->name('availability.daily.save');

        Route::post('/availability/settings', [AvailabilityAdminController::class, 'saveSettings'])
            ->name('availability.settings.save');

        Route::post('/availability/settings/reset', [AvailabilityAdminController::class, 'resetSettings'])
            ->name('availability.settings.reset');
    });
});
