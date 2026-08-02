<?php

use App\Http\Controllers\Ui\CommandPaletteController;
use App\Http\Controllers\Ui\CvExtrasController;
use App\Http\Controllers\Ui\ExportImageController;
use App\Http\Controllers\Ui\ProfileExtrasController;
use App\Http\Controllers\Ui\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «الواجهة والبروفايل والاستوديو»
|--------------------------------------------------------------------------
| المراجع: الاستخراج كصورة (12.14-هـ) · مساحة العمل (2.15-د) ·
| البروفايل (10) · السيرة الذاتيّة (9).
|
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم
| **يُخفى من الواجهة** فوق المنع في المسار (2.15-أ-7).
*/

Route::middleware('auth')->group(function () {

    // ============================================ زرّ [استخراج كصورة] (12.14-هـ)
    // الحمولة موقَّعة في الرابط، والصلاحيّة `image_export.use` فوق التوقيع.
    Route::get('/export/image', ExportImageController::class)
        ->middleware('permission:image_export.use')
        ->name('export.image');

    // ============================================ مساحة العمل (2.15-د)
    Route::middleware('permission:user_profile.edit')->group(function () {
        // ⭐ التثبيت (Pin) — البديل المعتمَد عن «آخر ما زرت» المرفوض
        Route::post('/ui/pins', [WorkspaceController::class, 'togglePin'])->name('ui.pins.toggle');
        Route::post('/ui/pins/reorder', [WorkspaceController::class, 'reorderPins'])->name('ui.pins.reorder');

        // العروض المحفوظة
        Route::post('/ui/views', [WorkspaceController::class, 'storeView'])->name('ui.views.store');
        Route::delete('/ui/views/{view}', [WorkspaceController::class, 'destroyView'])->name('ui.views.destroy');

        // شاشة أوّل مرّة
        Route::post('/ui/first-run', [WorkspaceController::class, 'seenFirstRun'])->name('ui.first-run.seen');

        // ⭐ التراجع خلال 5 ثوانٍ بعد الأفعال القابلة للتراجع
        Route::post('/ui/undo/{token}', [WorkspaceController::class, 'undo'])->name('ui.undo');
    });

    Route::middleware('permission:user_profile.view')->group(function () {
        Route::get('/ui/first-run', [WorkspaceController::class, 'firstRunContent'])->name('ui.first-run.content');

        // ⭐ البحث الموحّد (Ctrl+K) — وما لا يملكه المستخدم لا يظهر في نتائجه
        Route::get('/ui/palette', CommandPaletteController::class)->name('ui.palette');
    });

    // ============================================ البروفايل (10)
    Route::middleware('permission:user_profile.edit')
        ->patch('/profile/bio', [ProfileExtrasController::class, 'updateBio'])->name('profile.bio');

    // ============================================ السيرة الذاتيّة (9)
    Route::middleware('permission:user_cv.edit')->group(function () {
        // رفع CV جاهز ⟵ استخراج البيانات ⟵ **تبديل ولا إضافة؟** ⟵ معاينة قبل الحفظ
        Route::post('/cv/import', [CvExtrasController::class, 'import'])->name('cv.import');
        Route::post('/cv/import/apply', [CvExtrasController::class, 'applyImport'])->name('cv.import.apply');
        // رابط CV عامّ قابل للمشاركة — زيّ صفحة الشهادة
        Route::post('/cv/public', [CvExtrasController::class, 'togglePublic'])->name('cv.public.toggle');
    });

    // ⭐ الاستخراج النهائيّ: PDF متوافق مع ATS — يُولَّد على الخادم بلا مكتبات
    Route::get('/cv/ats.pdf', [CvExtrasController::class, 'atsPdf'])
        ->middleware('permission:user_cv.export')->name('cv.ats');
});

/*
| الرابط العامّ للسيرة — بلا تسجيل دخول، ويُغلق بضغطة من صاحبه (9).
| ولا يحمل أيّ بيان حسّاس: الموبايل والبريد لا يخرجان منه إطلاقًا.
*/
Route::get('/cv/{slug}', [CvExtrasController::class, 'publicShow'])
    ->where('slug', '[A-Za-z0-9]{6,32}')
    ->name('cv.public');
