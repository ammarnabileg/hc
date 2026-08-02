<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * مكتبة الرسائل الإيجابيّة (2.6-ب) — يديرها الأدمن بالكامل:
     * إضافة/تعديل/حذف/تفعيل، **ولكلّ رسالة سياقها** الذي تظهر فيه
     * فلا تُقال كلمة تشجيع في لحظة لا تناسبها (2.17-ج).
     */
    public function up(): void
    {
        Schema::create('positive_messages', function (Blueprint $table) {
            $table->id();
            // سياق الظهور: lesson_complete · streak_broken · … · any (يصلح لأيّ لحظة)
            $table->string('context', 48)->index();
            $table->text('body_ar');
            // رمز صغير اختياريّ — نبرة دافئة بلا مبالغة (2.17-ج)
            $table->string('emoji', 16)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            // عدّاد الظهور: مادّة الأدمن ليعرف الرسالة المستهلَكة من المهملة
            $table->unsignedInteger('shown_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positive_messages');
    }
};
