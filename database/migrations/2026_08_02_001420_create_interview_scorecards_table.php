<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('interview_scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->text('skills_notes')->nullable();
            $table->text('personality_notes')->nullable();
            $table->json('criteria_scores')->nullable();
            $table->decimal('total_score', 6, 2)->nullable();
            $table->string('decision', 24)->nullable(); // passed · rejected
            $table->string('rejection_reason')->nullable();
            $table->boolean('is_draft')->default(true);
            $table->timestamps();
        });
    }
};
