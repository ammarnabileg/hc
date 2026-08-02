<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('interview_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('label_ar');
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_archived')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
