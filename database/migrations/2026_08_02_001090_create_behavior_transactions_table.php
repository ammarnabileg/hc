<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('behavior_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('behavior_violation_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 5, 2);
            $table->text('justification'); // مبرّر إلزاميّ
            $table->string('attachment_path')->nullable();
            $table->string('status', 24)->default('applied')->index(); // pending_approval · applied · reversed
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }
};
