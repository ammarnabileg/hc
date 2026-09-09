<?php

use App\Http\Controllers\Volunteer\FileInviteController;
use App\Http\Controllers\Volunteer\GoalController;
use App\Http\Controllers\Volunteer\PerformanceController;
use App\Http\Controllers\Volunteer\ProjectController;
use App\Http\Controllers\Volunteer\WorkPackageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «المشاريع والأهداف والأداء» (الدستور 24.4 · 23 · 13.4-ن)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، واختيار المفتاح مقصود:
|
|  • الأهداف والحزم: عرضٌ تنفيذيّ لا شاشات بناء — ولذلك `*.list` / `*.view`.
|  • **إعلان تحقّق المعيار** لدايركتور الكيان ⟵ `wp_items.edit` (النطاق ENTITY وحده)،
|    و**اعتماده** لمشرف المسار ⟵ `milestones.edit` (النطاق TRACK/ALL) — فلا يعتمد أحدٌ إعلانَ نفسه.
|  • **الاعتراض على نسخة الاعتماد** لدايركتور الكيان ⟵ `wp_items.edit` كذلك.
|  • الأداء: كلّ صفحة بمفتاح موردها (VXP · Rep · الليدر بورد · التقييمات)،
|    والتقييم نفسه بـ`evaluations.create` لأنّه فعل لا عرض.
*/

Route::middleware('auth')->prefix('volunteer')->group(function () {

    // ------------------------------------------------------- الأهداف والمَعالِم
    Route::middleware('permission:goals.list,goals.view')->group(function () {
        Route::get('/goals', [GoalController::class, 'index'])->name('volunteer.goals');
    });

    /*
    | ⭐ شاشة إطلاق الهدف — المعاينة النهائيّة و«إرسال للتنفيذ» (23 — 1.5 · 1.6).
    | المفتاح `goals.approve` ونطاقه **ALL** وحده في المصفوفة — وهو حرفيًّا
    | مشرف عام التطوّع: «الوحيد الذي يوقف هدفًا معتمَدًا» وصاحب الضغطة.
    | وبالضغطة تبدأ **نافذة التفكيك** فيُختَم `breakdown_due_at` (23-3.9-١).
    */
    Route::middleware('permission:goals.approve')->group(function () {
        Route::get('/goals/launch', [GoalController::class, 'launch'])->name('volunteer.goals.launch');
        Route::post('/goals/{goal}/launch', [GoalController::class, 'send'])->name('volunteer.goals.launch.send');
    });

    /*
    | ============================================================================
    |  ⭐ رحلة بناء الهدف — المرحلة صفر (23 — 1.1 … 1.4)
    | ============================================================================
    |
    | الرحلة كلّها **غير مرئيّة للداونلاينز**، والصلاحيّة أوّل الحرّاس لا آخرهم:
    | كلّ مسار هنا يمرّ بمفتاحه، ثمّ يفحص الكنترولر **الطبقة والحيازة** على الخادم
    | (`BuildAccess`) — فالمفتاح يقول «هل تقدر مبدئيًّا؟»، والطبقة تقول «هل ده
    | هدفك أصلًا، وهل التحرير في يدك دلوقتي؟».
    |
    |  • 1.1 إنشاء الهدف وربطه بمسار ⟵ `goals.create` (نطاق ALL وحده = القمّة).
    |  • 1.2 المَعالِم ⟵ `milestones.create` · الحزم وربطها بالكيان ⟵ `work_packages.create`
    |    (كلاهما TRACK = مشرف عام المسار).
    |  • 1.3 ملء الحزم ⟵ `wp_items.create` (ENTITY = دايركتور الكيان)، والرفع
    |    للمراجعة ⟵ `wp_items.edit`.
    |  • 1.4 التعديل المباشر والتسعير و«رفع معاينة» ⟵ `milestones.edit` (TRACK/ALL).
    |  • الحذف ⟵ مفاتيح `*.delete` ونطاقها **ALL** وحده في المصفوفة: للقمّة.
    */

    // لوحة الرحلة — تُفلتَر بالطبقة لا بالمفتاح وحده، فالفارغ فارغٌ لمن لا دور له
    Route::middleware('permission:goals.list,goals.view')->group(function () {
        Route::get('/goals/build', [GoalController::class, 'build'])->name('volunteer.goals.build');
    });

    // 1.1 — إنشاء الهدف بمعيار تحقّق إلزاميّ، ثمّ ربطه بمسار أو أكثر
    Route::middleware('permission:goals.create')->group(function () {
        Route::get('/goals/build/new', [GoalController::class, 'create'])->name('volunteer.goals.build.create');
        Route::post('/goals/build', [GoalController::class, 'store'])->name('volunteer.goals.build.store');
        Route::post('/goals/build/{goal}/tracks', [GoalController::class, 'linkTracks'])
            ->name('volunteer.goals.build.tracks');
    });

    // 1.2 — التفكيك: مَعالِم داخل الهدف
    Route::middleware('permission:milestones.create')->group(function () {
        Route::get('/goals/build/{goal}/breakdown', [GoalController::class, 'breakdown'])
            ->name('volunteer.goals.build.breakdown');
        Route::post('/goals/build/{goal}/milestones', [GoalController::class, 'storeMilestone'])
            ->name('volunteer.goals.build.milestones');
    });

    // 1.2 — حزم العمل وربطها بالكيان نفسه (فرادى أو كلّ كيانات المسار بضغطة)
    Route::middleware('permission:work_packages.create')->group(function () {
        Route::post('/goals/build/milestones/{milestone}/packages', [GoalController::class, 'storePackages'])
            ->name('volunteer.goals.build.packages');

        /*
        | ⭐ **مسودّة ملفّ** أثناء البناء (23 — 1.2، سيناريو مشرف عام الملفّات).
        |
        | المسار **يُنشئ ولا يفتح**: الكيان يُولَد بحالة `draft` ودعواته صفوفٌ
        | بلا أثر، ولا يوجد مسارٌ ثانٍ لفتحه — التفعيل دالّةٌ لا يستدعيها إلّا
        | ضغطة «إرسال للتنفيذ» (1.6). ولو وُجد هنا مسار «افتح» لصار قول الدستور
        | «الفتح حصريًّا للقمّة **بصفر خطوة إضافيّة**» كلامًا بلا سند.
        |
        | و`FileDrafts::canCreate` تفحص فوق الصلاحيّة أن يكون **مسار الملفّات**
        | من مسارات الفاعل — فمشرف عام الأقسام لا يفتح ملفًّا.
        */
        Route::post('/goals/build/{goal}/file-drafts', [GoalController::class, 'storeFileDraft'])
            ->name('volunteer.goals.build.file_drafts');
    });

    // 1.3 — ملء الحزم: الدايركتور يضيف مهامّه بلا حدّ أقصى
    Route::middleware('permission:wp_items.create')->group(function () {
        Route::get('/goals/build/{goal}/fill', [WorkPackageController::class, 'fill'])
            ->name('volunteer.goals.build.fill');
        Route::post('/goals/build/packages/{workPackage}/tasks', [WorkPackageController::class, 'storeTask'])
            ->name('volunteer.goals.build.tasks');
    });

    Route::middleware('permission:wp_items.edit')->group(function () {
        Route::post('/goals/build/packages/{workPackage}/submit', [WorkPackageController::class, 'submitForReview'])
            ->name('volunteer.goals.build.submit');
    });

    // 1.4 — التجميع والتسعير والقفل الطبقيّ
    Route::middleware('permission:milestones.edit')->group(function () {
        Route::get('/goals/build/{goal}/aggregate', [GoalController::class, 'aggregate'])
            ->name('volunteer.goals.build.aggregate');
        Route::post('/goals/build/{goal}/field', [GoalController::class, 'saveField'])
            ->name('volunteer.goals.build.field');
        Route::get('/goals/build/{goal}/revisions', [GoalController::class, 'fieldRevisions'])
            ->name('volunteer.goals.build.revisions');
        Route::post('/goals/build/{goal}/preview', [GoalController::class, 'raisePreview'])
            ->name('volunteer.goals.build.preview');
        Route::post('/goals/build/{goal}/tasks', [GoalController::class, 'storeAggregateTask'])
            ->name('volunteer.goals.build.aggregate.task');
    });

    // الحذف في المعاينة — للقمّة وحدها، وبتأكيد «لا» فيه أوضح وأكبر من «نعم»
    Route::middleware('permission:milestones.delete')->delete('/goals/build/milestones/{milestone}', [GoalController::class, 'destroyMilestone'])
        ->name('volunteer.goals.build.milestones.destroy');

    Route::middleware('permission:work_packages.delete')->delete('/goals/build/packages/{workPackage}', [GoalController::class, 'destroyPackage'])
        ->name('volunteer.goals.build.packages.destroy');

    Route::middleware('permission:wp_items.delete')->delete('/goals/build/tasks/{task}', [GoalController::class, 'destroyTask'])
        ->name('volunteer.goals.build.tasks.destroy');

    // إعلان تحقّق المعيار بدليل مرفق — لدايركتور الكيان
    Route::middleware('permission:wp_items.edit')->group(function () {
        Route::post('/goals/milestones/{milestone}/declare', [GoalController::class, 'declare'])
            ->name('volunteer.goals.declare');

        // اعتراض على نسخة الاعتماد خلال 24 ساعة — والسكوت قبول
        Route::post('/packages/{workPackage}/object', [WorkPackageController::class, 'object'])
            ->name('volunteer.packages.object');

        // بوب-أب توزيع VXP بقيديه الآليّين
        Route::post('/packages/tasks/{task}/vxp', [WorkPackageController::class, 'distributeVxp'])
            ->name('volunteer.packages.vxp');
    });

    // اعتماد الإعلان خلال نافذة 24 ساعة — لمشرف المسار
    Route::middleware('permission:milestones.edit')->group(function () {
        Route::post('/goals/milestones/{milestone}/approve', [GoalController::class, 'approve'])
            ->name('volunteer.goals.approve');
    });

    // -------------------------------------------------- حزم العمل وبنودها
    Route::middleware('permission:work_packages.list,work_packages.view')->group(function () {
        Route::get('/packages', [WorkPackageController::class, 'index'])->name('volunteer.packages');
        Route::get('/packages/{workPackage}', [WorkPackageController::class, 'show'])->name('volunteer.packages.show');
    });

    // ------------------------------------------------ المشروع التشغيليّ للكيان
    Route::middleware('permission:operational_projects.view')->group(function () {
        Route::get('/project', [ProjectController::class, 'index'])->name('volunteer.project');
    });

    // الاعتماد الأوّل — مشرف المسار أو القمّة (23 — 1.8، هيدر السطر 5332)
    Route::post('/project/{project}/approve', [ProjectController::class, 'approve'])
        ->whereNumber('project')->middleware('permission:operational_projects.approve')->name('volunteer.project.approve');

    // ------------------------------------------- البنود المتكرّرة — نوبتي
    Route::middleware('permission:recurring_items.view')->group(function () {
        Route::get('/recurring', [ProjectController::class, 'shift'])->name('volunteer.recurring');
    });

    // ------------------------------------------------------------- الأداء
    Route::middleware('permission:vxp_transactions.view,leaderboards.view')->group(function () {
        Route::get('/performance/vxp', [PerformanceController::class, 'vxp'])->name('volunteer.performance.vxp');
    });

    Route::middleware('permission:rep_transactions.view')->group(function () {
        Route::get('/performance/rep', [PerformanceController::class, 'rep'])->name('volunteer.performance.rep');
        Route::post('/performance/rep/{transaction}/object', [PerformanceController::class, 'objectRep'])
            ->name('volunteer.performance.rep.object');
    });

    Route::middleware('permission:leaderboards.view')->group(function () {
        Route::get('/performance/champion', [PerformanceController::class, 'champion'])
            ->name('volunteer.performance.champion');
    });

    Route::middleware('permission:evaluations.view')->group(function () {
        Route::get('/performance/evaluations', [PerformanceController::class, 'evaluations'])
            ->name('volunteer.performance.evaluations');
    });

    Route::middleware('permission:evaluations.create')->group(function () {
        Route::post('/performance/evaluations', [PerformanceController::class, 'storeEvaluation'])
            ->name('volunteer.performance.evaluations.store');
    });

    /*
    | رابط دعوة ملفٍّ مبنيّ على البوزشن (23-0.2 · 8.1) — أيّ عضوٍ موثَّق يحمل
    | الرابط يقبله بنفسه؛ لا صلاحيّة إضافيّة هنا لأنّ الحرّاس الحقيقيّة
    | (الحرمان بعد بتر الاختياريّ · سقف المسار · عدم تكرار العضويّة) داخل
    | `FileDrafts::acceptInviteLink()` نفسها.
    */
    Route::get('/file-invites/{token}', [FileInviteController::class, 'show'])->name('volunteer.file-invites.show');
    Route::post('/file-invites/{token}', [FileInviteController::class, 'accept'])->name('volunteer.file-invites.accept');
});
