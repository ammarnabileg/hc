<?php

use App\Http\Controllers\Trainee\AchievementController;
use App\Http\Controllers\Trainee\ChallengeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال التحديات وإنجازاتي (15 · 7.2 · 7.3 · 7.4 · 7.5 · 24.5)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم
| يُخفى من السايد بار أصلًا لا يُعطَّل (2.15-أ-7).
*/

Route::middleware('auth')->group(function () {

    // ------------------------------------------------------------ التحديات ⚔️
    Route::prefix('challenges')->name('challenges.')->group(function () {
        Route::get('/', [ChallengeController::class, 'index'])
            ->middleware('permission:war_participation.view')->name('index');

        Route::get('/mine', [ChallengeController::class, 'mine'])
            ->middleware('permission:war_participation.view')->name('mine');

        Route::get('/leaderboard', [ChallengeController::class, 'leaderboard'])
            ->middleware('permission:war_participation.view')->name('leaderboard');

        Route::post('/{challenge}/enter', [ChallengeController::class, 'enter'])
            ->middleware('permission:war_participation.create')->name('enter');

        // شاشة تركيز بلا سايد بار
        Route::get('/play/{participation}', [ChallengeController::class, 'play'])
            ->middleware('permission:war_participation.view')->name('play');

        // Autosave لحظيّ — كلّ إجابة تُحفَظ على السيرفر لا مع التسليم (15.1)
        Route::post('/play/{participation}/answer', [ChallengeController::class, 'answer'])
            ->middleware('permission:wars_matches.edit')->name('answer');

        Route::post('/play/{participation}/submit', [ChallengeController::class, 'submit'])
            ->middleware('permission:wars_matches.edit')->name('submit');

        Route::get('/result/{participation}', [ChallengeController::class, 'result'])
            ->middleware('permission:war_participation.view')->name('result');
    });

    // ------------------------------------------------------------ إنجازاتي 🏆
    Route::prefix('achievements')->name('achievements.')->group(function () {
        Route::get('/leaderboard', [AchievementController::class, 'leaderboard'])
            ->middleware('permission:leaderboards.view')->name('leaderboard');

        Route::get('/badges', [AchievementController::class, 'badges'])
            ->middleware('permission:badges.view')->name('badges');

        Route::get('/streak', [AchievementController::class, 'streak'])
            ->middleware('permission:streaks.view')->name('streak');

        Route::post('/streak/check-in', [AchievementController::class, 'checkIn'])
            ->middleware('permission:streaks.create')->name('streak.checkin');

        Route::get('/games', [AchievementController::class, 'games'])
            ->middleware('permission:games.view')->name('games');
    });
});
