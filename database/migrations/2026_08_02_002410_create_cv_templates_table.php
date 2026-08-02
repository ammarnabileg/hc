<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('cv_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('preview_path')->nullable();
            $table->string('view_path')->nullable();
            $table->decimal('price_tickets', 12, 2)->default(0);
            $table->boolean('is_free')->default(false); // قالب مجّانيّ واحد كباب دخول (21.2-ج)
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
