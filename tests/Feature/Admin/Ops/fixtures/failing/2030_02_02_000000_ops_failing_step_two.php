<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * الخطوة الثانية — تمسح بيانات موجودة **ثمّ تنفجر**: هذا بالضبط أسوأ سيناريو في
 * 2.11 (قاعدة نصف مرحَّلة وبيانات ناقصة). و`down()` لا تعرف كيف ترجّع ما مُسِح،
 * فالطريق الوحيد لعودة البيانات هو **الاستعادة من النسخة الاحتياطيّة**.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_version_history')->delete();

        throw new RuntimeException('هجرة تجريبيّة بتفشل عمدًا في نصّ الترحيل.');
    }

    public function down(): void
    {
        // لا شيء يُنزَل — والمقصود إثبات أنّ الاستعادة هي ما يرجّع البيانات
    }
};
