<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | عمولة الريفيرال 7% عند نجاح الشحن (19.3 · 7.6).
         |
         | ⭐ `source_transaction_id` **فريد** — وهو كلّ حارس منع التكرار:
         | مهما تكرّر النداء (ويب هوك مُعاد · اعتماد يدويّ مكرّر) لا تُسجَّل
         | العمولة إلّا مرّة واحدة لكلّ حركة شحن، تمامًا كقيد `invoice_id` في 19.5.
         */
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('source_transaction_id')->unique()->constrained('transactions')->cascadeOnDelete();
            $table->decimal('base_amount', 14, 2);   // قيمة الشحن بعملة الكريدتس
            $table->string('base_currency', 16);
            $table->decimal('base_usd', 14, 2);      // القيمة بالدولار بسعر الصرف وقت التنفيذ
            $table->decimal('percent', 5, 2);
            $table->decimal('amount_usd', 14, 2);    // العمولة — تدخل أرباحًا قابلة للسحب

            $table->foreignId('credit_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['referrer_id', 'created_at']);
        });
    }
};
