<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * محاولات اختبار الدرس (الدستور 4.1): أسئلة بترتيب عشوائيّ ⟵ معاينة الإجابات ⟵
     * تسليم نهائيّ ⟵ كشف الصحّ والغلط ⟵ لو فيه غلطة واحدة يعيد **بعد انتظار 20 ثانية**.
     *
     * لماذا جدول محاولات أصلًا؟ لأنّ الترتيب العشوائيّ لازم يثبت أثناء المحاولة الواحدة
     * (وإلّا اختلفت المعاينة عن الأسئلة)، ولأنّ **حاجز الانتظار لا يجوز أن يعيش في المتصفّح**:
     * `retry_available_at` مخزَّن هنا، والخادم يرفض بدء محاولة جديدة قبله.
     */
    public function up(): void
    {
        Schema::create('lesson_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->json('question_order')->nullable();   // ترتيب الأسئلة العشوائيّ لهذه المحاولة
            $table->json('option_order')->nullable();     // ترتيب اختيارات كلّ سؤال
            $table->json('answers')->nullable();
            $table->string('status', 16)->default('in_progress'); // in_progress · submitted
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->boolean('passed')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('retry_available_at')->nullable();  // حاجز الـ20 ثانية (4.1)
            $table->timestamps();

            $table->index(['user_id', 'lesson_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_quiz_attempts');
    }
};
