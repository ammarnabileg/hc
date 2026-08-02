<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // أسئلة اختيارات أو OTP يضيفها صاحب الاجتماع أو أيّ أبلاين حتى السقف
        Schema::create('meeting_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('type', 24)->default('choice');
            $table->string('prompt');
            $table->json('options')->nullable();
            $table->string('correct_answer')->nullable();
            $table->timestamps();
        });
    }
};
