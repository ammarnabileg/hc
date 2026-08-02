<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // الجدول الموحّد للمعاملات — يُفلتَر بالطبقة (تدريب/تطوّع) عند العرض
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);              // موجب أو سالب
            $table->decimal('balance_after', 14, 2)->nullable();
            $table->string('layer', 16)->default('training')->index();
            $table->string('source', 64)->index();          // task · meeting · academy · leadership · behavior · purchase · topup · referral · admin
            $table->string('reason')->nullable();
            $table->nullableMorphs('reference');           // المهمّة/الاجتماع/الطلب…
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // حدّ الخسارة اليوميّ (13.4-ن-و): ما تخطّاه يُسجَّل كاملًا مع وسم
            $table->boolean('exceeded_daily_cap')->default(false);
            $table->decimal('applied_amount', 14, 2)->nullable(); // ما طُبِّق فعلًا على الرقم الظاهر

            // التصحيح بمعاملة عكسيّة موثّقة — لا تعديل للأصل
            $table->boolean('is_correction')->default(false);
            $table->foreignId('corrects_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->timestamp('objection_deadline_at')->nullable(); // 5 أيّام
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'currency_id', 'created_at']);
        });
    }
};
