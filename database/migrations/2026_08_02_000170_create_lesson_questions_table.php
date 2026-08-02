<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // أسئلة الدرس + الإدخال الرقميّ بنمط OTP (القسم 4)
        Schema::create('lesson_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24)->default('otp'); // otp · choice · text
            $table->string('prompt');
            $table->string('placeholder')->nullable();
            $table->json('options')->nullable();
            $table->string('correct_answer')->nullable();
            $table->boolean('is_general')->default(false);
            $table->unsignedInteger('xp_reward')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
