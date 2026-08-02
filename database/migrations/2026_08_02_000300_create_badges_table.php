<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('icon_path')->nullable();
            // شرط الفتح مكتوب صراحةً — لا ألغاز (24.5)
            $table->string('condition_text_ar');
            $table->string('condition_key', 64)->nullable();
            $table->unsignedBigInteger('condition_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
