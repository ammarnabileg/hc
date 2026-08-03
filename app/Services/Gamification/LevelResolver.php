<?php

namespace App\Services\Gamification;

use App\Models\Level;
use App\Models\User;

/**
 * ⭐ **مستوى الحساب وXP — المصدر الواحد الحاكم** (7 · 7.3 · 10 · 10.1 · 24.5-أ).
 *
 * **النصّ الحاكم (10.1 — «✅ معتمد»):**
 * «القاعدة: الزيادة للوصول للمستوى N = `base + (N − 2) × step`، والرقم في الجدول
 * = الإجمالي التراكمي للوصول للمستوى. **المستويات مفتوحة بلا سقف بنفس المعادلة**.»
 * ومسار «مستوى الحساب» وحدته **XP** و`base = 500` و`step = 250`.
 * وقبله في 10: «**مستوى الحساب** — مبني على نقاط **XP** ✅ … عتبات المستويات ⇐
 * **راجع 10.1 (✅ معتمد)**». فمستوى الحساب دالّةٌ واحدة في XP، لا أكثر.
 *
 * **العطل الذي يُصلحه هذا الصنف (ن-2):** كان للرقم الواحد **ثلاثة حُسّاب**:
 *   1. `DashboardService::level()` و`LevelResolver::levelFor()` القديمة تقرآن
 *      عتبات عمود `levels.min_xp` (0 · 500 · 1500 · 3500 · 7000 …) — عتباتٌ لا
 *      تمتّ لـ10.1 بصلة، **ومسقوفة عند الصفّ الثامن** خلافًا لنصّ «بلا سقف»؛
 *   2. `AchievementTracks` تحسب صيغة 10.1 الصحيحة — فالرادار يقول «مستوى 4»
 *      بينما كارت الـKPI يقول «المستوى 3» **لنفس المستخدم في اللحظة نفسها**؛
 *   3. عمود `users.level` يُكتَب في السيدرز برقمٍ اعتباطيّ (`3 + $index % 4`)
 *      فيقرؤه هيدر البروفايل ووثيقة الإفادة رقمًا رابعًا لا صلة له بـXP.
 *
 * **القاعدة من اليوم:** الحقيقة = **صيغة 10.1 على XP**؛ وجدول `levels` صار
 * **معجم أسماء** لا مصدر عتبات (فالأسماء بشريّة والعتبات دستوريّة)؛ وعمود
 * `users.level` **نسخة مخبَّأة** تُزامَن ولا تُقرَأ كمصدرٍ مستقلّ.
 */
class LevelResolver
{
    /** مفتاح مسار «مستوى الحساب» في مصفوفة مسارات 10.1 */
    public const TRACK = 'account';

    // ------------------------------------------------------------ العتبات (10.1)

    /** `base` مسار الحساب — إعدادٌ لا رقم محروق (2.13)، والافتراضيّ نصّ 10.1 */
    public function base(): int
    {
        return max(1, (int) setting('dashboard.achievements.'.self::TRACK.'.base', 500));
    }

    /** `step` مسار الحساب — إعدادٌ لا رقم محروق (2.13)، والافتراضيّ نصّ 10.1 */
    public function step(): int
    {
        return max(1, (int) setting('dashboard.achievements.'.self::TRACK.'.step', 250));
    }

    /**
     * ⭐ **صيغة 10.1 نفسها** — تخدم المسارات الخمسة كلّها لا مسار الحساب وحده،
     * فتقرؤها `AchievementTracks` من هنا ولا تكتبها ثانيةً.
     *
     * الزيادة للوصول للمستوى N = `base + (N−2) × step`، والعتبة = مجموع الزيادات.
     * والحلقة تنتهي حتمًا لأنّ الزيادة موجبة دائمًا (`base ≥ 1`)، فلا سقفَ يوقف
     * العدّ عند رقمٍ ما — «المستويات مفتوحة بلا سقف بنفس المعادلة» (10.1).
     *
     * @return array{level:int, current_at:int, next_at:int, fraction:float, percent:int}
     */
    public function progress(int $value, int $base, int $step): array
    {
        $base = max(1, $base);
        $step = max(1, $step);

        $level = 1;
        $cumulative = 0;

        while (true) {
            $needed = $cumulative + $base + ($level - 1) * $step;

            if ($value < $needed) {
                $span = $needed - $cumulative;
                $fraction = $span > 0 ? ($value - $cumulative) / $span : 0.0;
                $fraction = max(0.0, min(1.0, $fraction));

                return [
                    'level' => $level,
                    'current_at' => $cumulative,
                    'next_at' => $needed,
                    'fraction' => $fraction,
                    'percent' => (int) max(0, min(100, round($fraction * 100))),
                ];
            }

            $cumulative = $needed;
            $level++;
        }
    }

    // ------------------------------------------------------------ XP: مصدرٌ واحد

    /**
     * ⭐ **رصيد XP — المصدر الواحد**: دفتر المحفظة أوّلًا (19.2)، وعمود `users.xp`
     * احتياطًا لمن لم تُفتَح محفظته بعد. وكلّ عارضٍ يقرأ من هنا فلا يختلف رقمان.
     */
    public function xpFor(User $user): int
    {
        $code = (string) setting('wallet.currency.xp_code', 'xp');

        $balance = $user->balances()
            ->whereHas('currency', fn ($q) => $q->where('code', $code))
            ->value('balance');

        return (int) ($balance ?? $user->xp ?? 0);
    }

    // ------------------------------------------------------------ المستوى

    /** رقم المستوى الموافق لهذا الـXP — بصيغة 10.1 وحدها */
    public function levelFor(int $xp): int
    {
        return $this->progress($xp, $this->base(), $this->step())['level'];
    }

    /**
     * اسم المستوى (مبتدئ · متعلّم · …) من جدول `levels` — **معجم أسماء لا عتبات**.
     * وما فوق آخر اسمٍ معرَّف يرث اسمه، فالمستويات بلا سقف والأسماء منتهية.
     */
    public function nameFor(int $level): string
    {
        return (string) (Level::query()
            ->where('level', '<=', max(1, $level))
            ->orderByDesc('level')
            ->value('name_ar') ?? '');
    }

    /**
     * صورة المستوى الكاملة لقيمة XP — **هذا ما يعرضه كلّ عارض** (KPI · الرادار ·
     * السايد بار · البروفايل)، فلا أحد يعيد الحساب عنده.
     *
     * @return array{level:int, name:string, xp:int, current_at:int, next_at:int, fraction:float, percent:int}
     */
    public function forXp(int $xp): array
    {
        $progress = $this->progress($xp, $this->base(), $this->step());

        return [
            'level' => $progress['level'],
            'name' => $this->nameFor($progress['level']),
            'xp' => $xp,
            'current_at' => $progress['current_at'],
            'next_at' => $progress['next_at'],
            'fraction' => $progress['fraction'],
            // ⭐ نسبة بار XP — **محسوبة من بيانات المستخدم** لا قيمة ثابتة للجميع
            'percent' => $progress['percent'],
        ];
    }

    /** صورة المستوى لمستخدمٍ بعينه — XP من المصدر الواحد ثمّ الصيغة الواحدة */
    public function forUser(User $user): array
    {
        return $this->forXp($this->xpFor($user));
    }

    /**
     * مزامنة العمود المخبَّأ `users.level` مع الحقيقة.
     *
     * لماذا نُبقيه أصلًا: يقرؤه ما ليس بيدنا الآن (هيدر البروفايل · الإفادة ·
     * تقارير الأدمن · تصدير البيانات)، فبقاؤه **مطابقًا** يمنع رقمًا رابعًا.
     *
     * @return bool هل تغيّر المستوى فعلًا؟ (لحظة ترقٍّ تستحقّ احتفالًا — 2.14)
     */
    public function sync(User $user): bool
    {
        $level = $this->levelFor($this->xpFor($user));

        if ((int) $user->level === $level) {
            return false;
        }

        $user->forceFill(['level' => $level])->saveQuietly();

        return true;
    }
}
