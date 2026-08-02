<?php

use App\Http\Controllers\Trainee\ComplaintController;
use App\Http\Controllers\Trainee\HelpController;
use App\Http\Controllers\Trainee\ProfileController;
use App\Http\Controllers\Trainee\SearchController;
use App\Http\Controllers\Trainee\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «account» — الدعم وحسابي والبروفايل العامّ والبحث
|--------------------------------------------------------------------------
| المراجع: الشكاوى (11) · البروفايل بطبقتيه (10 · 10.0 · 13.4-م) ·
| البحث الكبير (13.1) · شاشات الدعم وحسابي (24.5) · خصوصيّة الحقول (12.14-د).
|
| ملاحظة على الحراسة: صفحات هذا المجال **شخصيّة بطبيعتها** (بياناتي أنا)،
| فالحارس فيها هو **الملكيّة** لا صلاحيّة إداريّة — إلّا الشكاوى فلها موردٌ
| في مصفوفة الصلاحيّات (12.2.2) بنطاق SELF للمتدرّب، فنستعمله كما هو.
*/

Route::middleware('auth')->group(function () {

    // ------------------------------------------------ الدعم ← الشكاوى والمقترحات (11)
    Route::middleware('permission:complaints.list')->group(function () {
        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
    });

    Route::middleware('permission:complaints.create')->group(function () {
        Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
        Route::post('/complaints/{complaint}/messages', [ComplaintController::class, 'reply'])->name('complaints.reply');
    });

    Route::middleware('permission:complaints.edit')->group(function () {
        Route::post('/complaints/{complaint}/close', [ComplaintController::class, 'close'])->name('complaints.close');
    });

    // ------------------------------------------------ الدعم ← دليل المستخدم
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::get('/help/{article:slug}', [HelpController::class, 'show'])->name('help.show');
    Route::post('/help/{article:slug}/feedback', [HelpController::class, 'feedback'])->name('help.feedback');

    // ------------------------------------------------ حسابي ← بروفايلي (10)
    Route::get('/profile', [ProfileController::class, 'me'])->name('profile.me');

    // ------------------------------------------------ حسابي ← الإعدادات (24.5)
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/field', [SettingsController::class, 'updateField'])->name('settings.field');
    Route::post('/settings/avatar', [SettingsController::class, 'updateAvatar'])->name('settings.avatar');
    Route::post('/settings/emergency', [SettingsController::class, 'storeEmergency'])->name('settings.emergency.store');
    Route::delete('/settings/emergency/{contact}', [SettingsController::class, 'destroyEmergency'])->name('settings.emergency.destroy');

    // ------------------------------------------------ حسابي ← الخصوصيّة والأمان (13.4-م)
    Route::get('/settings/privacy', [SettingsController::class, 'privacy'])->name('settings.privacy');
    Route::patch('/settings/privacy/field', [SettingsController::class, 'updatePrivacyField'])->name('settings.privacy.field');
    Route::post('/settings/privacy/consents/{consent}/revoke', [SettingsController::class, 'revokeConsent'])->name('settings.privacy.revoke');
    Route::post('/settings/security/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::delete('/settings/security/devices/{device}', [SettingsController::class, 'endSession'])->name('settings.devices.destroy');
    Route::get('/settings/security/export', [SettingsController::class, 'exportData'])->name('settings.export');

    // ------------------------------------------------ صفحة البحث الكبيرة (13.1)
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/more', [SearchController::class, 'more'])->name('search.more');
});

/*
| الرابط الدائم للبروفايل العامّ — يعمل من كلّ مكان وبلا تسجيل دخول (13.4-م · 8).
| والحسّاس مخفيّ افتراضيًّا بمستويات المشاهدة الأربعة، فالفتح العامّ آمن.
*/
Route::get('/u/{code}', [ProfileController::class, 'show'])->name('u.profile');
