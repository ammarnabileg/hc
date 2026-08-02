<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة اقتصاد التعلّم والتلعيب (الدستور 4.2 · 7 · 7.1 · 7.6).
 *
 * لماذا أعمدة لا مجرّد شرط في الكود؟ لأنّ المنح **يجب ألّا يتكرّر**، والحارس
 * الحقيقيّ ضدّ التكرار سطرٌ في قاعدة البيانات لا سطرٌ في الخدمة:
 *
 * 1) `lesson_completions.xp_awarded` و`tickets_awarded`: ما مُنِح فعلًا لحظة
 *    إتمام الدرس — فالقيمة تُجمَّد وقت الكتابة (7)، وإعادة الإتمام لا تمنح ثانيةً.
 * 2) `referrals.referrer_ticket_granted`: **تذكرة الداعي** (7.6) — كانت مفقودة،
 *    وللمدعوّ حارسه `welcome_ticket_granted`، فلكلّ طرفٍ حارسه المستقلّ.
 * 3) درجة نجاح الامتحان الافتراضيّة **70** لا 60 (4.2) — والقيمة القديمة كانت
 *    تخالف الدستور في مستوى المخطّط نفسه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_completions', function (Blueprint $table) {
            $table->unsignedInteger('xp_awarded')->default(0)->after('completed_at');
            $table->unsignedInteger('tickets_awarded')->default(0)->after('xp_awarded');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->boolean('referrer_ticket_granted')->default(false)->after('welcome_ticket_granted');
        });

        // 4.2: درجة النجاح 70%+ افتراضيًّا (أو حسب المحدَّد في لوحة الإدارة)
        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedTinyInteger('pass_score')->default(70)->change();
        });
    }

    public function down(): void
    {
        Schema::table('lesson_completions', function (Blueprint $table) {
            $table->dropColumn(['xp_awarded', 'tickets_awarded']);
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('referrer_ticket_granted');
        });
    }
};
