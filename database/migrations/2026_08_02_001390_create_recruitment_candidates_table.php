<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('recruitment_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // applied · screening · interview · final_list · placed · rejected
            $table->string('stage', 32)->default('applied')->index();
            $table->decimal('qualifying_score', 6, 2)->nullable();
            $table->json('course_scores')->nullable();
            $table->text('cv_summary')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('stage_changed_at')->nullable();
            $table->boolean('is_returning')->default(false); // شارة «عائد» (13.4-ق)
            $table->timestamp('previous_service_from')->nullable();
            $table->timestamp('previous_service_to')->nullable();
            $table->string('previous_exit_type', 32)->nullable();
            $table->boolean('renewed_readiness')->default(false);
            $table->timestamps();
        });
    }
};
