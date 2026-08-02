<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // فهرسة آليّة لحظة الاعتماد + بحث نصّيّ داخل المحتوى ووسوم (23-3.3)
        Schema::create('internal_library_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('type', 32)->index(); // design · content · plan · form · document · research
            $table->longText('content_text')->nullable(); // للبحث النصّيّ داخل المحتوى
            $table->string('file_path')->nullable();
            $table->string('preview_path')->nullable();
            $table->json('tags')->nullable();
            $table->string('access_level', 24)->default('entity'); // entity · all_volunteers · restricted
            $table->foreignId('min_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }
};
