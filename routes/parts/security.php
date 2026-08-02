<?php

use App\Http\Controllers\Admin\UserModerationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Trainee\SettingsController;
use App\Services\Security\RequireVerifiedEmail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «security» — الصيانة · الاسترجاع والتحقّق · منطقة الخطر · احتواء الحسابات
|--------------------------------------------------------------------------
| المراجع: وضع الصيانة العامّ (12.7-و-1) · استرجاع كلمة السرّ وحذف الحساب (2.3) ·
| تحقّق البريد بـOTP (2.5-ب) · أدوات الاحتواء والانتحال (12.1).
|
| الحراسة: كلّ مسار إدارة بصلاحيّته من المصفوفة (12.2.1)، وشاشات الزوّار
| بـ`guest` وشاشات الحساب بـ`auth` وصلاحيّتها الشخصيّة.
*/

// ---------------------------------------------------------------- الزوّار (2.3 · 2.5-ب)
Route::middleware('guest')->group(function () {

    // «نسيت كلمة السرّ»: طلب ⟵ رمز/رابط ⟵ إعادة تعيين
    Route::get('/forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->name('password.email');
    Route::get('/forgot-password/sent', [PasswordController::class, 'sent'])->name('password.sent');
    Route::post('/forgot-password/code', [PasswordController::class, 'exchangeCode'])->name('password.code');
    Route::get('/reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->name('password.update');

    // تحقّق البريد بـOTP — شاشة التأكيد وأزرارها
    Route::get('/register/verify', [EmailVerificationController::class, 'show'])->name('register.verify');
    Route::post('/register/verify/send', [EmailVerificationController::class, 'send'])->name('register.verify.send');
    Route::post('/register/verify/confirm', [EmailVerificationController::class, 'confirm'])->name('register.verify.confirm');

    /*
     | ⭐ نفس مسار التسجيل ونفس متحكّمه — بحارس واحد زيادة يمنع إنشاء الحساب قبل
     | تأكيد البريد (2.5-ب). المسار مُعاد تسجيله هنا لأنّ `routes/parts/*` تُحمَّل
     | بعد `routes/web.php` فتغلبه — ولا يُلمَس ملفّ يملكه غيرنا.
     */
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware(RequireVerifiedEmail::class);
});

// ------------------------------------------------------- منطقة الخطر في حسابي (2.3)
Route::middleware(['auth', 'permission:user_profile.edit'])->group(function () {
    Route::post('/settings/privacy/danger/code', [SettingsController::class, 'sendDeletionCode'])
        ->name('settings.danger.code');

    Route::delete('/settings/privacy/danger', [SettingsController::class, 'destroyAccount'])
        ->name('settings.danger.destroy');
});

// --------------------------------------------- أدوات الاحتواء في لوحة الإدارة (12.1)
Route::middleware(['auth', 'admin.panel'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::middleware('permission:account_suspension.create')->group(function () {
            Route::post('/users/{user}/ban', [UserModerationController::class, 'ban'])->name('users.ban');
            Route::post('/users/{user}/suspend', [UserModerationController::class, 'suspend'])->name('users.suspend');
        });

        Route::post('/users/{user}/release', [UserModerationController::class, 'release'])
            ->middleware('permission:account_suspension.delete')->name('users.release');

        Route::post('/users/{user}/sessions/end', [UserModerationController::class, 'endSessions'])
            ->middleware('permission:user_sessions.delete')->name('users.sessions.end');

        Route::post('/users/{user}/verify-email', [UserModerationController::class, 'verifyEmail'])
            ->middleware('permission:users.edit')->name('users.verify-email');

        Route::post('/users/{user}/password-link', [UserModerationController::class, 'passwordLink'])
            ->middleware('permission:users.edit')->name('users.password-link');

        /*
         | صفحة حساب المستخدم (12.1) — الصلاحيّات الثلاث المخصّصة لها في المصفوفة
         | كانت **معرَّفة وغير مستعمَلة في مسار واحد**، فصارت هنا حارسًا حقيقيًّا:
         | `admin_user_detail.edit` للتعديل اليدويّ وتثبيت الدولة، و`.export` للتصدير.
         */
        Route::put('/users/{user}', [UserModerationController::class, 'update'])
            ->middleware('permission:admin_user_detail.edit')->name('users.update');

        Route::post('/users/{user}/country', [UserModerationController::class, 'pinCountry'])
            ->middleware('permission:admin_user_detail.edit')->name('users.country');

        Route::get('/users/{user}/export', [UserModerationController::class, 'export'])
            ->middleware('permission:admin_user_detail.export')->name('users.export');

        // الانتحال مجموعة محميّة لمالك المنصّة (12.2.1-5)
        Route::post('/users/{user}/impersonate', [UserModerationController::class, 'impersonate'])
            ->middleware('permission:impersonation.create')->name('users.impersonate');
    });

/*
 | زرّ العودة من الانتحال: خارج حارس `admin_panel.view` عن قصد — الأدمن وقتها
 | مسجَّل بحساب المستخدم العاديّ، فلو حرسناه بصلاحيّة الإدارة لعلق بلا رجعة.
 | والحارس الحقيقيّ هو مفتاح السيشن الذي لا يُوضَع إلّا ببدء انتحال مشروع.
 */
Route::middleware('auth')->post('/impersonate/stop', [UserModerationController::class, 'stopImpersonating'])
    ->name('admin.impersonate.stop');
