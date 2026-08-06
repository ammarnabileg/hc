<?php

use App\Http\Controllers\Api\V1\CertificateVerificationController;
use App\Http\Controllers\Api\V1\CoursesController;
use App\Http\Controllers\Api\V1\PingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| واجهة الـAPI الخارجيّة (12.15-أ) — للمواقع/الأنظمة الخارجيّة لا للوحة نفسها
|--------------------------------------------------------------------------
| المصادقة بمفتاح `Authorization: Bearer <prefix>.<secret>` عبر `api.key`
| (12.15-ج) — لا جلسة ولا CSRF. ثلاث نقاط نهاية حقيقيّة مبدئيًّا؛ والبقيّة
| تُضاف لاحقًا بلا كسر التوافق (كتالوجها في `ApiEndpointCatalog`).
*/

Route::prefix('api/v1')->name('api.v1.')->group(function () {
    // بلا Scope — يتطلّب مفتاحًا صالحًا فقط، وأفضل نقطة لاختبار المصادقة كاملةً
    Route::get('/ping', PingController::class)
        ->middleware('api.key')->name('ping');

    Route::get('/courses', CoursesController::class)
        ->middleware('api.key:read:courses')->name('courses');

    Route::get('/certificates/{code}/verify', CertificateVerificationController::class)
        ->middleware('api.key:read:certificates')->name('certificates.verify');
});
