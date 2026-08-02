<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // ثلاثة مستويات لا رابع — مرّة واحدة لكلّ حدث Server-side (2.14)
        Schema::create('celebration_events', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label_ar');
            $table->unsignedTinyInteger('tier')->default(1); // 1 خفيف · 2 متوسّط · 3 ذروة
            $table->string('sound_path')->nullable();
            $table->text('message_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
