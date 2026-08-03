<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إرجاع السبب العربيّ من خانة «المصدر» إلى خانته (24.2 · 19.2 · 2.13).
 *
 * **العطل:** `WalletGateway` كان يمرّر وسائطه لدفتر الأستاذ **بالترتيب**، وترتيبه
 * `(…, string $source, ?Model $reference, string $layer, ?string $reason)` —
 * فنزلت جملة السبب في خانة `source` وبقي `reason` فارغًا. النتيجة في القاعدة:
 * صفوفٌ مصدرها «مواجهة حرب #1 — فوز» و«درع تجميد السلسلة» و«إنشاء تحدّي تركيز»،
 * فتتفتّت التقارير التي تجمّع بالمصدر، ويعمى عنها `withinDailyCap()` لأنّه
 * يرشّح بـ`where('source', …)` ⟵ **الحدّ اليوميّ المعلَن في اللوحة بلا أثر**.
 *
 * **ماذا نفعل بالصفوف القائمة؟** لا حذف (2.11-د)، ولا اصطناع حقلٍ غائب —
 * فالتعديل الذي يخترع قيمةً أخطر من الحذف لأنّه لا يبدو حذفًا. فالشرط هنا
 * **اليقين من الصفّ نفسه**: الجملة تُنقَل إلى `reason` (وهي القيمة التي مرّرها
 * المستدعي كسببٍ فعلًا — لا اختراع فيها)، ويُشتَقّ `source` من **`reference_type`
 * المكتوب في الصفّ** وحده، وهو مربوطٌ بكاتبٍ واحدٍ لا غير:
 *
 *   - `Challenge` · `FocusWar` · `WarMatch` ⟵ `FocusWarService` · `WarMatchService` ⟵ `challenge`
 *   - `StreakDay` · `StreakReward`          ⟵ `StreakService`                        ⟵ `streak`
 *
 * وما لا يُشتَقّ بيقين — صفٌّ بلا `reference_type`، أو نوعٌ خارج الخريطة، أو صفٌّ
 * له `reason` مكتوب أصلًا فالنقل يطمسه — **يُترَك كما هو ظاهرًا للعين**، فبقاء
 * العطل مرئيًّا خيرٌ من تغطيته بقيمةٍ مخمَّنة.
 */
return new class extends Migration
{
    /** نوع المرجع ⟵ دلو المصدر — ولكلّ نوعٍ كاتبٌ واحدٌ في الشجرة، فالاشتقاق يقينيّ */
    private const REFERENCE_TO_SOURCE = [
        'App\Models\Challenge' => 'challenge',
        'App\Models\FocusWar' => 'challenge',
        'App\Models\FocusWarMember' => 'challenge',
        'App\Models\WarMatch' => 'challenge',
        'App\Models\StreakDay' => 'streak',
        'App\Models\StreakReward' => 'streak',
    ];

    public function up(): void
    {
        // صفًّا صفًّا بـPHP لا بـSQL: تمييز الجملة العربيّة من المفتاح يختلف بين
        // المحرّكات (GLOB في SQLite · REGEXP في MySQL)، والقاعدة واحدة أيًّا كان.
        DB::table('transactions')
            ->whereIn('reference_type', array_keys(self::REFERENCE_TO_SOURCE))
            ->whereNull('reason')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    if (! $this->looksArabic((string) $row->source)) {
                        continue;
                    }

                    // الجملة تنتقل إلى خانتها، والمفتاح يحلّ محلّها — بلا فقد حرف
                    DB::table('transactions')->where('id', $row->id)->update([
                        'reason' => $row->source,
                        'source' => self::REFERENCE_TO_SOURCE[$row->reference_type],
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * الرجوع لا يعيد الجملة إلى خانة المصدر: إعادتها تعيد العطل نفسه —
     * تقاريرُ متفتّتة وحدٌّ يوميّ أعمى. والسطر محفوظٌ كاملًا في `reason`.
     */
    public function down(): void {}

    /** هل قيمة `source` جملةٌ عربيّة؟ — المفتاح لاتينيّ دائمًا (`challenge` · `streak`) */
    private function looksArabic(string $source): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $source);
    }
};
