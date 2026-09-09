<?php

use App\Http\Controllers\Growth\ArticleController;
use App\Http\Controllers\Growth\ConsentSnoozeController;
use App\Http\Controllers\Growth\ContentKitController;
use App\Http\Controllers\Growth\GrowthAdminController;
use App\Http\Controllers\Growth\InviteBoardController;
use App\Http\Controllers\Growth\OgController;
use App\Http\Controllers\Growth\PreviewController;
use App\Http\Controllers\Growth\ProfileCompletionController;
use App\Http\Controllers\Growth\SeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال النموّ — حلقات النموّ (21.1) · قنوات الاكتساب (21.2) · الإعلان المدفوع (21.3)
|--------------------------------------------------------------------------
| القاعدة هنا مقلوبة عن باقي المنصّة: **أغلب هذه المسارات عامّة بلا تسجيل** عمدًا،
| لأنّها هي **الباب** الذي يدخل منه مَن لا حساب له — مقالٌ من محرّك بحث، أو رابطٌ
| على واتساب، أو معاينة درسٍ قبل التسجيل. ولذلك:
|  · لا يُعرَض في العامّ إلّا **المنشور المفهرَس** — والمسودّة 404 لا 403،
|  · والحاجز في **الخادم** لا في إخفاء الرابط،
|  · وما كان شخصيًّا أو إداريًّا فبصلاحيّته من المصفوفة (12.2.1).
*/

// ==================================================== أ) تحسين الظهور في البحث (21.2-ب)
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap.xml');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots.txt');

/*
| تأجيل بانر الموافقة (21.1-د · 21.3-د) — «لاحقًا» ليس قرارًا فلا صلاحيّة عليه
| ولا تسجيل دخول: البانر يُعرَض من أوّل زيارة، والزائر بلا حسابٍ بعد.
*/
Route::post('/consent/snooze', [ConsentSnoozeController::class, 'store'])->name('consent.snooze');

// ==================================================== ب) مركز المقالات — الواجهة العامّة (21.2-أ)
Route::get('/articles', [ArticleController::class, 'index'])->name('growth.articles.index');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('growth.articles.show');

// ==================================================== ج) معاينة أوّل درس مجّانًا (21.1-أ)
Route::get('/preview/courses/{slug}', [PreviewController::class, 'course'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('growth.preview.course');

Route::get('/preview/courses/{slug}/lessons/{lesson}', [PreviewController::class, 'lesson'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('growth.preview.lesson');

/*
| د) صور الـOG — قالبٌ لكلّ نوع رابط (21.1-أ · 12.14).
| عامّة بلا تسجيل لأنّ قارئها هو **مَن تُشارَك معه** لا صاحب الحساب.
*/
Route::prefix('og')->name('growth.og.')->group(function () {
    Route::get('/articles.svg', [OgController::class, 'articles'])->name('articles');
    Route::get('/course/{slug}.svg', [OgController::class, 'course'])->where('slug', '[A-Za-z0-9\-_]+')->name('course');
    Route::get('/path/{slug}.svg', [OgController::class, 'path'])->where('slug', '[A-Za-z0-9\-_]+')->name('path');
    Route::get('/article/{slug}.svg', [OgController::class, 'article'])->where('slug', '[A-Za-z0-9\-_]+')->name('article');
    Route::get('/certificate/{code}.svg', [OgController::class, 'certificate'])->where('code', '[A-Za-z0-9\-_]+')->name('certificate');
    Route::get('/u/{code}.svg', [OgController::class, 'profile'])->where('code', '[A-Za-z0-9\-_]+')->name('profile');
    // الشهر معيارُ استعلام لا جزءٌ من المسار — فالمسار الاختياريّ يترك امتدادًا أعرج
    Route::get('/leaderboard.svg', [OgController::class, 'leaderboard'])->name('leaderboard');
});

// الكارت الأسبوعيّ كصورة — عامّ ليُنشَر مباشرةً (21.2-د)
Route::get('/cards/weekly.svg', [ContentKitController::class, 'weeklyCard'])->name('growth.kit.weekly-card');

// ==================================================== هـ) شاشات المستخدم المسجَّل
Route::middleware('auth')->group(function () {

    // بار «أكمل ملفك» ومكافأته 3 تذاكر (21.1-ب) — نطاق SELF: بياناتي أنا
    Route::middleware('permission:user_profile.view')->group(function () {
        Route::get('/profile/completion', [ProfileCompletionController::class, 'show'])->name('growth.profile.completion');
        Route::post('/profile/completion/dismiss', [ProfileCompletionController::class, 'dismiss'])->name('growth.profile.completion.dismiss');
        Route::post('/profile/completion/claim', [ProfileCompletionController::class, 'claim'])->name('growth.profile.completion.claim');
    });

    // لوحة متصدّري الدعوات شهريًّا (21.1-ج)
    Route::middleware('permission:leaderboards.view')
        ->get('/referral/leaderboard', [InviteBoardController::class, 'index'])->name('growth.invite.board');

    // حزمة محتوى المتطوّعين: رابط الدعوة + نصوص جاهزة + قوالب الاستوديو (21.2-هـ)
    Route::middleware('permission:invitations_page.view')
        ->get('/referral/content-kit', [ContentKitController::class, 'index'])->name('growth.kit.index');
});

// ==================================================== و) إدارة إعدادات النموّ (2.13)
Route::middleware(['auth', 'admin.panel'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:settings_general.view')
        ->get('/growth', [GrowthAdminController::class, 'index'])->name('growth.index');

    Route::middleware('permission:settings_general.edit')
        ->post('/growth/setting', [GrowthAdminController::class, 'saveSetting'])->name('growth.setting.save');
});
