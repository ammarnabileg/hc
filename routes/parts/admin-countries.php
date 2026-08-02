<?php

use App\Http\Controllers\Admin\CountriesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| لوحة الإدارة — بيانات الدول (12.7-د · 24.3)
|--------------------------------------------------------------------------
| الشاشة نفسها تابٌ داخل «الإعدادات والنظام»، وهذه أفعالها.
| الصلاحيّة على كلّ مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم يُخفى ولا يُعطَّل.
| والاستيراد سلطةٌ مستقلّة عن العرض لأنّه يمسّ بيانات كلّ المستخدمين.
*/

Route::middleware(['auth', 'admin.panel'])->prefix('admin')->name('admin.countries.')->group(function () {

    Route::middleware('permission:countries_data.import')->group(function () {
        Route::post('/countries/import', [CountriesController::class, 'import'])->name('import');
        Route::post('/countries/check', [CountriesController::class, 'check'])->name('check');
        // الدمج بعد عرض الفروق واختيار المالك — و`dry_run` يعاين بلا كتابة (2.11-ط)
        Route::post('/countries/merge', [CountriesController::class, 'merge'])->name('merge');
    });

    Route::middleware('permission:countries_data.edit')->group(function () {
        Route::put('/countries/{country}', [CountriesController::class, 'updateCountry'])->name('update');
        Route::put('/countries/governorates/{governorate}', [CountriesController::class, 'updateGovernorate'])->name('governorates.update');
    });

    Route::middleware('permission:countries_data.export')
        ->get('/countries/export', [CountriesController::class, 'export'])->name('export');
});
