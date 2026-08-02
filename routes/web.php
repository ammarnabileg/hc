<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| المسارات
|--------------------------------------------------------------------------
| هذا الملفّ مشترك ولا يُعدَّل بعد اليوم.
| كلّ مجال يضيف ملفّه الخاصّ في routes/parts/ ويُحمَّل تلقائيًّا — فلا تصادم.
*/

Route::get('/', fn () => view('welcome'))->name('home');

// المصادقة والتفعيل (2.5-د: التفعيل مجّانيّ باعتماد إداريّ)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/pending', [AuthController::class, 'pending'])->name('account.pending');

    // مسار مؤقّت: يُستبدَل بلوحة المتدرّب من routes/parts (المسارات في parts تُحمَّل بعده فتغلبه)
    Route::get('/dashboard', fn () => view('placeholder', ['title' => 'الرئيسيّة']))->name('dashboard');
});

foreach (glob(__DIR__.'/parts/*.php') ?: [] as $part) {
    require $part;
}
