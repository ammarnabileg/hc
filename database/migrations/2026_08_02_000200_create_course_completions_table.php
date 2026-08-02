<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // ⭐ سجلّ واحد لكلّ (مستخدم، كورس) — قاعدة نهائيّة (13.4-ل)
        Schema::create('course_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->unsignedBigInteger('xp_awarded')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });
    }
};
