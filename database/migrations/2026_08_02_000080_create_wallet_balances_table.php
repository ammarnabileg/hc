<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('wallet_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('lifetime_earned', 14, 2)->default(0); // المكتسَب التراكميّ (للترقية)
            $table->decimal('lifetime_spent', 14, 2)->default(0);
            $table->timestamp('last_reset_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'currency_id']);
        });
    }
};
