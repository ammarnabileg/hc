<?php

use App\Http\Controllers\Trainee\EventController;
use App\Http\Controllers\Trainee\ReferralController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال الفعاليّات والدعوات (13.3 · 7.6 · 21.1 · 24.5)
|--------------------------------------------------------------------------
| كلّ مسار محروس بصلاحيّته (12.2.1)، وما لا يملكه المستخدم يُخفى من الواجهة أصلًا.
*/

// صورة OG مرسومة SVG لكلّ رابط فعاليّة (21.1-أ) — عامّة ليقرأها من تُشارَك معه
Route::get('/events/{event:slug}/og.svg', [EventController::class, 'og'])->name('events.og');

// بوّابة رابط الدعوة لكلّ محتوى (Deep link) — تعمل للزائر وللمسجَّل (21.1-ج)
Route::get('/i/{code}', [ReferralController::class, 'invite'])->name('referral.invite');

Route::middleware(['auth', 'permission:events.view'])->group(function () {
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event:slug}/ticket', [EventController::class, 'ticket'])->name('events.ticket');
    Route::get('/events/{event:slug}/calendar.ics', [EventController::class, 'ics'])->name('events.ics');

    // ⭐ رمز تشيك-إن QR **ديناميكيّ** يخصّ صاحبه وحده (12.11) — يتجدّد كلّ 30ث
    // (24.3)، فلقطة شاشةٍ تُرسَل لغيره تموت قبل أن تصل، ولا يُقرأ إلّا خادميًّا.
    Route::get('/events/{event:slug}/checkin-qr.svg', [EventController::class, 'qr'])->name('events.qr');

    Route::post('/events/{event:slug}/register', [EventController::class, 'register'])->name('events.register');
    Route::post('/events/{event:slug}/checkin', [EventController::class, 'checkin'])->name('events.checkin');
});

// صفحة الدعوات للمستخدم نفسه (نطاق SELF) — والعمولة رقمٌ ماليّ فتُقبَل صلاحيّته كذلك
Route::middleware(['auth', 'permission:invitations_page.view,referrals.view'])->group(function () {
    Route::get('/referral', [ReferralController::class, 'index'])->name('referral.index');
});
