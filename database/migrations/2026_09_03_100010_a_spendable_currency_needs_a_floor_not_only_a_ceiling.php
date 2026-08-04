<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * قاعُ العملات القابلة للصرف (15.2-4 · 19.3).
 *
 * **النصّ الحاكم — 15.2-4:** «**بوابة ≥ 12 تذكرة** للطرفين، **والتذاكر لا تنزل
 * تحت الصفر**».
 *
 * وكان عمود `min_value` مضبوطًا لـ`rep` (−10) و`hours` و`usd` (0) وحدها، أمّا
 * **الكوينز والتذاكر** فبقيا `NULL` أي **بلا قاع**: خصم 12 من رصيد 3 كان يعطي
 * رصيدًا **−9** (مُثبَتٌ بالتشغيل). والقاع **بيانٌ لا رقمٌ محروق في الكود**
 * (2.13) — لذلك يسكن العمود نفسه الذي يقرؤه `LedgerService`، ولا يُكتَب `0`
 * في أيّ شرطٍ داخل الخدمة.
 *
 * و`xp` تبقى بلا قاع عن قصد: هي **تراكميّة غير قابلة للصرف**، ولا تُخصَم آليًّا
 * أصلًا (13.4-ن)، فحارسها حارسٌ آخر لا هذا.
 */
return new class extends Migration
{
    /** العملات القابلة للصرف التي بقيت بلا قاع، وقاعُها الصحيح */
    private const FLOORS = ['coins' => 0, 'tickets' => 0];

    public function up(): void
    {
        foreach (self::FLOORS as $code => $floor) {
            DB::table('currencies')
                ->where('code', $code)
                ->whereNull('min_value')
                ->update(['min_value' => $floor]);
        }
    }

    public function down(): void
    {
        DB::table('currencies')
            ->whereIn('code', array_keys(self::FLOORS))
            ->update(['min_value' => null]);
    }
};
