<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تنظيف «مصادر كسب XP» من الصفوف التي لا يقرؤها الكود (2.13 · 7 · 7.2 · 7.6).
 *
 * كان جدول `xp_rules.earn` يُشحَن بصفوفٍ **بلا مستدعٍ**: يفتح المالك لوحته
 * فيرى «حضور نادي الخامسة: 100 XP · حدّ يوميّ 1» ويضبطها، ولا يتغيّر شيء —
 * لأنّ XP النادي يأتي من **سلّم الحضور المتدرّج** وحده (7.2). وكذلك «دعوة
 * ناجحة» ومكافأتها **تذكرة لا XP** (7.6)، و«اختبار تمهيديّ» ومكافأته تُضبَط
 * **لكلّ سؤال على حدة** (7.1)، و«يوم ستريك» و«لعبة» و«فوز حرب» و«رسالة
 * إيجابيّة» ولكلٍّ مصدره في مكانه.
 *
 * وإعدادٌ بلا أثر أسوأ من غيابه: المالك يظنّ أنّه ضبط النظام وقد ضبط لا شيء.
 * فتُرفَع هذه الصفوف مرّةً واحدة، **ويبقى ما أضافه المالك بيده كما هو** —
 * والشاشة تعلّم أيّ صفٍّ بلا مستهلك بتحذيرٍ صريح بدل الحذف الصامت.
 */
return new class extends Migration
{
    /** ما شحنّاه نحن ولا يقرؤه أحد — والمفتاح الذي أضافه المالك لا يُمَسّ */
    private const SHIPPED_WITHOUT_CONSUMER = [
        'five_am_club',
        'streak.day',
        'referral.success',
        'placement_test',
        'game.session',
        'war.win',
        'positive_message',
    ];

    public function up(): void
    {
        $rows = $this->rows();

        if ($rows === null) {
            return;
        }

        $kept = array_values(array_filter(
            $rows,
            fn ($row) => ! in_array((string) ($row['key'] ?? ''), self::SHIPPED_WITHOUT_CONSUMER, true),
        ));

        if (count($kept) === count($rows)) {
            return;
        }

        DB::table('settings')->where('key', 'xp_rules.earn')->update([
            'value' => json_encode($kept, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    /**
     * الرجوع لا يعيد صفوفًا ميتة — إعادتها تعيد الوهم نفسه. والتراجع هنا
     * لا يخسر شيئًا: الصفوف لم تكن تؤثّر في سلوكٍ واحد.
     */
    public function down(): void {}

    /** @return list<array<string,mixed>>|null */
    private function rows(): ?array
    {
        $value = DB::table('settings')->where('key', 'xp_rules.earn')->value('value');

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : null;
    }
};
