<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // منع تكرار كسب XP لنفس السؤال — يُتحقَّق في الخادم
        Schema::create('lesson_question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_question_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'lesson_question_id']);
        });
    }
};
