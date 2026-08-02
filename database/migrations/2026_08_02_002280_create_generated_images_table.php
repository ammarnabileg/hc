<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // كاش بمفتاح (القالب + المستخدم + البيانات) — 12.14-و
        Schema::create('generated_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cache_key', 64)->unique();
            $table->string('path');
            $table->json('data_snapshot')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }
};
