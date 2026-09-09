<?php

use App\Http\Controllers\Trainee\AnnouncementController;
use App\Http\Controllers\Trainee\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| التعليمات ومركز الإشعارات (13.2 · 2.8)
|--------------------------------------------------------------------------
| صفحتان شخصيّتان: كلّ مستخدم يرى ما استُهدف به هو، وإشعاراته هو وحده.
| الحارس هنا هو **الاستهداف والملكيّة** لا صلاحيّة إداريّة — لأنّ قناة البثّ
| موجَّهة لكلّ مستخدم بحسب شريحته (24.5)، ومركز الإشعارات سجلّه الخاصّ.
| أمّا لوحة الأدمن (إنشاء/استهداف/تحليلات) فلها صلاحيّاتها في مجال الإدارة.
*/

Route::middleware('auth')->group(function () {

    // ---------------------------------------------------------- التعليمات
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');

    Route::post('/announcements/read-all', [AnnouncementController::class, 'readAll'])->name('announcements.read-all');
    Route::post('/announcements/{announcement}/read', [AnnouncementController::class, 'read'])->name('announcements.read');
    Route::post('/announcements/{announcement}/acknowledge', [AnnouncementController::class, 'acknowledge'])->name('announcements.acknowledge');
    Route::post('/announcements/{announcement}/react', [AnnouncementController::class, 'react'])->name('announcements.react');
    // استطلاع داخل المنشور (12.6-أ) — والحارس هو الاستهداف نفسه لا صلاحيّة إداريّة
    Route::post('/announcements/{announcement}/poll', [AnnouncementController::class, 'poll'])->name('announcements.poll');

    // ---------------------------------------------------- مركز الإشعارات
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // ⭐ استطلاعٌ لحظيّ (Toast — 2.8): «الجديد منذ آخر ما رآه» لا الفيد كلّه
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
});
