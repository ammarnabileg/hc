<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * العروض المحفوظة (2.15-د): أيّ تركيبة فلاتر تُحفَظ بضغطة وتظهر
         * كرقاقة فوق الجدول. لماذا جدول لا عمود JSON على المستخدم؟ لأنّ العرض
         * قد يصير مشتركًا لاحقًا، ولأنّ الحذف الفرديّ بالعمود يعيد كتابة الصفّ كلّه.
         */
        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('screen', 96)->index();   // مفتاح الشاشة: اسم المسار
            $table->string('name', 96);
            $table->json('filters')->nullable();      // تركيبة الفلاتر كما هي في الاستعلام
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'screen', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
