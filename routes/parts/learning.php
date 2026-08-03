<?php

use App\Http\Controllers\Trainee\CourseController;
use App\Http\Controllers\Trainee\CourseNoteController;
use App\Http\Controllers\Trainee\LearningController;
use App\Http\Controllers\Trainee\LessonController;
use App\Http\Controllers\Trainee\LessonQuizController;
use App\Http\Controllers\Trainee\PathController;
use App\Http\Controllers\Trainee\VideoCommentController;
use App\Http\Middleware\EnsureNotesWithinAvailability;
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
        /*
        | ⭐ تتبّع مشاهدة الفيديو (4.1): «إنهاء الدرس» = مشاهدة + اجتياز اختبار.
        | المتصفّح يبلّغ بموضعه، والخادم وحده يقرّر متى صارت المشاهدة كافية.
        */
        Route::post('/courses/{course:slug}/lessons/{lesson}/watch', [LessonController::class, 'watch'])->name('lesson.watch');
        Route::post('/courses/{course:slug}/lessons/{lesson}/complete', [LessonController::class, 'complete'])->name('lesson.complete');

        // حفظ درس (Bookmark — 3.4-34): حالة تُبدَّل في الخادم لا في المتصفّح
        Route::post('/courses/{course:slug}/lessons/{lesson}/bookmark', [LessonController::class, 'bookmark'])->name('lesson.bookmark');

        /*
        | اختبار الدرس (4.1): ترتيب عشوائيّ ⟵ معاينة ⟵ تسليم ⟵ الصحّ والغلط ⟵ إعادة بعد انتظار.
        | صلاحيّة `lesson_quiz.view` حارس الشاشة، وحاجز الانتظار نفسه داخل الخدمة.
        */
        Route::middleware('permission:lesson_quiz.view')->group(function () {
            Route::get('/courses/{course:slug}/lessons/{lesson}/quiz', [LessonQuizController::class, 'show'])->name('lesson.quiz');
            Route::post('/courses/{course:slug}/lessons/{lesson}/quiz/preview', [LessonQuizController::class, 'preview'])->name('lesson.quiz.preview');
            Route::post('/courses/{course:slug}/lessons/{lesson}/quiz/submit', [LessonQuizController::class, 'submit'])->name('lesson.quiz.submit');
            Route::get('/courses/{course:slug}/lessons/{lesson}/quiz/attempts/{attempt}', [LessonQuizController::class, 'result'])->name('lesson.quiz.result');
        });

        /*
        | تعليقات الفيديو (3.1) — كلّ فعل بصلاحيّته: القراءة والكتابة للمتدرّب،
        | والإخفاء والإظهار للإشراف وحده (`archive` / `restore`).
        */
        Route::prefix('/courses/{course:slug}/lessons/{lesson}/comments')
            ->name('lesson.comments')
            ->group(function () {
                Route::get('/', [VideoCommentController::class, 'index'])
                    ->middleware('permission:video_comments.view')->name('.index');

                Route::post('/', [VideoCommentController::class, 'store'])
                    ->middleware('permission:video_comments.create')->name('.store');

                Route::post('/{comment}/like', [VideoCommentController::class, 'like'])
                    ->middleware('permission:video_comments.create')->name('.like');

                Route::post('/{comment}/hide', [VideoCommentController::class, 'hide'])
                    ->middleware('permission:video_comments.archive')->name('.hide');

                Route::post('/{comment}/unhide', [VideoCommentController::class, 'unhide'])
                    ->middleware('permission:video_comments.restore')->name('.unhide');

                Route::delete('/{comment}', [VideoCommentController::class, 'destroy'])
                    ->middleware('permission:video_comments.delete')->name('.destroy');
            });

        /*
        | ملاحظات التدريب (3.2): مساحة واحدة لكلّ دروسه، بحفظ تلقائيّ وتصدير.
        |
        | ⭐ والكتابة محروسة بالإتاحة (5): «خارج الساعات دي التدريب **مقفول**»،
        | والملاحظة كتابةٌ داخل التدريب لا خارجه — فحارس واحد على `save` و`clear`
        | احتذاءً بـ`EnsureExamWithinAvailability` لا أسلوبًا ثانيًا. والتصدير
        | يبقى مفتوحًا: قراءةٌ لبيانات صاحبها لا تفتح بابًا ولا تكتب حرفًا.
        */
        Route::post('/courses/{course:slug}/notes', [CourseNoteController::class, 'save'])
            ->middleware(['permission:course_notes.edit', EnsureNotesWithinAvailability::class])
            ->name('course.notes.save');

        Route::delete('/courses/{course:slug}/notes', [CourseNoteController::class, 'clear'])
            ->middleware(['permission:course_notes.delete', EnsureNotesWithinAvailability::class])
            ->name('course.notes.clear');

        Route::get('/courses/{course:slug}/notes/export', [CourseNoteController::class, 'export'])
            ->middleware('permission:course_notes.export')->name('course.notes.export');
    });
