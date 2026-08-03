<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الدرجة الوسطى في سلّم العتبات: **«بتر الاختياريّ» عند −9.5** (13.4-س-أ · 23-0.2).
 *
 * النصّ (13.4-س-أ): «**إقصاء** — **حصرًا** عبر سلّم العتبات (−8 إنذار ⟵ −9.5 بتر
 * الاختياريّ ⟵ −10 تعليق ولجنة تحقيق ⟵ قرار بشريّ من مشرف عام التطوّع)».
 *
 * والتفصيل الإجرائيّ (23-0.2 — البندان 2 و3):
 *  «**عند بلوغ −9.5 (بتر الاختياري):** **تُنهى فورًا عضويّاته في المحافظات
 *   والملفات إن وُجدت** … **ويستمرّ حسابه وعضويّة قسمه شغّالَين**».
 *  «**الحرمان:** لا يفتح عضويّة جديدة في مسارَي المحافظات والملفات **حتى
 *   التصفير الشهري التالي**».
 *
 * ولماذا جدول لا علَم على المستخدم؟ لأنّ للدرجة **مفعولًا ممتدًّا** (الحرمان
 * حتى التصفير) وأثرًا **قابلًا للمراجعة والطعن** (كم عضويّة أُنهيت وأيّ مهامّ
 * انتقلت) — فالصفّ هو ما يقرأه بابُ الانضمام وما تقرأه لجنةُ التحقيق بعده.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_optional_cuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // العتبة وقت الوقوع ورصيده عندها — لا يُقرآن من الإعدادات لاحقًا،
            // فالإعداد يتغيّر والحادثة لا تتغيّر (سجلٌّ للطعن — 13.4-ط).
            $table->decimal('threshold', 6, 2);
            $table->decimal('rep_at_cut', 6, 2);

            $table->unsignedInteger('memberships_ended')->default(0);
            $table->unsignedInteger('tasks_handed_over')->default(0);
            $table->unsignedInteger('contributions_withdrawn')->default(0);

            // «حتى التصفير الشهري التالي» — لحظةٌ محسوبة لا مدّة ثابتة
            $table->timestamp('deprived_until')->nullable();
            $table->timestamp('released_at')->nullable();

            // ماذا وقع بالضبط: الكيانات والمسارات والمهامّ — مرجعُ أيّ اعتراض
            $table->json('detail')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_optional_cuts');
    }
};
