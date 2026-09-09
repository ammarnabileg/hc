<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔧 تصحيح فجوة: «الميتينج فعاليّة بكود حضور — والانعقاد والحضور موثَّقان
 * آليًّا بنظام الفعاليّات القائم» (23-0.2-4-5) كان `meeting_scheduled_at`
 * طابعًا زمنيًّا مجرَّدًا بلا صفٍّ حقيقيّ في `meetings` ولا كود حضور.
 *
 * `meeting_invitees` جدولٌ إضافيّ لا يمسّ سلوك أيّ اجتماعٍ قائم: جمهور
 * `meetings.audience` الثلاثة الحاليّة (كيان/فرعيّ/الكلّ) لا تقرأه إطلاقًا،
 * وقيمة `'specific'` الجديدة محجوزة لهذا الاستخدام وحده — فلا اجتماعٌ عاديّ
 * يمرّ بها (`MeetingController::store` يقبل الثلاثة الأصليّة حصرًا).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_invitees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });

        Schema::table('investigation_cases', function (Blueprint $table): void {
            $table->foreignId('meeting_id')->nullable()->after('meeting_scheduled_at')->constrained('meetings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('investigation_cases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('meeting_id');
        });

        Schema::dropIfExists('meeting_invitees');
    }
};
