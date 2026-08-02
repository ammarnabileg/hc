<?php

use App\Http\Controllers\Volunteer\OverviewController;
use App\Http\Controllers\Volunteer\PublicBoardController;
use App\Http\Controllers\Volunteer\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مجال «لوحة التطوّع: النظرة العامّة والمهام» (الدستور 24.4 · 13.4-ح · 23)
|--------------------------------------------------------------------------
| الصلاحيّة إلزاميّة على كلّ مسار (12.2.1)، والنطاق يُقيَّم داخل العضويّة النشطة
| — فلا سلطة عابرة للكيانات. والعناصر التي لا يملكها المستخدم تُخفى من السايد بار أصلًا.
*/

Route::middleware('auth')->prefix('volunteer')->name('volunteer.')->group(function () {

    // ------------------------------------------------------------ نظرة عامّة (24.4-1)
    Route::middleware('permission:personal_reports.view')->group(function () {
        Route::get('/', [OverviewController::class, 'index'])->name('overview');
        Route::get('/report', [OverviewController::class, 'report'])->name('report');
    });

    Route::get('/calendar', [OverviewController::class, 'calendar'])
        ->middleware('permission:calendar.view')->name('calendar');

    // ------------------------------------------------------------ لوحة المهام العامّة (24.4-2)
    // ⛔ لا زرّ إضافة هنا — الإضافة للأدمن ومشرف عام التطوّع حصرًا، وللقادة ترشيح بند.
    Route::middleware('permission:public_board.list')->group(function () {
        Route::get('/tasks/board', [PublicBoardController::class, 'index'])->name('tasks.board');
    });

    Route::post('/tasks/board/{task}/claim', [PublicBoardController::class, 'claim'])
        ->whereNumber('task')->middleware('permission:public_board.view')->name('tasks.claim');

    // ترشيح بندٍ ليصير عامًّا — يُرفَع لمشرف عام التطوّع (23-3.1)
    Route::post('/tasks/board/nominate', [PublicBoardController::class, 'nominate'])
        ->middleware('permission:public_board.create,work_packages.edit,tasks.assign')->name('tasks.nominate');

    // ------------------------------------------------------------ مهامّي وصفحة المهمّة (24.4-2)
    Route::get('/tasks', [TaskController::class, 'index'])
        ->middleware('permission:tasks.list')->name('tasks.index');

    Route::post('/tasks', [TaskController::class, 'store'])
        ->middleware('permission:tasks.create')->name('tasks.store');

    Route::get('/tasks/{task}', [TaskController::class, 'show'])
        ->whereNumber('task')->middleware('permission:tasks.view')->name('tasks.show');

    Route::middleware('permission:tasks.edit')->group(function () {
        Route::post('/tasks/{task}/deliver', [TaskController::class, 'deliver'])->whereNumber('task')->name('tasks.deliver');
        Route::post('/tasks/{task}/block', [TaskController::class, 'block'])->whereNumber('task')->name('tasks.block');
        Route::post('/tasks/{task}/extension', [TaskController::class, 'extension'])->whereNumber('task')->name('tasks.extension');
        Route::post('/tasks/{task}/apology', [TaskController::class, 'apology'])->whereNumber('task')->name('tasks.apology');
        Route::post('/tasks/{task}/flag', [TaskController::class, 'flag'])->whereNumber('task')->name('tasks.flag');
    });

    // دفعة الصب-تاسكات: حفظ للمراجعة بقيد الديدلاين (23-2.3)
    Route::post('/tasks/{task}/subtasks', [TaskController::class, 'storeSubtasks'])
        ->whereNumber('task')->middleware('permission:subtasks.create')->name('tasks.subtasks.store');

    // دعوة مساهم — على صب-تاسك معتمد (23-4)
    Route::post('/tasks/{task}/contributors', [TaskController::class, 'inviteContributor'])
        ->whereNumber('task')->middleware('permission:contributions.create')->name('tasks.contributors.store');

    // التودو: شخصيّ بلا اعتماد وبلا أثر على أيّ درجة (23-2.1)
    Route::post('/tasks/{task}/todos', [TaskController::class, 'storeTodo'])
        ->whereNumber('task')->middleware('permission:todos.create')->name('tasks.todos.store');

    Route::post('/tasks/{task}/todos/{todo}/toggle', [TaskController::class, 'toggleTodo'])
        ->whereNumber('task')->whereNumber('todo')->middleware('permission:todos.edit')->name('tasks.todos.toggle');

    Route::delete('/tasks/{task}/todos/{todo}', [TaskController::class, 'destroyTodo'])
        ->whereNumber('task')->whereNumber('todo')->middleware('permission:todos.delete')->name('tasks.todos.destroy');
});
