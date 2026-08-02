<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('category', 64)->nullable()->index();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedInteger('helpful_yes')->default(0);
            $table->unsignedInteger('helpful_no')->default(0);
            $table->string('status', 24)->default('draft')->index();
            $table->timestamps();
        });
    }
};
