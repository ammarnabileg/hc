<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('cvs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cv_template_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data')->nullable();
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->timestamps();
            $table->unique('user_id');
        });
    }
};
