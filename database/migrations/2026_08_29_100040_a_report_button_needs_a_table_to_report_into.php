<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ **«أبلغ عن شهادة مشبوهة»** (12.5-هـ) — الزرّ كان موجودًا بلا جدولٍ يستقبله.
 *
 * والنصّ الحاكم يذكر الجدول صراحةً في شاشة صفحة التحقّق (24.1):
 * «وأسفلها **جدول البلاغات:** الكود · المبلِّغ · السبب · التاريخ · الحالة ·
 * [مراجعة]» و«`pop-box` «مراجعة بلاغ» [التفاصيل + إجراء: تجاهل/إلغاء الشهادة/
 * تصعيد]». فالأعمدة هنا **هي** أعمدة النصّ، والحالات **هي** إجراءاته الثلاثة —
 * بلا دورة عملٍ مخترَعة فوقها.
 *
 * و`code` محفوظٌ نصًّا إلى جانب المفتاح الأجنبيّ: البلاغ قد يرد بكودٍ لا صفّ
 * له (وهو بذاته دلالةٌ على تزوير)، ولو حُذفت الشهادة بقي البلاغ شاهدًا بكودها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_reports', function (Blueprint $table) {
            $table->id();

            // الشهادة المبلَّغ عنها — والكود يبقى ولو غاب الصفّ
            $table->foreignId('certificate_id')->nullable()->constrained('certificates')->nullOnDelete();
            $table->string('code', 64)->index();

            /*
             | المبلِّغ: صفحة التحقّق **عامّة تمامًا بلا تسجيل ولا حساب** (21.2-ز)،
             | فالمستخدم قد يكون غائبًا — ويبقى ما تركه من وسيلة تواصل اختياريّة.
             */
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_contact', 190)->nullable();

            // السبب كما كتبه المبلِّغ
            $table->text('reason');

            // الحالة: new · dismissed (تجاهل) · revoked (إلغاء الشهادة) · escalated (تصعيد)
            $table->string('status', 24)->default('new')->index();

            // المراجعة (24.1): مَن راجع ومتى وبأيّ ملاحظة — أثرٌ لا يُمحى
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // أثر تقنيّ للبلاغ المجهول — يكشف قصف بلاغاتٍ من مصدرٍ واحد
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 190)->nullable();

            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_reports');
    }
};
