<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ المحرّر المرئيّ (Drag-drop) لقوالب الـCV — المرحلة 1/2 (12.7-ب).
 *
 * طبقةٌ زخرفيّةٌ إضافيّة فقط (خلفيّة/إطار صورة/أشكال ونصوص ثابتة) تُوضَع
 * بإحداثيّات X/Y فوق أو خلف محتوى القالب المتدفّق — لا تستبدله ولا ترتبط
 * بحقول بيانات الـCV. راجع `App\Services\Library\CvTemplateDecor` لتوثيق
 * صيغة كلّ طبقة وقرار وحدة الإحداثيّات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cv_templates', function (Blueprint $table) {
            $table->json('decor_layers')->nullable()->after('ats_options');
        });
    }

    public function down(): void
    {
        Schema::table('cv_templates', function (Blueprint $table) {
            $table->dropColumn('decor_layers');
        });
    }
};
