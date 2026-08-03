<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «admin-core» — ليَاوت لوحة الإدارة والقيادة والمستخدمون والأدوار
|--------------------------------------------------------------------------
| 12.0 (خريطة السايد بار) · 12.3 (لوحة القيادة) · 12.13 (إدارة المستخدمين) · 12.2 (الأدوار).
|
| الباب واحد: `admin_panel.view` — ثمّ **كلّ صفحة بصلاحيّتها** (12.2.1)،
| فما لا يملكه المستخدم لا يظهر له في السايد بار أصلًا ولا يُفتَح برابط مباشر.
*/

Route::middleware(['auth', 'admin.panel'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // ---------------------------------------------------- لوحة القيادة
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // تصدير سجلّ النشاطات (12.3-20) — بصلاحيّته المستقلّة عن العرض
        Route::get('/dashboard/activity/export', [DashboardController::class, 'exportActivity'])
            ->middleware('permission:audit_logs.export')->name('dashboard.activity.export');

        // تخصيص اللوحة لكلّ دور (12.3-3) — تعديل إعداد منصّة فبصلاحيّة الإعدادات
        Route::post('/dashboard/layout', [DashboardController::class, 'saveLayout'])
            ->middleware('permission:settings_general.edit')->name('dashboard.layout');

        // ------------------------------------------------- إدارة المستخدمين
        // المسارات الثابتة قبل `{user}` حتى لا تبتلعها معلمة المسار
        Route::get('/users/approvals', [UserController::class, 'approvals'])
            ->middleware('permission:user_approvals.list')->name('users.approvals');

        Route::post('/users/approvals/approve', [UserController::class, 'approve'])
            ->middleware('permission:user_approvals.approve')->name('users.approve');

        Route::post('/users/approvals/reject', [UserController::class, 'reject'])
            ->middleware('permission:user_approvals.reject')->name('users.reject');

        // ------------------------------------------------- شرائح الجمهور (12.13)
        // كلّ فعلٍ بصلاحيّته المستقلّة، ومن لا يملكها **لا يرى زرّه** أصلًا (2.15-أ-7)
        Route::get('/users/segments', [UserController::class, 'segments'])
            ->middleware('permission:user_segments.list')->name('users.segments');

        // المعاينة اللحظيّة (عدد + عيّنة) بلا حفظ — بصلاحيّة العرض لا الإنشاء
        Route::post('/users/segments/preview', [UserController::class, 'previewSegment'])
            ->middleware('permission:user_segments.list')->name('users.segments.preview');

        Route::get('/users/segments/export', [UserController::class, 'exportSegments'])
            ->middleware('permission:user_segments.list')->name('users.segments.export');

        Route::post('/users/segments', [UserController::class, 'storeSegment'])
            ->middleware('permission:user_segments.create')->name('users.segments.store');

        Route::get('/users/segments/{audience}/members', [UserController::class, 'segmentMembers'])
            ->middleware('permission:user_segments.view')->name('users.segments.members');

        Route::post('/users/segments/{audience}/duplicate', [UserController::class, 'duplicateSegment'])
            ->middleware('permission:user_segments.create')->name('users.segments.duplicate');

        Route::post('/users/segments/{audience}/archive', [UserController::class, 'archiveSegment'])
            ->middleware('permission:user_segments.archive')->name('users.segments.archive');

        Route::delete('/users/segments/{audience}', [UserController::class, 'destroySegment'])
            ->middleware('permission:user_segments.delete')->name('users.segments.destroy');

        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:users.list')->name('users.index');

        // اختيار الأعمدة يُحفَظ لكلّ مستخدم (2.15-د)
        Route::post('/users/columns', [UserController::class, 'columns'])
            ->middleware('permission:users.list')->name('users.columns');

        // صفحة حساب المستخدم (12.1): صلاحيّتها المخصّصة في المصفوفة أو صلاحيّة العرض العامّة
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->middleware('permission:admin_user_detail.view,users.view')->name('users.show');

        // ---------------------------------------------- الأدوار والصلاحيّات
        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:roles.list')->name('roles.index');

        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:roles.create')->name('roles.store');

        Route::get('/roles/assign', [RoleController::class, 'assign'])
            ->middleware('permission:roles.assign')->name('roles.assign');

        Route::post('/roles/assign', [RoleController::class, 'storeAssignment'])
            ->middleware('permission:roles.assign')->name('roles.assign.store');

        Route::delete('/roles/assign/{assignment}', [RoleController::class, 'destroyAssignment'])
            ->middleware('permission:roles.assign')->name('roles.assign.destroy');

        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
            ->middleware('permission:roles.view')->name('roles.edit');

        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.edit')->name('roles.update');

        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles.delete')->name('roles.destroy');

        // --------------------------------------- مصفوفة الصلاحيّات واستثناءاتها
        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('permission:permissions.list')->name('permissions.index');

        Route::post('/permissions/users/{user}', [PermissionController::class, 'update'])
            ->middleware('permission:permissions.assign')->name('permissions.update');
    });
