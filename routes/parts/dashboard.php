<?php

use App\Http\Controllers\Trainee\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| مجال «dashboard» — لوحة المتدرّب الرئيسيّة (الدستور 14 · 24.5).
| المستخدم يرى بياناته هو فقط، فالحارس صلاحيّة «enrollments.view» بنطاق SELF (12.2.1).
*/

Route::middleware(['auth', 'permission:enrollments.view'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
