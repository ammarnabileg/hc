<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أعمدة ناقصة لشاشتَي الفعاليّات (12.11 · 13.3) والحروب (12.10-ج):
     *  - الفعاليّة ثنائيّة اللغة ولها رابط تسجيل خارجيّ وجدول مكافأة متدرّجة.
     *  - الحرب لها نصوص شاشة ومؤقّتات قابلة للتعديل بـOverride عن القواعد العامّة.
     * ولا يُمَسّ أيّ مايجريشن قائم — إضافة فقط (دليل البناء 1).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('description_en')->nullable()->after('description');
            $table->string('registration_link')->nullable()->after('join_link');
            // جدول المكافأة المتدرّجة زمنيًّا: [{hours, xp, tickets}] — 13.3
            $table->json('reward_tiers')->nullable()->after('ticket_reward');
        });

        Schema::table('challenges', function (Blueprint $table) {
            // نصوص الشاشة (هيدلاين · وصف · جملة فوز · جملة خسارة) — 12.10-ج
            $table->json('texts')->nullable()->after('question_source');
            // المؤقّتات (وقت السؤال · مؤقّت الحسم · مدد التركيز) — 12.10-ج
            $table->json('timers')->nullable()->after('texts');
            // التكاليف (شرط الاستعداد · الإنشاء · الانضمام) — 12.10-ج
            $table->json('costs')->nullable()->after('timers');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['description_en', 'registration_link', 'reward_tiers']);
        });

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn(['texts', 'timers', 'costs']);
        });
    }
};
