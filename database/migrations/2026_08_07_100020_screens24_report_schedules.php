<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * التقارير المجدولة (24.3-خامسًا).
     *
     * جدولان لا واحد: **الجدولة** تصف النيّة (أيّ تقرير · لمن · متى · بأيّ صيغة)،
     * و**سجلّ الإرسال** يصف ما حدث فعلًا. فصلُهما شرط الصدق: الجدولة تُعدَّل
     * والسجلّ لا يُعاد كتابته، فيبقى «آخر إرسال ونتيجته» قابلًا للمراجعة.
     */
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // مفتاح التاب في شاشة الإحصائيّات (users · sales · training …)
            $table->string('report_tab', 32)->index();
            // 🔒 التقارير الماليّة تُوسَم هنا فلا تُرسَل ولا تُقرأ خارج المجموعة المحميّة
            $table->boolean('is_financial')->default(false);
            $table->string('format', 8)->default('csv');           // csv · xlsx · pdf
            $table->string('frequency', 16)->default('weekly');    // daily · weekly · monthly
            $table->unsignedTinyInteger('day_of_week')->nullable();   // 0=الأحد
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->unsignedTinyInteger('hour')->default(7);
            $table->string('timezone', 64)->default('Africa/Cairo');
            $table->unsignedSmallInteger('period_days')->default(30);
            $table->boolean('include_comparison')->default(false);
            $table->boolean('skip_when_empty')->default(true);
            $table->json('recipient_emails')->nullable();
            $table->json('recipient_role_ids')->nullable();
            $table->json('recipient_user_ids')->nullable();
            $table->string('status', 16)->default('active')->index(); // active · paused
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_result', 16)->nullable();            // sent · empty · failed
            $table->timestamp('next_run_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('report_schedule_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_schedule_id')->constrained()->cascadeOnDelete();
            $table->timestamp('ran_at');
            $table->string('result', 16);                 // sent · empty · failed
            $table->unsignedInteger('rows_count')->default(0);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->boolean('was_manual')->default(false);
            $table->text('message')->nullable();          // ماذا حدث + ماذا تفعل (2.17-ب)
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedule_runs');
        Schema::dropIfExists('report_schedules');
    }
};
