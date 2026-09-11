<?php

use App\Http\Controllers\AdminScreens\MeetingsAdminController;
use App\Http\Controllers\AdminScreens\QuestionBankController;
use App\Http\Controllers\AdminScreens\ReferralAdminController;
use App\Http\Controllers\AdminScreens\ReportDownloadController;
use App\Http\Controllers\AdminScreens\ReportScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| شاشات لوحة الإدارة الناقصة من القسم 24 (24.1-3 · 24.2 · 24.3-خامسًا)
|--------------------------------------------------------------------------
| أربع شاشات نصّ عليها الدستور ولم تكن مبنيّة:
|   1) بنك الأسئلة والامتحانات المركزيّ (24.1-3)
|   2) الريفيرال والسفراء — الطرف الإداريّ (24.2)
|   3) التقارير المجدولة وسجلّ إرسالها (24.3-خامسًا)
|   4) مرآة اجتماعات التطوّع في لوحة الإدارة (24.2-أوّلًا)
|
| الصلاحيّة إلزاميّة على **كلّ** مسار (12.2.1)، والقوائم هنا «أو»: تكفي واحدة
| لعبور الحارس. والعنصر الذي لا يملكه المستخدم يُخفى من الواجهة أيضًا لا يُعطَّل.
| 🔒 وما يلمس مالًا (عمولة الريفيرال · التقارير الماليّة) محجوزٌ لمالك المنصّة
|    بحارسٍ إضافيّ داخل الكنترولر — لأنّ إخفاء الزرّ وحده ليس منعًا.
*/

Route::middleware(['auth', 'admin.panel'])->prefix('admin')->name('admin.')->group(function () {

    // ============================================ 1) بنك الأسئلة والامتحانات (24.1-3)
    Route::middleware('permission:question_bank.list,question_bank.view')->group(function () {
        Route::get('/question-bank', [QuestionBankController::class, 'index'])->name('question-bank.index');
        Route::get('/question-bank/preview', [QuestionBankController::class, 'preview'])->name('question-bank.preview');
    });

    Route::middleware('permission:question_bank.create')->group(function () {
        Route::post('/question-bank', [QuestionBankController::class, 'store'])->name('question-bank.store');
        Route::post('/question-bank/{question}/duplicate', [QuestionBankController::class, 'duplicate'])->name('question-bank.duplicate');
    });

    Route::middleware('permission:question_bank.edit')->group(function () {
        Route::put('/question-bank/{question}', [QuestionBankController::class, 'update'])->name('question-bank.update');
        Route::post('/question-bank/{question}/active', [QuestionBankController::class, 'toggleActive'])->name('question-bank.active');
        Route::post('/question-bank/{question}/move', [QuestionBankController::class, 'move'])->name('question-bank.move');
    });

    // تبديل «سؤال عام» صلاحيّة مستقلّة — لأنّه يغيّر الامتحان النهائيّ نفسه (24.1-3)
    Route::middleware('permission:general_questions.edit,question_bank.manage')
        ->post('/question-bank/{question}/general', [QuestionBankController::class, 'toggleGeneral'])->name('question-bank.general');

    // إعادة استخدام السؤال في أكثر من امتحان
    Route::middleware('permission:course_exam.edit,question_bank.manage')
        ->post('/question-bank/{question}/reuse', [QuestionBankController::class, 'reuse'])->name('question-bank.reuse');

    Route::middleware('permission:question_bank.delete')
        ->delete('/question-bank/{question}', [QuestionBankController::class, 'destroy'])->name('question-bank.destroy');

    // الاستيراد صلاحيّة منفصلة عن الإنشاء (24.1-3)
    Route::middleware('permission:question_bank.import')
        ->post('/question-bank/import', [QuestionBankController::class, 'import'])->name('question-bank.import');

    Route::middleware('permission:question_bank.export')
        ->get('/question-bank/export', [QuestionBankController::class, 'export'])->name('question-bank.export');

    Route::middleware('permission:question_bank.manage')->group(function () {
        Route::post('/question-bank/settings', [QuestionBankController::class, 'saveSettings'])->name('question-bank.settings');
        Route::post('/question-bank/settings/reset', [QuestionBankController::class, 'resetSettings'])->name('question-bank.settings.reset');
    });

    // ============================================ 2) الريفيرال والسفراء (24.2)
    Route::middleware('permission:referrals.list,referrals.view,ambassadors.list')->group(function () {
        Route::get('/referrals', [ReferralAdminController::class, 'index'])->name('referrals.index');
        Route::get('/referrals/audit/{user}', [ReferralAdminController::class, 'audit'])->name('referrals.audit');
    });

    // صرف/تعليق المكافأة — إدارة لا عرض
    Route::middleware('permission:referrals.manage')->group(function () {
        Route::post('/referrals/{referral}/payout', [ReferralAdminController::class, 'payout'])->name('referrals.payout');
        Route::post('/referrals/{referral}/hold', [ReferralAdminController::class, 'hold'])->name('referrals.hold');
        Route::post('/referrals/settings', [ReferralAdminController::class, 'saveSettings'])->name('referrals.settings');
        Route::post('/referrals/settings/reset', [ReferralAdminController::class, 'resetSettings'])->name('referrals.settings.reset');
    });

    // عتبات الألقاب — صلاحيّة السفراء لا صلاحيّة الدعوات
    Route::middleware('permission:ambassadors.manage,ambassadors.edit')
        ->post('/referrals/tiers', [ReferralAdminController::class, 'saveTiers'])->name('referrals.tiers');

    Route::middleware('permission:referrals.export,ambassadors.export')
        ->get('/referrals/export', [ReferralAdminController::class, 'export'])->name('referrals.export');

    // ============================================ 3) التقارير المجدولة (24.3-خامسًا)
    Route::middleware('permission:report_schedules.list,report_schedules.view')->group(function () {
        Route::get('/report-schedules', [ReportScheduleController::class, 'index'])->name('report-schedules.index');
        Route::get('/report-schedules/{schedule}/log', [ReportScheduleController::class, 'log'])->name('report-schedules.log');
    });

    Route::middleware('permission:report_schedules.create')
        ->post('/report-schedules', [ReportScheduleController::class, 'store'])->name('report-schedules.store');

    Route::middleware('permission:report_schedules.edit')->group(function () {
        Route::put('/report-schedules/{schedule}', [ReportScheduleController::class, 'update'])->name('report-schedules.update');
        Route::post('/report-schedules/{schedule}/toggle', [ReportScheduleController::class, 'toggle'])->name('report-schedules.toggle');
    });

    // «شغّل الآن» فعلٌ تشغيليّ لا تحريريّ — فله صلاحيّة الإدارة
    Route::middleware('permission:report_schedules.manage')->group(function () {
        Route::post('/report-schedules/{schedule}/run', [ReportScheduleController::class, 'run'])->name('report-schedules.run');
        Route::post('/report-schedules/settings', [ReportScheduleController::class, 'saveSettings'])->name('report-schedules.settings');
        Route::post('/report-schedules/settings/reset', [ReportScheduleController::class, 'resetSettings'])->name('report-schedules.settings.reset');
    });

    Route::middleware('permission:report_schedules.delete')
        ->delete('/report-schedules/{schedule}', [ReportScheduleController::class, 'destroy'])->name('report-schedules.destroy');

    // ============================================ 4) اجتماعات التطوّع في اللوحة (24.2-أوّلًا)
    Route::middleware('permission:meetings.list,meetings.view')->group(function () {
        Route::get('/meetings', [MeetingsAdminController::class, 'index'])->name('meetings.index');
        Route::get('/meetings/{meeting}', [MeetingsAdminController::class, 'show'])->name('meetings.show');
    });

    /*
     | ⭐ [2026-09-11] «+ اجتماع» من اللوحة — بصلاحيّة الإنشاء نفسها التي تحرس
     | `volunteer.meetings.store` حرفيًّا (`meetings.create`)، لا بصلاحيّة إدارةٍ
     | أوسع: مَن لا يُنشئ من لوحة التطوّع لا يُنشئ من هنا. و24.2-أوّلًا يقول
     | صراحةً في حالة «بلا صلاحيّة»: «يرى اجتماعات نطاقه فقط **بلا إنشاء ولا
     | إدارة كود**».
     */
    Route::middleware('permission:meetings.create')
        ->post('/meetings', [MeetingsAdminController::class, 'store'])->name('meetings.store');

    Route::middleware('permission:meetings.manage,meetings.edit')->group(function () {
        Route::post('/meetings/{meeting}/end', [MeetingsAdminController::class, 'end'])->name('meetings.end');
        // إدارة الكود/الأسئلة · رفع المحضر والمرفقات · تثبيت بوست · إلغاء بسبب
        Route::post('/meetings/{meeting}/questions', [MeetingsAdminController::class, 'questions'])->name('meetings.questions');
        Route::post('/meetings/{meeting}/minutes', [MeetingsAdminController::class, 'minutes'])->name('meetings.minutes');
        Route::post('/meetings/{meeting}/pin', [MeetingsAdminController::class, 'pin'])->name('meetings.pin');
        Route::post('/meetings/{meeting}/cancel', [MeetingsAdminController::class, 'cancel'])->name('meetings.cancel');
    });

    // منح الحضور الاستثنائيّ صلاحيّة الحضور لا صلاحيّة الاجتماع
    Route::middleware('permission:meeting_attendance.manage,meeting_attendance.edit')
        ->post('/meetings/{meeting}/grant', [MeetingsAdminController::class, 'grant'])->name('meetings.grant');

    Route::middleware('permission:meeting_attendance.export,meetings.export')
        ->get('/meetings-attendance/export', [MeetingsAdminController::class, 'export'])->name('meetings.export');

    Route::middleware('permission:meetings.manage')->group(function () {
        Route::post('/meetings-settings', [MeetingsAdminController::class, 'saveSettings'])->name('meetings.settings');
        Route::post('/meetings-settings/reset', [MeetingsAdminController::class, 'resetSettings'])->name('meetings.settings.reset');
    });
});

/*
| ⭐ تنزيل التقرير الكبير برابط موقَّع محدود المدّة (24.3-خامسًا).
|
| خارج مجموعة `auth` عمدًا: مستقبِل التقرير قد يكون بريدًا صريحًا لا حسابًا،
| والتقرير أُرسِل له عن قصد. فالصلاحيّة هنا **هي التوقيع** — رمزٌ لا يُخمَّن
| ومدّةٌ من الإعدادات وحارسٌ في الكنترولر يتحقّق من السطر في القاعدة.
| 🔒 والتقرير الماليّ لا يفتحه إلّا مالك المنصّة داخلًا بحسابه — والحارس هناك.
*/
Route::middleware('signed')
    ->get('/reports/download/{token}', [ReportDownloadController::class, 'show'])
    ->name('reports.download');
