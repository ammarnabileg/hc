<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // العضويّة تحدّد «أين» والدور يحدّد «ماذا» — والتقييم داخل العضويّة النشطة (12.2.1)
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upline_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_acting')->default(false); // «قائم بأعمال» لحين الاعتماد البشريّ
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 64)->nullable();
            $table->string('status', 24)->default('active')->index(); // active · absent · suspended · ended
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['entity_id', 'status']);
        });
    }
};
