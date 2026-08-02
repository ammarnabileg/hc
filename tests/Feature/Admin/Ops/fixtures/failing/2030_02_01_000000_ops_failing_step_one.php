<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الخطوة الأولى في سيناريو «الفشل في منتصف الترحيل» — تنجح، فيصير عندنا هجرةٌ
 * مطبَّقة لازم تُنزَل عند فشل الخطوة التالية (2.11-ح).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_step_one', function (Blueprint $table) {
            $table->id();
            $table->string('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_step_one');
    }
};
