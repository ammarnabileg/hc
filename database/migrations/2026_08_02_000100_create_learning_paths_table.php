<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('cover_path')->nullable();
            $table->boolean('forced_order')->default(false); // ترتيب مشاهدة إجباريّ أو عشوائيّ
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('is_indexable')->default(true);  // 21.1 صفحة عامّة مفهرسة
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
