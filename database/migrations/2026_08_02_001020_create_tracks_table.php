<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // المسارات الثلاثة: قسم · محافظة · ملفّ مؤقّت (23-0.2)
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique(); // department · governorate · case_file
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_temporary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
