<?php

use App\Http\Controllers\Admin\EventAdminController;
use App\Http\Controllers\Admin\GamificationController;
use App\Http\Controllers\Admin\OffboardingAdminController;
use App\Http\Controllers\Admin\OrgAdminController;
use App\Http\Controllers\Admin\RepAdminController;
use App\Http\Controllers\Admin\RewardController;
use App\Http\Controllers\Admin\VolunteerAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| إدارة التطوّع والتلعيب والمكافآت والفعاليّات (24.2 · 24.3 · 13.4-ك · 12.9 · 12.10 · 12.11)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم
| يُخفى من السايد بار ولا يُعطَّل (2.15-أ-7).
*/

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {

    // ------------------------------------------------------ الإدارة المركزيّة للتطوّع 🤝
    Route::prefix('volunteer')->name('volunteer.')->group(function () {

        // لوحة التطوّع + صفحة التطوّع التعريفيّة (13.4-ك · 13.4-أ)
        Route::get('/', [VolunteerAdminController::class, 'index'])
            ->middleware('permission:volunteer_central_settings.view')->name('index');

        Route::post('/page', [VolunteerAdminController::class, 'savePage'])
            ->middleware('permission:volunteer_page.edit')->name('page.save');
        Route::post('/page/block', [VolunteerAdminController::class, 'saveBlock'])
            ->middleware('permission:volunteer_page.edit')->name('page.block.save');
        Route::post('/page/block/delete', [VolunteerAdminController::class, 'deleteBlock'])
            ->middleware('permission:volunteer_page.manage')->name('page.block.delete');

        Route::post('/reset/{group}', [VolunteerAdminController::class, 'resetGroup'])
            ->middleware('permission:volunteer_central_settings.manage')->name('reset');

        // الهيكل والبوزشنز والسعة (13.4-ف)
        Route::get('/org', [OrgAdminController::class, 'index'])
            ->middleware('permission:org_chart.view')->name('org');
        Route::post('/org/entity', [OrgAdminController::class, 'saveEntity'])
            ->middleware('permission:org_chart.edit')->name('org.entity.save');
        Route::post('/org/entity/{entity}/archive', [OrgAdminController::class, 'archiveEntity'])
            ->middleware('permission:org_chart.edit')->name('org.entity.archive');
        Route::post('/org/positions', [OrgAdminController::class, 'savePositions'])
            ->middleware('permission:positions.edit')->name('org.positions.save');
        Route::post('/org/settings', [OrgAdminController::class, 'saveSettings'])
            ->middleware('permission:capacity.edit')->name('org.settings.save');
        Route::post('/org/override', [OrgAdminController::class, 'saveOverride'])
            ->middleware('permission:capacity.edit')->name('org.override.save');
        Route::post('/org/override/drop', [OrgAdminController::class, 'dropOverride'])
            ->middleware('permission:capacity.edit')->name('org.override.drop');
        Route::get('/org/capacity', [OrgAdminController::class, 'capacityReport'])
            ->middleware('permission:capacity.view')->name('org.capacity');

        // ضبط Rep — كلّ قيم rep_rules (13.4-ن)
        Route::get('/rep', [RepAdminController::class, 'index'])
            ->middleware('permission:rep_transactions.view')->name('rep');
        Route::post('/rep/rules', [RepAdminController::class, 'saveRules'])
            ->middleware('permission:volunteer_central_settings.edit')->name('rep.rules.save');
        Route::post('/rep/rules/reset', [RepAdminController::class, 'resetRule'])
            ->middleware('permission:volunteer_central_settings.manage')->name('rep.rules.reset');
        Route::post('/rep/rules/reset-group', [RepAdminController::class, 'resetGroup'])
            ->middleware('permission:volunteer_central_settings.manage')->name('rep.rules.reset_group');
        Route::post('/rep/settings', [RepAdminController::class, 'saveSettings'])
            ->middleware('permission:volunteer_central_settings.edit')->name('rep.settings.save');
        Route::post('/rep/violations', [RepAdminController::class, 'saveViolation'])
            ->middleware('permission:volunteer_central_settings.edit')->name('rep.violations.save');
        Route::post('/rep/behavior/preview', [RepAdminController::class, 'previewBehavior'])
            ->middleware('permission:rep_manual.view')->name('rep.behavior.preview');
        Route::post('/rep/behavior', [RepAdminController::class, 'recordBehavior'])
            ->middleware('permission:rep_manual.create')->name('rep.behavior.record');
        Route::post('/rep/behavior/{behaviorTransaction}/approve', [RepAdminController::class, 'approveBehavior'])
            ->middleware('permission:rep_manual.approve')->name('rep.behavior.approve');

        // الأوفبوردنج والعائدون (13.4-س · 13.4-ق)
        Route::get('/offboarding', [OffboardingAdminController::class, 'index'])
            ->middleware('permission:offboarding.view')->name('offboarding');
        Route::get('/offboarding/reentries', [OffboardingAdminController::class, 'reentries'])
            ->middleware('permission:offboarding.view')->name('reentries');
        Route::post('/offboarding', [OffboardingAdminController::class, 'open'])
            ->middleware('permission:offboarding.create')->name('offboarding.open');
        Route::post('/offboarding/{offboarding}/clearance', [OffboardingAdminController::class, 'saveClearance'])
            ->middleware('permission:offboarding.create')->name('offboarding.clearance');
        Route::post('/offboarding/{offboarding}/interview', [OffboardingAdminController::class, 'saveExitInterview'])
            ->middleware('permission:offboarding.create')->name('offboarding.interview');
        Route::post('/offboarding/{offboarding}/complete', [OffboardingAdminController::class, 'complete'])
            ->middleware('permission:offboarding.approve')->name('offboarding.complete');
        Route::post('/offboarding/{offboarding}/reentry', [OffboardingAdminController::class, 'openReentry'])
            ->middleware('permission:offboarding.approve')->name('offboarding.reentry');
        Route::post('/offboarding/settings', [OffboardingAdminController::class, 'saveSettings'])
            ->middleware('permission:volunteer_central_settings.edit')->name('offboarding.settings.save');

        // شهادات التطوّع (13.4-ع)
        Route::get('/certificates', [VolunteerAdminController::class, 'certificates'])
            ->middleware('permission:volunteer_certificates.view')->name('certificates');
        Route::post('/certificates/settings', [VolunteerAdminController::class, 'saveCertificateSettings'])
            ->middleware('permission:volunteer_certificates.edit')->name('certificates.settings.save');
        Route::post('/certificates/issue', [VolunteerAdminController::class, 'issueCertificate'])
            ->middleware('permission:volunteer_certificates.create')->name('certificates.issue');
        Route::post('/certificates/{certificate}/revoke', [VolunteerAdminController::class, 'revokeCertificate'])
            ->middleware('permission:volunteer_certificates.edit')->name('certificates.revoke');

        // تحليلات التطوّع
        Route::get('/analytics', [VolunteerAdminController::class, 'analytics'])
            ->middleware('permission:reports_volunteer.view')->name('analytics');
        Route::post('/analytics/settings', [VolunteerAdminController::class, 'saveAnalyticsSettings'])
            ->middleware('permission:reports_volunteer.view')->name('analytics.settings.save');
    });

    // ------------------------------------------------------ التلعيب والتحديات 🎮
    Route::prefix('gamification')->name('gamification.')->group(function () {
        Route::get('/', [GamificationController::class, 'index'])
            ->middleware('permission:xp_rules.view,badges.view,wars_settings.view,celebrations.view')->name('index');

        Route::post('/settings', [GamificationController::class, 'saveSettings'])
            ->middleware('permission:xp_rules.edit,badges.edit,wars_settings.edit,celebrations.edit')->name('settings.save');
        Route::post('/reset', [GamificationController::class, 'resetGroup'])
            ->middleware('permission:xp_rules.manage,wars_settings.manage,celebrations.manage')->name('reset');

        Route::post('/xp-rows', [GamificationController::class, 'saveXpRows'])
            ->middleware('permission:xp_rules.edit')->name('xp.rows.save');

        Route::post('/badges', [GamificationController::class, 'saveBadge'])
            ->middleware('permission:badges.edit,badges.create')->name('badges.save');
        Route::post('/badges/{badge}/delete', [GamificationController::class, 'deleteBadge'])
            ->middleware('permission:badges.delete')->name('badges.delete');

        Route::post('/levels', [GamificationController::class, 'saveLevel'])
            ->middleware('permission:achievements.edit')->name('levels.save');
        Route::post('/levels/{level}/delete', [GamificationController::class, 'deleteLevel'])
            ->middleware('permission:achievements.manage')->name('levels.delete');

        Route::post('/wars/{challenge}', [GamificationController::class, 'saveWar'])
            ->middleware('permission:wars_settings.edit')->name('wars.save');
        Route::post('/wars/{challenge}/reset', [GamificationController::class, 'resetWar'])
            ->middleware('permission:wars_settings.manage')->name('wars.reset');

        Route::post('/celebrations/{celebrationEvent}', [GamificationController::class, 'saveCelebration'])
            ->middleware('permission:celebrations.edit')->name('celebrations.save');
    });

    // ------------------------------------------------------ إدارة المكافآت 🎁
    Route::prefix('rewards')->name('rewards.')->group(function () {
        Route::get('/', [RewardController::class, 'index'])
            ->middleware('permission:manual_rewards.list')->name('index');
        Route::post('/preview', [RewardController::class, 'preview'])
            ->middleware('permission:manual_rewards.create')->name('preview');
        Route::post('/grant', [RewardController::class, 'grant'])
            ->middleware('permission:manual_rewards.create')->name('grant');
        Route::post('/segment', [RewardController::class, 'segment'])
            ->middleware('permission:manual_rewards.import')->name('segment');
        Route::post('/settings', [RewardController::class, 'saveSettings'])
            ->middleware('permission:manual_rewards.manage')->name('settings.save');
    });

    // ------------------------------------------------------ الفعاليّات 📅
    Route::prefix('events')->name('events.')->group(function () {
        Route::get('/', [EventAdminController::class, 'index'])
            ->middleware('permission:events.list')->name('index');
        Route::post('/', [EventAdminController::class, 'save'])
            ->middleware('permission:events.create,events.edit')->name('save');
        Route::post('/{event}/cancel', [EventAdminController::class, 'cancel'])
            ->middleware('permission:events.edit')->name('cancel');
        Route::get('/{event}/registrations', [EventAdminController::class, 'registrations'])
            ->middleware('permission:event_registrations.list')->name('registrations');
        Route::post('/{event}/check-in', [EventAdminController::class, 'checkIn'])
            ->middleware('permission:event_attendance.create')->name('check-in');
        Route::post('/registrations/{registration}/toggle', [EventAdminController::class, 'toggleAttendance'])
            ->middleware('permission:event_attendance.edit')->name('attendance.toggle');
        Route::post('/settings', [EventAdminController::class, 'saveSettings'])
            ->middleware('permission:events.manage')->name('settings.save');
    });
});
