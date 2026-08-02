<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->decimal('price_coins', 12, 2)->default(0);
            $table->decimal('original_value', 12, 2)->default(0); // لعرض «وفّرت كذا»
            $table->string('status', 24)->default('draft')->index();
            $table->timestamps();
        });
    }
};
