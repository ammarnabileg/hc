<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ملاحظات التدريب (الدستور 3.2): **مساحة واحدة مشتركة لكلّ دروس التدريب**،
     * يكتبها المتدرّب من أيّ درس ويجدها كما هي في الدرس التالي.
     *
     * ولذلك القيد الفريد على (مستخدم، تدريب) لا على الدرس — فالوحدة هي التدريب،
     * والقاعدة هي التي تضمنها لا الكود.
     */
    public function up(): void
    {
        Schema::create('course_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->longText('body')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_notes');
    }
};
