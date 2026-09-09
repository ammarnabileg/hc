<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ [2026-09-10] «عناصر أمان بصريّة اختياريّة (Guilloché/Microtext)» (سطر
 * 2407 · 4644 · 12.5-ب) — كانا اسمَي تقنيّتين مذكورَين في الدستور بلا حقلٍ
 * ولا راسمٍ. الحقل هنا Toggle واحد كالختم/التوقيع تمامًا: بلا إعدادٍ لكلّ
 * تفصيلة (كثافة النقش/محتوى النصّ المصغّر) لأنّ الدستور لم يشترط تخصيصًا،
 * فالنمط والنصّ يُشتقّان تلقائيًّا من كود كلّ شهادة (فريدان لكلّ شهادة بلا
 * إعدادٍ إضافيّ يُثقِل الفورم بما لم يُطلَب).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_types', function (Blueprint $table) {
            $table->boolean('security_elements_enabled')->default(false)->after('signature_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_types', function (Blueprint $table) {
            $table->dropColumn('security_elements_enabled');
        });
    }
};
