<?php

use App\Http\Controllers\Volunteer\ArbitrationController;
use App\Http\Controllers\Volunteer\ContributionController;
use App\Http\Controllers\Volunteer\EscalationController;
use App\Http\Controllers\Volunteer\ObjectionController;
use App\Http\Controllers\Volunteer\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «دورة العمل»: المساهمات · المراجعة · التصعيد · التحكيم (23 · 24.4)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم
| يُخفى من الواجهة أصلًا ولا يُعطَّل (2.15-أ-7).
*/

Route::middleware('auth')->prefix('volunteer')->name('volunteer.')->group(function () {

    // ------------------------------------------------------------- مساهماتي (24.4)
    Route::middleware('permission:contributions.list')->group(function () {
        Route::get('/contributions', [ContributionController::class, 'index'])->name('contributions');
    });

    Route::middleware('permission:contributions.view')->group(function () {
        // التفاصيل في بوب-أب لا صفحة جديدة (2.15-أ-6) — وهذا المسار يخدمه
        Route::get('/contributions/{contribution}', [ContributionController::class, 'show'])
            ->whereNumber('contribution')->name('contributions.show');
    });

    Route::middleware('permission:contributions.create')->group(function () {
        // معاينة قبل الإرسال: مهامّه المفتوحة · مسلَّماته · مساهماته · رصيدي بعد الخصم
        Route::get('/tasks/{task}/contributions/preview', [ContributionController::class, 'preview'])
            ->whereNumber('task')->name('contributions.preview');
        Route::post('/tasks/{task}/contributions', [ContributionController::class, 'store'])
            ->whereNumber('task')->name('contributions.store');
    });

    Route::middleware('permission:contributions.edit')->group(function () {
        Route::post('/contributions/{contribution}/respond', [ContributionController::class, 'respond'])
            ->whereNumber('contribution')->name('contributions.respond');
        Route::post('/contributions/{contribution}/deliver', [ContributionController::class, 'deliver'])
            ->whereNumber('contribution')->name('contributions.deliver');
    });

    Route::middleware('permission:contributions.delete')->group(function () {
        // طلب سحب مساهم ⟵ الحالة 6 على محرّك التصعيد (بلا أثر على درجة أيّ طرف)
        Route::post('/contributions/{contribution}/withdraw', [ContributionController::class, 'withdraw'])
            ->whereNumber('contribution')->name('contributions.withdraw');
    });

    Route::middleware('permission:checkpoints.edit')->group(function () {
        Route::post('/checkpoints/{checkpoint}/respond', [ContributionController::class, 'checkpoint'])
            ->whereNumber('checkpoint')->name('contributions.checkpoint');
    });

    // ------------------------------------------------------------- بانتظار مراجعتي (24.4)
    Route::middleware('permission:tasks.approve,contributions.approve')->group(function () {
        Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews');
        Route::get('/reviews/batch/{task}', [ReviewController::class, 'batch'])
            ->whereNumber('task')->name('reviews.batch');
    });

    Route::middleware('permission:tasks.approve')->group(function () {
        Route::post('/reviews/task/{task}/approve', [ReviewController::class, 'approveTask'])
            ->whereNumber('task')->name('reviews.task.approve');
    });

    Route::middleware('permission:tasks.reject')->group(function () {
        Route::post('/reviews/task/{task}/return', [ReviewController::class, 'returnTask'])
            ->whereNumber('task')->name('reviews.task.return');
    });

    Route::middleware('permission:contributions.approve')->group(function () {
        Route::post('/reviews/contribution/{contribution}/approve', [ReviewController::class, 'approveContribution'])
            ->whereNumber('contribution')->name('reviews.contribution.approve');
    });

    Route::middleware('permission:contributions.reject')->group(function () {
        Route::post('/reviews/contribution/{contribution}/return', [ReviewController::class, 'returnContribution'])
            ->whereNumber('contribution')->name('reviews.contribution.return');
    });

    // شاشة الدفعة: موافقة جماعيّة · تعديل مباشر · حذف بند يرجع مسودّة
    Route::middleware('permission:subtasks.approve')->group(function () {
        Route::post('/reviews/batch/{task}/approve', [ReviewController::class, 'approveBatch'])
            ->whereNumber('task')->name('reviews.batch.approve');
    });

    Route::middleware('permission:subtasks.edit')->group(function () {
        Route::post('/reviews/batch/item/{item}', [ReviewController::class, 'editBatchItem'])
            ->whereNumber('item')->name('reviews.batch.edit');
    });

    Route::middleware('permission:subtasks.delete')->group(function () {
        Route::delete('/reviews/batch/item/{item}', [ReviewController::class, 'removeBatchItem'])
            ->whereNumber('item')->name('reviews.batch.remove');
    });

    // ------------------------------------------------------------- يحتاج قرارك (24.4 · 23 القسم 5)
    Route::middleware('permission:escalations.list')->group(function () {
        Route::get('/escalations', [EscalationController::class, 'index'])->name('escalations');
    });

    Route::middleware('permission:escalations.view')->group(function () {
        Route::get('/escalations/{escalation}', [EscalationController::class, 'show'])
            ->whereNumber('escalation')->name('escalations.show');
    });

    // القرار: الموافقة والرفض كلاهما قرارٌ صريح داخل النافذة
    Route::middleware('permission:escalations.approve,escalations.reject')->group(function () {
        Route::post('/escalations/{escalation}/decide', [EscalationController::class, 'decide'])
            ->whereNumber('escalation')->name('escalations.decide');
    });

    // فتح حالة جديدة على المحرّك (تمديد · تعثّر · اعتذار · عدم تسليم · بلاغ رابط…)
    Route::middleware('permission:escalations.view')->group(function () {
        Route::post('/escalations', [EscalationController::class, 'store'])->name('escalations.store');
    });

    /*
    |---------------------------------------------------------------------------
    | ⬆️ الاعتراضات المصعَّدة إليّ (24.4-8) — الشقّ الثاني من تاب «التصعيدات»
    |---------------------------------------------------------------------------
    | «اعتراضاتي» تصنع الاعتراض ولا تبتّ فيه؛ وهنا يُبتّ. ولولا هذه الشاشة
    | لبقيت الدورة مقطوعة: يُرفَع الاعتراض ولا يجد مَن يقرّره.
    |
    | ⭐ والمسار **مستقلّ عن `escalations`** (23-6): حالته ومهلته وصاحب مكتبه
    | على جدول `objections` وحده — فلا يندرج ضمن الحالات التسع ولا يُسوّى آليًّا.
    |
    | والحصر الحقيقيّ على الخادم في `ObjectionDesk`: المفتاح يفتح الشاشة،
    | و**المكتب** وحده يفتح القرار.
    */
    Route::middleware('permission:objections.list')->group(function () {
        Route::get('/escalations/objections', [ObjectionController::class, 'desk'])
            ->name('escalations.objections');

        // ردّ المسؤول: نصّ + مرفق ⟵ «قيد المراجعة» بمهلة جديدة
        Route::post('/escalations/objections/{objection}/reply', [ObjectionController::class, 'reply'])
            ->whereNumber('objection')->name('escalations.objections.reply');
    });

    Route::middleware('permission:objections.assign')->group(function () {
        Route::post('/escalations/objections/{objection}/escalate', [ObjectionController::class, 'escalate'])
            ->whereNumber('objection')->name('escalations.objections.escalate');
    });

    // [للمخوَّل] القبول ⟵ معاملة عكسيّة ظاهرة، والأصل لا يُمَسّ أبدًا
    Route::middleware('permission:objections.approve')->group(function () {
        Route::post('/escalations/objections/{objection}/accept', [ObjectionController::class, 'accept'])
            ->whereNumber('objection')->name('escalations.objections.accept');
    });

    Route::middleware('permission:objections.reject')->group(function () {
        Route::post('/escalations/objections/{objection}/reject', [ObjectionController::class, 'reject'])
            ->whereNumber('objection')->name('escalations.objections.reject');
    });

    // ------------------------------------------------------------- التحكيمات (24.4)
    Route::middleware('permission:arbitration.list')->group(function () {
        Route::get('/arbitrations', [ArbitrationController::class, 'index'])->name('arbitrations');
    });

    Route::middleware('permission:arbitration.view')->group(function () {
        Route::get('/arbitrations/{arbitration}', [ArbitrationController::class, 'show'])
            ->whereNumber('arbitration')->name('arbitrations.show');
        // تواصل بلا كشف الرقم — ويُكشَف فقط لمن بينه وبين الطرف علاقة أبلاين/داونلاين
        Route::get('/arbitrations/{arbitration}/contact/{party}', [ArbitrationController::class, 'contact'])
            ->whereNumber(['arbitration', 'party'])->name('arbitrations.contact');
    });

    Route::middleware('permission:arbitration.create')->group(function () {
        Route::post('/tasks/{task}/arbitrations', [ArbitrationController::class, 'store'])
            ->whereNumber('task')->name('arbitrations.store');
        Route::post('/arbitrations/{arbitration}/messages', [ArbitrationController::class, 'message'])
            ->whereNumber('arbitration')->name('arbitrations.message');
    });

    Route::middleware('permission:arbitration.manage')->group(function () {
        Route::post('/arbitrations/{arbitration}/lock', [ArbitrationController::class, 'lock'])
            ->whereNumber('arbitration')->name('arbitrations.lock');
    });

    Route::middleware('permission:arbitration.approve')->group(function () {
        Route::post('/arbitrations/{arbitration}/decide', [ArbitrationController::class, 'decide'])
            ->whereNumber('arbitration')->name('arbitrations.decide');
    });
});
