<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هجرة تجريبيّة للاختبار وحده — تعيش خارج `database/migrations` فلا تُطبَّق
 * في التنصيب الحقيقيّ، ووجودها يجعل «هجرة معلّقة» حالةً حقيقيّةً نختبرها
 * بدل أن نفترضها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_probe', function (Blueprint $table) {
            $table->id();
            $table->string('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_probe');
    }
};
