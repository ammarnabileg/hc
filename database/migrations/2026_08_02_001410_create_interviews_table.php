<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interviewer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('external_link')->nullable();
            $table->string('status', 24)->default('scheduled')->index(); // scheduled · done · no_show · cancelled
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });
    }
};
