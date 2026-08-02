<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // بوّابة فواتيرك — الرصيد يُضاف من الويب هوك حصرًا + Idempotency على invoice_id (19.5-ج)
        Schema::create('gateway_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topup_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->default('fawaterk');
            $table->string('invoice_id')->unique();   // ⭐ منع التكرار
            $table->string('invoice_key')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('EGP');
            $table->string('payment_url')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 24)->default('unpaid')->index(); // unpaid · paid · expired · refunded
            $table->timestamp('paid_at')->nullable();
            $table->boolean('credited')->default(false); // لا يُضاف الرصيد مرّتين أبدًا
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }
};
