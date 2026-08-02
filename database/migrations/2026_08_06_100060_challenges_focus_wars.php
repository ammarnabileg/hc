<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * حرب التركيز (15.3).
     *
     * الاقتصاد: **إنشاء = 5 تذاكر** (رسوم غير قابلة للاسترجاع) و**انضمام =
     * تذكرة تُحوَّل لصاحب التحدّي** — تحويلٌ مباشر بين المستخدمين لا سكّ،
     * فالفارمينج مقفول (15.3).
     */
    public function up(): void
    {
        Schema::create('focus_wars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('intention', 240)->nullable();
            $table->boolean('is_group')->default(false)->index();
            $table->string('status', 16)->default('active')->index(); // active · cancelled · ended
            $table->decimal('create_cost', 12, 2)->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
        });

        Schema::create('focus_war_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('focus_war_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('ends_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('minutes_awarded')->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['focus_war_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focus_war_members');
        Schema::dropIfExists('focus_wars');
    }
};
