<?php

use App\Http\Controllers\Volunteer\MeetingController;
use App\Http\Controllers\Volunteer\ObjectionController;
use App\Http\Controllers\Volunteer\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «الاجتماعات والمعاملات والاعتراضات» (الدستور 13.4-ح · 13.4-ط · 13.4-ن-ب · 24.4)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، وفوقها تحقّقٌ من النطاق داخل
| المتحكّمات: الاجتماع لا يُفتَح لغير جمهوره، والمعاملة لا تُقرَأ لغير صاحبها.
| وقوائم الصلاحيّات هنا «أو»: يكفي واحدة منها لعبور الحارس.
*/

Route::middleware(['auth'])->prefix('volunteer')->group(function () {

    // ---------------------------------------------------------------- الاجتماعات (24.4-5)
    Route::middleware('permission:meetings.list,meetings.view')->group(function () {
        Route::get('/meetings', [MeetingController::class, 'index'])->name('volunteer.meetings');
    });

    Route::middleware('permission:meetings.view')->group(function () {
        Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('volunteer.meetings.show');
    });

    // إنشاء اجتماع — بصلاحيّة ونطاقها يحدّد الجمهور (قسم/فرعيّ/الكلّ)
    Route::middleware('permission:meetings.create')->group(function () {
        Route::post('/meetings', [MeetingController::class, 'store'])->name('volunteer.meetings.store');
    });

    // الإنهاء وإضافة الأسئلة/الـOTP: لصاحب الاجتماع أو أيّ أبلاين فوقه حتى السقف
    Route::middleware('permission:meetings.manage,meetings.edit,meetings.create')->group(function () {
        Route::post('/meetings/{meeting}/end', [MeetingController::class, 'end'])->name('volunteer.meetings.end');
        Route::post('/meetings/{meeting}/questions', [MeetingController::class, 'questions'])->name('volunteer.meetings.questions');
        Route::post('/meetings/posts/{post}/pin', [MeetingController::class, 'pin'])->name('volunteer.meetings.posts.pin');
    });

    // ⭐ توليد مهمّة «تنفيذ» من بند المحضر (23-0.3) — صلاحيّة إنشاء المهامّ
    // نفسها، وفوقها تحقّقُ إدارة الاجتماع داخل المتحكّم.
    Route::middleware('permission:tasks.create')->group(function () {
        Route::post('/meetings/{meeting}/minutes/tasks', [MeetingController::class, 'storeMinutesTask'])
            ->name('volunteer.meetings.minutes.tasks');
    });

    // تسجيل الحضور والاعتذار المسبق — فعلٌ شخصيّ داخل نطاق الاجتماع
    Route::middleware('permission:meeting_attendance.create,meetings.view')->group(function () {
        Route::post('/meetings/{meeting}/register', [MeetingController::class, 'register'])->name('volunteer.meetings.register');
        Route::post('/meetings/{meeting}/excuse', [MeetingController::class, 'excuse'])->name('volunteer.meetings.excuse');
    });

    // النقاش: بوستات وكومنتات وتصويت
    Route::middleware('permission:meeting_posts.create,meetings.view')->group(function () {
        Route::post('/meetings/{meeting}/posts', [MeetingController::class, 'storePost'])->name('volunteer.meetings.posts');
        Route::post('/meetings/posts/{post}/vote', [MeetingController::class, 'vote'])->name('volunteer.meetings.posts.vote');
    });

    // ---------------------------------------------------------------- حضوري والمحاضر (24.4-5)
    Route::middleware('permission:meeting_attendance.view,meeting_minutes.view,meetings.view')->group(function () {
        Route::get('/attendance', [MeetingController::class, 'attendance'])->name('volunteer.attendance');
        // المرفق المقيَّد يظهر بقفل وزرّ [اطلب وصولًا] — ولا يُخفى (24.4 · 4030)
        Route::post('/attendance/{meeting}/request-access', [MeetingController::class, 'requestAccess'])->name('volunteer.attendance.request');
    });

    // ---------------------------------------------------------------- معاملاتي (24.4-6)
    Route::middleware('permission:rep_transactions.list,rep_transactions.view,vxp_transactions.list')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index'])->name('volunteer.transactions');
    });

    Route::middleware('permission:rep_transactions.export,vxp_transactions.export,rep_transactions.list')->group(function () {
        Route::get('/transactions/export', [TransactionController::class, 'export'])->name('volunteer.transactions.export');
    });

    // ---------------------------------------------------------------- اعتراضاتي (24.4-6)
    Route::middleware('permission:objections.view,objections.list')->group(function () {
        Route::get('/objections', [ObjectionController::class, 'index'])->name('volunteer.objections');
        Route::post('/objections/{objection}/messages', [ObjectionController::class, 'addMessage'])->name('volunteer.objections.messages');
    });

    Route::middleware('permission:objections.create')->group(function () {
        Route::post('/objections', [ObjectionController::class, 'store'])->name('volunteer.objections.store');
    });
});
