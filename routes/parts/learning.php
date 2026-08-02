<?php

use App\Http\Controllers\Trainee\CourseController;
use App\Http\Controllers\Trainee\LearningController;
use App\Http\Controllers\Trainee\LessonController;
use App\Http\Controllers\Trainee\PathController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال التعلّم — تدريباتي · صفحة التدريب · صفحة الدرس · المسارات (24.5)
|--------------------------------------------------------------------------
| الحارس «enrollments.view»: المتدرّب يرى تسجيلاته هو فقط بنطاق SELF (12.2.1)،
| ثمّ تتحقّق كلّ شاشة من ملكيّة التدريب بعينه قبل أن تعرض أيّ محتوى.
*/

Route::middleware(['auth', 'permission:enrollments.view'])
    ->prefix('learning')
    ->name('learning.')
    ->group(function () {
        Route::get('/courses', [LearningController::class, 'index'])->name('courses');

        Route::get('/paths', [PathController::class, 'index'])->name('paths');
        Route::get('/paths/{path:slug}', [PathController::class, 'show'])->name('path');

        Route::get('/courses/{course:slug}', [CourseController::class, 'show'])->name('course');
        Route::post('/courses/{course:slug}/report', [CourseController::class, 'report'])->name('course.report');

        Route::get('/courses/{course:slug}/lessons/{lesson}', [LessonController::class, 'show'])->name('lesson');
        Route::post('/courses/{course:slug}/lessons/{lesson}/complete', [LessonController::class, 'complete'])->name('lesson.complete');
        Route::post('/courses/{course:slug}/lessons/{lesson}/questions/{question}', [LessonController::class, 'answer'])->name('lesson.answer');
    });
