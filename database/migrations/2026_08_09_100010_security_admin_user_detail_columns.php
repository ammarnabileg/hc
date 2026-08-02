<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | صفحة حساب المستخدم في لوحة الإدارة (12.1) — الأعمدة الناقصة.
         |
         | 1) `admin_notes`: **ملاحظات إداريّة داخليّة (للفريق فقط)** في تاب المعلومات
         |    الأساسيّة. عمود مستقلّ لا حقل في `meta` لأنّه يُقرَأ ويُكتَب من شاشة واحدة
         |    ولا يجوز أن يتسرّب لأيّ استجابة يراها صاحب الحساب.
         |
         | 2) `country_locked_at` / `country_locked_by`: **تثبيت الدولة يدويًّا**
         |    (12.1-متقدّم-5). الكشف التلقائيّ (5) يتبع مكان المستخدم الآن، وأحيانًا
         |    يُخطئ بـVPN أو ترويسة CDN غلط — فالأدمن يثبّتها والكشف بعدها لا يدهسها.
         */
        Schema::table('users', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('containment_reason');
            $table->timestamp('country_locked_at')->nullable()->after('governorate_id');
            $table->foreignId('country_locked_by')->nullable()->after('country_locked_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_locked_by');
            $table->dropColumn(['admin_notes', 'country_locked_at']);
        });
    }
};
