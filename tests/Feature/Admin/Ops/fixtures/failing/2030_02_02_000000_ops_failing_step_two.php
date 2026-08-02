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
        // في المعاينة (Dry-run) لا شيء يُنفَّذ فعلًا — فلا نفشل فيها كما لا تفشل
        // هجرةٌ حقيقيّة تسقط على قيدٍ لم يُنفَّذ أصلًا.
        if (DB::connection()->pretending()) {
            return;
        }

        DB::table('app_version_history')->delete();

        throw new RuntimeException('هجرة تجريبيّة بتفشل عمدًا في نصّ الترحيل.');
    }

    public function down(): void
    {
        // لا شيء يُنزَل — والمقصود إثبات أنّ الاستعادة هي ما يرجّع البيانات
    }
};
