<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // أدوات الاسم تُعامَل جزءًا من الكلمة التالية — قابلة للتعديل بالكامل (12.14-ج)
        Schema::create('name_particles', function (Blueprint $table) {
            $table->id();
            $table->string('particle', 32)->unique();
            $table->string('locale', 5)->default('ar');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
