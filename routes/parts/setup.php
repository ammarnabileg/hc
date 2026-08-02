<?php

use App\Http\Controllers\Setup\DatabaseController;
use App\Http\Controllers\Setup\FinishController;
use App\Http\Controllers\Setup\OwnerController;
use App\Http\Controllers\Setup\PlatformController;
use App\Http\Controllers\Setup\RequirementsController;
use App\Http\Controllers\Setup\SetupController;
use App\Services\Setup\Middleware\EnsureSetupIsOpen;
use App\Services\Setup\Middleware\RequireSetupToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «setup» — معالج التنصيب (الدستور 2.2)
|--------------------------------------------------------------------------
| المشروع يبدأ من domain-name.com/setup.php: بلا أيّ تيرمينال وبلا أيّ تحكّم
| من داخل السيرفر. والمعالج خطوات بحفظ بينها (2.15-ب)، وكلّ خطوة سؤال واحد.
|
| الحراسة هنا ليست بالصلاحيّات (12.2.1) لأنّ المنصّة قبل التنصيب بلا مستخدمين
| ولا قاعدة بيانات أصلًا، فالبديل طبقتان:
|  · EnsureSetupIsOpen — بعد اكتمال التنصيب تُقفَل كلّ المسارات نهائيًّا (404).
|  · RequireSetupToken — توكن يُولَّد في storage/setup-token.txt عند أوّل فتح،
|    فلا يسبقك أحد للتنصيب على خادم عامّ.
*/

/*
 * قبل التنصيب لا توجد جداول جلسات ولا كاش في قاعدة بيانات غير مهيّأة أصلًا،
 * فنحوّل مخازن الطلب لمخازن لا تحتاج قاعدة بيانات — وهذا يسري على مسارات
 * التنصيب وحدها وقبل اشتغال الميدل-وير لأنّ ملفّات المسارات تُحمَّل وقت الإقلاع.
 */
if (! is_file(storage_path('installed.lock'))
    && str_starts_with(ltrim(request()->getPathInfo(), '/'), 'setup')) {
    config([
        'session.driver' => 'cookie',
        'cache.default' => 'array',
        'queue.default' => 'sync',
    ]);
}

Route::middleware(EnsureSetupIsOpen::class)->prefix('setup')->name('setup.')->group(function () {

    // ------------------------------------------------ بوّابة التوكن (أمان 2.2)
    Route::get('/token', [SetupController::class, 'token'])->name('token');
    Route::post('/token', [SetupController::class, 'verifyToken'])->name('token.verify');

    Route::middleware(RequireSetupToken::class)->group(function () {

        Route::get('/', [SetupController::class, 'index'])->name('index');

        // ------------------------------------------------ 1) فحص المتطلّبات
        Route::get('/requirements', [RequirementsController::class, 'show'])->name('requirements');
        Route::post('/requirements', [RequirementsController::class, 'store'])->name('requirements.store');

        // ------------------------------------------------ 2) قاعدة البيانات
        Route::get('/database', [DatabaseController::class, 'show'])->name('database');
        Route::post('/database/test', [DatabaseController::class, 'test'])->name('database.test');
        Route::post('/database', [DatabaseController::class, 'store'])->name('database.store');

        // ------------------------------------------------ 3) تجهيز الجداول والبيانات
        Route::get('/migrate', [DatabaseController::class, 'migrate'])->name('migrate');
        Route::post('/migrate', [DatabaseController::class, 'runMigrations'])->name('migrate.run');

        // ------------------------------------------------ 4) بيانات المنصّة
        Route::get('/platform', [PlatformController::class, 'show'])->name('platform');
        Route::post('/platform', [PlatformController::class, 'store'])->name('platform.store');

        // ------------------------------------------------ 5) حساب مالك المنصّة
        Route::get('/owner', [OwnerController::class, 'show'])->name('owner');
        Route::post('/owner', [OwnerController::class, 'store'])->name('owner.store');

        // ------------------------------------------------ 6) الإنهاء والقفل
        Route::get('/finish', [FinishController::class, 'show'])->name('finish');
        Route::post('/finish', [FinishController::class, 'install'])->name('finish.install');
    });
});
