<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('exam_version')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->json('answers')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->boolean('passed')->default(false);
            $table->string('status', 24)->default('in_progress')->index(); // in_progress · submitted · expired
            $table->timestamps();
            $table->index(['user_id', 'exam_id']);
        });
    }
};
