<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | تحويل العملة برسوم ثابتة لكلّ المسارات (19.3).
         | السعر المستعمَل يُجمَّد في السطر، فلا يتغيّر معنى عمليّةٍ قديمة
         | لو عدّل مالك المنصّة أسعار الصرف بعدها.
         */
        Schema::create('wallet_exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->foreignId('to_currency_id')->constrained('currencies')->cascadeOnDelete();

            $table->decimal('amount', 14, 2);          // ما خُصِم من العملة المصدر
            $table->decimal('fee_percent', 5, 2);
            $table->decimal('fee_amount', 14, 2);      // الرسوم بعملة المصدر
            $table->decimal('rate', 16, 6);            // كم وحدة هدف لكلّ وحدة مصدر وقت التنفيذ
            $table->decimal('credited_amount', 14, 2); // ما أُضيف بعملة الهدف

            $table->foreignId('debit_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('credit_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }
};
