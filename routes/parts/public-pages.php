<?php

use App\Http\Controllers\PublicPagesController;
use Illuminate\Support\Facades\Route;

/*
| صفحات لم يملكها مجال بعينه: صفحة «تطوّع معنا» التعريفيّة (13.4-أ)،
| وموافقة التتبّع (21.3-د)، ووصلة إشعارات التطوّع في سايد بار اللوحة.
*/

Route::middleware('auth')->group(function () {
    Route::get('/volunteering', [PublicPagesController::class, 'volunteering'])->name('volunteering.landing');

    // إشعارات التطوّع = مركز الإشعارات على تاب التطوّع (2.8) — مصدر واحد بلا ازدواج
    Route::get('/volunteer/notifications', [PublicPagesController::class, 'volunteerNotifications'])
        ->name('volunteer.notifications');
});

// موافقة التتبّع — شرط لازم قبل تشغيل أيّ بكسل (21.3-د)
Route::post('/consent/tracking', [PublicPagesController::class, 'storeConsent'])->name('consent.tracking');
