<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // اعتراض واحد لكلّ معاملة خلال 5 أيّام — والتصحيح بمعاملة عكسيّة لا بتعديل الأصل
        Schema::create('objections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->foreignId('current_handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('open')->index(); // open · in_review · escalated · accepted · rejected
            $table->timestamp('sla_due_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('correction_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique('transaction_id');
        });
    }
};
