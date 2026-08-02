<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تتبّع مشاهدة فيديو الدرس (4.1).
 *
 * الدستور يعرّف **«إنهاء الدرس» = مشاهدة الفيديو + اجتياز اختباره** — «الاتنين
 * مطلوبين لاحتساب الإكمال والـXP». وكان الشقّ الثاني وحده مطبَّقًا: `POST
 * /complete` ينجح بلا أيّ تتبّع مشاهدة، ولا عمود ولا نقطة نهاية له أصلًا —
 * فيمرّ المتدرّب على الأسئلة ويأخذ XP الدرس بلا أن يفتح الفيديو.
 *
 * والثواني تُحفَظ لا مجرّد راية «شوهد»: النسبة المطلوبة إعدادٌ في لوحة الإدارة
 * (2.13)، فلو رفعها المالك سرت على التقدّم المحفوظ بلا هجرة بيانات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_video_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            // أقصى موضعٍ بلغه فعلًا — لا مجموع ما شغّل، فالإعادة لا تُراكِم
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_video_views');
    }
};
