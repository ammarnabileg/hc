<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رابط التنزيل المؤقّت للتقرير الكبير (24.3-خامسًا).
 *
 * كان الإعدادان `max_attachment_kb` و`download_link_hours` موجودَين بلا أثر:
 * فوق الحدّ يُرسَل البريد **بلا مرفق وبلا بديل** — وعدٌ في شاشة الإعدادات بلا
 * تنفيذ. فنحفظ الملفّ ونعطيه **رمزًا موقَّعًا محدود المدّة** بدل المرفق.
 *
 * ولماذا على سطر السجلّ لا في جدول مستقلّ؟ لأنّ الرابط **واقعةٌ من وقائع
 * الإرسال**: هذه الجدولة أُرسِلت في هذه اللحظة وملفّها كان هنا وصلاحيّته لكذا.
 * فحذف سطر السجلّ عند التنظيف يحذف معه الرابط بلا يتامى.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_schedule_runs', function (Blueprint $table) {
            // الرمز عشوائيّ طويل، والمسار موقَّع فوقه — طبقتان لا واحدة
            $table->string('download_token', 64)->nullable()->unique()->after('message');
            $table->string('download_path')->nullable()->after('download_token');
            $table->string('download_name')->nullable()->after('download_path');
            $table->string('download_format', 8)->nullable()->after('download_name');
            $table->unsignedBigInteger('download_size')->nullable()->after('download_format');
            $table->timestamp('download_expires_at')->nullable()->after('download_size');
        });
    }

    public function down(): void
    {
        Schema::table('report_schedule_runs', function (Blueprint $table) {
            $table->dropColumn([
                'download_token', 'download_path', 'download_name',
                'download_format', 'download_size', 'download_expires_at',
            ]);
        });
    }
};
