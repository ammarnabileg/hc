<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الوعد قبل التدريب (الفكرة #13 في ملف الهويّة): سطر نتيجة تعلّم عمليّة في رأس التدريب
 * يكتبه محرّر المحتوى ويُراجَع؛ لا وعود تلقائيّة. اختياريّ، فالتدريب بلا وعدٍ لا يطبع سطرًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('outcome_ar', 190)->nullable()->after('description_en');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('outcome_ar');
        });
    }
};
