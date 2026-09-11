<?php

use App\Http\Controllers\Admin\WarQuestionController;
use App\Http\Controllers\Trainee\AchievementController;
use App\Http\Controllers\Trainee\ChallengeController;
use App\Http\Controllers\Trainee\FocusWarController;
use App\Http\Controllers\Trainee\RewardQuestionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال التحديات/الحروب وإنجازاتي (15 · 12.10-ب · 24.2 · 7.2 · 7.3 · 7.4 · 7.5)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم
| يُخفى من السايد بار أصلًا لا يُعطَّل (2.15-أ-7).
*/

Route::middleware('auth')->group(function () {

    // ------------------------------------------------------------ الحروب ⚔️
    Route::prefix('challenges')->name('challenges.')->group(function () {
        Route::get('/', [ChallengeController::class, 'index'])
            ->middleware('permission:war_participation.view')->name('index');

        Route::get('/mine', [ChallengeController::class, 'mine'])
            ->middleware('permission:war_participation.view')->name('mine');

        Route::get('/leaderboard', [ChallengeController::class, 'leaderboard'])
            ->middleware('permission:war_participation.view')->name('leaderboard');

        // ---------------------------------------------- حرب التركيز (15.3)
        Route::prefix('focus')->name('focus.')->group(function () {
            Route::get('/', [FocusWarController::class, 'index'])
                ->middleware('permission:war_participation.view')->name('index');
            // حالة العدّاد — يقرؤها المتصفّح ليعيد المزامنة مع ساعة الخادم (15.3-ب)
            Route::get('/status', [FocusWarController::class, 'status'])
                ->middleware('permission:war_participation.view')->name('status');
            Route::post('/', [FocusWarController::class, 'store'])
                ->middleware('permission:war_participation.create')->name('store');
            Route::post('/{focus_war}/join', [FocusWarController::class, 'join'])
                ->middleware('permission:war_participation.create')->name('join');
            Route::post('/{focus_war}/cancel', [FocusWarController::class, 'cancel'])
                ->middleware('permission:war_participation.delete')->name('cancel');
        });

        // ---------------------------------------------- الاستعداد (15.0)
        // إلغاء الاستعداد من الشريط العائم — متاح من أيّ صفحة
        Route::post('/unready', [ChallengeController::class, 'unready'])
            ->middleware('permission:war_participation.create')->name('unready');

        // ---------------------------------------------- المواجهة (15.1 · 15.5 · 15.6)
        Route::get('/match/{match}', [ChallengeController::class, 'play'])
            ->middleware('permission:wars_matches.view')->name('play');

        // Autosave لحظيّ — كلّ إجابة تُحفَظ وتُصحَّح على الخادم (15.1)
        Route::post('/match/{match}/answer', [ChallengeController::class, 'answer'])
            ->middleware('permission:wars_matches.edit')->name('answer');

        Route::get('/match/{match}/state', [ChallengeController::class, 'state'])
            ->middleware('permission:wars_matches.view')->name('state');

        Route::post('/match/{match}/submit', [ChallengeController::class, 'submit'])
            ->middleware('permission:wars_matches.edit')->name('submit');

        Route::post('/match/{match}/withdraw', [ChallengeController::class, 'withdraw'])
            ->middleware('permission:wars_matches.reject')->name('withdraw');

        Route::get('/match/{match}/result', [ChallengeController::class, 'result'])
            ->middleware('permission:wars_matches.view')->name('result');

        // ---------------------------------------------- الساحة
        Route::get('/{challenge}', [ChallengeController::class, 'arena'])
            ->middleware('permission:war_participation.view')->name('arena');

        Route::post('/{challenge}/ready', [ChallengeController::class, 'ready'])
            ->middleware('permission:war_participation.create')->name('ready');

        Route::get('/{challenge}/fighters', [ChallengeController::class, 'fighters'])
            ->middleware('permission:war_participation.view')->name('fighters');

        Route::post('/{challenge}/duel/{opponent}', [ChallengeController::class, 'duel'])
            ->middleware('permission:wars_matches.create')->name('duel');
    });

    /*
    | ------------------------------------------------------------ سؤال المكافأة 🎁
    | 12.10-أ: رابط مؤقّت يُنشَر في جروبات المتدرّبين — يفتحه صاحب الحساب فيجد
    | السؤال وفوقه تايمر الديدلاين. والصلاحيّة `achievements.view` لأنّها ما
    | يملكه المتدرّب على مكاسبه، وحدّ إجابةٍ واحدة مضمون في قاعدة البيانات.
    */
    Route::prefix('q')->name('reward-questions.')->group(function () {
        Route::get('/{token}', [RewardQuestionController::class, 'show'])
            ->middleware('permission:achievements.view')->name('show');

        Route::post('/{token}', [RewardQuestionController::class, 'answer'])
            ->middleware('permission:achievements.view')->name('answer');
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

        // تذكرة مكافأة السلسلة ودرع التجميد (7.2) — الصرف والخصم في الخادم حصرًا
        Route::post('/streak/reward', [AchievementController::class, 'claimStreakReward'])
            ->middleware('permission:streaks.create')->name('streak.reward');

        Route::post('/streak/freeze', [AchievementController::class, 'freezeStreak'])
            ->middleware('permission:streaks.create')->name('streak.freeze');
    });

    // ------------------------------------------------------------ لوحة الإدارة
    Route::middleware('admin.panel')->prefix('admin')->name('admin.')->group(function () {

        // بنك أسئلة الحروب (12.10-ب · 24.2)
        Route::prefix('wars/bank')->name('wars.bank.')->group(function () {
            Route::get('/', [WarQuestionController::class, 'index'])
                ->middleware('permission:wars_bank.list')->name('index');
            Route::post('/', [WarQuestionController::class, 'save'])
                ->middleware('permission:wars_bank.create,wars_bank.edit')->name('save');
            Route::post('/bulk', [WarQuestionController::class, 'bulk'])
                ->middleware('permission:wars_bank.archive')->name('bulk');
            Route::post('/import', [WarQuestionController::class, 'import'])
                ->middleware('permission:wars_bank.import')->name('import');
            Route::get('/export', [WarQuestionController::class, 'export'])
                ->middleware('permission:wars_bank.export')->name('export');
            // كشف الإجابة مؤقّتًا — مسجَّل في Audit
            Route::post('/{warQuestion}/reveal', [WarQuestionController::class, 'reveal'])
                ->middleware('permission:wars_bank.view')->name('reveal');
            Route::post('/{warQuestion}/delete', [WarQuestionController::class, 'destroy'])
                ->middleware('permission:wars_bank.delete')->name('delete');
        });

    });
});
