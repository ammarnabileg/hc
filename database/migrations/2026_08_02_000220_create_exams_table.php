<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->morphs('examable'); // Course أو LearningPath
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->unsignedInteger('attempts_allowed')->default(1);
            $table->unsignedInteger('retry_cooldown_hours')->default(24);
            $table->unsignedTinyInteger('pass_score')->default(60);
            $table->decimal('price_coins', 12, 2)->default(0); // امتحان شهادة المسار
            $table->unsignedInteger('version')->default(1);
            $table->boolean('requires_retake_on_version_change')->default(false); // 13.4-ق
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
