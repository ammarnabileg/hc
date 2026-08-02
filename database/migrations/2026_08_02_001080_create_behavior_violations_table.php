<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // قائمة المخالفات المكوَّدة يحدّدها الأدمن (13.4-ن-هـ)
        Schema::create('behavior_violations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('label_ar');
            $table->decimal('default_value', 5, 2)->default(-0.5); // −0.5 تنبيه · −1 جسيمة
            $table->boolean('requires_higher_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
