<?php

namespace App\Services\Store;

use App\Models\Bundle;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * ⭐ **مُصنِّع لاندنج بيدج البندل** (18 — والبند الذي كان مؤجَّلًا في 22، وأمر المالك ببنائه).
 *
 * كلّ رقمٍ تعرضه الصفحة يُولَد هنا **من قاعدة البيانات لحظةَ العرض**، ولا يُكتَب
 * في أيّ فورم. وهذا ليس ترتيبًا هندسيًّا بل امتثالٌ لنصّين:
 *
 *  > «Anchoring: إظهار السعر الطبيعيّ مشطوبًا + **القيمة الإجماليّة محسوبةً
 *  >  تلقائيًّا** مقابل سعر البندل» (18)
 *  > «ممنوع Dark Patterns — لا ندرة كاذبة … **لا أرقام وهمية**» (2.9)
 *
 * ولذلك ثلاثة أشياء **لا تخرج من هنا إلّا بشرطها**:
 *  - **«وفّرت X»**: لا تُعرَض إطلاقًا إن كانت القيمة الإجماليّة ≤ سعر الباقة —
 *    لا صفرًا ولا رقمًا سالبًا ولا شطبًا. «كلّ عرضٍ بقيمته الحقيقيّة» (21.1-د).
 *  - **العدّاد**: `null` ما لم يكن للبندل `available_until` فعليّ في المستقبل —
 *    فلا يوجد في الـHTML أصلًا، لا مخفيًّا ولا مصفَّرًا (2.9-10 · 21.1-د).
 *  - **«باقي N مقعدًا»**: `null` ما لم يكن `purchase_limit` مضبوطًا، والرقم
 *    **معدودٌ من الطلبات المدفوعة** لا مكتوبًا.
 *
 * ولا شهادات عملاء ولا آراء ولا «شاهد الآن X شخصًا»: لا جدول لأيٍّ منها في
 * المنصّة، ورأيٌ مفبرك أو عدّاد مخترَع خرقٌ مباشر لـ«لا أرقام وهمية» (2.9).
 * والإثبات هنا **حقيقيّ**: ما بداخل الباقة بقيمته · الشهادة المعتمَدة بشرطها
 * (8) · عدد العناصر والدروس · الوصول الدائم.
 */
class BundleLanding
{
    public function __construct(
        private readonly StoreCatalog $catalog,
        private readonly PricingService $pricing,
    ) {}

    /**
     * كلّ ما تحتاجه الصفحة في مصفوفةٍ واحدة — والقالب يعرض ولا يحسب.
     *
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    public function build(Bundle $bundle, ?User $user, array $quote): array
    {
        $items = $this->items($bundle);
        $totalValue = round($items->sum(fn (array $line) => (float) $line['list_value']), 2);
        $price = (float) $quote['total'];

        /*
         | ⭐ **إن كانت القيمة الإجماليّة ≤ سعر الباقة فلا «وفّرت» إطلاقًا.**
         | الفرق يُحسَب ثمّ يُختبَر: موجبٌ صريحٌ فقط يفتح الشطب والنسبة والسطر —
         | وإلّا فالصفحة تبيع بلا مرساةٍ أصلًا، وهو أصدق من مرساةٍ صفريّة.
         */
        $savings = round($totalValue - $price, 2);
        $hasSavings = $savings > 0 && $totalValue > 0;

        return [
            'bundle' => $bundle,

            // ------------------------------------------------ الهيرو (نصوصه من الأدمن مع ارتداد)
            'headline' => $this->text($bundle->landing_headline) ?? $bundle->name_ar,
            'promise' => $this->text($bundle->landing_promise) ?? $this->text($bundle->description),
            'has_custom_headline' => $this->text($bundle->landing_headline) !== null,

            // ------------------------------------------------ الميزان المحسوب (18)
            'items' => $items,
            'bonuses' => $items->where('is_bonus', true)->values(),
            'total_value' => $totalValue,
            'price' => $price,
            'savings' => $hasSavings ? $savings : 0.0,
            'has_savings' => $hasSavings,
            'percent_off' => $hasSavings ? (int) round($savings / $totalValue * 100) : 0,
            // توجّلا [العرض] في 24 — والشطب لا يقع إلّا مع توفيرٍ حقيقيّ مهما كان التوجّل
            'show_strikethrough' => $hasSavings && $bundle->show_anchor_strikethrough,
            'show_total_value' => $hasSavings && $bundle->show_total_value,

            // ------------------------------------------------ قوائم يحرّرها الأدمن
            'outcomes' => $this->list($bundle->landing_outcomes),
            'fit_for' => $this->list($bundle->landing_fit_for),
            'not_fit_for' => $this->list($bundle->landing_not_fit_for),
            'faq' => $this->faq($bundle),

            // ------------------------------------------------ إثباتٌ حقيقيّ لا مفبرك
            'facts' => $this->facts($items),
            'certificate' => $this->certificate($items),

            // ------------------------------------------------ الندرة الحقيقيّة وحدها
            'countdown_ends_at' => $this->countdownEndsAt($bundle),
            'seats_left' => $this->seatsLeft($bundle),
            'purchases' => $this->purchases($bundle),

            'owned' => (bool) $quote['owned'],
        ];
    }

    // ================================================================ العناصر والبونص

    /**
     * عناصر الباقة بقيمها **وبعلَم البونص** — والـOverride محصورٌ في هذه الصفحة (18).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function items(Bundle $bundle): Collection
    {
        return $this->catalog->includes('bundle', $bundle);
    }

    /**
     * سطر البونص بقالبه **المنصوص حرفيًّا** في 18:
     * «🎁 بونص: [العنصر] بقيمة X — مجّانًا مع الباقة».
     * وقالب البندل يسبق قالب الإعدادات، وكلاهما يحرّره الأدمن (2.13).
     */
    public function bonusLine(Bundle $bundle, array $line): string
    {
        $template = $this->text($bundle->bonus_text_template)
            ?? (string) setting('store.bundle.bonus_text', '🎁 بونص: {item} بقيمة {amount} — مجّانًا مع الباقة');

        return str_replace(
            ['{item}', '{amount}'],
            [$line['title'], Coins::label($line['list_value'])],
            $template,
        );
    }

    // ================================================================ الإثبات الحقيقيّ

    /**
     * صفّ الثقة — **حقائق تُقرأ من القاعدة** لا وعودًا:
     * عدد العناصر · عدد الدروس إن وُجدت · الوصول الدائم · بلا اشتراك.
     * ولا يُذكر رقمٌ لا نملك مصدره: لو لا دروس فلا سطر دروس أصلًا.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function facts(Collection $items): array
    {
        $facts = [];

        $facts[] = str_replace(
            '{count}',
            (string) $items->count(),
            (string) setting('store.bundle.fact_items', '{count} عناصر في الباقة'),
        );

        $lessons = $this->lessonCount($items);

        if ($lessons > 0) {
            $facts[] = str_replace(
                '{count}',
                (string) $lessons,
                (string) setting('store.bundle.fact_lessons', '{count} درسًا مسجّلًا'),
            );
        }

        // 19.4 يمنع الاسترجاع، فالبديل الأمين **وصولٌ دائم** لا «ضمان استرجاع»
        $facts[] = (string) setting('store.bundle.fact_lifetime', 'وصول دائم — من غير اشتراك ولا تجديد');

        return $facts;
    }

    /** @param  Collection<int, array<string, mixed>>  $items */
    private function lessonCount(Collection $items): int
    {
        $courseIds = $this->courseIds($items);

        if ($courseIds === []) {
            return 0;
        }

        return (int) DB::table('lessons')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->whereIn('sections.course_id', $courseIds)
            ->count();
    }

    /**
     * ⭐ **الشهادة بشرطها** (8): «تُصدَر الشهادة **فقط بعد اجتياز الامتحان النهائي
     * للتدريب** بدرجة 70%+ (أو الدرجة المحددة من لوحة الإدارة)».
     *
     * فالبلوك لا يظهر إلّا إن كان في الباقة تدريبٌ له **امتحانٌ مفعَّل فعلًا**،
     * ويُعرَض معه **شرطه ودرجته من الامتحان نفسه** — فلا نَعِد بشهادةٍ بلا شرطها،
     * ولا نكتب «70%» رقمًا محروقًا بينما الأدمن ضبط غيره.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{courses:array<int,string>,pass_score:int}|null
     */
    private function certificate(Collection $items): ?array
    {
        $courseIds = $this->courseIds($items);

        if ($courseIds === []) {
            return null;
        }

        $exams = Exam::query()
            ->where('examable_type', Course::class)
            ->whereIn('examable_id', $courseIds)
            ->where('is_active', true)
            ->get(['examable_id', 'pass_score']);

        if ($exams->isEmpty()) {
            return null;
        }

        $named = Course::query()
            ->whereIn('id', $exams->pluck('examable_id')->all())
            ->pluck('name_ar')
            ->all();

        return [
            'courses' => array_values($named),
            'pass_score' => (int) $exams->max('pass_score'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, int>
     */
    private function courseIds(Collection $items): array
    {
        return $items->where('type', 'course')->pluck('itemable_id')->filter()->map('intval')->values()->all();
    }

    // ================================================================ الندرة — الحقيقيّة وحدها

    /**
     * ⭐ نهاية العدّاد أو **`null`**. و`null` تعني أنّ القالب **لا يطبع عدّادًا
     * إطلاقًا** — لا صفرًا ولا عنصرًا مخفيًّا (2.15-أ-7: المحظور يُخفى لا يُعطَّل).
     *
     * والتاريخ **تاريخ البندل نفسه**: لا يُعاد ضبطه عند التحديث، ولا يبدأ من
     * جديد لكلّ زائر — فذلك هو «العدّاد الوهميّ» بعينه (21.1-د).
     */
    public function countdownEndsAt(Bundle $bundle): ?Carbon
    {
        $endsAt = $bundle->available_until;

        if (! $endsAt || $endsAt->isPast()) {
            return null;
        }

        return $endsAt;
    }

    /**
     * ⭐ «باقي N مقعدًا» — **معدودةٌ من الطلبات المدفوعة** لا مكتوبة، و`null`
     * إن لم يضبط الأدمن حدّ شراء. ولا رقم سالب: المستنفَد صفر.
     */
    public function seatsLeft(Bundle $bundle): ?int
    {
        if (! $bundle->purchase_limit) {
            return null;
        }

        return max($bundle->purchase_limit - $this->purchases($bundle), 0);
    }

    /** عدد مرّات شراء الباقة فعلًا — من `order_items` المدفوعة (عمود «المشتريات» في 24) */
    public function purchases(Bundle $bundle): int
    {
        return (int) OrderItem::query()
            ->where('purchasable_type', Bundle::class)
            ->where('purchasable_id', $bundle->id)
            ->whereIn('order_id', Order::query()->where('status', 'paid')->select('id'))
            ->count();
    }

    /** هل الباقة داخل نافذة إتاحتها الآن؟ ([الإتاحة] في 24 — والقاعدة العامّة في 5) */
    public function withinWindow(Bundle $bundle): bool
    {
        if ($bundle->available_from && $bundle->available_from->isFuture()) {
            return false;
        }

        if ($bundle->available_until && $bundle->available_until->isPast()) {
            return false;
        }

        return true;
    }

    /** المقاعد نفدت؟ — حدٌّ حقيقيّ يُقفِل الشراء، لا لافتةً تخوّف (2.9) */
    public function soldOut(Bundle $bundle): bool
    {
        return $this->seatsLeft($bundle) === 0;
    }

    // ================================================================ مساعدات

    /** أسئلة هذا البندل، وإلّا فالافتراضيّ العامّ من الإعدادات (2.13) */
    private function faq(Bundle $bundle): array
    {
        $own = $this->list($bundle->landing_faq, ['q', 'a']);

        if ($own !== []) {
            return $own;
        }

        $fallback = setting('store.bundle.faq', []);

        return $this->list(is_array($fallback) ? $fallback : [], ['q', 'a']);
    }

    /**
     * قائمةٌ نظيفة: تُسقِط الفراغ فلا يظهر بلوكٌ بعنوانٍ وقائمةٍ خاوية (2.15).
     *
     * @param  array<int, string>  $requiredKeys
     */
    private function list(mixed $value, array $requiredKeys = []): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $row) {
            if ($requiredKeys === []) {
                $line = trim((string) $row);

                if ($line !== '') {
                    $rows[] = $line;
                }

                continue;
            }

            $first = $requiredKeys[0];

            if (is_array($row) && trim((string) ($row[$first] ?? '')) !== '') {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
