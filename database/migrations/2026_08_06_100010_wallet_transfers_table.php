<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | إرسال حوالة بين المستخدمين بالكود (19.3).
         | كلّ الأرقام هنا **محسوبة في الخادم**: المُرسَل والرسوم والصافي —
         | ولا يصل من المتصفّح إلّا كود المستلم والكمّيّة والعملة.
         */
        Schema::create('wallet_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 14, 2);        // ما خُصِم من المُرسِل
            $table->decimal('fee_percent', 5, 2);    // النسبة وقت التنفيذ — تُجمَّد في السطر
            $table->decimal('fee_amount', 14, 2);    // الرسوم بالعملة نفسها
            $table->decimal('net_amount', 14, 2);    // ما استلمه المستلم (Ceil)

            $table->string('note')->nullable();
            $table->foreignId('debit_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('credit_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['sender_id', 'created_at']);
            $table->index(['recipient_id', 'created_at']);
        });
    }
};
