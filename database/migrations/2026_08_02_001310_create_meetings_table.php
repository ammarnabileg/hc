<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience', 24)->default('entity'); // entity · sub_entity · all
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('external_link')->nullable();
            $table->string('status', 24)->default('scheduled')->index(); // scheduled · running · ended
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('attendance_window_hours')->nullable();
            $table->timestamp('attendance_closes_at')->nullable();
            $table->string('attendance_code', 32)->nullable();
            $table->text('minutes')->nullable(); // المحضر
            $table->timestamps();
        });
    }
};
