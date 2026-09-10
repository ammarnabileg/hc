<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ثلاثة مفاتيح مكتبةٍ ميّتة من `settings:coverage --dead` (2.13) — لا قارئ
 * واحد يقرؤها في `app/` رغم أنّها مزروعة ومرحَّلة لمجموعة `library` بهجرة
 * `2026_08_06_100010` (REKEY_GROUP هناك نقلت مجموعتها فقط، ولم تدمجها ولا
 * حذفتها — كانت لا تزال بلا قارئ وقتها).
 *
 * - `library.watermark.font_size` و`library.watermark.opacity_percent`:
 *   العلامة المائيّة الفعليّة (20.3) تُبنى في `PageWatermark.php:91` و
 *   `resources/views/library/read.blade.php:147-148`، وكلاهما يقرأ
 *   `reader.watermark.opacity_percent`/`reader.watermark.font_size_px` —
 *   مزروعتان فعلًا في `LibraryDemoSeeder` بمجموعة `reader`. فالمفتاحان هنا
 *   تكرارٌ باسمٍ مختلف لا يقرؤه أحد، تمامًا كحالات `MERGE` في الهجرة
 *   السابقة — فيُدمَجان بنفس منطقها (تخصيص المالك، إن وُجد، ينتقل للمعتمَد).
 * - `library.reader.session_minutes`: لا قارئ له إطلاقًا ولا بندٌ دستوريّ
 *   يطلبه — 20.3 (دستور اساسي.md:3607-3616) يذكر فقط معاينة الصفحات
 *   والعلامة المائيّة و«تحديث الملفّ يصل للمالك تلقائيًّا»، بلا أيّ مهلة
 *   جلسة. يتيمٌ حقيقيّ بلا معتمَد ولا مواصفة، فيُحذَف مباشرةً كحالات
 *   `DEAD_KEYS` في هجرة `2026_09_04_100010`.
 */
return new class extends Migration
{
    /** المفتاح المهجور ⟵ المفتاح المعتمَد (نفس منطق هجرة 2026_08_06_100010) */
    private const MERGE = [
        'library.watermark.font_size' => 'reader.watermark.font_size_px',
        'library.watermark.opacity_percent' => 'reader.watermark.opacity_percent',
    ];

    /** لا معتمَد له ولا مواصفة دستوريّة — يُحذَف مباشرةً */
    private const DEAD_KEYS = [
        'library.reader.session_minutes',
    ];

    public function up(): void
    {
        foreach (self::MERGE as $orphanKey => $canonicalKey) {
            $orphan = DB::table('settings')->where('key', $orphanKey)->first();

            if (! $orphan) {
                continue;
            }

            $canonical = DB::table('settings')->where('key', $canonicalKey)->first();

            if (! $canonical) {
                // المعتمَد غير مزروع بعد: نرقّي المهجور نفسه بدل حذف قيمةٍ بلا بديل
                DB::table('settings')->where('key', $orphanKey)->update(['key' => $canonicalKey]);

                continue;
            }

            // ⭐ لا نضيع تخصيص المالك: لو عدّل المهجور ولم يمسّ المعتمَد، القيمة تنتقل
            $ownerTouchedOrphan = $orphan->value !== $orphan->default_value;
            $canonicalUntouched = $canonical->value === $canonical->default_value;

            if ($ownerTouchedOrphan && $canonicalUntouched) {
                DB::table('settings')->where('key', $canonicalKey)->update(['value' => $orphan->value]);
            }

            DB::table('setting_overrides')->where('setting_id', $orphan->id)->delete();
            DB::table('settings')->where('key', $orphanKey)->delete();
        }

        $deadIds = DB::table('settings')->whereIn('key', self::DEAD_KEYS)->pluck('id');

        DB::table('setting_overrides')->whereIn('setting_id', $deadIds)->delete();
        DB::table('settings')->whereIn('key', self::DEAD_KEYS)->delete();
    }

    /**
     * الترحيل دمجٌ وحذفٌ لا نقل: بعد الدمج لا نعرف أيّ مفتاح جاء من أين،
     * وإعادة مصدرَي حقيقة عمدًا أو إحياء يتيمٍ حقيقيّ ليست «تراجعًا» بل
     * إعادةٌ للعطل. فالرجوع لا يفعل شيئًا، والاسترجاع الصحيح من نسخة احتياطيّة.
     */
    public function down(): void
    {
        // بلا عكس — انظر التعليق أعلاه.
    }
};
