<?php

use App\Http\Controllers\Admin\CertificateAdminController;
use App\Http\Controllers\Admin\CourseAdminController;
use App\Http\Controllers\Admin\EmailTemplateAdminController;
use App\Http\Controllers\Admin\GuidanceController;
use App\Http\Controllers\Admin\LearningSettingsController;
use App\Http\Controllers\Admin\LessonAdminController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PathAdminController;
use App\Http\Controllers\Admin\PlacementTestAdminController;
use App\Http\Controllers\Admin\TemplateDesignerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «إدارة التدريب والشهادات والتوجيه والدعم» (12.4 · 12.5 · 12.6 · 24.1 · 24.3)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، وبفصلٍ متعمَّد بين القراءة والتعديل
| والحذف — فالعنصر الذي لا يملكه المستخدم يُخفى من الواجهة ويُمنَع في المسار معًا
| (2.15-أ-7). ولاحِظ أنّ **إلغاء الشهادة** صلاحيّةٌ مستقلّة عن **إصدارها** (24.1).
*/

Route::middleware(['auth', 'admin.panel'])->prefix('admin')->name('admin.')->group(function () {

    // ==================================================== أ) إدارة التدريب — المسارات (12.4-أ)
    Route::middleware('permission:paths.list,paths.view')->group(function () {
        Route::get('/paths', [PathAdminController::class, 'index'])->name('paths.index');
        // الضغط على «عدد التدريبات» ⟵ إدارة تدريبات المسار (بحث/سحب-ترتيب/إضافة/حذف)
        Route::get('/paths/{path}/courses', [PathAdminController::class, 'courses'])->name('paths.courses');
    });

    Route::middleware('permission:paths.create')->group(function () {
        Route::post('/paths', [PathAdminController::class, 'store'])->name('paths.store');
        // ⭐ تكرار/نسخ (Duplicate) المسار (12.4-هـ) — بنفس قياس تكرار التدريب بـ`courses.create`
        Route::post('/paths/{path}/duplicate', [PathAdminController::class, 'duplicate'])->name('paths.duplicate');
    });

    Route::middleware('permission:paths.edit')->group(function () {
        Route::put('/paths/{path}', [PathAdminController::class, 'update'])->name('paths.update');
        Route::post('/paths/reorder', [PathAdminController::class, 'reorder'])->name('paths.reorder');
        Route::post('/paths/{path}/courses/attach', [PathAdminController::class, 'attach'])->name('paths.courses.attach');
        Route::post('/paths/{path}/courses/reorder', [PathAdminController::class, 'reorderCourses'])->name('paths.courses.reorder');
        // فكّ الارتباط فقط — التدريب نفسه يبقى (12.4-أ)
        Route::delete('/paths/{path}/courses/{course}', [PathAdminController::class, 'detach'])->name('paths.courses.detach');
    });

    // ⭐ حذف المسار لا يحذف تدريباته (12.4-أ)
    Route::middleware('permission:paths.delete')
        ->delete('/paths/{path}', [PathAdminController::class, 'destroy'])->name('paths.destroy');

    // ==================================================== أ) إدارة التدريب — التدريبات (12.4-ب)
    Route::middleware('permission:courses.list,courses.view')->group(function () {
        Route::get('/courses', [CourseAdminController::class, 'index'])->name('courses.index');
        // الضغط على «عدد المسجّلين» ⟵ مَن هم
        Route::get('/courses/{course}/enrollees', [CourseAdminController::class, 'enrollees'])->name('courses.enrollees');
        Route::get('/courses/{course}/stats', [CourseAdminController::class, 'stats'])->name('courses.stats');
        // معاينة كطالب قبل النشر (12.4-هـ)
        Route::get('/courses/{course}/preview', [CourseAdminController::class, 'preview'])->name('courses.preview');
        Route::get('/courses/{course}/audit', [CourseAdminController::class, 'audit'])->name('courses.audit');
    });

    Route::middleware('permission:courses.create')->group(function () {
        Route::get('/courses/create', [CourseAdminController::class, 'create'])->name('courses.create');
        Route::post('/courses', [CourseAdminController::class, 'store'])->name('courses.store');
        Route::post('/courses/{course}/duplicate', [CourseAdminController::class, 'duplicate'])->name('courses.duplicate');
    });

    Route::middleware('permission:courses.edit')->group(function () {
        Route::get('/courses/{course}/edit', [CourseAdminController::class, 'edit'])->name('courses.edit');
        Route::put('/courses/{course}', [CourseAdminController::class, 'update'])->name('courses.update');
        // حفظ تلقائيّ كدرافت — «اتحفظ ✓» فلا يضيع عمل مهما حصل (12.4-ب · 2.17-ب)
        Route::post('/courses/{course}/autosave', [CourseAdminController::class, 'autosave'])->name('courses.autosave');
        // وتجاهل مسوّدة التحرير المعلّقة — بلا أيّ أثر على المنشور وحالته (12.4-ب)
        Route::delete('/courses/{course}/draft', [CourseAdminController::class, 'discardDraft'])->name('courses.draft.discard');
        // إجراءات جماعيّة تظهر عند الاختيار فقط (2.15-ب)
        Route::post('/courses/bulk', [CourseAdminController::class, 'bulk'])->name('courses.bulk');
    });

    Route::middleware('permission:courses.delete')
        ->delete('/courses/{course}', [CourseAdminController::class, 'destroy'])->name('courses.destroy');

    // ==================================================== أ) إدارة التدريب — السيكشنز والدروس (12.4-ج)
    Route::middleware('permission:sections.create,sections.edit')->group(function () {
        Route::post('/courses/{course}/sections', [LessonAdminController::class, 'storeSection'])->name('sections.store');
        Route::put('/sections/{section}', [LessonAdminController::class, 'updateSection'])->name('sections.update');
        Route::post('/courses/{course}/sections/reorder', [LessonAdminController::class, 'reorderSections'])->name('sections.reorder');
    });

    // ⭐ تكرار السيكشن (12.4-هـ) — بمفتاح `sections.create` المنصوص في 12.2.2،
    //    نفس قياس تكرار التدريب بـ`courses.create`. ولا مفتاح `duplicate` يُخترَع.
    Route::middleware('permission:sections.create')
        ->post('/sections/{section}/duplicate', [LessonAdminController::class, 'duplicateSection'])->name('sections.duplicate');

    Route::middleware('permission:sections.delete')
        ->delete('/sections/{section}', [LessonAdminController::class, 'destroySection'])->name('sections.destroy');

    Route::middleware('permission:lessons.view,lessons.list')
        ->get('/lessons/{lesson}', [LessonAdminController::class, 'show'])->name('lessons.show');

    Route::middleware('permission:lessons.create,lessons.edit')->group(function () {
        Route::post('/sections/{section}/lessons', [LessonAdminController::class, 'storeLesson'])->name('lessons.store');
        Route::put('/lessons/{lesson}', [LessonAdminController::class, 'updateLesson'])->name('lessons.update');
        // نقل الدرس بين السيكشنز (12.4-هـ)
        Route::post('/lessons/{lesson}/move', [LessonAdminController::class, 'moveLesson'])->name('lessons.move');
        Route::post('/lessons/{lesson}/duplicate', [LessonAdminController::class, 'duplicateLesson'])->name('lessons.duplicate');
    });

    Route::middleware('permission:lessons.delete')
        ->delete('/lessons/{lesson}', [LessonAdminController::class, 'destroyLesson'])->name('lessons.destroy');

    // أسئلة الدرس + «سؤال عامّ» يدخل بنك الامتحان النهائيّ (12.4-ج)
    Route::middleware('permission:lesson_quiz.create,lesson_quiz.edit')->group(function () {
        Route::post('/lessons/{lesson}/questions', [LessonAdminController::class, 'storeQuestion'])->name('questions.store');
        Route::put('/questions/{question}', [LessonAdminController::class, 'updateQuestion'])->name('questions.update');
        Route::post('/questions/{question}/general', [LessonAdminController::class, 'toggleGeneral'])->name('questions.general');
    });

    Route::middleware('permission:lesson_quiz.delete')
        ->delete('/questions/{question}', [LessonAdminController::class, 'destroyQuestion'])->name('questions.destroy');

    Route::middleware('permission:lesson_quiz.import,question_bank.import')
        ->post('/lessons/{lesson}/questions/import', [LessonAdminController::class, 'importQuestions'])->name('questions.import');

    // ==================================================== أ) إدارة التدريب — مكتبة الوسائط (12.4-د)
    Route::middleware('permission:media_library.list,media_library.view')->group(function () {
        Route::get('/media', [MediaController::class, 'index'])->name('media.index');
        // «اختَر من المكتبة» المستدعى من أيّ حقل رفع
        Route::get('/media/picker', [MediaController::class, 'picker'])->name('media.picker');
        Route::get('/media/{media}/usage', [MediaController::class, 'usage'])->name('media.usage');
    });

    Route::middleware('permission:media_library.create')
        ->post('/media', [MediaController::class, 'store'])->name('media.store');

    Route::middleware('permission:media_library.edit')
        ->put('/media/{media}', [MediaController::class, 'update'])->name('media.update');

    Route::middleware('permission:media_library.delete')
        ->delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    /*
     |==================================================== أ) إدارة التدريب — إعدادات التعلّم (24.4 · 1039)
     | الصلاحيّة `learning_ux.*` من 12.2.2 حرفيًّا — لا `learning_settings.manage`
     | التي يسمّيها نثر 24.4 وحدها بلا مقابل في المصفوفة (15 · 6077).
     */
    Route::middleware('permission:learning_ux.view,learning_ux.edit,learning_ux.manage')
        ->get('/learning-settings', [LearningSettingsController::class, 'index'])->name('learning-settings.index');

    Route::middleware('permission:learning_ux.edit,learning_ux.manage')->group(function () {
        Route::post('/learning-settings/field', [LearningSettingsController::class, 'save'])->name('learning-settings.field');
        Route::post('/learning-settings/reset', [LearningSettingsController::class, 'resetField'])->name('learning-settings.reset');
    });

    // ⭐ «إعادة الكلّ للافتراضيّ» لمجموعة كاملة — أثرٌ أوسع من حقلٍ واحد فيتطلّب `manage` (24.4)
    Route::middleware('permission:learning_ux.manage')
        ->post('/learning-settings/reset-group', [LearningSettingsController::class, 'resetGroup'])->name('learning-settings.reset-group');

    // ==================================================== ب) إدارة الشهادات (12.5) — نعرّف ⟵ نُعِدّ ⟵ نُصدِر ⟵ نتابع
    Route::middleware('permission:certificate_ledger.view,certificate_templates.view,accreditations.view')
        ->get('/certificates', [CertificateAdminController::class, 'index'])->name('certificates.index');

    // 1) الاعتمادات — واعتماد المنصّة لا يُحذَف (12.5-أ)
    Route::middleware('permission:accreditations.create')
        ->post('/certificates/accreditations', [CertificateAdminController::class, 'storeAccreditation'])->name('certificates.accreditations.store');
    Route::middleware('permission:accreditations.edit')
        ->put('/certificates/accreditations/{accreditation}', [CertificateAdminController::class, 'updateAccreditation'])->name('certificates.accreditations.update');
    Route::middleware('permission:accreditations.delete')
        ->delete('/certificates/accreditations/{accreditation}', [CertificateAdminController::class, 'destroyAccreditation'])->name('certificates.accreditations.destroy');

    // 2) الأنواع والقوالب
    Route::middleware('permission:certificate_templates.create')
        ->post('/certificates/types', [CertificateAdminController::class, 'storeType'])->name('certificates.types.store');
    Route::middleware('permission:certificate_templates.edit')->group(function () {
        Route::put('/certificates/types/{type}', [CertificateAdminController::class, 'updateType'])->name('certificates.types.update');
        Route::post('/certificates/types/languages', [CertificateAdminController::class, 'bulkLanguages'])->name('certificates.types.languages');
    });
    Route::middleware('permission:certificate_templates.delete')
        ->delete('/certificates/types/{type}', [CertificateAdminController::class, 'destroyType'])->name('certificates.types.destroy');

    // ⭐ مصمّم القوالب المرئيّ — JS خام بلا أيّ مكتبة سحب/رسم خارجيّة (12.5-ب)
    Route::middleware('permission:certificate_templates.view')->group(function () {
        Route::get('/certificates/types/{type}/designer', [TemplateDesignerController::class, 'edit'])->name('certificates.designer');
        Route::get('/certificates/templates/{template}/preview', [TemplateDesignerController::class, 'preview'])->name('certificates.designer.preview');
    });

    Route::middleware('permission:certificate_templates.edit')->group(function () {
        Route::post('/certificates/types/{type}/designer', [TemplateDesignerController::class, 'save'])->name('certificates.designer.save');
        Route::post('/certificates/templates/{template}/background', [TemplateDesignerController::class, 'background'])->name('certificates.designer.background');
        Route::post('/certificates/templates/{template}/reset', [TemplateDesignerController::class, 'reset'])->name('certificates.designer.reset');
        Route::post('/certificates/templates/{template}/duplicate', [TemplateDesignerController::class, 'duplicate'])->name('certificates.designer.duplicate');
    });

    // الربط بأعمدة قاعدة البيانات — صلاحيّة مستقلّة وحسّاسة (24.1)
    Route::middleware('permission:certificate_templates.manage')
        ->get('/certificates/bindings/columns', [TemplateDesignerController::class, 'columns'])->name('certificates.bindings.columns');

    // 3) الإصدار — فرديّ وجماعيّ مع تحقّق ومعاينة ومنع تكرار (12.5-ج)
    Route::middleware('permission:certificates.create')->group(function () {
        Route::post('/certificates/verify-codes', [CertificateAdminController::class, 'verifyCodes'])->name('certificates.verify-codes');
        Route::post('/certificates/preview', [CertificateAdminController::class, 'previewIssue'])->name('certificates.preview');
        Route::post('/certificates/issue', [CertificateAdminController::class, 'issue'])->name('certificates.issue');
        Route::post('/certificates/{certificate}/reissue', [CertificateAdminController::class, 'reissue'])->name('certificates.reissue');
    });

    // 4) السجلّ — والإلغاء صلاحيّة مستقلّة عن الإصدار (24.1)
    Route::middleware('permission:certificates.delete')
        ->post('/certificates/{certificate}/revoke', [CertificateAdminController::class, 'revoke'])->name('certificates.revoke');

    Route::middleware('permission:certificate_ledger.export,certificates.export')
        ->get('/certificates/export', [CertificateAdminController::class, 'export'])->name('certificates.export');

    /*
     | 5) صفحة التحقّق — **مراجعة بلاغ «شهادة مشبوهة»** (12.5-هـ · 24.1).
     |
     | والحارس `certificate_verification.edit` هو ما تعطيه المصفوفة (12.2.2) لهذه
     | الشاشة — ومورد `certificate_verification` كلّه بيد «مسؤول الشهادات»
     | (12.2.3-4). ولم نخترع مفتاحًا جديدًا: 24.1 يسمّي `certificate_verification.manage`
     | ولا وجود له في المصفوفة، والمصفوفة **مرجعٌ كامل** لا يُزاد عليه من شاشة.
     | أمّا **إلغاء الشهادة** فيتحقّق منه المتحكّم بـ`certificates.delete` وحدها،
     | فتبقى «صلاحيّة الإلغاء منفصلة عن الإصدار» كما ينصّ 24.1.
     */
    Route::middleware('permission:certificate_verification.edit')
        ->post('/certificates/reports/{report}/review', [CertificateAdminController::class, 'reviewReport'])
        ->name('certificates.reports.review');

    // ==================================================== ج) التوجيه والدعم (12.6 · 24.3)
    Route::middleware('permission:announcements.list,announcements.view')->group(function () {
        Route::get('/guidance', [GuidanceController::class, 'index'])->name('guidance.index');
        Route::get('/guidance/analytics/{announcement}', [GuidanceController::class, 'analytics'])->name('guidance.analytics');
        // ⭐ معاينة على الأجهزة (موبايل ⇄ ديسكتوب) قبل النشر (12.6-أ · 24.3)
        Route::get('/guidance/preview/{announcement}', [GuidanceController::class, 'preview'])->name('guidance.preview');
    });

    // تصدير التحليلات — سلطةُ تصديرٍ مستقلّة عن سلطة العرض (12.2.2)
    Route::middleware('permission:announcements.export')
        ->get('/guidance/analytics/{announcement}/export', [GuidanceController::class, 'exportAnalytics'])->name('guidance.analytics.export');

    Route::middleware('permission:announcements.create')
        ->post('/guidance/announcements', [GuidanceController::class, 'storeAnnouncement'])->name('guidance.announcements.store');
    Route::middleware('permission:announcements.edit')->group(function () {
        Route::put('/guidance/announcements/{announcement}', [GuidanceController::class, 'updateAnnouncement'])->name('guidance.announcements.update');
        Route::post('/guidance/announcements/{announcement}/duplicate', [GuidanceController::class, 'duplicateAnnouncement'])->name('guidance.announcements.duplicate');
    });
    Route::middleware('permission:announcements.archive,announcements.delete')
        ->post('/guidance/announcements/{announcement}/archive', [GuidanceController::class, 'archiveAnnouncement'])->name('guidance.announcements.archive');

    // الإشعارات: أنواع + إرسال يدويّ برابط أو بدون + تجميع المتشابهة (12.6-ب)
    Route::middleware('permission:announcements.view,announcements.list')
        ->get('/guidance/notifications', [GuidanceController::class, 'notifications'])->name('guidance.notifications');
    Route::middleware('permission:announcements.create')
        ->post('/guidance/notifications', [GuidanceController::class, 'sendNotification'])->name('guidance.notifications.send');
    Route::middleware('permission:notifications.manage')
        ->post('/guidance/notifications/matrix', [GuidanceController::class, 'saveNotificationMatrix'])->name('guidance.notifications.matrix.save');
    // ⭐ معاينة الجرس (24.3 سطر 5067) — العنوان والجسم كما سيصلان فعليًّا لنوعٍ بعينه
    Route::middleware('permission:announcements.view,announcements.list')
        ->get('/guidance/notifications/bell-preview', [GuidanceController::class, 'bellPreview'])->name('guidance.notifications.bell-preview');

    // ⭐ قوالب البريد (24.3 سطر 5067 · email_templates.* — صلاحيّةٌ كانت بلا شاشة إطلاقًا)
    Route::middleware('permission:email_templates.list,email_templates.view')
        ->get('/guidance/email-templates', [EmailTemplateAdminController::class, 'index'])->name('guidance.email-templates.index');
    Route::middleware('permission:email_templates.create')
        ->post('/guidance/email-templates', [EmailTemplateAdminController::class, 'store'])->name('guidance.email-templates.store');
    Route::middleware('permission:email_templates.edit')->group(function () {
        Route::put('/guidance/email-templates/{template}', [EmailTemplateAdminController::class, 'update'])->name('guidance.email-templates.update');
        // زرّ «نصّ القالب»/«مفعّل» داخل مصفوفة الإشعارات نفسها — لا يمرّ بالشاشة الكاملة
        Route::post('/guidance/notifications/matrix/{category}/template', [EmailTemplateAdminController::class, 'upsertForCategory'])->name('guidance.notifications.matrix.template');
    });
    Route::middleware('permission:email_templates.delete')
        ->delete('/guidance/email-templates/{template}', [EmailTemplateAdminController::class, 'destroy'])->name('guidance.email-templates.destroy');
    Route::middleware('permission:email_templates.archive')
        ->post('/guidance/email-templates/{template}/archive', [EmailTemplateAdminController::class, 'archive'])->name('guidance.email-templates.archive');
    Route::middleware('permission:email_templates.export')
        ->get('/guidance/email-templates/export', [EmailTemplateAdminController::class, 'export'])->name('guidance.email-templates.export');
    Route::middleware('permission:email_templates.import')
        ->post('/guidance/email-templates/import', [EmailTemplateAdminController::class, 'import'])->name('guidance.email-templates.import');

    // دليل المستخدم (12.6-ج)
    Route::middleware('permission:user_guide.list,user_guide.view')
        ->get('/guidance/help', [GuidanceController::class, 'help'])->name('guidance.help');
    Route::middleware('permission:user_guide.create')
        ->post('/guidance/help', [GuidanceController::class, 'storeArticle'])->name('guidance.help.store');
    Route::middleware('permission:user_guide.edit')
        ->put('/guidance/help/{article}', [GuidanceController::class, 'updateArticle'])->name('guidance.help.update');
    Route::middleware('permission:user_guide.delete')
        ->delete('/guidance/help/{article}', [GuidanceController::class, 'destroyArticle'])->name('guidance.help.destroy');

    // الشكاوى: طابور بالحالات + إسناد + ردّ داخليّ/خارجيّ + إغلاق بسبب (24.3)
    Route::middleware('permission:complaints.list,complaints.view')->group(function () {
        Route::get('/guidance/complaints', [GuidanceController::class, 'complaints'])->name('guidance.complaints');
        Route::get('/guidance/complaints/{complaint}', [GuidanceController::class, 'showComplaint'])->name('guidance.complaints.show');
    });
    Route::middleware('permission:complaints.assign')
        ->post('/guidance/complaints/{complaint}/assign', [GuidanceController::class, 'assignComplaint'])->name('guidance.complaints.assign');
    Route::middleware('permission:complaints.edit,complaints.manage')->group(function () {
        Route::post('/guidance/complaints/{complaint}/reply', [GuidanceController::class, 'replyComplaint'])->name('guidance.complaints.reply');
        Route::post('/guidance/complaints/{complaint}/close', [GuidanceController::class, 'closeComplaint'])->name('guidance.complaints.close');

        // ⭐ أسباب الشكوى: إضافة/تعديل/حذف من لوحة الأدمن (11) — لا دروب-داون فلترة فقط
        Route::get('/guidance/complaint-reasons', [GuidanceController::class, 'complaintReasons'])->name('guidance.complaint_reasons');
        Route::put('/guidance/complaint-reasons', [GuidanceController::class, 'updateComplaintReasons'])->name('guidance.complaint_reasons.update');
    });
    /*
    |============================================ بناء الاختبار التمهيديّ (2.5-د-2 · 12 · 24)
    | «**اختبار تمهيدي (Placement):** يُدار من الأدمن — الأسئلة ممكن تكون (فيديو
    | و/أو كود Embedded HTML من أي مكان و/أو نص و/أو صورة) … **مكافأة لكل سؤال**
    | بجانبه: **XP فقط أو تذاكر فقط أو الاثنين**» (2.5-د-2).
    |
    | ⚠️ كان `placement_test_questions` **جدولًا بلا شاشة**: تقرأ منه خدمةُ
    | `PlacementTest` وشاشةُ المتدرّب، ولا مسارَ ينشئ فيه سؤالًا — فيبقى فارغًا
    | فتُتخطّى خطوةُ التصفية المنصوصة صامتةً.
    |
    | وموارده `placement_test.*` من مصفوفة 12.2.2 — **لا `placements.*`** (تلك
    | تسكينُ المتطوّعين في الهيكل، 13.4-هـ، ومورد آخر تمامًا).
    */
    Route::middleware('permission:placement_test.manage,placement_test.edit,placement_test.create')
        ->get('/placement-test', [PlacementTestAdminController::class, 'index'])->name('placement-test.index');

    Route::middleware('permission:placement_test.create')
        ->post('/placement-test', [PlacementTestAdminController::class, 'store'])->name('placement-test.store');

    Route::middleware('permission:placement_test.edit')->group(function () {
        Route::put('/placement-test/{question}', [PlacementTestAdminController::class, 'update'])->name('placement-test.update');
        Route::post('/placement-test/{question}/toggle', [PlacementTestAdminController::class, 'toggle'])->name('placement-test.toggle');
        // «الترتيب (سحب)» (24) — والترتيب يُحفَظ في الخادم لا في المتصفّح
        Route::post('/placement-test/reorder', [PlacementTestAdminController::class, 'reorder'])->name('placement-test.reorder');
    });

    Route::middleware('permission:placement_test.delete')
        ->delete('/placement-test/{question}', [PlacementTestAdminController::class, 'destroy'])->name('placement-test.destroy');

    // «تصدير إجابات المتقدّمين ونتائجهم» — نصّ `placement_test.export` في 12.2.2
    Route::middleware('permission:placement_test.export')
        ->get('/placement-test/export', [PlacementTestAdminController::class, 'export'])->name('placement-test.export');
});
