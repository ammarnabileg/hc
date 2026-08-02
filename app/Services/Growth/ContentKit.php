<?php

namespace App\Services\Growth;

use App\Models\ImageTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * ⭐ الكارت الأسبوعيّ (21.2-د) و**حزمة محتوى المتطوّعين** (21.2-هـ).
 *
 * المبدأ في الاثنين واحد: **لا يُنشَأ محتوًى جديد** — الاستوديو (12.14) قائم،
 * وروابط الدعوة قائمة، وكلّ ما نفعله تجميعُهما في **حزمة جاهزة للنشر**:
 *  · الكارت الأسبوعيّ: معلومة/نصيحة تدور بدوريّة معتمَدة، بهويّة المنصّة، بلا مصمّم.
 *  · حزمة المتطوّع: رابط دعوته الشخصيّ + نصوص جاهزة + بطاقات — «شبكة موزّعين
 *    قائمة بالفعل تُستثمَر».
 *
 * ⭐ وكلّ رابطٍ في الحزمة موسومٌ بـUTM (21.2-ح) وإلّا لم نعرف عائد القناة.
 */
class ContentKit
{
    public function __construct(private readonly UtmBuilder $utm) {}

    /** نصائح الكارت الأسبوعيّ — إعدادٌ يُحرَّر من لوحة الإدارة (21.2-ي) */
    public function tips(): array
    {
        $configured = setting('growth.weekly_card.tips');

        if (is_array($configured) && $configured !== []) {
            return array_values(array_map(fn ($v) => (string) $v, $configured));
        }

        return [
            'ذاكر ٢٥ دقيقة وارتاح ٥ — العقل بيثبّت المعلومة في الراحة مش في الزحمة.',
            'اكتب اللي فهمته بكلامك إنت. لو عرفت تشرحه، يبقى فهمته.',
            'الاستمرار أهمّ من الشدّة: نصّ ساعة كلّ يوم أنفع من يوم كامل في الأسبوع.',
            'راجع درس امبارح قبل ما تبدأ درس النهارده — دقيقتين بيوفّروا ساعة.',
            'اسأل بدري. السؤال المتأخّر بيتكلّف وقت، والسؤال البدري بيوفّره.',
        ];
    }

    /** دوريّة الكارت: كم يومًا يبقى الكارت نفسه؟ (21.2-ي) */
    public function periodDays(): int
    {
        return max((int) setting('growth.weekly_card.period_days', 7), 1);
    }

    /**
     * كارت هذه الدورة — يدور تلقائيًّا بلا تدخّل، فالمحتوى منتظم بلا مصمّم.
     *
     * @return array{index:int, tip:string, from:Carbon, to:Carbon}
     */
    public function currentCard(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $tips = $this->tips();
        $days = $this->periodDays();

        $bucket = (int) floor($now->copy()->startOfDay()->timestamp / ($days * 86400));
        $index = count($tips) > 0 ? $bucket % count($tips) : 0;

        $from = Carbon::createFromTimestamp($bucket * $days * 86400)->startOfDay();

        return [
            'index' => $index,
            'tip' => $tips[$index] ?? '',
            'from' => $from,
            'to' => $from->copy()->addDays($days - 1)->endOfDay(),
        ];
    }

    /** قوالب الاستوديو المتاحة للمتطوّعين — نستهلك 12.14 ولا نبني استوديو ثانيًا */
    public function studioTemplates()
    {
        return ImageTemplate::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->whereIn('audience', (array) setting('growth.volunteer_kit.audiences', ['volunteers', 'everyone']))
            ->orderBy('name')
            ->limit((int) setting('growth.volunteer_kit.templates_limit', 8))
            ->get(['id', 'name', 'purpose', 'width_px', 'height_px']);
    }

    /**
     * ⭐ نصوص جاهزة للنشر — قابلة للتعديل، وبلا مبالغة ولا ندرة مزيّفة (2.9).
     * و`{link}` تُستبدَل برابط المتطوّع الموسوم.
     *
     * @return array<int,array{title:string, body:string}>
     */
    public function scripts(string $link): array
    {
        $configured = setting('growth.volunteer_kit.scripts');

        $rows = is_array($configured) && $configured !== [] ? $configured : [
            ['title' => 'رسالة واتساب قصيرة', 'body' => "لو بتدوّر على تدريب عربيّ جادّ ومجّانيّ التفعيل، جرّب من هنا:\n{link}"],
            ['title' => 'منشور لينكدإن', 'body' => "بتعلّم على منصّة عربيّة بتشتغل بنظام: تدريب ⟵ امتحان ⟵ شهادة بكود تحقّق.\nلو مهتمّ، الرابط ده هيوصّلك:\n{link}"],
            ['title' => 'ستوري', 'body' => "بنبدأ دفعة جديدة — لو ناوي تتعلّم حاجة جديدة الشهر ده، ده مكانك:\n{link}"],
        ];

        return array_values(array_map(fn ($row) => [
            'title' => (string) ($row['title'] ?? ''),
            'body' => str_replace('{link}', $link, (string) ($row['body'] ?? '')),
        ], $rows));
    }

    /** رابط المتطوّع الموسوم — وهو ما يجعل «لوحة مصادر الاكتساب» تعرف عائده */
    public function volunteerLink(User $user): string
    {
        $param = (string) setting('referral.link.param', 'offer');
        $base = url((string) setting('referral.link.path', '/register')).'?'.http_build_query([$param => $user->code]);

        return $this->utm->tag($base, 'volunteer_kit', 'volunteer_distribution', (string) $user->code);
    }
}
