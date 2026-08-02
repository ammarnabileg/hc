<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('certificate_accreditations', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('logo_path')->nullable();
            $table->boolean('is_platform')->default(false); // اعتماد المنصّة لا يُحذَف
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
