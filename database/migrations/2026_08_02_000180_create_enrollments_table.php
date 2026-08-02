<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('source', 32)->default('purchase'); // purchase · bundle · gift · academy
            $table->timestamp('started_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedBigInteger('xp_earned')->default(0);
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });
    }
};
