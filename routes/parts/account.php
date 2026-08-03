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
| الحراسة: كلّ مسار بصلاحيّته من المصفوفة (12.2.1)، وكلّها هنا بنطاق **SELF**
| لأنّ الشاشات شخصيّة: بياناتي أنا وتذاكري أنا وجلساتي أنا.
| واستثناءان بلا صلاحيّة لأنّ مصفوفة 12.2.2 لا تُتيح لهما نطاقًا شخصيًّا:
|  · **دليل المستخدم** — مرجع عامّ لكلّ مستخدم (24.5) ولا بيانات فيه أصلًا.
|  · **البحث الكبير** — «يراها كلّ مستخدم مفعَّل» (24.5)، وشرط التفعيل يُفحَص
|    في المتحكّم، والنتائج بروفايل عامّ بلا أيّ بيان حسّاس (13.1).
*/

Route::middleware('auth')->group(function () {

    // ------------------------------------------------ الدعم ← الشكاوى والمقترحات (11)
    Route::middleware('permission:complaints.view')->group(function () {
        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::post('/complaints/{complaint}/close', [ComplaintController::class, 'close'])->name('complaints.close');
    });

    Route::middleware('permission:complaints.create')->group(function () {
        Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
        Route::post('/complaints/{complaint}/messages', [ComplaintController::class, 'reply'])->name('complaints.reply');
    });

    // ------------------------------------------------ الدعم ← دليل المستخدم
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::get('/help/{article:slug}', [HelpController::class, 'show'])->name('help.show');
    Route::post('/help/{article:slug}/feedback', [HelpController::class, 'feedback'])->name('help.feedback');

    // ------------------------------------------------ حسابي ← بروفايلي (10)
    Route::middleware('permission:user_profile.view')->group(function () {
        Route::get('/profile', [ProfileController::class, 'me'])->name('profile.me');
    });

    // ------------------------------------------------ حسابي ← الإعدادات (24.5)
    Route::middleware('permission:user_profile.edit')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings/field', [SettingsController::class, 'updateField'])->name('settings.field');
        Route::post('/settings/avatar', [SettingsController::class, 'updateAvatar'])->name('settings.avatar');
        Route::post('/settings/security/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    });

    Route::middleware('permission:emergency_contact.edit')->group(function () {
        Route::post('/settings/emergency', [SettingsController::class, 'storeEmergency'])->name('settings.emergency.store');
        Route::delete('/settings/emergency/{contact}', [SettingsController::class, 'destroyEmergency'])->name('settings.emergency.destroy');
    });

    // ------------------------------------------------ حسابي ← الخصوصيّة والأمان (13.4-م)
    Route::middleware('permission:privacy_settings.view')->group(function () {
        Route::get('/settings/privacy', [SettingsController::class, 'privacy'])->name('settings.privacy');
    });

    Route::middleware('permission:privacy_settings.edit')->group(function () {
        Route::patch('/settings/privacy/field', [SettingsController::class, 'updatePrivacyField'])->name('settings.privacy.field');
    });

    Route::middleware('permission:contact_consent.delete')->group(function () {
        Route::post('/settings/privacy/consents/{consent}/revoke', [SettingsController::class, 'revokeConsent'])->name('settings.privacy.revoke');
    });

    Route::middleware('permission:user_sessions.delete')->group(function () {
        Route::delete('/settings/security/devices/{device}', [SettingsController::class, 'endSession'])->name('settings.devices.destroy');

        // ⭐ «تسجيل الخروج من كلّ الأجهزة» — إنهاء كلّ الجلسات دفعةً واحدة (2.3)
        Route::delete('/settings/security/devices', [SettingsController::class, 'endAllSessions'])->name('settings.devices.destroy-all');
    });

    Route::middleware('permission:data_export.create')->group(function () {
        Route::get('/settings/security/export', [SettingsController::class, 'exportData'])->name('settings.export');
    });

    /*
    | ------------------------------------------------ صفحة البحث الكبيرة (13.1)
    | ⭐ الصلاحيّة إلزاميّة على كلّ مسار (12.2.1) — وكان المساران بلا أيّ مفتاح،
    | فيمرّ البحث في دليل المنصّة كلّه بلا حارسٍ ولا نطاق. والمفتاحان منصوصان
    | في المصفوفة: `user_search.view` للصفحة و`user_search.list` للتنفيذ.
    */
    Route::middleware('permission:user_search.view')->group(function () {
        Route::get('/search', [SearchController::class, 'index'])->name('search');
    });

    Route::middleware('permission:user_search.list')->group(function () {
        Route::get('/search/more', [SearchController::class, 'more'])->name('search.more');
    });
});

/*
| الرابط الدائم للبروفايل العامّ — يعمل من كلّ مكان وبلا تسجيل دخول (13.4-م · 8).
| والحسّاس مخفيّ افتراضيًّا بمستويات المشاهدة الأربعة، فالفتح العامّ آمن.
*/
Route::get('/u/{code}', [ProfileController::class, 'show'])->name('u.profile');
