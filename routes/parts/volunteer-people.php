<?php

use App\Http\Controllers\Volunteer\AcademyController;
use App\Http\Controllers\Volunteer\InterviewController;
use App\Http\Controllers\Volunteer\KudosController;
use App\Http\Controllers\Volunteer\LibraryController;
use App\Http\Controllers\Volunteer\PlacementController;
use App\Http\Controllers\Volunteer\RecruitmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «التوظيف والتسكين والأكاديمية والمكتبة الداخليّة والتقدير»
| (الدستور 13.4-د/هـ/ل/ي/ق · 23-3.3 · 24.4)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على **كلّ** مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم
| يُخفى من السايد بار أصلًا ولا يُعطَّل (2.15-أ-7).
*/

Route::middleware('auth')->prefix('volunteer')->name('volunteer.')->group(function () {

    // ---------------------------------------------------------------- المرشّحون (13.4-د)
    Route::middleware('permission:candidates.list')->group(function () {
        Route::get('/recruitment', [RecruitmentController::class, 'index'])->name('recruitment');
    });

    Route::get('/recruitment/{candidate}', [RecruitmentController::class, 'show'])
        ->middleware('permission:candidates.view')->name('recruitment.show');

    // سحب الكارت بين الأعمدة ⟵ تأكيد بسبب ويُسجَّل في `audit_logs`
    Route::post('/recruitment/{candidate}/move', [RecruitmentController::class, 'move'])
        ->middleware('permission:candidates.edit')->name('recruitment.move');

    // ---------------------------------------------------------------- المقابلات والـScorecards
    Route::middleware('permission:interviews.list,interviews.view')->group(function () {
        Route::get('/interviews', [InterviewController::class, 'index'])->name('interviews');
    });

    Route::post('/interviews', [InterviewController::class, 'store'])
        ->middleware('permission:interviews.create')->name('interviews.store');

    Route::post('/interviews/{interview}/status', [InterviewController::class, 'status'])
        ->middleware('permission:interviews.edit')->name('interviews.status');

    Route::middleware('permission:scorecards.view,scorecards.create')->group(function () {
        Route::get('/interviews/{interview}/scorecard', [InterviewController::class, 'scorecard'])->name('interviews.scorecard');
    });

    // حفظ تلقائيّ كمسودّة — «اتحفظ ✓» (2.17-ب)
    Route::post('/interviews/{interview}/scorecard/autosave', [InterviewController::class, 'autosave'])
        ->middleware('permission:scorecards.create,scorecards.edit')->name('interviews.scorecard.autosave');

    Route::post('/interviews/{interview}/scorecard/decide', [InterviewController::class, 'decide'])
        ->middleware('permission:scorecards.create,scorecards.edit')->name('interviews.scorecard.decide');

    Route::get('/interviews/{interview}/scorecard/export', [InterviewController::class, 'export'])
        ->middleware('permission:scorecards.export')->name('interviews.scorecard.export');

    // ---------------------------------------------------------------- القوائم والتسكين (13.4-هـ)
    Route::middleware('permission:placements.list')->group(function () {
        Route::get('/placement', [PlacementController::class, 'index'])->name('placement');
    });

    Route::post('/placement/{candidate}', [PlacementController::class, 'store'])
        ->middleware('permission:placements.create')->name('placement.store');

    Route::post('/placement/request/{placementRequest}/withdraw', [PlacementController::class, 'withdraw'])
        ->middleware('permission:placements.delete')->name('placement.withdraw');

    // ردّ المرشّح نفسه داخل مهلة الـ48 ساعة (النطاق SELF)
    Route::post('/placement/request/{placementRequest}/respond', [PlacementController::class, 'respond'])
        ->middleware('permission:placements.approve,placements.reject')->name('placement.respond');

    // ---------------------------------------------------------------- الأكاديمية (13.4-ل)
    Route::middleware('permission:academy_paths.list,academy_paths.view')->group(function () {
        Route::get('/academy', [AcademyController::class, 'index'])->name('academy');
        Route::get('/academy/path/{path}', [AcademyController::class, 'path'])->name('academy.path');
    });

    Route::middleware('permission:academy_recordings.list,academy_recordings.view')->group(function () {
        Route::get('/academy/recordings', [AcademyController::class, 'recordings'])->name('academy.recordings');
    });

    // بوب-أب OTP بتحقّق Server-side ومنع تكرار الكسب
    Route::post('/academy/recordings/{recording}/claim', [AcademyController::class, 'claim'])
        ->middleware('permission:academy_recordings.view')->name('academy.recordings.claim');

    Route::post('/academy/recordings/{recording}/report', [AcademyController::class, 'reportBroken'])
        ->middleware('permission:academy_recordings.view')->name('academy.recordings.report');

    // ---------------------------------------------------------------- المكتبة الداخليّة (23-3.3)
    Route::middleware('permission:internal_library.list')->group(function () {
        Route::get('/library', [LibraryController::class, 'index'])->name('library');
    });

    Route::get('/library/{item}', [LibraryController::class, 'show'])
        ->middleware('permission:internal_library.view')->name('library.show');

    Route::post('/library/{item}/request-access', [LibraryController::class, 'requestAccess'])
        ->middleware('permission:library_access_requests.create')->name('library.request-access');

    // ---------------------------------------------------------------- التقدير (13.4-ي)
    Route::middleware('permission:kudos.view')->group(function () {
        Route::get('/kudos', [KudosController::class, 'index'])->name('kudos');
    });

    Route::middleware('permission:kudos.create')->group(function () {
        Route::post('/kudos', [KudosController::class, 'store'])->name('kudos.store');
        Route::get('/kudos/search', [KudosController::class, 'search'])->name('kudos.search');
    });

    Route::middleware('permission:thanks_wall.view')->group(function () {
        Route::get('/kudos/wall', [KudosController::class, 'wall'])->name('kudos.wall');
    });

    Route::middleware('permission:thanks_wall.create')->group(function () {
        Route::post('/kudos/wall', [KudosController::class, 'post'])->name('kudos.wall.post');
        Route::post('/kudos/wall/{post}/vote', [KudosController::class, 'vote'])->name('kudos.wall.vote');
    });
});
