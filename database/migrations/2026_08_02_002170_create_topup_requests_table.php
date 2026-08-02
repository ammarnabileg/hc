<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // 4 حالات فقط + بصمة الإيصال + طلب معلَّق واحد (19.5-ب)
        Schema::create('topup_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topup_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transfer_method_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('transferred_amount', 12, 2);
            $table->timestamp('paid_at');
            $table->string('receipt_path');
            $table->string('receipt_hash', 64)->index(); // بصمة الإيصال — المكرَّر يُوسَم
            $table->string('contact_phone', 32);
            // pending_review · completed · cancelled · duplicate
            $table->string('status', 24)->default('pending_review')->index();
            $table->text('cancel_reason')->nullable();
            $table->decimal('credited_amount', 12, 2)->nullable();
            $table->string('credit_mode', 16)->nullable(); // offer · manual
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }
};
