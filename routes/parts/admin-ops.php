<?php

use App\Http\Controllers\Admin\OnboardingContentController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UpdatesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| لوحة الإدارة — صفحات النظام (12.7-أ · 12.7-هـ · 12.7-و)
|--------------------------------------------------------------------------
| ثلاث شاشات: محتوى الـOnboarding · التحديثات والترحيل · النسخ الاحتياطيّ وصحّة النظام.
| الصلاحيّة على **كلّ** مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم يُخفى ولا يُعطَّل.
| ⛔ ولا مسار تنفيذ واحد بلا تأكيد — الحارس في الكنترولر لا في الواجهة.
*/

Route::middleware(['auth', 'admin.panel'])->prefix('admin/ops')->name('admin.ops.')->group(function () {

    // ------------------------------------------------------------ محتوى الـOnboarding
    Route::middleware('permission:onboarding.view')->group(function () {
        Route::get('/onboarding', [OnboardingContentController::class, 'index'])->name('onboarding');
        Route::get('/onboarding/preview', [OnboardingContentController::class, 'preview'])->name('onboarding.preview');
    });

    Route::middleware('permission:onboarding.create')->group(function () {
        Route::post('/onboarding/slides', [OnboardingContentController::class, 'store'])->name('onboarding.slides.store');
        Route::post('/onboarding/template', [OnboardingContentController::class, 'applyTemplate'])->name('onboarding.template');
    });

    Route::middleware('permission:onboarding.edit')->group(function () {
        Route::put('/onboarding/slides/{slide}', [OnboardingContentController::class, 'update'])->name('onboarding.slides.update');
        Route::post('/onboarding/slides/{slide}/toggle', [OnboardingContentController::class, 'toggle'])->name('onboarding.slides.toggle');
        Route::post('/onboarding/reorder', [OnboardingContentController::class, 'reorder'])->name('onboarding.reorder');
        Route::post('/onboarding/first-time', [OnboardingContentController::class, 'saveFirstTime'])->name('onboarding.first-time');
        Route::post('/onboarding/pages', [OnboardingContentController::class, 'savePages'])->name('onboarding.pages');
    });

    Route::middleware('permission:onboarding.delete')
        ->delete('/onboarding/slides/{slide}', [OnboardingContentController::class, 'destroy'])->name('onboarding.slides.destroy');

    // ------------------------------------------------------------ التحديثات والترحيل
    Route::middleware('permission:updates.view')->group(function () {
        Route::get('/updates', [UpdatesController::class, 'index'])->name('updates');
        Route::post('/updates/check', [UpdatesController::class, 'check'])->name('updates.check');
        // الفحوص القبليّة قراءةٌ لا تلمس شيئًا — فصلاحيّة العرض تكفي لتشغيلها (2.11-ب)
        Route::post('/updates/preflight', [UpdatesController::class, 'preflight'])->name('updates.preflight');
    });

    Route::middleware('permission:version_history.list')
        ->get('/updates/history', [UpdatesController::class, 'history'])->name('updates.history');

    Route::middleware('permission:version_history.export')
        ->get('/updates/history/export', [UpdatesController::class, 'exportHistory'])->name('updates.history.export');

    // ⛔ التنفيذ والـDry-run بصلاحيّة الإدارة وحدها — والتأكيد المزدوج داخل الكنترولر
    Route::middleware('permission:updates.manage')->group(function () {
        Route::post('/updates/dry-run', [UpdatesController::class, 'dryRun'])->name('updates.dry-run');
        Route::post('/updates/migrate', [UpdatesController::class, 'migrate'])->name('updates.migrate');
        Route::post('/updates/version', [UpdatesController::class, 'recordVersion'])->name('updates.version');
    });

    Route::middleware('permission:updates.restore')
        ->post('/updates/rollback', [UpdatesController::class, 'rollback'])->name('updates.rollback');

    // ⛔ الاستعادة من نسخة تكتب فوق البيانات الحاليّة — صلاحيّة الاستعادة وتأكيد مكتوب (2.11-ح)
    Route::middleware('permission:backups.restore')
        ->post('/updates/restore/{backup}', [UpdatesController::class, 'restore'])
        ->whereNumber('backup')->name('updates.restore');

    // ------------------------------------------------------------ النسخ وصحّة النظام
    Route::middleware('permission:system_health.view,backups.view')
        ->get('/system', [SystemHealthController::class, 'index'])->name('system');

    Route::middleware('permission:system_health.view')
        ->post('/system/health', [SystemHealthController::class, 'runCheck'])->name('system.health');

    Route::middleware('permission:system_health.export')
        ->get('/system/health/export', [SystemHealthController::class, 'exportHealth'])->name('system.health.export');

    Route::middleware('permission:backups.create')
        ->post('/system/backups', [SystemHealthController::class, 'createBackup'])->name('system.backups.store');

    Route::middleware('permission:backups.export')
        ->get('/system/backups/{backup}/download', [SystemHealthController::class, 'download'])->name('system.backups.download');

    Route::middleware('permission:backups.delete')
        ->delete('/system/backups/{backup}', [SystemHealthController::class, 'destroy'])->name('system.backups.destroy');

    Route::middleware('permission:scheduled_jobs.manage')
        ->post('/system/schedule', [SystemHealthController::class, 'saveSchedule'])->name('system.schedule');
});
