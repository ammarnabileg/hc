<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // طرق التحويل: حساب بنكيّ · محفظة موبايل · إنستا باي · أخرى — كلّها من لوحة الإدارة
        Schema::create('transfer_methods', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // bank · wallet · instapay · other
            $table->string('name_ar');
            $table->string('logo_path')->nullable();
            $table->string('account_number')->nullable();
            $table->string('beneficiary_name')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
