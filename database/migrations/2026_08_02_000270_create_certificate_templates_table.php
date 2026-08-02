<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // محرّر مرئيّ: خلفيّة مرفوعة + طبقات نصّ حرّة (12.5-ب) — ويُجمَّد نسخةً عند الإصدار
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_type_id')->constrained()->cascadeOnDelete();
            $table->string('language', 5)->default('ar');
            $table->string('name');
            $table->string('background_path')->nullable();
            $table->unsignedInteger('width_px')->default(1754);
            $table->unsignedInteger('height_px')->default(1240);
            $table->json('layers')->nullable(); // [{type,field,x,y,w,h,font,size,color,align,rotate}]
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }
};
