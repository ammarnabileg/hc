<?php

use App\Http\Controllers\Trainee\CertificateController;
use App\Http\Controllers\Trainee\ExamController;
use App\Http\Controllers\Trainee\PublicVerificationController;
use App\Http\Middleware\EnsureExamWithinAvailability;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال الامتحانات والشهادات (4 · 8 · 12.5 · 21.1 · 24.5)
|--------------------------------------------------------------------------
| الامتحان شاشة تركيز بلا سايد بار، والشهادات من مصدرٍ واحد،
| وصفحة التحقّق **عامّة تمامًا: بلا تسجيل دخول ولا حساب** (21.2-ز).
*/

// ------------------------------------------------------------ عامّ بلا تسجيل
Route::get('/verify/{code?}', [PublicVerificationController::class, 'show'])
    ->where('code', '[A-Za-z0-9\-_]+')
    ->name('verify.certificate');

Route::post('/verify/report', [PublicVerificationController::class, 'report'])
    ->name('verify.certificate.report');

Route::get('/certificates/{code}/qr.png', [PublicVerificationController::class, 'qr'])
    ->where('code', '[A-Za-z0-9\-_]+')
    ->name('certificates.qr');

Route::get('/certificates/{code}/image.png', [CertificateController::class, 'image'])
    ->where('code', '[A-Za-z0-9\-_]+')
    ->name('certificates.image');

Route::get('/certificates/{code}/download', [CertificateController::class, 'download'])
    ->where('code', '[A-Za-z0-9\-_]+')
    ->name('certificates.download');

Route::get('/certificates/{code}/print', [CertificateController::class, 'pdf'])
    ->where('code', '[A-Za-z0-9\-_]+')
    ->name('certificates.print');

// ------------------------------------------------------------ المتدرّب
Route::middleware('auth')->group(function () {
    // شهاداتي — 24.5
    Route::get('/learning/certificates', [CertificateController::class, 'index'])
        ->middleware('permission:certificates.view')
        ->name('learning.certificates');

    /*
    | الامتحان — 4.2 · 24.5
    |
    | ⭐ **حاجز الإتاحة على المجموعة كلّها** (5): الامتحان النهائيّ جزءٌ من
    | التدريب، فما دام التدريب مقفولًا خارج فترته أو خارج ساعاته اليوميّة
    | **بتوقيت المستخدم المحلّيّ** فامتحانه مقفول — وإلّا صدرت شهادةٌ (8) من
    | بابٍ مغلق. والحاجز على **المجموعة** لا على مسارٍ بعينه كي يشمل أيّ مسارٍ
    | يُضاف هنا لاحقًا؛ و`EnsureExamWithinAvailability` هو من يقرّر الاستثناءات
    | (المحاولة الجارية · شاشة النتيجة · امتحان شهادة المسار) لا ملفّ المسارات.
    */
    Route::middleware(['permission:course_exam.view', EnsureExamWithinAvailability::class])->group(function () {
        Route::get('/exams/{exam}/start', [ExamController::class, 'start'])->name('exams.start');
        Route::post('/exams/{exam}/begin', [ExamController::class, 'begin'])->name('exams.begin');
        Route::get('/exams/{exam}/take', [ExamController::class, 'take'])->name('exams.take');
        Route::post('/exams/{exam}/answer', [ExamController::class, 'answer'])->name('exams.answer');
        Route::post('/exams/{exam}/submit', [ExamController::class, 'submit'])->name('exams.submit');
        Route::get('/exams/attempts/{attempt}/result', [ExamController::class, 'result'])->name('exams.result');
    });
});
