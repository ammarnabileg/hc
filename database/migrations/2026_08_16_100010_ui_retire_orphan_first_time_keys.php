<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إزالة مفتاحَي «أوّل مرّة» اليتيمَين نهائيًّا (2.13).
 *
 * هجرة التوحيد (`2026_08_09_120030`) حذفتهما بالفعل، لكنّ `SettingGapSeeder` كان
 * لا يزال يعرّفهما فيعيد زرعهما بعدها في كلّ تشغيلٍ للبذور — فيعود المفتاح إلى
 * لوحة المالك يوهمه أنّ ما يكتبه فيه يُقرَأ، وهو **لا يُقرَأ من أيّ سطر كود**:
 * مصدر «شاشة أوّل مرّة» هو جدول `onboarding_slides` وحده.
 *
 * حُذِف تعريفهما من البذرة، وهذه الهجرة تنظّف التنصيبات التي زُرِعا فيها فعلًا —
 * فلا يبقى في اللوحة مفتاحٌ بلا قارئ.
 */
return new class extends Migration
{
    private const RETIRED = ['ux.first_time.content', 'ux.first_time.default_template'];

    public function up(): void
    {
        DB::table('settings')->whereIn('key', self::RETIRED)->delete();
    }

    public function down(): void
    {
        // لا رجعة: المفتاح الذي لا يقرؤه كود لا يُعاد إنشاؤه (2.13)
    }
};
