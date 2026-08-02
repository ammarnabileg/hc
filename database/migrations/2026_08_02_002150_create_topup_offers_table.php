<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // عروض الشحن: «ادفع كذا واحصل على كذا» — منفصلة لكلّ طريقة (19.5)
        Schema::create('topup_offers', function (Blueprint $table) {
            $table->id();
            $table->string('method', 24)->default('manual')->index(); // manual · gateway
            $table->string('label_ar');
            $table->decimal('pay_amount', 12, 2);
            $table->decimal('credit_amount', 12, 2);
            $table->decimal('bonus_percent', 5, 2)->default(0);
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
