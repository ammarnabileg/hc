<?php

use App\Http\Controllers\Volunteer\CapacityController;
use App\Http\Controllers\Volunteer\DepartmentController;
use App\Http\Controllers\Volunteer\HealthController;
use App\Http\Controllers\Volunteer\OrgChartController;
use App\Http\Controllers\Volunteer\VolunteerCardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «قسمي والهيكل والسعة والبطاقة» (الدستور 24.4-7 · 13.4-م · 13.4-ف · 13.4-ر · 13.4-ص)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، و**صحّة القسم والسعة مخفيّتان
| عمّن لا يملكهما** — لا معطَّلتين ولا رماديّتين (2.15-أ-7).
| وبطاقة المتطوّع صفحةٌ عامّة بلا تسجيل (13.4-ر).
*/

Route::middleware('auth')->group(function () {

    /*
    | ---------------------------------------------- الأعضاء والبوزشنز (24.4-7)
    | تاب «قسمي» لكلّ عضوٍ في القسم — ولذلك يُحرَس بـ`org_chart.view` لا بـ
    | `memberships.list`: الأخيرة أضيقُ نطاقٍ لها **TEAM** في المصفوفة (12.2.2)،
    | بينما سقف الكوردنيتور **SELF** (12.2.3-ب) — فحراستها به كانت تُغلق الشاشة
    | في وجه أصحابها أنفسهم، أو تُجبرنا على منحه نطاقًا يتجاوز سقفه.
    | و`org_chart.view` تقبل SELF نصًّا، والبيانات تبقى محصورة بقسم صاحبها.
    */
    Route::middleware('permission:org_chart.view')->group(function () {
        Route::get('/volunteer/department', [DepartmentController::class, 'index'])
            ->name('volunteer.department');

        // التفاصيل في بوب-أب لا صفحة جديدة (2.15-أ-6)
        Route::get('/volunteer/department/member/{membership}', [DepartmentController::class, 'member'])
            ->name('volunteer.department.member');

        // «اطلب إظهار الرقم» — الطلب على البيانات لا على الشخص (13.4-م-2)
        Route::post('/volunteer/department/member/{membership}/consent', [DepartmentController::class, 'requestConsent'])
            ->name('volunteer.department.consent');
    });

    // ⭐ وضع «غائب» والتفويض المؤقّت (23-6) — يضيفه المشرف/الدايركتور لا الشخص نفسه
    Route::middleware('permission:delegations.create')->group(function () {
        Route::post('/volunteer/department/member/{membership}/absence', [DepartmentController::class, 'absence'])
            ->name('volunteer.department.absence');
    });

    // ---------------------------------------------- الهيكل التنظيميّ (كانفاس — 13.4-م-3)
    Route::middleware('permission:org_chart.view')->group(function () {
        Route::get('/volunteer/org', [OrgChartController::class, 'index'])->name('volunteer.org');
        Route::get('/volunteer/org/node/{membership}', [OrgChartController::class, 'node'])->name('volunteer.org.node');
    });

    // ---------------------------------------------- صحّة القسم — لمسؤول القسم والأبلاين المخوَّل فقط
    Route::middleware('permission:team_health.view')->group(function () {
        Route::get('/volunteer/health', [HealthController::class, 'index'])->name('volunteer.health');
    });

    // ---------------------------------------------- السعة والأحمال — مؤشّرات لا موانع (13.4-ف)
    Route::middleware('permission:capacity.view')->group(function () {
        Route::get('/volunteer/capacity', [CapacityController::class, 'index'])->name('volunteer.capacity');
        Route::get('/volunteer/capacity/entity/{entity}', [CapacityController::class, 'entity'])
            ->name('volunteer.capacity.entity');
    });
});

// ---------------------------------------------- بطاقة المتطوّع الرقميّة — عامّة بلا تسجيل (13.4-ر)
Route::get('/card/{code}', [VolunteerCardController::class, 'show'])->name('card.show');
Route::get('/card/{code}/verify', [VolunteerCardController::class, 'verify'])->name('card.verify');
