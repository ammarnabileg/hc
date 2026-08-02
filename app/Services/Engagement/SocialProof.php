<?php

namespace App\Services\Engagement;

use App\Models\LessonCompletion;
use App\Models\StreakDay;

/**
 * الدليل الاجتماعيّ الحيّ (2.9-7) والمقارنة القريبة (2.9-5).
 *
 * 🛡️ **الحارس الأخلاقيّ أوّلًا:** العدّادات هنا **حقيقيّة** ولا تُجمَّل أبدًا —
 * لا ندرة كاذبة ولا أرقام وهميّة (2.9). ولذلك بالضبط وُضعت **الحدود الدنيا**:
 * الرقم الصغير («أنهى 2 اليوم») يُضعِف الدافع بدل أن يقوّيه، فالدستور لا يأمرنا
 * بتضخيمه بل **بتغيير التأطير** إلى **الريادة**: «كن أوّل من ينهي هذا الدرس
 * اليوم». نقول الصدق دائمًا، لكن نختار أيّ وجهٍ صادقٍ نُبرزه.
 *
 * الحدود المعتمَدة نصًّا: تحت الدرس **20** · نادي الخامسة **10** ·
 * الحروب **3** · نسبة الليدر بورد **20** — وكلّها من `setting()` لا محروقة (2.13).
 */
class SocialProof
{
    /** الحدود الدنيا المعتمَدة في 2.9-7 — مرساةٌ لو غابت الإعدادات */
    private const FLOORS = [
        'lesson' => 20,
        'club_5am' => 10,
        'war' => 3,
        'leaderboard' => 20,
    ];

    private const LEAD_TEXT = [
        'lesson' => 'كن أوّل من ينهي الدرس ده النهارده!',
        'club_5am' => 'كن من أوائل الصاحيين النهارده!',
        'war' => 'كن أوّل محارب في الساحة',
        'leaderboard' => '',
    ];

    private const COUNT_TEXT = [
        'lesson' => 'أنهى :count النهارده',
        'club_5am' => ':count صحيوا معاك',
        'war' => ':count جاهزين دلوقتي',
        'leaderboard' => '',
    ];

    public function enabled(): bool
    {
        return (bool) setting('engagement.social_proof.enabled', true);
    }

    /** الحدّ الأدنى لهذا السياق — فوقه نعرض الرقم، وتحته نؤطّر بالريادة */
    public function floor(string $context): int
    {
        return max(0, (int) setting(
            "engagement.social_proof.{$context}.min",
            self::FLOORS[$context] ?? 0,
        ));
    }

    /**
     * تأطير عدّاد حقيقيّ حسب حدّه.
     *
     * @return array{count:int,floor:int,show:bool,lead:bool,text:string}
     */
    public function frame(string $context, int $count): array
    {
        $count = max(0, $count);
        $floor = $this->floor($context);
        $show = $this->enabled() && $count >= $floor && $floor > 0;

        $text = $show
            ? str_replace(':count', number_format($count), (string) setting(
                "engagement.social_proof.{$context}.count_text",
                self::COUNT_TEXT[$context] ?? ':count',
            ))
            : (string) setting(
                "engagement.social_proof.{$context}.lead_text",
                self::LEAD_TEXT[$context] ?? '',
            );

        return [
            'count' => $count,
            'floor' => $floor,
            'show' => $show,
            'lead' => ! $show,
            'text' => $text,
        ];
    }

    // ------------------------------------------- عدّادات حقيقيّة من قاعدة البيانات

    /** كم متدرّبًا أنهى هذا الدرس **اليوم** فعلًا (2.9-7) */
    public function lessonFinishersToday(int $lessonId, ?int $exceptUserId = null): int
    {
        return (int) LessonCompletion::query()
            ->where('lesson_id', $lessonId)
            ->whereDate('completed_at', now()->toDateString())
            ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId))
            ->count();
    }

    /** كم شخصًا صحا معك في نادي الخامسة **اليوم** (2.9-7) */
    public function clubPeersToday(?int $exceptUserId = null): int
    {
        return (int) StreakDay::query()
            ->where('club_5am', true)
            ->whereDate('day', now()->toDateString())
            ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId))
            ->count();
    }

    /**
     * ⭐ المقارنة الاجتماعيّة القريبة (2.9-5): «أفضل من X%».
     *
     * تحت حدّ نطاق المقارنة (**20**) نعرض **الترتيب فقط** — لأنّ «أفضل من 66%»
     * وسط ثلاثة أشخاص رقمٌ صادقٌ لكنّه **مضلِّل**، وهو ما تمنعه 2.9.
     *
     * @return array{show:bool,percent:int,text:string}
     */
    public function betterThanPercent(int $rank, int $total): array
    {
        $floor = $this->floor('leaderboard');
        $show = $this->enabled() && $total >= $floor && $rank >= 1 && $total > 1;

        // نسبة مَن هم خلفك من نطاق المقارنة كلّه — حساب مباشر بلا تدوير لأعلى
        $percent = $show ? (int) floor((($total - $rank) / max(1, $total - 1)) * 100) : 0;

        return [
            'show' => $show,
            'percent' => $percent,
            'text' => str_replace(
                ':percent',
                (string) $percent,
                (string) setting('engagement.social_proof.better_than_text', 'أنت أفضل من :percent% من المتدرّبين في النطاق ده'),
            ),
        ];
    }

    /**
     * ⭐ «محتاج N XP تتخطّى [منافس قريب]» (2.9-5).
     *
     * الفارق **حقيقيّ ومحسوب من الصفّ الذي فوقك مباشرةً**، وواحدٌ يُضاف ليصير
     * التخطّي فعليًّا لا تعادلًا.
     *
     * @return array{show:bool,gap:int,rival:?string,text:string}
     */
    public function gapToNext(int $myScore, ?int $rivalScore, ?string $rivalName): array
    {
        $show = $this->enabled() && $rivalScore !== null && $rivalName !== null && $rivalScore >= $myScore;
        $gap = $show ? max(1, $rivalScore - $myScore + 1) : 0;

        return [
            'show' => $show,
            'gap' => $gap,
            'rival' => $show ? $rivalName : null,
            'text' => $show
                ? str_replace(
                    [':gap', ':rival'],
                    [number_format($gap), (string) $rivalName],
                    (string) setting('engagement.social_proof.gap_text', 'محتاج :gap XP تتخطّى :rival'),
                )
                : '',
        ];
    }
}
