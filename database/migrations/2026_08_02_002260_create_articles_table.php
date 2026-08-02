<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // دورة نشر إلزاميّة: مسودّة ⟵ مراجعة ⟵ نشر — والكاتب لا ينشر مقاله (21.2-أ)
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('article_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_path')->nullable();
            $table->json('tags')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->nullableMorphs('related'); // ربط بتدريب أو مسار
            $table->string('status', 24)->default('draft')->index(); // draft · in_review · published · archived
            $table->text('review_notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }
};
