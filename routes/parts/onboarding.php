<?php

use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Onboarding\PlacementTestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «onboarding» — رحلة التسجيل بالترتيب المنصوص (2.5)
|--------------------------------------------------------------------------
| أ) شاشة «هل دعاك شخص ما؟» (2.5-أ) — للزائر، وتُتخطّى تلقائيًّا لمن دخل برابط دعوة.
| د) بعد التسجيل بالترتيب: تعليمات ⟵ اختبار تمهيديّ ⟵ تحت المراجعة ⟵ تمّ القبول.
|
| الحراسة:
|  · شاشة الدعوة `guest` لأنّها **قبل** إنشاء الحساب أصلًا — ولا مستخدم بعد.
|  · باقي الشاشات `auth` + `permission:user_profile.view`، وهي صلاحيّة يملكها
|    دور «تحت المراجعة» بنطاق SELF (12.2.1) — فالمستخدم يمرّ برحلته هو ولا يُقفَل
|    خارجها، ولا نفتح مسارًا بلا صلاحيّة.
|  · وترتيب الخطوات نفسه محروس في المتحكّم بـ`OnboardingJourney`: من سبق خطوةً
|    أو تخلّف عنها يُردّ لمكانه الصحيح بدل أن يرى شاشةً ليست دوره.
*/

// ------------------------------------------------------ أ) شاشة الدعوة (2.5-أ)
Route::middleware('guest')->group(function () {
    Route::get('/welcome', [OnboardingController::class, 'referral'])->name('onboarding.referral');
    Route::post('/welcome', [OnboardingController::class, 'applyReferral'])->name('onboarding.referral.apply');
    Route::post('/welcome/skip', [OnboardingController::class, 'skipReferral'])->name('onboarding.referral.skip');
});

// -------------------------------------------- د) رحلة ما بعد التسجيل (2.5-د)
Route::middleware(['auth', 'permission:user_profile.view'])->group(function () {

    // د-1) صفحة «تعليمات» وزرّ الموافقة
    Route::get('/onboarding/instructions', [OnboardingController::class, 'instructions'])
        ->name('onboarding.instructions');
    Route::post('/onboarding/instructions', [OnboardingController::class, 'agree'])
        ->name('onboarding.instructions.agree');

    // د-2) الاختبار التمهيديّ
    Route::get('/onboarding/placement', [PlacementTestController::class, 'show'])
        ->name('onboarding.placement');
    Route::post('/onboarding/placement', [PlacementTestController::class, 'submit'])
        ->name('onboarding.placement.submit');

    // د-4) صفحة «تمّ قبول حسابك» — تُرى مرّةً واحدة ثمّ يدخل المنصّة
    Route::get('/onboarding/accepted', [OnboardingController::class, 'accepted'])
        ->name('onboarding.accepted');
    Route::post('/onboarding/accepted', [OnboardingController::class, 'enter'])
        ->name('onboarding.accepted.enter');
});
