<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // البوزشنز الستّة (13.4) + «أخوكم» عنصر شرفيّ فوق الجميع بلا صلاحيّات (13.4-ص)
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->unsignedTinyInteger('rank');       // 1 كوردنيتور … 6 مشرف عام التطوّع
            $table->unsignedInteger('span_min')->nullable();      // نطاق الإشراف: مؤشّرات لا موانع (13.4-ف)
            $table->unsignedInteger('span_default')->nullable();
            $table->unsignedInteger('span_max')->nullable();
            $table->unsignedInteger('task_load_cap')->nullable(); // سقف الانشغال (عدد مهامّ)
            $table->boolean('is_honorary')->default(false);       // «أخوكم»
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
