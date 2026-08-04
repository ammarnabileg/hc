<?php

namespace App\Services\Store;

use App\Models\Bundle;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Ads\Consent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **مُصنِّع لاندنج بيدج البندل** (18 — والبند الذي كان مؤجَّلًا في 22، وأمر المالك ببنائه).
 *
 * ثلاث مسؤوليّات لا رابعة:
 *
 * **1) الوراثة الحيّة.** أمرُ المالك: «خلّي أيّ نصوص وأيّ سكشن في صفحة البندل
 *    قابل للتعديل من إعدادات نفس البندل». فلكلّ نصٍّ مفتاحٌ في `TEXTS`، ولكلّ
 *    سكشن مفتاحٌ في `SECTIONS`، **وقاعدة الحلّ واحدة**: `text()` و`sectionVisible()`
 *    — لا منطقَ مكرّرًا ولا `setting()` في القالب أصلًا (يحرسه
 *    `BundleLandingInheritanceTest`).
 *
 *    ⚠️ **ولا يُنسَخ عامٌّ إلى خاصّ أبدًا.** الحقل الفارغ يعني «ورِث»، والقيمة
 *    تُحسَب لحظةَ العرض — فتعديل المالك للنصّ العامّ يصل **كلّ** بندلٍ موروث
 *    فورًا. ولو نُسِخ لصار كلّ بندلٍ لقطةً مجمّدة، والإعداد العامّ زينةً بلا أثر
 *    وهو نقضُ 2.13 من داخلها.
 *
 * **2) الأرقام محسوبة لا مكتوبة.**
 *    > «Anchoring: إظهار السعر الطبيعيّ مشطوبًا + **القيمة الإجماليّة محسوبةً
 *    >  تلقائيًّا** مقابل سعر البندل» (18)
 *    > «ممنوع Dark Patterns — لا ندرة كاذبة … **لا أرقام وهمية**» (2.9)
 *
 *    فثلاثة أشياء **لا تخرج من هنا إلّا بشرطها**:
 *     - **«وفّرت X»**: لا تُعرَض إطلاقًا إن كانت القيمة الإجماليّة ≤ سعر الباقة —
 *       لا صفرًا ولا رقمًا سالبًا ولا شطبًا (21.1-د: «كلّ عرضٍ بقيمته الحقيقيّة»).
 *     - **العدّاد**: `null` ما لم يكن `available_until` فعليًّا في المستقبل — فلا
 *       يوجد في الـHTML أصلًا، لا مخفيًّا ولا مصفَّرًا (2.9-10 · 21.1-د).
 *     - **«باقي N مقعدًا»**: `null` ما لم يُضبَط `purchase_limit`، والرقم **معدودٌ
 *       من الطلبات المدفوعة** لا مكتوبًا.
 *
 *    ولا شهادات عملاء ولا آراء ولا «شاهد الآن X شخصًا»: لا جدول لأيٍّ منها في
 *    المنصّة، ورأيٌ مفبرك أو عدّاد مخترَع خرقٌ مباشر لـ«لا أرقام وهمية» (2.9).
 *    والإثبات هنا **حقيقيّ**: ما بداخل الباقة بقيمته · الشهادة المعتمَدة بشرطها
 *    (8) · عدد العناصر والدروس · الوصول الدائم.
 *
 * **3) الكود المخصّص وبوّابة الموافقة.** الحقن خامٌّ بلا تعقيم (أمر المالك)،
 *    لكنّه **لا يُحقَن** إلّا إذا سمحت خانة «متى يُحقَن؟» — راجع `injections()`.
 */
class BundleLanding
{
    /**
     * ⭐ **جرد نصوص اللاندنج** — المفتاح المحلّيّ ⟵ مفتاح الإعداد العامّ الذي يُورَث.
     *
     * وهذه القائمة هي **العقد**: كلّ نصٍّ ظاهر في الصفحة له سطرٌ هنا، ولذلك
     * صار لكلّ نصٍّ حقلٌ في فورم البندل بلا استثناء. والقالب لا يقرأ إلّا منها.
     *
     * @var array<string, string>
     */
    public const TEXTS = [
        // ------------------------------------------------------------ الهيرو
        'hero.badge' => 'store.bundle.hero_badge',
        // هذان بلا إعدادٍ عامّ: افتراضيّهما **اسم الباقة ووصفها** لا نصٌّ عامّ (راجع `dynamicFallback`)
        'hero.headline' => '',
        'hero.promise' => '',
        'hero.percent_off' => 'store.bundle.percent_off_text',
        'hero.savings' => 'store.savings.text',
        'hero.balance_label' => 'store.bundle.balance_label',
        'hero.cta' => 'store.bundle.cta_label',
        'hero.login_cta' => 'store.bundle.login_cta',
        'hero.owned_text' => 'store.bundle.owned_text',
        'hero.owned_badge' => 'store.bundle.owned_badge',
        'hero.library_link' => 'store.bundle.library_link_text',
        'hero.fact_items' => 'store.bundle.fact_items',
        'hero.fact_lessons' => 'store.bundle.fact_lessons',
        'hero.fact_lifetime' => 'store.bundle.fact_lifetime',
        'hero.fact_certificate' => 'store.bundle.fact_certificate',
        'hero.free_label' => 'store.free_label',

        // ------------------------------------------------------------ مناسبة لـ / مش مناسبة لـ
        'fit.title' => 'store.bundle.fit_title',
        'fit.not_title' => 'store.bundle.not_fit_title',
        'fit.note' => 'store.bundle.not_fit_note',

        // ------------------------------------------------------------ النتائج
        'outcomes.title' => 'store.bundle.outcomes_title',

        // ------------------------------------------------------------ اللي جوّه الباقة
        'includes.title' => 'store.bundle.includes_title',
        'includes.honest_note' => 'store.bundle.honest_note',
        'includes.bonus_template' => 'store.bundle.bonus_text',

        // ------------------------------------------------------------ الشهادة
        'certificate.title' => 'store.bundle.certificate_title',
        'certificate.text' => 'store.bundle.certificate_text',

        // ------------------------------------------------------------ ميزان القيمة
        'ledger.title' => 'store.bundle.ledger_title',
        'ledger.total_value_label' => 'store.bundle.total_value_label',
        'ledger.price_label' => 'store.bundle.price_label',
        'ledger.savings_label' => 'store.bundle.savings_label',

        // ------------------------------------------------------------ الإتاحة الحقيقيّة
        'availability.title' => 'store.bundle.countdown_title',
        'availability.countdown_text' => 'store.bundle.countdown_text',
        'availability.seats_text' => 'store.bundle.seats_text',
        'availability.sold_out_text' => 'store.bundle.sold_out_text',
        'availability.window_closed_text' => 'store.bundle.window_closed_text',
        'availability.not_started_text' => 'store.bundle.not_started_text',

        // ------------------------------------------------------------ الأسئلة
        'faq.title' => 'store.bundle.faq_title',

        // ------------------------------------------------------------ الإغلاق
        'closing.title' => 'store.bundle.after_purchase_title',
        'closing.text' => 'store.bundle.after_purchase_text',
        'closing.no_refund_notice' => 'store.bundle.no_refund_notice',
        'closing.refund_link' => 'store.refund.link_text',

        // ------------------------------------------------------------ الشريط اللاصق
        'sticky.price_label' => 'store.bundle.sticky_price_label',
    ];

    /**
     * ⭐ **جرد سكشنات اللاندنج** — المفتاح ⟵ توجّله العامّ الذي يُورَث.
     *
     * والحالة لكلّ بندل **ثلاثيّة** (`inherit` · `show` · `hide`) لا ثنائيّة:
     * التوجّل الثنائيّ يخلط «أخفِه لهذا البندل» بـ«اتبع العامّ»، فيتجمّد البندل
     * على قيمة اليوم ويُبطِل الإعداد العامّ صامتًا.
     *
     * @var array<string, string>
     */
    public const SECTIONS = [
        'hero' => 'store.bundle.blocks.hero_enabled',
        'fit' => 'store.bundle.blocks.fit_enabled',
        'outcomes' => 'store.bundle.blocks.outcomes_enabled',
        'includes' => 'store.bundle.blocks.includes_enabled',
        'certificate' => 'store.bundle.blocks.certificate_enabled',
        'ledger' => 'store.bundle.blocks.ledger_enabled',
        'availability' => 'store.bundle.blocks.availability_enabled',
        'faq' => 'store.bundle.blocks.faq_enabled',
        'closing' => 'store.bundle.blocks.closing_enabled',
        'sticky' => 'store.bundle.blocks.sticky_enabled',
    ];

    /** الحالات الثلاث لسكشنٍ في بندلٍ بعينه */
    public const STATE_INHERIT = 'inherit';

    public const STATE_SHOW = 'show';

    public const STATE_HIDE = 'hide';

    /** خيارات خانة «متى يُحقَن؟» — والافتراضيّ **الأضيق** (`ads`) */
    public const INJECT_ALWAYS = 'always';

    public const INJECT_ANALYTICS = 'analytics';

    public const INJECT_ADS = 'ads';

    public function __construct(
        private readonly StoreCatalog $catalog,
        private readonly Consent $consent,
    ) {}

    // ================================================================ 1) الوراثة الحيّة

    /**
     * ⭐ **قاعدة الحلّ الوحيدة للنصوص**: قيمة البندل إن وُجدت ⟵ وإلّا الإعداد
     * العامّ ⟵ وإلّا الارتداد الديناميّ (الاسم/الوصف). ولا نسخ ولا تجميد.
     */
    public function text(Bundle $bundle, string $key): string
    {
        $own = $this->trim(($bundle->landing_texts ?? [])[$key] ?? null);

        if ($own !== null) {
            return $own;
        }

        $globalKey = self::TEXTS[$key] ?? '';

        if ($globalKey !== '') {
            $value = $this->trim((string) setting($globalKey, ''));

            if ($value !== null) {
                return $value;
            }
        }

        return $this->dynamicFallback($bundle, $key);
    }

    /** هل هذا النصّ **مخصَّصٌ** لهذا البندل أم موروث؟ (شارة الفورم) */
    public function isOverridden(Bundle $bundle, string $key): bool
    {
        return $this->trim(($bundle->landing_texts ?? [])[$key] ?? null) !== null;
    }

    /** القيمة العامّة وحدها — تُعرَض `placeholder` في الفورم فيرى الأدمن ما سيرثه */
    public function globalText(string $key): string
    {
        $globalKey = self::TEXTS[$key] ?? '';

        return $globalKey === '' ? '' : (string) setting($globalKey, '');
    }

    /**
     * ما لا إعدادَ عامًّا له: العنوان يرتدّ إلى **اسم الباقة**، والوعد إلى **وصفها**
     * — فالصفحة لا تظهر بفراغٍ لو ترك الأدمن الحقلين.
     */
    private function dynamicFallback(Bundle $bundle, string $key): string
    {
        return match ($key) {
            'hero.headline' => (string) $bundle->name_ar,
            'hero.promise' => (string) ($bundle->description ?? ''),
            default => '',
        };
    }

    /** ⭐ **قاعدة الحلّ الوحيدة للسكشنات** — الحالة الثلاثيّة أوّلًا ثمّ التوجّل العامّ */
    public function sectionVisible(Bundle $bundle, string $section): bool
    {
        return match ($this->sectionState($bundle, $section)) {
            self::STATE_SHOW => true,
            self::STATE_HIDE => false,
            default => (bool) setting(self::SECTIONS[$section] ?? '', true),
        };
    }

    public function sectionState(Bundle $bundle, string $section): string
    {
        $state = (string) (($bundle->landing_sections ?? [])[$section] ?? self::STATE_INHERIT);

        return in_array($state, [self::STATE_SHOW, self::STATE_HIDE], true) ? $state : self::STATE_INHERIT;
    }

    // ================================================================ البناء الكامل

    /**
     * كلّ ما تحتاجه الصفحة في مصفوفةٍ واحدة — والقالب **يعرض ولا يحسب ولا يقرأ إعدادًا**.
     *
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    public function build(Bundle $bundle, ?User $user, array $quote): array
    {
        $items = $this->items($bundle);
        // ⭐ مجموع **أسعار العناصر بالـOverride** — نفس مصدر عمود الجدول في 24
        $totalValue = round($items->sum(fn (array $line) => (float) $line['value']), 2);
        $price = (float) $quote['total'];

        /*
         | ⭐ **إن كانت القيمة الإجماليّة ≤ سعر الباقة فلا «وفّرت» إطلاقًا.**
         | الفرق يُحسَب ثمّ يُختبَر: موجبٌ صريحٌ فقط يفتح الشطب والنسبة والسطر —
         | وإلّا فالصفحة تبيع بلا مرساةٍ أصلًا، وهو أصدق من مرساةٍ صفريّة.
         */
        $savings = round($totalValue - $price, 2);
        $hasSavings = $savings > 0 && $totalValue > 0;

        $texts = [];

        foreach (array_keys(self::TEXTS) as $key) {
            $texts[$key] = $this->text($bundle, $key);
        }

        $sections = [];

        foreach (array_keys(self::SECTIONS) as $section) {
            $sections[$section] = $this->sectionVisible($bundle, $section);
        }

        $certificate = $this->certificate($items);

        return [
            'bundle' => $bundle,
            'texts' => $texts,
            'sections' => $sections,

            // ------------------------------------------------ الميزان المحسوب (18)
            'items' => $items,
            'bonus_lines' => $this->bonusLines($bundle, $items),
            'total_value' => $totalValue,
            'price' => $price,
            'savings' => $hasSavings ? $savings : 0.0,
            'has_savings' => $hasSavings,
            'percent_off' => $hasSavings ? (int) round($savings / $totalValue * 100) : 0,
            /*
             | توجّلا [العرض] في 24 — **عامٌّ وخاصّ معًا**: التوجّل العامّ في بلوك
             | إعدادات الشاشة (`store.bundle.anchoring_enabled` · `…total_value_enabled`)
             | يقفل الميزة للمنصّة كلّها، وتوجّل البندل يقفلها لهذه الباقة وحدها.
             | ولا يقع الشطب أصلًا بلا **توفيرٍ حقيقيّ** مهما كان التوجّلان (2.9).
             */
            'show_strikethrough' => $hasSavings
                && $bundle->show_anchor_strikethrough
                && (bool) setting('store.bundle.anchoring_enabled', true),
            'show_total_value' => $hasSavings
                && $bundle->show_total_value
                && (bool) setting('store.bundle.total_value_enabled', true),

            // ------------------------------------------------ قوائم يحرّرها الأدمن
            'outcomes' => $this->list($bundle->landing_outcomes),
            'fit_for' => $this->list($bundle->landing_fit_for),
            'not_fit_for' => $this->list($bundle->landing_not_fit_for),
            'faq' => $this->faq($bundle),

            // ------------------------------------------------ إثباتٌ حقيقيّ لا مفبرك
            'facts' => $this->facts($texts, $items),
            'certificate' => $certificate,

            // ------------------------------------------------ الندرة الحقيقيّة وحدها
            'countdown_ends_at' => $this->countdownEndsAt($bundle),
            'countdown_units' => (array) setting('store.bundle.countdown_units', []),
            'seats_left' => $this->seatsLeft($bundle),
            'purchases' => $this->purchases($bundle),

            // ------------------------------------------------ الإتاحة الفعليّة للشراء
            'available' => $this->purchasable($bundle),
            'unavailable_text' => $this->unavailableText($bundle, $texts),

            'item_type_labels' => (array) setting('store.bundle.item_type_labels', []),
            // فتات الخبز — نصّان يحرّرهما الأدمن كغيرهما (2.13)
            'breadcrumbs' => [
                'store' => (string) setting('store.breadcrumb_label', 'المتجر'),
                'bundles' => (string) setting('store.bundles.breadcrumb_label', 'الباقات'),
            ],
            'owned' => (bool) $quote['owned'],
        ];
    }

    // ================================================================ 3) الكود المخصّص

    /**
     * ⭐ **الكود المحقون** — العامّ أوّلًا ثمّ الخاصّ بالبندل، لكلٍّ من الموضعين.
     *
     * ولا يُحقَن شيءٌ إلّا بعد اجتياز **بوّابة الموافقة**: 21.3-د و2.9 يجعلان
     * الرفض يوقف التتبّع **فعليًّا** — فبكسلٌ يُحقَن رغم الرفض يكسر ضمانًا قائمًا
     * بصمت، ويجعل بانر الموافقة يَعِد بما لا يقع.
     *
     * ⚠️ والمخرَج **خام** بلا تعقيم ولا تصفية: هذا نصّ المالك «مسموح أضيف فيهم
     *    أي حاجة» — ولذلك حصرناه بيده وحده (`isPlatformOwner`).
     *
     * @return array{head:array<int,string>, body_end:array<int,string>}
     */
    public function injections(Bundle $bundle, ?User $user = null): array
    {
        $out = ['head' => [], 'body_end' => []];

        $sources = [
            'head' => [
                [(string) setting('store.bundle.head_code', ''), (string) setting('store.bundle.head_code_when', self::INJECT_ADS)],
                [(string) $bundle->landing_head_code, (string) ($bundle->landing_head_code_when ?: self::INJECT_ADS)],
            ],
            'body_end' => [
                [(string) setting('store.bundle.body_end_code', ''), (string) setting('store.bundle.body_end_code_when', self::INJECT_ADS)],
                [(string) $bundle->landing_body_end_code, (string) ($bundle->landing_body_end_code_when ?: self::INJECT_ADS)],
            ],
        ];

        foreach ($sources as $slot => $rows) {
            foreach ($rows as [$code, $when]) {
                if (trim($code) !== '' && $this->mayInject($when, $user)) {
                    $out[$slot][] = $code;
                }
            }
        }

        return $out;
    }

    /** بوّابة الموافقة الواحدة — ولا مسارَ يلتفّ حولها */
    public function mayInject(string $when, ?User $user = null): bool
    {
        return match ($when) {
            self::INJECT_ALWAYS => true,
            self::INJECT_ANALYTICS => $this->consent->allowsAnalytics($user),
            default => $this->consent->allowsAds($user),
        };
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
     * والقالب يمرّ بقاعدة الوراثة نفسها: قالب البندل ⟵ ثمّ القالب العامّ.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    public function bonusLines(Bundle $bundle, Collection $items): array
    {
        /*
         | `bonus_text_template` عمودُ 24 المنصوص لهذا البندل، ويسبق خريطة النصوص.
         | و24 ينصّ على **قالب نصّ البونص (ع/إ)** — فالنسخة الإنجليزيّة تُقرأ حين
         | تكون لغة العرض إنجليزيّة، وإلّا فالعربيّة. ولو كانت الإنجليزيّة فارغة
         | رجعنا للعربيّة بدل أن يختفي السطر كلّه.
         */
        $template = $this->trim($bundle->bonus_text_template)
            ?? (app()->getLocale() === 'en' ? $this->trim(setting('store.bundle.bonus_text_en', '')) : null)
            ?? $this->text($bundle, 'includes.bonus_template');
        $lines = [];

        foreach ($items as $line) {
            $lines[$line['id']] = str_replace(
                ['{item}', '{amount}'],
                [$line['title'], Coins::label($line['list_value'])],
                $template,
            );
        }

        return $lines;
    }

    // ================================================================ الإثبات الحقيقيّ

    /**
     * صفّ الثقة — **حقائق تُقرأ من القاعدة** لا وعودًا:
     * عدد العناصر · عدد الدروس إن وُجدت · الوصول الدائم بلا اشتراك.
     * ولا يُذكَر رقمٌ لا نملك مصدره: بلا دروسٍ فلا سطرَ دروسٍ أصلًا.
     *
     * @param  array<string, string>  $texts
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function facts(array $texts, Collection $items): array
    {
        $facts = [str_replace('{count}', (string) $items->count(), $texts['hero.fact_items'])];

        $lessons = $this->lessonCount($items);

        if ($lessons > 0) {
            $facts[] = str_replace('{count}', (string) $lessons, $texts['hero.fact_lessons']);
        }

        // 19.4 يمنع الاسترجاع، فالبديل الأمين **وصولٌ دائم** لا «ضمان استرجاع»
        $facts[] = $texts['hero.fact_lifetime'];

        return array_values(array_filter($facts));
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
     * ولا نكتب «70%» رقمًا محروقًا بينما ضبط الأدمن غيره.
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

        return [
            'courses' => array_values(Course::query()
                ->whereIn('id', $exams->pluck('examable_id')->all())
                ->pluck('name_ar')
                ->all()),
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

        return ($endsAt && $endsAt->isFuture()) ? $endsAt : null;
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

    /** هل الباقة قابلة للشراء الآن؟ نافذة الإتاحة + المقاعد ([الإتاحة] في 24 · 5) */
    public function purchasable(Bundle $bundle): bool
    {
        if ($bundle->available_from && $bundle->available_from->isFuture()) {
            return false;
        }

        if ($bundle->available_until && $bundle->available_until->isPast()) {
            return false;
        }

        return $this->seatsLeft($bundle) !== 0;
    }

    /** @param  array<string, string>  $texts */
    private function unavailableText(Bundle $bundle, array $texts): string
    {
        if ($bundle->available_from && $bundle->available_from->isFuture()) {
            return $texts['availability.not_started_text'];
        }

        if ($bundle->available_until && $bundle->available_until->isPast()) {
            return $texts['availability.window_closed_text'];
        }

        return $texts['availability.sold_out_text'];
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

            if (is_array($row) && trim((string) ($row[$requiredKeys[0]] ?? '')) !== '') {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function trim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
