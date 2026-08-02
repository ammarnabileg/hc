<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // العملات: Coins · XP · Tickets · VXP · Rep (+ Hours مستقبلًا) — قابل للتوسّع (21)
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('icon')->nullable();
            $table->unsignedTinyInteger('decimals')->default(0);
            $table->string('layer', 16)->default('training'); // training · volunteer
            $table->boolean('is_spendable')->default(false);
            $table->boolean('is_cumulative')->default(true);   // VXP تراكميّ لا يتصفّر
            $table->decimal('min_value', 12, 2)->nullable();   // Rep = -10
            $table->decimal('max_value', 12, 2)->nullable();   // Rep = +10
            $table->boolean('resets_monthly')->default(false); // Rep يتصفّر يوم 1 الساعة 5ص القاهرة
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
