<?php

use App\Http\Controllers\Volunteer\ConsentController;
use App\Http\Controllers\Volunteer\VolunteerProfileController;
use App\Services\Volunteer\Profile\ProfileTabInjector;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/*
|--------------------------------------------------------------------------
| مجال «بروفايل المتطوّع» (الدستور 13.4-م · 10.0)
|--------------------------------------------------------------------------
| بروفايل **واحد** بطبقتين: طبقة المتدرّب يملكها مجال الحساب، وطبقة التطوّع
| تُحقَن هنا في ستاكات الصفحة نفسها — بلا صفحة ثانية وبلا لمس ملفّ يملكه غيرنا.
|
| والصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، والحسّاس مخفيّ افتراضيًّا (13.4-م).
*/

// ⭐ حقن التابات الخمس ومحتواها في `profile.show` — الـComposer يعمل **قبل** رسم
//    الصفحة، فتصل الدفعات إلى الستاك في وقتها (والستاك يُرسَم مرّةً واحدة).
View::composer('profile.show', fn ($view) => app(ProfileTabInjector::class)->compose($view));

Route::middleware('auth')->group(function () {

    // ---------------------------------------------- تقرير الترقية (13.4-م-1)
    Route::middleware('permission:reports_volunteer.view')->group(function () {
        Route::get('/volunteer/profile/{code}/report', [VolunteerProfileController::class, 'report'])
            ->name('volunteer.profile.report');
    });

    // ---------------------------------------------- ملاحظات إداريّة سرّيّة (13.4-م-5)
    Route::middleware('permission:admin_notes.create')->group(function () {
        Route::post('/volunteer/profile/{code}/notes', [VolunteerProfileController::class, 'storeNote'])
            ->name('volunteer.profile.notes.store');
    });

    // ---------------------------------------------- موافقة إظهار التواصل (13.4-م-2)
    Route::middleware('permission:contact_consent.create')->group(function () {
        Route::post('/volunteer/profile/{code}/consent', [ConsentController::class, 'request'])
            ->name('volunteer.profile.consent.request');
    });

    Route::middleware('permission:contact_consent.approve')->group(function () {
        Route::post('/volunteer/consent/{consent}/approve', [ConsentController::class, 'approve'])
            ->name('volunteer.profile.consent.approve');
    });

    Route::middleware('permission:contact_consent.reject')->group(function () {
        Route::post('/volunteer/consent/{consent}/deny', [ConsentController::class, 'deny'])
            ->name('volunteer.profile.consent.deny');
    });

    // سجلّ الطلبات ومؤشّرات الثقة — شاشة الإدارة المركزيّة لطلبات الإظهار (13.4-ك)
    Route::middleware('permission:contact_consent.list')->group(function () {
        Route::get('/volunteer/consent/insights', [ConsentController::class, 'insights'])
            ->name('volunteer.profile.consent.insights');
    });
});
