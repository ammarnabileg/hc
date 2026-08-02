<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // المصطلح المعروض «تدريب» والاسم التقنيّ Course (القسم 3)
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('cover_path')->nullable();

            $table->boolean('is_free')->default(false);
            $table->decimal('price_coins', 12, 2)->default(0);

            // XP بقيمتين حسب نصف المهلة (القسم 7)
            $table->unsignedInteger('xp_before_half')->default(0);
            $table->unsignedInteger('xp_after_half')->default(0);
            $table->unsignedInteger('deadline_days')->nullable();

            $table->boolean('forced_order')->default(true);
            $table->boolean('rating_enabled')->default(true);
            $table->unsignedInteger('free_preview_lessons')->default(1); // 21.1
            $table->boolean('is_indexable')->default(true);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();

            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
