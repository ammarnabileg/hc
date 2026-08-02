<?php

namespace App\Services\Store;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Course;
use App\Models\Currency;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\LibraryEntitlement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\WalletBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * كتالوج المتجر (17): كلّ ما يُباع في **شبكة واحدة** بلا فصل حسب النوع،
 * والفلاتر هي المنظِّم. ولا يُخفى ما يملكه المستخدم — يُوسَم «تملكه بالفعل» (24.5).
 */
class StoreCatalog
{
    /** الأنواع المباعة — المفتاح في الرابط، والقيمة الموديل */
    public const TYPES = [
        'product' => Product::class,
        'bundle' => Bundle::class,
        'course' => Course::class,
        'path' => LearningPath::class,
    ];

    /** أنواع الفلتر الظاهرة والمطويّة (2.15-أ-4: ثلاثة ظاهرة والباقي مطويّ) */
    public function typeOptions(): array
    {
        return [
            'visible' => [
                'course' => 'تدريبات',
                'bundle' => 'باقات',
                'product' => 'منتجات رقميّة',
            ],
            'folded' => [
                'protected_pdf' => 'كتب محميّة',
                'cv_template' => 'قوالب سيرة ذاتيّة',
                'path' => 'مسارات',
            ],
        ];
    }

    public function allTypeKeys(): array
    {
        $options = $this->typeOptions();

        return array_keys($options['visible'] + $options['folded']);
    }

    // ------------------------------------------------------------ الحلّ والإتاحة

    public function resolve(string $type, string $slug): ?Model
    {
        $class = self::TYPES[$type] ?? null;

        if (! $class) {
            return null;
        }

        return $class::query()->where('slug', $slug)->first();
    }

    /** المنشور فقط يُباع ويُعرَض (شرط «الحالة = منشور» في مصفوفة الصلاحيّات) */
    public function isAvailable(string $type, ?Model $item): bool
    {
        if (! $item) {
            return false;
        }

        return in_array($item->status, ['published', 'active'], true);
    }

    /** المسار حاوية بلا سعر — يُباع داخل باقة فقط (16) */
    public function isSellable(string $type): bool
    {
        return $type !== 'path';
    }

    // ------------------------------------------------------------ الملكيّة

    public function owns(?User $user, string $type, Model $item): bool
    {
        if (! $user) {
            return false;
        }

        $class = self::TYPES[$type] ?? $item::class;

        $hasEntitlement = LibraryEntitlement::query()
            ->where('user_id', $user->id)
            ->where('itemable_type', $class)
            ->where('itemable_id', $item->id)
            ->exists();

        if ($hasEntitlement) {
            return true;
        }

        // التدريب قد يُملَك بالتسجيل وحده (هديّة/أكاديمية) بلا سطر ملكيّة
        return $type === 'course' && Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $item->id)
            ->exists();
    }

    /** ملكيّات المستخدم مجمَّعة: [النوع => [المعرّفات]] — لتفادي استعلام لكلّ كارت */
    public function ownedMap(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $map = [];

        foreach (LibraryEntitlement::query()->where('user_id', $user->id)->get(['itemable_type', 'itemable_id']) as $row) {
            $type = array_search($row->itemable_type, self::TYPES, true);

            if ($type !== false) {
                $map[$type][] = (int) $row->itemable_id;
            }
        }

        foreach (Enrollment::query()->where('user_id', $user->id)->pluck('course_id') as $courseId) {
            $map['course'][] = (int) $courseId;
        }

        return $map;
    }

    // ------------------------------------------------------------ الشبكة الموحّدة

    /**
     * @param  array{q?:string,category?:int|null,types?:array,min?:float|null,max?:float|null,sort?:string,owned?:string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function cards(?User $user, array $filters = []): Collection
    {
        $types = $filters['types'] ?? [];
        $types = $types !== [] ? array_intersect($types, $this->allTypeKeys()) : $this->allTypeKeys();
        $owned = $this->ownedMap($user);
        $cards = collect();

        if (array_intersect($types, ['course'])) {
            $cards = $cards->concat($this->courseCards($owned, $filters));
        }

        if (array_intersect($types, ['bundle'])) {
            $cards = $cards->concat($this->bundleCards($owned, $filters));
        }

        $productTypes = array_values(array_intersect($types, ['product', 'protected_pdf', 'cv_template']));

        if ($productTypes !== []) {
            $cards = $cards->concat($this->productCards($owned, $filters, $productTypes));
        }

        if (array_intersect($types, ['path'])) {
            $cards = $cards->concat($this->pathCards($owned, $filters));
        }

        return $this->finish($cards, $filters);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function bundleCards(array $owned, array $filters = []): Collection
    {
        $query = Bundle::query()->where('status', 'published');

        if ($term = ($filters['q'] ?? null)) {
            $query->where('name_ar', 'like', '%'.$term.'%');
        }

        return $query->get()->map(fn (Bundle $bundle) => $this->card(
            type: 'bundle',
            item: $bundle,
            title: $bundle->name_ar,
            price: (float) $bundle->price_coins,
            // القيمة الإجماليّة **محسوبة من العناصر** لا من رقمٍ مكتوب (18 · 2.9)
            listPrice: app(PricingService::class)->bundleItemsValue($bundle),
            owned: in_array($bundle->id, $owned['bundle'] ?? [], true),
            summary: $bundle->description,
        ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function courseCards(array $owned, array $filters): Collection
    {
        $query = Course::query()->where('status', 'published');

        if ($term = ($filters['q'] ?? null)) {
            $query->where(fn ($q) => $q->where('name_ar', 'like', '%'.$term.'%')
                ->orWhere('description_ar', 'like', '%'.$term.'%'));
        }

        return $query->get()->map(function (Course $course) use ($owned) {
            $offer = $this->activeOffer($course);

            return $this->card(
                type: 'course',
                item: $course,
                title: $course->name_ar,
                price: $course->is_free ? 0.0 : ($offer ?? (float) $course->price_coins),
                listPrice: (float) $course->price_coins,
                owned: in_array($course->id, $owned['course'] ?? [], true),
                summary: $course->description_ar,
            );
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function productCards(array $owned, array $filters, array $productTypes): Collection
    {
        $query = Product::query()->where('status', 'published');

        if ($category = ($filters['category'] ?? null)) {
            $query->where('product_category_id', $category);
        }

        if ($term = ($filters['q'] ?? null)) {
            $query->where(fn ($q) => $q->where('name_ar', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%'));
        }

        // «منتجات رقميّة» = ما ليس كتابًا محميًّا ولا قالب سيرة ذاتيّة
        $query->where(function ($q) use ($productTypes) {
            if (in_array('product', $productTypes, true)) {
                $q->orWhereNotIn('type', ['protected_pdf', 'cv_template']);
            }

            foreach (array_diff($productTypes, ['product']) as $type) {
                $q->orWhere('type', $type);
            }
        });

        $pricing = app(PricingService::class);

        return $query->get()->map(function (Product $product) use ($owned, $pricing) {
            return $this->card(
                type: 'product',
                item: $product,
                title: $product->name_ar,
                // ⭐ السعر بعملة المنتج نفسه (17) — والتسعير مصدره واحد لا نسخة ثانية
                price: $pricing->priceOf('product', $product),
                listPrice: $pricing->listPriceOf('product', $product),
                owned: in_array($product->id, $owned['product'] ?? [], true),
                summary: $product->description,
                categoryId: $product->product_category_id,
                currency: $pricing->currencyOf('product', $product),
            );
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function pathCards(array $owned, array $filters): Collection
    {
        $query = LearningPath::query()->where('status', 'published');

        if ($term = ($filters['q'] ?? null)) {
            $query->where('name_ar', 'like', '%'.$term.'%');
        }

        return $query->get()->map(fn (LearningPath $path) => $this->card(
            type: 'path',
            item: $path,
            title: $path->name_ar,
            price: 0.0,
            listPrice: 0.0,
            owned: in_array($path->id, $owned['path'] ?? [], true),
            summary: $path->description_ar,
        ));
    }

    private function card(
        string $type,
        Model $item,
        string $title,
        float $price,
        float $listPrice,
        bool $owned,
        ?string $summary = null,
        ?int $categoryId = null,
        ?string $currency = null,
    ): array {
        $currency = $currency ?: Coins::defaultCode();

        return [
            'type' => $type,
            'id' => $item->id,
            'slug' => $item->slug,
            'title' => $title,
            'summary' => $summary,
            'cover' => $item->cover_path,
            'currency' => $currency,
            'currency_label' => Coins::currencyLabel($currency),
            'price' => round($price, 2),
            'list_price' => round($listPrice, 2),
            // الخصم بقيمته الحقيقيّة فقط — بلا سعر مرجعيّ وهميّ (2.9 · 21.1-د)
            'savings' => round(max($listPrice - $price, 0), 2),
            'owned' => $owned,
            'sellable' => $this->isSellable($type),
            'category_id' => $categoryId,
            'created_at' => $item->created_at,
        ];
    }

    /** الفلترة بالعملة ثمّ بالسعر ثمّ الفرز — بعد التوحيد لأنّ المصادر ثلاثة جداول */
    private function finish(Collection $cards, array $filters): Collection
    {
        $currencies = array_values(array_filter((array) ($filters['currencies'] ?? [])));

        /*
         | ⭐ فلتر **نوع العملة** (Multi-select) وشريط السحب يتحرّك **ضمن العملة
         | المختارة** (17): مقارنة «50 تذكرة» بـ«50 كوين» على منزلقٍ واحد بلا معنى،
         | فنطاق السعر لا يُطبَّق إلّا حين تُختار عملة واحدة بعينها.
         */
        if ($currencies !== []) {
            $cards = $cards->filter(fn ($c) => in_array($c['currency'], $currencies, true));
        }

        $range = $this->rangeCurrency($currencies);
        $min = $filters['min'] ?? null;
        $max = $filters['max'] ?? null;

        // ما ليس بعملة المنزلق خارج نطاقه أصلًا فلا يُقصّ به
        if ($min !== null) {
            $cards = $cards->filter(fn ($c) => $c['currency'] !== $range || $c['price'] >= (float) $min);
        }

        if ($max !== null) {
            $cards = $cards->filter(fn ($c) => $c['currency'] !== $range || $c['price'] <= (float) $max);
        }

        if (($filters['owned'] ?? null) === 'mine') {
            $cards = $cards->filter(fn ($c) => $c['owned']);
        }

        if (($filters['owned'] ?? null) === 'new') {
            $cards = $cards->filter(fn ($c) => ! $c['owned']);
        }

        $cards = match ($filters['sort'] ?? setting('store.grid.default_sort', 'newest')) {
            'price_asc' => $cards->sortBy('price'),
            'price_desc' => $cards->sortByDesc('price'),
            'free' => $cards->sortBy('price'),
            default => $cards->sortByDesc(fn ($c) => $c['created_at']?->getTimestamp() ?? 0),
        };

        return $cards->values();
    }

    /** سعر العرض إن كان ساريًا الآن (16) */
    public function activeOffer(Model $item): ?float
    {
        $offer = $item->offer_price_coins ?? null;

        if ($offer === null) {
            return null;
        }

        $endsAt = $item->offer_ends_at ?? null;

        if ($endsAt && now()->greaterThan(Carbon::parse($endsAt))) {
            return null;
        }

        return round((float) $offer, 2);
    }

    // ------------------------------------------------------------ مساعدات العرض

    /** @return Collection<int, ProductCategory> */
    public function categories(): Collection
    {
        return ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * سقف منزلق السعر **لعملة المنزلق** — من الإعدادات لا محروقًا (2.13 · 24.3).
     * سقف الكوينز لا يصلح للتذاكر (تذكرة = 10 كوينز — 19.1)، فلكلّ عملة سقفها.
     */
    public function priceCeiling(?string $currencyCode = null): float
    {
        $code = $currencyCode ?: Coins::defaultCode();
        $ceilings = (array) setting('store.filters.price_max', []);

        if (isset($ceilings[$code])) {
            return (float) $ceilings[$code];
        }

        return (float) setting('store.filters.price_max_coins', 100000);
    }

    /**
     * عملة منزلق السعر: العملة المختارة إن كانت واحدة، وإلّا عملة المتجر
     * الافتراضيّة — فالمنزلق «يتحرّك ضمن العملة المختارة» (17).
     *
     * @param  array<int, string>  $selected
     */
    public function rangeCurrency(array $selected): string
    {
        return count($selected) === 1 ? (string) $selected[0] : Coins::defaultCode();
    }

    /**
     * خيارات فلتر نوع العملة (Multi-select — 17): الكود ⟵ الاسم العربيّ.
     *
     * @return array<string, string>
     */
    public function currencyOptions(): array
    {
        $options = [];

        foreach (app(PricingService::class)->currencies() as $code) {
            $options[$code] = Coins::currencyLabel($code);
        }

        return $options;
    }

    public function coinsCurrency(): ?Currency
    {
        return $this->currency(Coins::defaultCode());
    }

    /** عملةٌ بكودها — والمتجر صار يسعّر بثلاث عملات (17) */
    public function currency(string $code): ?Currency
    {
        static $cache = [];

        return $cache[$code] ??= Currency::query()->where('code', $code)->first();
    }

    public function balance(?User $user, ?string $currencyCode = null): float
    {
        if (! $user) {
            return 0.0;
        }

        $currency = $this->currency($currencyCode ?: Coins::defaultCode());

        if (! $currency) {
            return 0.0;
        }

        return (float) WalletBalance::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $currency->id)
            ->value('balance');
    }

    /**
     * ما يشمله العنصر — لعرضه في صفحة الباقة (18).
     *
     * لكلّ سطر رقمان لا واحد، وهذا ما يفرضه «قاعدة السعر السياقيّ» (18):
     *  - `list_value`: **القيمة الطبيعيّة** للعنصر — وهي التي يُعرَض بها كبونص،
     *    ومنها تُجمَع «القيمة الإجماليّة» المحسوبة تلقائيًّا مقابل سعر الباقة.
     *  - `value`: **Override سعر العنصر داخل الباقة** إن ضبطه الأدمن، وإلّا الطبيعيّة —
     *    ولا يظهر إلّا في صفحة الباقة نفسها.
     */
    public function includes(string $type, Model $item): Collection
    {
        if ($type !== 'bundle') {
            return collect();
        }

        return BundleItem::query()
            ->where('bundle_id', $item->id)
            ->orderBy('sort_order')
            ->get()
            ->map(function (BundleItem $row) {
                $child = $row->itemable;

                if (! $child) {
                    return null;
                }

                $childType = array_search($row->itemable_type, self::TYPES, true) ?: 'product';
                $offer = $this->activeOffer($child);
                $listValue = round($offer ?? (float) ($child->price_coins ?? 0), 2);
                $override = $row->price_coins === null ? null : round((float) $row->price_coins, 2);

                return [
                    'id' => $row->id,
                    'type' => $childType,
                    'slug' => $child->slug,
                    'title' => $child->name_ar,
                    'list_value' => $listValue,
                    'value' => $override ?? $listValue,
                    'has_override' => $override !== null,
                ];
            })
            ->filter()
            ->values();
    }

    /** صفحات العيّنة المجّانيّة (20.3 · 21.1-أ) */
    public function previewNote(string $type, Model $item): ?string
    {
        if ($type === 'product' && (int) $item->teaser_pages > 0) {
            return str_replace(
                '{pages}',
                (string) (int) $item->teaser_pages,
                (string) setting('store.preview.pages_text', 'أوّل {pages} صفحات مجّانيّة كمعاينة قبل الشراء.'),
            );
        }

        if ($type === 'course' && (int) ($item->free_preview_lessons ?? 0) > 0) {
            return str_replace(
                '{lessons}',
                (string) (int) $item->free_preview_lessons,
                (string) setting('store.preview.lessons_text', 'أوّل {lessons} درس مجّانيّ كمعاينة — جرّب قبل ما تشتري.'),
            );
        }

        return null;
    }
}
