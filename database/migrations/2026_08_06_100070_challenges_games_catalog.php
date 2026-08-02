<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تاب «الألعاب» في لوحة التلعيب (24.2 · 7.5).
     * الغلاف **SVG مرسوم** لا مكتبة أيقونات (2.16-ج).
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name_ar', 120);
            $table->string('name_en', 120)->nullable();
            $table->text('description')->nullable();
            $table->text('icon_svg')->nullable();
            $table->decimal('ticket_cost', 12, 2)->default(1);
            $table->string('xp_mode', 16)->default('fixed'); // fixed · by_score
            $table->unsignedInteger('xp_reward')->default(0);
            $table->unsignedInteger('daily_limit')->nullable();
            $table->string('status', 16)->default('active')->index(); // active · soon · paused
            $table->string('soon_text', 160)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('tickets_spent', 12, 2)->default(0);
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->unsignedInteger('score')->default(0);
            $table->string('status', 16)->default('finished')->index(); // finished · reversed
            $table->string('reversed_reason', 240)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
        Schema::dropIfExists('games');
    }
};
