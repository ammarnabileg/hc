<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🧩 المطوّرين — الطرفيّة (Terminal) — مستحدَثةٌ بأمر المالك المباشر
 * (12.15-هـ · v5.6، سجلّ القرارات 25، 2026-08-06).
 *
 * تابٌ ثالث تحت دروب-داون «🧩 المطوّرين ▾» — **لمالك المنصّة حصرًا، لا صلاحيّة
 * تُمنَح لأيّ دورٍ آخر**. لا قائمة أوامرَ مسموحة أو ممنوعة (12.15-هـ صريح:
 * «اسمح بكلّ الأوامر») — القيد الأمنيّ الوحيد غير القابل للتفاوض هو **سجلّ
 * التدقيق لكلّ أمر** (من/متى/الأمر/كود الخروج)، وهذه الهجرة تبني قاعدته.
 *
 * لماذا جدولٌ مستقلّ لا مجرّد صفٍّ في `audit_logs`؟ لأنّ سجلّ الأوامر يُعرَض
 * كجدولٍ تشغيليّ في الشاشة نفسها (آخر 200 · 12.15-و) بأعمدةَ لا يحملها
 * `audit_logs` العامّ (المخرَجات الكاملة · كود الخروج · مدّة التنفيذ) — فالجدول
 * المستقلّ يخدم العرض التشغيليّ، وقيد `AuditTrail::log()` في `TerminalService`
 * يخدم الأثر الإداريّ الموحَّد (2.13-و) — كلاهما يُكتَب معًا لا أحدهما بدل الآخر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_command_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->text('command');
            $table->text('output')->nullable(); // stdout+stderr مدموجَين، مقصوصان بحدّ أسطر (12.15-و)
            $table->integer('exit_code')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            // آخر 200 أمر (12.15-هـ) — الفهرس يخدم الفرز والتنظيف الدوريّ معًا
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_command_logs');
    }
};
