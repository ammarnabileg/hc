<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('leadership_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('label_ar');
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_archived')->default(false); // المعيار المؤرشف يُعرَض بدرجته بوسم
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
