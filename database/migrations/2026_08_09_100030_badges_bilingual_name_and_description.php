<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فورم الشارة: **وصفٌ مستقلّ** واسمٌ ثنائيّ اللغة (7.4 · 3).
 *
 * 7.4 ينصّ: «لكلّ شارة **اسم + وصف + صورة**». وكان الوصف مخلوطًا بـ«شرط الفتح
 * المكتوب صراحةً» — وهما شيئان: الشرط يقول **كيف تفتحها**، والوصف يقول **ما
 * معناها**. وقاعدة التسمية ثنائيّة اللغة في القسم 3 تشمل الشارات نصًّا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            if (! Schema::hasColumn('badges', 'description_ar')) {
                $table->string('description_ar', 500)->nullable()->after('name_en');
            }

            if (! Schema::hasColumn('badges', 'description_en')) {
                $table->string('description_en', 500)->nullable()->after('description_ar');
            }

            if (! Schema::hasColumn('badges', 'condition_text_en')) {
                $table->string('condition_text_en')->nullable()->after('condition_text_ar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->dropColumn(['description_ar', 'description_en', 'condition_text_en']);
        });
    }
};
