<?php

use App\Http\Controllers\Admin\DelegationAdminController;
use App\Http\Controllers\Admin\EventAdminController;
use App\Http\Controllers\Admin\EventRegistrationsController;
use App\Http\Controllers\Admin\GamificationController;
use App\Http\Controllers\Admin\OffboardingAdminController;
use App\Http\Controllers\Admin\OrgAdminController;
use App\Http\Controllers\Admin\RepAdminController;
use App\Http\Controllers\Admin\RewardController;
use App\Http\Controllers\Admin\ScorecardCriteriaController;
use App\Http\Controllers\Admin\TaskTypeController;
use App\Http\Controllers\Admin\VolunteerAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| إدارة التطوّع والتلعيب والمكافآت والفعاليّات (24.2 · 24.3 · 13.4-ك · 12.9 · 12.10 · 12.11)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم
| يُخفى من السايد بار ولا يُعطَّل (2.15-أ-7).
*/

Route::middleware(['auth', 'admin.panel'])->prefix('admin')->name('admin.')->group(function () {

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

        // 🔒 العنصر الشرفيّ «أخوكم» — التعديل لمالك المنصّة وحده (13.4-ص-د)
        Route::post('/honorary', [VolunteerAdminController::class, 'saveHonorary'])
            ->middleware('permission:volunteer_central_settings.manage')->name('honorary.save');

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

        /*
        | ⭐ الغيابات والتفويض المؤقّت (23-6 · 24) — شاشة **إدارة** لا إضافة:
        | الإضافة موضعها «الأعضاء والبوزشنز» بـ`delegations.create`، وهنا
        | الاستعراض بـ`delegations.list` والإنهاء المبكّر بـ`delegations.edit`
        | («تعديل مدّة الغياب أثناء سريانه» — 12.2.2). فلا يُنهي مَن يقرأ فقط.
        */
        Route::get('/delegations', [DelegationAdminController::class, 'index'])
            ->middleware('permission:delegations.list')->name('delegations');
        Route::post('/delegations/{membershipAbsence}/end', [DelegationAdminController::class, 'end'])
            ->middleware('permission:delegations.edit')->name('delegations.end');

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

        // شهادات التطوّع (13.4-ع · 24.2)
        Route::get('/certificates', [VolunteerAdminController::class, 'certificates'])
            ->middleware('permission:volunteer_certificates.view')->name('certificates');
        Route::get('/certificates/more', [VolunteerAdminController::class, 'certificatesMore'])
            ->middleware('permission:volunteer_certificates.view')->name('certificates.more');
        Route::get('/certificates/export', [VolunteerAdminController::class, 'exportCertificates'])
            ->middleware('permission:volunteer_certificates.list')->name('certificates.export');
        Route::post('/certificates/settings', [VolunteerAdminController::class, 'saveCertificateSettings'])
            ->middleware('permission:volunteer_certificates.edit')->name('certificates.settings.save');
        Route::post('/certificates/issue', [VolunteerAdminController::class, 'issueCertificate'])
            ->middleware('permission:volunteer_certificates.create')->name('certificates.issue');
        Route::post('/certificates/issue-appreciation', [VolunteerAdminController::class, 'issueAppreciationCertificate'])
            ->middleware('permission:volunteer_certificates.create')->name('certificates.issue-appreciation');
        Route::post('/certificates/auto-issue', [VolunteerAdminController::class, 'autoIssueCertificates'])
            ->middleware('permission:volunteer_certificates.create')->name('certificates.auto-issue');
        Route::post('/certificates/{certificate}/revoke', [VolunteerAdminController::class, 'revokeCertificate'])
            ->middleware('permission:volunteer_certificates.edit')->name('certificates.revoke');
        Route::post('/certificates/types/{type}/toggle', [VolunteerAdminController::class, 'toggleCertificateType'])
            ->middleware('permission:volunteer_certificates.edit')->name('certificates.types.toggle');

        // ⭐ أنواع المهامّ (23-0.3): قالب وتشيك ليست وقيم مقترحة — بلا سلوك خاصّ
        Route::get('/task-types', [TaskTypeController::class, 'index'])
            ->middleware('permission:task_types.list')->name('task-types.index');
        Route::post('/task-types', [TaskTypeController::class, 'save'])
            ->middleware('permission:task_types.manage')->name('task-types.save');
        Route::post('/task-types/{taskType}/toggle', [TaskTypeController::class, 'toggle'])
            ->middleware('permission:task_types.manage')->name('task-types.toggle');

        /*
         | ⭐ معايير المقابلة (13.4-د): «معايير يضيفها الأدمن... عبر صفحة
         | الأدمن» — بلا هذه الشاشة الكتالوج رقمٌ محروق في سيدر (2.13).
         */
        Route::get('/scorecard-criteria', [ScorecardCriteriaController::class, 'index'])
            ->middleware('permission:scorecard_criteria.list')->name('scorecard-criteria.index');
        Route::post('/scorecard-criteria', [ScorecardCriteriaController::class, 'store'])
            ->middleware('permission:scorecard_criteria.create')->name('scorecard-criteria.store');
        Route::put('/scorecard-criteria/{criterion}', [ScorecardCriteriaController::class, 'update'])
            ->middleware('permission:scorecard_criteria.edit')->name('scorecard-criteria.update');
        Route::delete('/scorecard-criteria/{criterion}', [ScorecardCriteriaController::class, 'destroy'])
            ->middleware('permission:scorecard_criteria.delete')->name('scorecard-criteria.destroy');
        Route::post('/scorecard-criteria/{criterion}/restore', [ScorecardCriteriaController::class, 'restore'])
            ->middleware('permission:scorecard_criteria.restore')->name('scorecard-criteria.restore');

        // تحليلات التطوّع
        Route::get('/analytics', [VolunteerAdminController::class, 'analytics'])
            ->middleware('permission:reports_volunteer.view')->name('analytics');
        Route::post('/analytics/settings', [VolunteerAdminController::class, 'saveAnalyticsSettings'])
            ->middleware('permission:reports_volunteer.view')->name('analytics.settings.save');
    });

    // ------------------------------------------------------ التلعيب والتحديات 🎮
    Route::prefix('gamification')->name('gamification.')->group(function () {
        /*
         | ⭐ **الباب بسعة تاباته** — نظير ما وقع في الإحصائيّات (12.2.1-أ).
         |
         | الشاشة ثمانية تابات (`GamificationController::TAB_KEYS`) منها **الستريكس
         | ونادي الخامسة** و**الليدر بورد**، وقالب 12.2.3-أ-8 «مسؤول التلعيب
         | والتحديات» يغطّي بالنصّ: «… `badges · achievements · **leaderboards ·
         | streaks** · five_am_club · positive_messages · celebrations`».
         | وكان الحارس يسقط المفتاحين، فمَن مُنِح إدارة الستريك أو الليدر بورد
         | **وحدها** يُردّ عن شاشتهما الوحيدة — والبند مخفيٌّ في السايد بار فلا
         | يعرف حتى أنّها موجودة.
         */
        Route::get('/', [GamificationController::class, 'index'])
            ->middleware('permission:'.implode(',', GamificationController::GATE_KEYS))->name('index');

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

        // أسئلة المكافآت (12.10-أ) — الإجابات والنتائج بصلاحيّتها وحدها
        Route::post('/reward-questions', [GamificationController::class, 'saveRewardQuestion'])
            ->middleware('permission:reward_questions.create,reward_questions.edit')->name('reward-questions.save');
        Route::post('/reward-questions/import', [GamificationController::class, 'importRewardQuestions'])
            ->middleware('permission:reward_questions.import')->name('reward-questions.import');
        Route::post('/reward-questions/{rewardQuestion}/close', [GamificationController::class, 'closeRewardQuestion'])
            ->middleware('permission:reward_questions.edit')->name('reward-questions.close');
        Route::get('/reward-questions/{rewardQuestion}/results', [GamificationController::class, 'rewardQuestionResults'])
            ->middleware('permission:reward_questions.view')->name('reward-questions.results');
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

        /*
         | ⚠️ **الترتيب حاكم:** هذه المسارات الثابتة تسبق `/{event}/…` عمدًا.
         | فـ`/{event}` يلتقط أيّ مقطعٍ أوّل، ولو جاء بعدها لَحاولت لارافيل ربط
         | فعاليّةٍ اسمُها «registrations» أو «scan» فتردّ 404 على شاشةٍ سليمة.
         */

        // 🖥️ الشاشة الجامعة «المسجّلون والحضور» — بند خريطة 12.0 الذي كان بلا شاشة
        Route::get('/registrations', [EventRegistrationsController::class, 'index'])
            ->middleware('permission:event_registrations.list')->name('registrations.index');
        Route::get('/registrations/export', [EventRegistrationsController::class, 'export'])
            ->middleware('permission:event_registrations.export')->name('registrations.export');
        Route::post('/registrations/notify/{event}', [EventRegistrationsController::class, 'notify'])
            ->middleware('permission:events.edit')->name('registrations.notify');

        // مسح QR للتشيك-إن (13.3 · 12.11 · 24.3) — الرابط الذي تفتحه كاميرا المنظِّم
        Route::get('/scan/{token}', [EventRegistrationsController::class, 'scan'])
            ->middleware('permission:event_attendance.create')->name('scan');
        Route::post('/scan', [EventRegistrationsController::class, 'scanSubmit'])
            ->middleware('permission:event_attendance.create')->name('scan.submit');

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
