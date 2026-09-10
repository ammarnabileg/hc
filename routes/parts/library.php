<?php

use App\Http\Controllers\Admin\CvTemplateAdminController;
use App\Http\Controllers\Trainee\AttestationController;
use App\Http\Controllers\Trainee\CvController;
use App\Http\Controllers\Trainee\LibraryController;
use App\Http\Controllers\Trainee\ReaderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «مكتبتي والقارئ المحميّ وخبراتي» (الدستور 20 · 9 · 9.1 · 24.5)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، والقارئ فوقها تحقّقٌ صريح
| من الملكيّة في `library_entitlements` — فالرابط لا يفتح لغير المالك (20.3).
*/

Route::middleware('auth')->group(function () {

    // ---------------------------------------------------------------- مكتبتي (20)
    Route::middleware('permission:my_library.list')->group(function () {
        Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    });

    Route::middleware('permission:my_library.view')->group(function () {
        // تفاصيل العنصر في بوب-أب: معاينة + الفاتورة/الإيصال (2.15-أ-6 — لا صفحة جديدة)
        Route::get('/library/item/{entitlement}', [LibraryController::class, 'item'])->name('library.item');
        // «أوصِ بهذا» ⟵ رابط ريفيرال بعمولة 19.3 (20.4)
        Route::post('/library/item/{entitlement}/recommend', [LibraryController::class, 'recommend'])->name('library.recommend');
    });

    // ---------------------------------------------------------------- القارئ المحميّ (20.3)
    Route::middleware('permission:flip_reader.view,my_library.view')->group(function () {
        Route::get('/library/read/{product}', [ReaderController::class, 'read'])->name('library.read');
        // الصفحة تُخدَم صورةً مربوطةً بالجلسة — بلا تحميل وبلا رابط ملفّ مباشر
        Route::get('/library/read/{product}/page/{page}', [ReaderController::class, 'page'])
            ->whereNumber('page')->name('library.page');
        Route::get('/library/read/{product}/thumb/{page}', [ReaderController::class, 'thumb'])
            ->whereNumber('page')->name('library.thumb');
        Route::post('/library/read/{product}/progress', [ReaderController::class, 'progress'])->name('library.progress');
    });

    // ---------------------------------------------------------------- خبراتي ← السيرة الذاتيّة (9)
    Route::middleware('permission:user_cv.view')->group(function () {
        Route::get('/cv', [CvController::class, 'index'])->name('cv.index');
        Route::get('/cv/preview', [CvController::class, 'preview'])->name('cv.preview');
    });

    Route::middleware('permission:user_cv.edit')->group(function () {
        // حفظ تلقائيّ بين الخطوات مع «اتحفظ ✓» (2.15-د · 2.17-ب)
        Route::post('/cv/autosave', [CvController::class, 'autosave'])->name('cv.autosave');
        Route::post('/cv/template/{template}', [CvController::class, 'chooseTemplate'])->name('cv.template');
        Route::post('/cv/pull/{source}', [CvController::class, 'togglePull'])->name('cv.pull');
    });

    Route::get('/cv/download', [CvController::class, 'download'])
        ->middleware('permission:user_cv.export')->name('cv.download');

    // ---------------------------------------------------------------- خبراتي ← الإفادة (9.1)
    Route::middleware('permission:user_attestation.view')->group(function () {
        Route::get('/attestations', [AttestationController::class, 'index'])->name('attestations.index');
        Route::post('/attestations', [AttestationController::class, 'store'])->name('attestations.store');
        // ⭐ الموافقة على النشر ومفتاح الإغلاق — على غرار الـCV (9.1)
        Route::post('/attestations/public', [AttestationController::class, 'togglePublic'])->name('attestations.public.toggle');
    });

    Route::get('/attestations/export', [AttestationController::class, 'export'])
        ->middleware('permission:user_attestation.export')->name('attestations.export');
});

/*
| ⭐ شاشة إدارة قوالب الـCV للأدمن (9 · 12.0): الأدمن يضيف قوالب
| ويحدّد تذاكر كلٍّ منها ويوقف ما لا يريد — بصلاحيّة على كلّ مسار (12.2.1).
*/
Route::middleware(['auth', 'admin.panel'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:cv_templates.list')
        ->get('/cv-templates', [CvTemplateAdminController::class, 'index'])->name('cv-templates.index');

    Route::middleware('permission:cv_templates.create')
        ->post('/cv-templates', [CvTemplateAdminController::class, 'store'])->name('cv-templates.store');

    Route::middleware('permission:cv_templates.edit')
        ->put('/cv-templates/{template}', [CvTemplateAdminController::class, 'update'])->name('cv-templates.update');

    // ⭐ المحرّر المرئيّ (Drag-drop) — الطبقة الزخرفيّة (12.7-ب)، بنفس صلاحيّة
    // تعديل القالب على كلّ مسار: شاشة التحرير (GET · المرحلة 2/2) · إطار
    // المعاينة الحيّة خلف الكانفس (GET · المرحلة 2/2) · الحفظ (PUT · المرحلة 1/2)
    Route::middleware('permission:cv_templates.edit')->group(function () {
        Route::get('/cv-templates/{template}/decor', [CvTemplateAdminController::class, 'decor'])->name('cv-templates.decor.edit');
        Route::get('/cv-templates/{template}/decor/preview', [CvTemplateAdminController::class, 'decorPreview'])->name('cv-templates.decor.preview');
        Route::put('/cv-templates/{template}/decor', [CvTemplateAdminController::class, 'updateDecor'])->name('cv-templates.decor.update');
    });

    Route::middleware('permission:cv_templates.delete')
        ->delete('/cv-templates/{template}', [CvTemplateAdminController::class, 'destroy'])->name('cv-templates.destroy');

    Route::middleware('permission:cv_templates.export')
        ->get('/cv-templates/{template}/download', [CvTemplateAdminController::class, 'download'])->name('cv-templates.download');
});

/*
| صفحاتٌ بلا تسجيل:
|  - قالب CV مجّانيّ واحد كباب دخول، والتحميل يطلب إنشاء حساب (21.2-ج).
|  - صفحات عيّنة المنتج المحميّ (Teaser) ثمّ بلوك [شراء] (20.3).
|  - رابط الإفادة العامّ — مجّانيّ بلا تذاكر (9.1).
*/
Route::get('/cv-free', [CvController::class, 'free'])->name('cv.free');
Route::get('/cv-free/preview', [CvController::class, 'freePreview'])->name('cv.free.preview');
Route::post('/cv-free/autosave', [CvController::class, 'freeAutosave'])->name('cv.free.autosave');

Route::get('/library/teaser/{product}', [ReaderController::class, 'teaser'])->name('library.teaser');
Route::get('/library/teaser/{product}/page/{page}', [ReaderController::class, 'teaserPage'])
    ->whereNumber('page')->name('library.teaser.page');

/*
| رابط الإفادة العامّ — **لمن وافق وحده**، وبـSlug عشوائيّ لا كود متسلسل
| فلا يُعدّ الزائرُ الأكوادَ ليقرأ إفادات مَن لم يوافقوا (9.1).
*/
Route::get('/attestation/{code}', [AttestationController::class, 'public'])
    ->where('code', '[A-Za-z0-9]{6,32}')
    ->name('attestations.public');
