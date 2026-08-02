<?php

use App\Http\Controllers\PublicPagesController;
use Illuminate\Support\Facades\Route;

/*
| صفحات لم يملكها مجال بعينه: صفحة «تطوّع معنا» التعريفيّة وشاشة حالة المتقدّم
| (13.4-أ · ب · ج · هـ)، وموافقة التتبّع (21.3-د)، ووصلة إشعارات التطوّع.
|
| ⭐ **الحارس هنا هو النطاق SELF بطبيعته**: كلّ مسار من هذه المسارات يعمل على
| **صاحب الطلب نفسه** لا على غيره — الميثاق ميثاقُه، والمرشّح مرشّحُه، وطلب
| التسكين يُفحَص بملكيّته صراحةً (403 لغير صاحبه). ولذلك لم نضع فوقها مفتاح
| `volunteer_page.view` — فمداه المسموح `ALL` وحده، ووضعُه كان يحجب الصفحةَ عن
| المتدرّب نفسه (نطاقه SELF) وهو الجمهور المقصود بها.
*/

Route::middleware('auth')->group(function () {
    Route::get('/volunteering', [PublicPagesController::class, 'volunteering'])->name('volunteering.landing');

    // ⭐ الميثاق يُوافَق عليه **قبل** بدء التأهيليّ (13.4-أ)
    Route::post('/volunteering/charter', [PublicPagesController::class, 'acceptCharter'])
        ->name('volunteering.charter');

    // ⭐ «الدخول للمرحلة التالية» ⟵ قائمة الانتظار المبدئيّة (13.4-ب)
    Route::post('/volunteering/next', [PublicPagesController::class, 'enterPipeline'])
        ->name('volunteering.next');

    // ⭐ «جدّد استعدادك» بمهلة تبريد (13.4-هـ)
    Route::post('/volunteering/renew', [PublicPagesController::class, 'renewReadiness'])
        ->name('volunteering.renew');

    // ردّ المرشّح على طلب تسكينه — حارسه الملكيّة لا صلاحيّة التوظيف (13.4-هـ)
    Route::post('/volunteering/placement/{placementRequest}/respond', [PublicPagesController::class, 'respondPlacement'])
        ->name('volunteering.placement.respond');

    // إشعارات التطوّع = مركز الإشعارات على تاب التطوّع (2.8) — مصدر واحد بلا ازدواج
    Route::get('/volunteer/notifications', [PublicPagesController::class, 'volunteerNotifications'])
        ->name('volunteer.notifications');
});

// موافقة التتبّع — شرط لازم قبل تشغيل أيّ بكسل (21.3-د)
Route::post('/consent/tracking', [PublicPagesController::class, 'storeConsent'])->name('consent.tracking');
