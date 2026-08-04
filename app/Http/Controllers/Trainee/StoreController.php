<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Services\Store\BundleLanding;
use App\Services\Store\PricingService;
use App\Services\Store\StoreCatalog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * شاشة المتجر وصفحة العنصر (24.5 · 16 · 17 · 18).
 * سؤال واحد للشاشة: «أشتري إيه؟» — وفعل رئيسيّ واحد في صفحة العنصر (2.15).
 */
class StoreController extends Controller
{
    public function __construct(
        private readonly StoreCatalog $catalog,
        private readonly PricingService $pricing,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);
        $cards = $this->catalog->cards($user, $filters);
        // منزلق السعر يتحرّك **ضمن العملة المختارة** (17) — وسقفه يتبعها
        $rangeCurrency = $this->catalog->rangeCurrency($filters['currencies']);

        return view('store.index', [
            'cards' => $this->paginate($cards, $request),
            'total' => $cards->count(),
            'filters' => $filters,
            'categories' => $this->catalog->categories(),
            'typeOptions' => $this->catalog->typeOptions(),
            'currencyOptions' => $this->catalog->currencyOptions(),
            'rangeCurrency' => $rangeCurrency,
            'priceCeiling' => $this->catalog->priceCeiling($rangeCurrency),
            'balance' => $this->catalog->balance($user),
        ]);
    }

    public function bundles(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);
        $cards = $this->catalog->bundleCards($this->catalog->ownedMap($user), $filters);
        $cards = $cards->filter(fn ($c) => $c['price'] >= (float) ($filters['min'] ?? 0)
            && $c['price'] <= (float) ($filters['max'] ?? $this->catalog->priceCeiling()))->values();

        return view('store.bundles', [
            'cards' => $this->paginate($cards, $request),
            'total' => $cards->count(),
            'filters' => $filters,
            'priceCeiling' => $this->catalog->priceCeiling(),
            'balance' => $this->catalog->balance($user),
        ]);
    }

    public function product(Request $request, string $type, string $slug): View
    {
        $item = $this->catalog->resolve($type, $slug);

        abort_unless($item && $this->catalog->isAvailable($type, $item), 404);

        /*
         | ⭐ `bundles.enabled` من بلوك إعدادات 24 — **علَمٌ له أثر لا زينة**:
         | إطفاؤه يُخفي صفحات البندلات فعلًا (2.13-و: العلَم الذي لا يفعل شيئًا
         | أسوأ من غيابه لأنّه يَعِد بسلوكٍ غير موجود). و«يُخفى لا يُعطَّل» (2.15-أ-7).
         */
        abort_if($type === 'bundle' && ! setting('store.bundles.enabled', true), 404);

        $user = $request->user();
        $quote = $this->pricing->quote($user, $type, $item);

        /*
         | ⭐ الباقة لها **لاندنج بيدج مخصّصة** (18 — والبند المفتوح في القسم 22):
         | كانت تُعرَض بقالب المنتج نفسه فتضيع Anchoring وميزان القيمة والبونص.
         | والمسار واحد كما هو (`store.product`) — القالب وحده هو الذي يختلف.
         |
         | والقالب **يعرض ولا يحسب ولا يقرأ إعدادًا**: `BundleLanding` يحلّ كلّ
         | نصٍّ وكلّ سكشن بقاعدة الوراثة الواحدة (قيمة البندل ⟵ الإعداد العامّ)،
         | ويقرّر ما يُحقَن من كودٍ مخصّص بعد بوّابة الموافقة (21.3-د).
         */
        if ($type === 'bundle') {
            $landing = app(BundleLanding::class);

            return view('store.bundle', [
                'type' => $type,
                'item' => $item,
                'quote' => $quote,
                'landing' => $landing->build($item, $user, $quote),
                'injections' => $landing->injections($item),
                'balance' => $quote['balance_before'],
                'indexable' => $this->indexable($type, $item),
                'ogImage' => $this->ogImage($item),
                'schema' => $this->schema($type, $item, $quote),
            ]);
        }

        return view('store.product', [
            'type' => $type,
            'item' => $item,
            'quote' => $quote,
            'includes' => $this->catalog->includes($type, $item),
            'previewNote' => $this->catalog->previewNote($type, $item),
            'balance' => $quote['balance_before'],
            // ⭐ الفهرسة تحترم إعداد النموّ (21.1-هـ) وعلَم العنصر نفسه
            'indexable' => $this->indexable($type, $item),
            'schema' => $this->schema($type, $item, $quote),
        ]);
    }

    /** صفحة مستقلّة دائمة لسياسة عدم الاسترجاع (19.4) — ونصّها من الإعدادات ويقبل HTML */
    public function refundPolicy(): View
    {
        return view('store.refund-policy', [
            'title' => (string) setting('store.refund.policy_title', 'سياسة عدم الاسترجاع'),
            'body' => (string) setting(
                'store.refund.policy_text',
                'لا يوجد استرجاع نقديّ للمدفوعات، ويبقى رصيدك في محفظتك تشتري به ما تشاء من الموقع.',
            ),
        ]);
    }

    // ------------------------------------------------------------ داخليّ

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $types = (array) $request->input('types', []);
        $currencies = array_map('strval', (array) $request->input('currencies', []));

        return [
            'q' => $request->string('q')->trim()->value() ?: null,
            'category' => $request->integer('category') ?: null,
            'types' => array_values(array_intersect($types, $this->catalog->allTypeKeys())),
            // فلتر نوع العملة (Multi-select — 17)، وما ليس عملةً معتمدة يُهمَل
            'currencies' => array_values(array_intersect($currencies, array_keys($this->catalog->currencyOptions()))),
            'min' => $request->has('min') ? max((float) $request->input('min'), 0) : null,
            'max' => $request->has('max') ? max((float) $request->input('max'), 0) : null,
            'sort' => (string) $request->input('sort', setting('store.grid.default_sort', 'newest')),
            'owned' => (string) $request->input('owned', ''),
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $cards */
    private function paginate(Collection $cards, Request $request): LengthAwarePaginator
    {
        $perPage = max((int) setting('store.grid.per_page', 24), 1);
        $page = max((int) $request->input('page', 1), 1);

        return new LengthAwarePaginator(
            $cards->forPage($page, $perPage)->values(),
            $cards->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    private function indexable(string $type, object $item): bool
    {
        if (! (bool) ($item->is_indexable ?? true)) {
            return false;
        }

        return match ($type) {
            'course', 'path' => (bool) setting('growth.seo.index_courses', true),
            default => (bool) setting('store.seo.index_products', true),
        };
    }

    /** صورة OG لهذا العنصر إن رفعها الأدمن — «صورة OG لكلّ رابط» (21.1-أ) */
    private function ogImage(object $item): ?string
    {
        $path = $item->og_image_path ?? $item->cover_path ?? null;

        return $path ? url(Storage::url($path)) : null;
    }

    /**
     * Schema.org: التدريب والمسار بـ`Course`، وغيرهما بـ`Product` (21.1-أ).
     *
     * ⭐ و`availability` **حقيقيّ** لا `InStock` دائمًا: الباقة خارج نافذتها أو
     * المستنفَدة مقاعدها تُعلَن `SoldOut` — فمحرّك البحث لا يَعِد بما لا يُباع،
     * وهو الوجه التقنيّ لـ«لا أرقام وهميّة» (2.9 · 21.2-ب).
     *
     * @return array<string, mixed>
     */
    private function schema(string $type, object $item, array $quote): array
    {
        $currency = (string) setting('store.currency.schema_code', 'COINS');
        $available = $type !== 'bundle' || app(BundleLanding::class)->purchasable($item);

        $offer = [
            '@type' => 'Offer',
            'price' => (string) $quote['total'],
            'priceCurrency' => $currency,
            'availability' => $available ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            'url' => route('store.product', ['type' => $type, 'slug' => $item->slug]),
        ];

        if (in_array($type, ['course', 'path'], true)) {
            return [
                '@context' => 'https://schema.org',
                '@type' => 'Course',
                'name' => $item->name_ar,
                'description' => (string) ($item->description_ar ?? ''),
                'inLanguage' => 'ar',
                'provider' => [
                    '@type' => 'Organization',
                    'name' => config('app.name'),
                    'url' => url('/'),
                ],
                'hasCourseInstance' => [
                    '@type' => 'CourseInstance',
                    'courseMode' => 'online',
                ],
                'offers' => $offer,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $item->name_ar,
            'description' => (string) ($item->description ?? ''),
            'offers' => $offer,
        ];
    }
}
