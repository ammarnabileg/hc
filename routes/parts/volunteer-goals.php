<?php

use App\Http\Controllers\Volunteer\GoalController;
use App\Http\Controllers\Volunteer\PerformanceController;
use App\Http\Controllers\Volunteer\ProjectController;
use App\Http\Controllers\Volunteer\WorkPackageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «المشاريع والأهداف والأداء» (الدستور 24.4 · 23 · 13.4-ن)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، واختيار المفتاح مقصود:
|
|  • الأهداف والحزم: عرضٌ تنفيذيّ لا شاشات بناء — ولذلك `*.list` / `*.view`.
|  • **إعلان تحقّق المعيار** لدايركتور الكيان ⟵ `wp_items.edit` (النطاق ENTITY وحده)،
|    و**اعتماده** لمشرف المسار ⟵ `milestones.edit` (النطاق TRACK/ALL) — فلا يعتمد أحدٌ إعلانَ نفسه.
|  • **الاعتراض على نسخة الاعتماد** لدايركتور الكيان ⟵ `wp_items.edit` كذلك.
|  • الأداء: كلّ صفحة بمفتاح موردها (VXP · Rep · الليدر بورد · التقييمات)،
|    والتقييم نفسه بـ`evaluations.create` لأنّه فعل لا عرض.
*/

Route::middleware('auth')->prefix('volunteer')->group(function () {

    // ------------------------------------------------------- الأهداف والمَعالِم
    Route::middleware('permission:goals.list,goals.view')->group(function () {
        Route::get('/goals', [GoalController::class, 'index'])->name('volunteer.goals');
    });

    // إعلان تحقّق المعيار بدليل مرفق — لدايركتور الكيان
    Route::middleware('permission:wp_items.edit')->group(function () {
        Route::post('/goals/milestones/{milestone}/declare', [GoalController::class, 'declare'])
            ->name('volunteer.goals.declare');

        // اعتراض على نسخة الاعتماد خلال 24 ساعة — والسكوت قبول
        Route::post('/packages/{workPackage}/object', [WorkPackageController::class, 'object'])
            ->name('volunteer.packages.object');

        // بوب-أب توزيع VXP بقيديه الآليّين
        Route::post('/packages/tasks/{task}/vxp', [WorkPackageController::class, 'distributeVxp'])
            ->name('volunteer.packages.vxp');
    });

    // اعتماد الإعلان خلال نافذة 24 ساعة — لمشرف المسار
    Route::middleware('permission:milestones.edit')->group(function () {
        Route::post('/goals/milestones/{milestone}/approve', [GoalController::class, 'approve'])
            ->name('volunteer.goals.approve');
    });

    // -------------------------------------------------- حزم العمل وبنودها
    Route::middleware('permission:work_packages.list,work_packages.view')->group(function () {
        Route::get('/packages', [WorkPackageController::class, 'index'])->name('volunteer.packages');
        Route::get('/packages/{workPackage}', [WorkPackageController::class, 'show'])->name('volunteer.packages.show');
    });

    // ------------------------------------------------ المشروع التشغيليّ للكيان
    Route::middleware('permission:operational_projects.view')->group(function () {
        Route::get('/project', [ProjectController::class, 'index'])->name('volunteer.project');
    });

    // ------------------------------------------- البنود المتكرّرة — نوبتي
    Route::middleware('permission:recurring_items.view')->group(function () {
        Route::get('/recurring', [ProjectController::class, 'shift'])->name('volunteer.recurring');
    });

    // ------------------------------------------------------------- الأداء
    Route::middleware('permission:vxp_transactions.view,leaderboards.view')->group(function () {
        Route::get('/performance/vxp', [PerformanceController::class, 'vxp'])->name('volunteer.performance.vxp');
    });

    Route::middleware('permission:rep_transactions.view')->group(function () {
        Route::get('/performance/rep', [PerformanceController::class, 'rep'])->name('volunteer.performance.rep');
        Route::post('/performance/rep/{transaction}/object', [PerformanceController::class, 'objectRep'])
            ->name('volunteer.performance.rep.object');
    });

    Route::middleware('permission:leaderboards.view')->group(function () {
        Route::get('/performance/champion', [PerformanceController::class, 'champion'])
            ->name('volunteer.performance.champion');
    });

    Route::middleware('permission:evaluations.view')->group(function () {
        Route::get('/performance/evaluations', [PerformanceController::class, 'evaluations'])
            ->name('volunteer.performance.evaluations');
    });

    Route::middleware('permission:evaluations.create')->group(function () {
        Route::post('/performance/evaluations', [PerformanceController::class, 'storeEvaluation'])
            ->name('volunteer.performance.evaluations.store');
    });
});
