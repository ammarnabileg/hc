<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | سحب الأرباح (19.2 · 19.3).
         |
         | لماذا يُخصَم الرصيد لحظة الطلب لا لحظة الصرف؟ لأنّ «جاهزة للسحب»
         | لا بدّ أن تنقص فورًا وإلّا سحب المستخدم نفس الدولار مرّتين قبل المراجعة.
         | والمبلغ المخصوم يظهر في كارت «قيد التحويل» حتى تُصرَف أو تُرفَض فيُعاد.
         */
        Schema::create('wallet_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 14, 2);       // القيمة المطلوبة بالدولار
            $table->decimal('fee_percent', 5, 2);
            $table->decimal('fee_amount', 14, 2);   // النسبة أو الحدّ الأدنى — أيّهما أكبر
            $table->decimal('net_amount', 14, 2);   // ما يصل للمستخدم فعلًا

            $table->string('method', 24);           // wallet · bank · instapay · other
            $table->string('account_number', 120);
            $table->string('account_name', 120)->nullable();

            // pending · processing · paid · rejected
            $table->string('status', 24)->default('pending')->index();
            $table->string('receipt_path')->nullable();   // عمود «صورة الفاتورة» في جدول المسحوبات
            $table->string('admin_note')->nullable();
            $table->string('reject_reason')->nullable();

            $table->foreignId('debit_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('refund_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }
};
