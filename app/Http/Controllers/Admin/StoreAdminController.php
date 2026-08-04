<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Admin\System\StoreAdminService;
use App\Services\Library\ProductToc;
use App\Services\Library\ReadingAnalytics;
use App\Services\Store\BundleLanding;
use App\Services\Store\BundleScreenSettings;
use App\Services\Store\PricingService;
use App\Services\Store\StoreCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * لوحة المتجر (24.3-أوّلًا): المنتجات والتصنيفات · البندلز · الكوبونات وOrder-bump ·
 * الطلبات والفواتير · المكتبة الرقميّة والحماية.
 *
 * ⭐ **ولا مسار استرجاع نقديّ هنا إطلاقًا** (19.4) — البديل الوحيد «تصحيح خطأ تقنيّ»
 *    بمرجع معاملة إلزاميّ، ويُقيَّد كمعاملة موثّقة لا كاسترداد.
 */
class StoreAdminController extends Controller
{
    public function __construct(private readonly StoreAdminService $store) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tabs = $this->store->tabsFor($user);
        $tab = $request->string('tab')->toString();

        if (! array_key_exists($tab, $tabs)) {
            $tab = (string) array_key_first($tabs);
        }

        $filters = [
            'q' => $request->string('q')->toString() ?: null,
            'category' => $request->integer('category') ?: null,
            'type' => $request->string('type')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'protection' => $request->string('protection')->toString() ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            // فلاتر البندلز بنصّ 24: نطاق السعر + الفرز (الأعلى مبيعًا/الأحدث)
            'price_min' => $request->filled('price_min') ? (float) $request->input('price_min') : null,
            'price_max' => $request->filled('price_max') ? (float) $request->input('price_max') : null,
            'sort' => $request->string('sort')->toString() ?: null,
        ];

        $screenSettings = app(BundleScreenSettings::class);

        return view('admin.store.index', [
            'tabs' => $tabs,
            'tab' => $tab,
            'filters' => $filters,
            'kpis' => $this->store->kpis(),
            'categories' => $this->store->categories(),
            'protectionModes' => $this->store->protectionModes(),
            // تحليلات المكتبة **مجمّعة فقط** (20.5) — تُحسَب لتاب المكتبة وحده
            'analytics' => $tab === 'library'
                ? app(ReadingAnalytics::class)->summary((int) setting('library.analytics.top_limit', 5))
                : null,
            'toc' => app(ProductToc::class),
            // 🔒 عنصر الماليّات لا يظهر أصلًا لغير مالك المنصّة (12.2.1)
            'financeVisible' => $this->store->financeVisible($user),
            'bundleSortOptions' => $this->store->bundleSortOptions(),
            /*
             | بلوك إعدادات شاشة البندلز (24) — **يُحذَف كلّه** لمن لا يملك
             | `bundles.edit`، فلا يرى مفاتيح لا يقدر على حفظها (2.15-أ-7).
             */
            'bundleSettings' => ($tab === 'bundles' && $user->allows('bundles.edit')) ? $screenSettings->rows() : null,
            'bundleLockedRules' => $screenSettings->lockedRules(),
            'rows' => match ($tab) {
                'bundles' => $this->store->bundles($filters),
                'coupons' => $this->store->coupons($filters),
                'orders' => $this->store->orders($filters, $user),
                'library' => $this->store->protectedItems($filters),
                default => $this->store->products($filters),
            },
        ]);
    }

    // ---------------------------------------------------------------- المنتجات

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'type' => ['required', 'in:digital,protected_pdf,cv_template'],
            'price_currency' => ['required', 'string', Rule::in($this->currencies())],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,published,archived'],
        ]);

        $payload = $this->withPricing($data);
        $product = Product::create($payload + ['slug' => Str::slug($data['name_ar']).'-'.Str::lower(Str::random(5))]);
        $this->audit($request, $product, 'store_products.create', [], $payload);

        return back()->with('status', 'المنتج اتحفظ ✓');
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'price_currency' => ['required', 'string', Rule::in($this->currencies())],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,published,archived'],
        ]);

        $payload = $this->withPricing($data);
        $old = $product->only(array_keys($payload));
        $product->update($payload);
        $this->audit($request, $product, 'store_products.edit', $old, $payload);

        return back()->with('status', 'التعديل اتحفظ ✓');
    }

    /**
     * ⭐ **التسعير متعدّد العملات (17): عملةٌ واحدة معلَنة وقيمةٌ واحدة.**
     *
     * لماذا حقلٌ واحد للسعر مع Select للعملة بدل ثلاثة أعمدة مفتوحة؟ لأنّ ثلاثة
     * أرقام تجعل «كم سعره؟» بلا جواب واحد، وهي جذر العطل الذي كان: عمود
     * `price_tickets` معبَّأ ولا يقرؤه أحد، فيمرّ المنتج بصفر ويُسلَّم مجّانًا.
     * فالأعمدة الأخرى تُصفَّر صراحةً كي لا يبقى رقمٌ يتيمٌ يوهم بسعرٍ ثانٍ.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withPricing(array $data): array
    {
        $currency = (string) $data['price_currency'];
        $amount = round((float) $data['price'], 2);

        unset($data['price']);

        foreach ($this->currencies() as $code) {
            $data['price_'.$code] = $code === $currency ? $amount : 0;
        }

        return $data;
    }

    /** @return array<int, string> */
    private function currencies(): array
    {
        return app(PricingService::class)->currencies();
    }

    /** ⭐ أرشفة لا حذف — العنصر المشترى يبقى في مكتبات أصحابه */
    public function archiveProduct(Request $request, Product $product): RedirectResponse
    {
        $old = ['status' => $product->status];
        $product->update(['status' => 'archived']);
        $this->audit($request, $product, 'store_products.archive', $old, ['status' => 'archived']);

        return back()->with('status', 'المنتج اتأرشف — ومحدش هيفقد نسخته.');
    }

    /**
     * إعدادات حماية المنتج الرقميّ (20.5): قابل للتحميل ⇄ Flip-only محميّ ·
     * **تشغيل العلامة المائيّة** · **صلاحيّة زمنيّة** · عدد صفحات العيّنة · وفهرس القارئ (20.3)
     * — **ولا سجلّ فتح فرديّ لأيّ ملفّ** (مرفوض صراحةً في 20.5).
     */
    public function updateProtection(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'protection' => ['required', 'in:download,flip'],
            'teaser_pages' => ['required', 'integer', 'min:0', 'max:200'],
            'watermark_enabled' => ['nullable', 'boolean'],
            // فارغ = وصولٌ دائم، وهو الأصل في «مكتبتي» (20)
            'access_days' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'toc' => ['nullable', 'string', 'max:20000'],
        ]);

        $old = [
            'is_downloadable' => $product->is_downloadable,
            'teaser_pages' => $product->teaser_pages,
            'watermark_enabled' => $product->watermark_enabled,
            'access_days' => $product->access_days,
        ];

        $new = [
            'is_downloadable' => $data['protection'] === 'download',
            'teaser_pages' => $data['teaser_pages'],
            'watermark_enabled' => (bool) ($data['watermark_enabled'] ?? false),
            'access_days' => $data['access_days'] ?? null,
        ];

        $product->update($new + [
            'toc' => array_key_exists('toc', $data)
                ? app(ProductToc::class)->fromText($data['toc'])
                : $product->toc,
        ]);

        $this->audit($request, $product, 'product_protection.manage', $old, $new);

        return back()->with('status', 'إعدادات الحماية اتحفظت ✓');
    }

    // ---------------------------------------------------------------- التصنيفات والبندلز والكوبونات

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $category = ProductCategory::create([
            'name_ar' => $data['name_ar'],
            'slug' => Str::slug($data['name_ar']).'-'.Str::lower(Str::random(4)),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $this->audit($request, $category, 'product_categories.create', [], $data);

        return back()->with('status', 'التصنيف اتضاف ✓');
    }

    /**
     * ⭐ **لا حقل «القيمة الإجماليّة» بعد اليوم** (18 · 2.9): كانت رقمًا يكتبه الأدمن
     * بقيد `numeric|min:0` وحده، فيصير «وفّرت X» ادّعاءً لا يسنده شيء. والقيمة
     * الآن **تُحسَب من عناصر الباقة** في `PricingService::bundleItemsValue()`،
     * والعمود يُملأ تلقائيًّا ليبقى ما في قاعدة البيانات مطابقًا لما يُعرَض.
     */
    public function storeBundle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'price_coins' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,published,archived'],
        ]);

        // 🔒 السعر عند الإنشاء أيضًا خلف `pricing.edit` — وإلّا فبندلٌ بصفر (12.2.2)
        $price = $this->canPrice($request->user()) ? round((float) ($data['price_coins'] ?? 0), 2) : 0.0;

        $bundle = Bundle::create([
            'name_ar' => $data['name_ar'],
            'status' => $data['status'],
            'price_coins' => $price,
            'slug' => Str::slug($data['name_ar']).'-'.Str::lower(Str::random(5)),
            'original_value' => 0, // تُحسَب من العناصر فور إضافتها
        ]);
        $this->audit($request, $bundle, 'bundles.create', [], $data);

        return back()->with('status', 'البندل اتحفظ ✓ — ضيف عناصره وهتتحسب قيمته تلقائيًّا.');
    }

    // ---------------------------------------------------------------- فورم البندل (24 · 18)

    /**
     * ⭐ **شاشة البندل بأقسامها الستّة** (24: `[الهويّة] [العناصر] [التسعير 🔒]
     * [العرض] [الإتاحة]` + `[كود مخصّص 🔒]` بأمر المالك).
     *
     * 🔒 وقسمان **يُحذَفان من المخرَج** لغير صاحبهما — لا يُعطَّلان (2.15-أ-7):
     *  - **[التسعير]** لمن لا يملك `pricing.edit` («مالك المنصّة فقط» — 12.2.2).
     *  - **[كود مخصّص]** لغير **مالك المنصّة** نفسه.
     */
    public function showBundle(Request $request, Bundle $bundle): View
    {
        $user = $request->user();
        $landing = app(BundleLanding::class);

        return view('admin.store.bundle', [
            'bundle' => $bundle,
            'items' => app(StoreCatalog::class)->includes('bundle', $bundle),
            'options' => $this->store->bundleItemOptions(),
            'totalValue' => app(PricingService::class)->bundleItemsValue($bundle),
            'purchases' => $landing->purchases($bundle),
            'landingService' => $landing,
            // 🔒 الحصر يُقرَّر هنا **ويُعاد فرضه في الحفظ** — لا في القالب وحده
            'canPrice' => $this->canPrice($user),
            'canInjectCode' => $this->canInjectCode($user),
        ]);
    }

    /**
     * ⭐ حفظ فورم البندل — والحصر **على الخادم** لا في القالب:
     *
     * 🔒 **[التسعير]**: 12.2.2 يجعل `pricing.edit` «مالك المنصّة فقط» ونصّه
     *    «تعديل الأسعار وأسعار العروض **وOverride عناصر البندل**». فمسؤول التسويق
     *    والمتجر (12.2.3-6) يملك `bundles.*` ويصل هذه الصفحة — **وحقول السعر
     *    تُنزَع من حمولته قبل أن تلمس الموديل**، فحمولةٌ مزوَّرة لا تغيّر رقمًا.
     *
     * 🔒 **[كود مخصّص]**: بيد **مالك المنصّة** وحده. ولا مفتاح في 12.2.2 يصف حقن
     *    كودٍ حرّ، وممنوعٌ اختراع مفتاح — فالحارس هو `isPlatformOwner()` نفسه،
     *    وهو الحارس الذي تستعمله المنصّة أصلًا للمجموعة المحميّة (12.2.1-ز-3).
     */
    public function updateBundle(Request $request, Bundle $bundle): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            // ---------------------------------------------------- [الهويّة]
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'slug' => ['required', 'string', 'max:190', 'alpha_dash', Rule::unique('bundles', 'slug')->ignore($bundle->id)],
            'status' => ['required', 'in:draft,published,archived'],
            'is_indexable' => ['nullable', 'boolean'],

            // ---------------------------------------------------- [التسعير 🔒]
            'price_coins' => ['nullable', 'numeric', 'min:0'],

            // ---------------------------------------------------- [العرض]
            'show_anchor_strikethrough' => ['nullable', 'boolean'],
            'show_total_value' => ['nullable', 'boolean'],
            'bonus_text_template' => ['nullable', 'string', 'max:255'],

            // ---------------------------------------------------- [الإتاحة]
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'purchase_limit' => ['nullable', 'integer', 'min:1'],

            // ---------------------------------------------------- نصوص اللاندنج وسكشناتها
            'landing_texts' => ['nullable', 'array'],
            'landing_texts.*' => ['nullable', 'string', 'max:2000'],
            'landing_sections' => ['nullable', 'array'],
            'landing_sections.*' => ['nullable', 'string', Rule::in([
                BundleLanding::STATE_INHERIT, BundleLanding::STATE_SHOW, BundleLanding::STATE_HIDE,
            ])],
            'landing_outcomes' => ['nullable', 'string', 'max:4000'],
            'landing_fit_for' => ['nullable', 'string', 'max:4000'],
            'landing_not_fit_for' => ['nullable', 'string', 'max:4000'],
            'landing_faq' => ['nullable', 'string', 'max:8000'],

            // ---------------------------------------------------- [كود مخصّص 🔒]
            // ⚠️ بلا قيدٍ على المحتوى: «مسموح أضيف فيهم أي حاجة» — والحارس هو المالك لا المصفّي
            'landing_head_code' => ['nullable', 'string'],
            'landing_body_end_code' => ['nullable', 'string'],
        ]);

        $payload = [
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'description' => $data['description'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'slug' => $data['slug'],
            'status' => $data['status'],
            'is_indexable' => (bool) ($data['is_indexable'] ?? false),
            'show_anchor_strikethrough' => (bool) ($data['show_anchor_strikethrough'] ?? false),
            'show_total_value' => (bool) ($data['show_total_value'] ?? false),
            'bonus_text_template' => $this->blankToNull($data['bonus_text_template'] ?? null),
            'available_from' => $data['available_from'] ?? null,
            'available_until' => $data['available_until'] ?? null,
            'purchase_limit' => $data['purchase_limit'] ?? null,
            /*
             | ⚠️ **لا تُنسَخ القيمة العامّة إلى صفّ البندل.** الحقل الفارغ يُحذَف من
             | الخريطة فيبقى المفتاح غائبًا = **وراثةٌ حيّة**. ولو خزّنّا الافتراضيّ
             | لصار كلّ بندلٍ لقطةً مجمّدة وتعديلُ النصّ العامّ بلا أثر (نقضُ 2.13).
             */
            'landing_texts' => $this->compactMap($data['landing_texts'] ?? [], array_keys(BundleLanding::TEXTS)),
            'landing_sections' => $this->sectionStates($data['landing_sections'] ?? []),
            'landing_outcomes' => $this->lines($data['landing_outcomes'] ?? null),
            'landing_fit_for' => $this->lines($data['landing_fit_for'] ?? null),
            'landing_not_fit_for' => $this->lines($data['landing_not_fit_for'] ?? null),
            'landing_faq' => $this->faqLines($data['landing_faq'] ?? null),
        ];

        /*
         | 🔒 **عزل التسعير على الخادم** (12.7 · 12.2.2): من لا يملك `pricing.edit`
         | لا يمرّ سعرُه — لا يُرفَض الطلب كلّه فيفقد بقيّة عمله، بل **يُنزَع الحقل**
         | فلا يتغيّر رقمٌ واحد. والمحاولة تُسجَّل في الأوديت لأنّها إشارةٌ تستحقّ.
         */
        if ($this->canPrice($user)) {
            $payload['price_coins'] = round((float) ($data['price_coins'] ?? $bundle->price_coins), 2);
        } elseif ($request->has('price_coins')) {
            $this->audit($request, $bundle, 'bundles.edit', ['price_coins' => (string) $bundle->price_coins], [
                'rejected_price_coins' => $request->input('price_coins'),
            ]);
        }

        // 🔒 والكود الحرّ بيد مالك المنصّة وحده — بنفس المنطق: يُنزَع لا يُرفَض
        if ($this->canInjectCode($user)) {
            $payload['landing_head_code'] = $this->blankToNull($data['landing_head_code'] ?? null);
            $payload['landing_body_end_code'] = $this->blankToNull($data['landing_body_end_code'] ?? null);
        } elseif ($request->hasAny(['landing_head_code', 'landing_body_end_code'])) {
            $this->audit($request, $bundle, 'bundles.edit', [], ['rejected_custom_code' => true]);
        }

        $old = $bundle->only(array_keys($payload));
        $bundle->update($payload);
        $this->syncBundleValue($bundle);
        $this->audit($request, $bundle, 'bundles.edit', $old, $payload);

        return back()->with('status', setting('store.admin.bundles.saved_text'));
    }

    /** ⭐ «تكرار بندل» من هيدر 24 — نسخةٌ **مسودّة** بعناصرها، فلا يُنشَر عرضٌ بالخطأ */
    public function duplicateBundle(Request $request, Bundle $bundle): RedirectResponse
    {
        $copy = $bundle->replicate(['slug', 'original_value']);
        $copy->name_ar = $bundle->name_ar.' '.setting('store.admin.bundles.duplicate_suffix');
        $copy->slug = Str::slug($bundle->slug).'-'.Str::lower(Str::random(5));
        $copy->status = 'draft';
        $copy->original_value = 0;
        $copy->save();

        foreach ($bundle->items as $row) {
            BundleItem::create([
                'bundle_id' => $copy->id,
                'itemable_type' => $row->itemable_type,
                'itemable_id' => $row->itemable_id,
                'sort_order' => $row->sort_order,
                'price_coins' => $row->price_coins,
                'is_bonus' => $row->is_bonus,
            ]);
        }

        $this->syncBundleValue($copy);
        $this->audit($request, $copy, 'bundles.create', [], ['duplicated_from' => $bundle->id]);

        return redirect()
            ->route('admin.store.bundles.show', $copy)
            ->with('status', setting('store.admin.bundles.duplicated_text'));
    }

    /** ⭐ أرشفة لا حذف — العنصر المشترى يبقى في مكتبات أصحابه */
    public function archiveBundle(Request $request, Bundle $bundle): RedirectResponse
    {
        $old = ['status' => $bundle->status];
        $bundle->update(['status' => 'archived']);
        $this->audit($request, $bundle, 'bundles.archive', $old, ['status' => 'archived']);

        return back()->with('status', setting('store.admin.bundles.archived_text'));
    }

    // ---------------------------------------------------------------- بلوك إعدادات الشاشة (24)

    public function updateBundleSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);

        app(BundleScreenSettings::class)->putMany($data['settings'], $request->user());

        return back()->with('status', setting('store.admin.bundles.settings_saved_text'));
    }

    public function resetBundleSettings(Request $request): RedirectResponse
    {
        app(BundleScreenSettings::class)->reset($request->user());

        return back()->with('status', setting('store.admin.bundles.settings_reset_text'));
    }

    // ---------------------------------------------------------------- حرّاس ومساعدات

    /** 🔒 «pricing.edit — مالك المنصّة فقط» (12.2.2) */
    private function canPrice(?User $user): bool
    {
        return (bool) $user?->allows('pricing.edit');
    }

    /**
     * 🔒 الكود الحرّ: **لا مفتاح في 12.2.2 يصفه**، وممنوعٌ اختراع مفتاح — فالحارس
     * هو صفة **مالك المنصّة** نفسها (12.2.1-ز-3)، وهي أضيق من أيّ صلاحيّة.
     * وهو **الحارس الوحيد** على الحقلين: لا شرطَ حقنٍ ولا مستوًى عامّ (قرار المالك).
     */
    private function canInjectCode(?User $user): bool
    {
        return (bool) $user?->isPlatformOwner();
    }

    /**
     * ⭐ **الفارغ لا يُخزَّن** — وهذا هو الفرق بين وراثةٍ حيّة ولقطةٍ مجمّدة.
     *
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $allowed
     */
    private function compactMap(array $values, array $allowed): ?array
    {
        $map = [];

        foreach ($values as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                $map[$key] = $value;
            }
        }

        return $map === [] ? null : $map;
    }

    /** حالات السكشنات — و`inherit` لا تُخزَّن أصلًا فهي الغياب نفسه */
    private function sectionStates(array $values): ?array
    {
        $map = [];

        foreach ($values as $section => $state) {
            if (isset(BundleLanding::SECTIONS[$section])
                && in_array($state, [BundleLanding::STATE_SHOW, BundleLanding::STATE_HIDE], true)) {
                $map[$section] = $state;
            }
        }

        return $map === [] ? null : $map;
    }

    /** أسطر ⟵ قائمة (سطرٌ لكلّ عنصر — أبسط ما يحرّره الأدمن بلا تدريب) */
    private function lines(?string $text): ?array
    {
        $rows = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text) ?: [])));

        return $rows === [] ? null : $rows;
    }

    /** أسطر `سؤال | إجابة` ⟵ قائمة أسئلة */
    private function faqLines(?string $text): ?array
    {
        $rows = [];

        foreach ($this->lines($text) ?? [] as $line) {
            [$q, $a] = array_pad(explode('|', $line, 2), 2, '');
            $q = trim($q);

            if ($q !== '') {
                $rows[] = ['q' => $q, 'a' => trim($a)];
            }
        }

        return $rows === [] ? null : $rows;
    }

    private function blankToNull(?string $value): ?string
    {
        return trim((string) $value) === '' ? null : $value;
    }

    /**
     * إضافة عنصر للباقة مع **تسعير مستقلّ (Override)** (18).
     * والإنبوت يصل بالسعر الطبيعيّ كقيمة افتراضيّة، فترْكُه كما هو = بلا Override.
     */
    public function storeBundleItem(Request $request, Bundle $bundle): RedirectResponse
    {
        $catalog = app(StoreCatalog::class);

        $data = $request->validate([
            'item_type' => ['required', 'string', Rule::in(array_keys(StoreCatalog::TYPES))],
            'item_slug' => ['required', 'string', 'max:190'],
            'price_coins' => ['nullable', 'numeric', 'min:0'],
            'is_bonus' => ['nullable', 'boolean'],
        ]);

        $item = $catalog->resolve($data['item_type'], $data['item_slug']);

        if (! $item) {
            return back()->withErrors(['item_slug' => 'العنصر ده مش موجود — اختر من القائمة.']);
        }

        if ($item instanceof Bundle) {
            return back()->withErrors(['item_slug' => 'الباقة لا تُضاف داخل باقة.']);
        }

        $row = BundleItem::query()->firstOrNew([
            'bundle_id' => $bundle->id,
            'itemable_type' => $item::class,
            'itemable_id' => $item->id,
        ]);

        $natural = round((float) ($catalog->activeOffer($item) ?? $item->price_coins ?? 0), 2);
        $override = $data['price_coins'] === null ? null : round((float) $data['price_coins'], 2);

        // 🔒 «Override عناصر البندل» منصوصٌ في وصف `pricing.edit` — فلا يمرّ من غير مالكه
        if (! $this->canPrice($request->user())) {
            $override = $row->price_coins === null ? null : (float) $row->price_coins;
        }

        $row->forceFill([
            // ما ساوى السعر الطبيعيّ ليس Override — فلا نجمّد سعرًا سيتغيّر لاحقًا
            'price_coins' => ($override === null || abs($override - $natural) < 0.001) ? null : $override,
            'is_bonus' => (bool) ($data['is_bonus'] ?? false),
            'sort_order' => $row->sort_order ?? BundleItem::query()->where('bundle_id', $bundle->id)->count(),
        ])->save();

        $this->syncBundleValue($bundle);
        $this->audit($request, $bundle, 'bundles.edit', [], $data);

        return back()->with('status', 'العنصر اتضاف للباقة ✓');
    }

    /**
     * ⭐ صفّ العنصر في [العناصر] (24): **Override السعر** + **Toggle «اعرضه كبونص»**.
     *
     * 🔒 والسعر وحده خلف `pricing.edit` — «Override عناصر البندل» منصوصٌ في وصف
     *    المفتاح نفسه (12.2.2). أمّا وسم البونص فقرارُ عرضٍ يملكه مسؤول المتجر.
     */
    public function updateBundleItem(Request $request, Bundle $bundle, BundleItem $item): RedirectResponse
    {
        abort_unless((int) $item->bundle_id === (int) $bundle->id, 404);

        $data = $request->validate([
            'price_coins' => ['nullable', 'numeric', 'min:0'],
            'is_bonus' => ['nullable', 'boolean'],
        ]);

        $payload = ['is_bonus' => (bool) ($data['is_bonus'] ?? false)];

        if ($this->canPrice($request->user())) {
            $natural = $this->naturalPriceOf($item);
            $override = $data['price_coins'] === null ? null : round((float) $data['price_coins'], 2);

            // ما ساوى السعر الطبيعيّ ليس Override — فلا نجمّد سعرًا سيتغيّر لاحقًا
            $payload['price_coins'] = ($override === null || abs($override - $natural) < 0.001) ? null : $override;
        } elseif ($request->has('price_coins')) {
            $this->audit($request, $bundle, 'bundles.edit', ['item_price' => (string) $item->price_coins], [
                'rejected_item_price' => $request->input('price_coins'), 'item' => $item->id,
            ]);
        }

        $old = $item->only(array_keys($payload));
        $item->forceFill($payload)->save();

        $this->syncBundleValue($bundle);
        $this->audit($request, $bundle, 'bundles.edit', $old, $payload + ['item' => $item->id]);

        return back()->with('status', setting('store.admin.bundles.saved_text'));
    }

    private function naturalPriceOf(BundleItem $item): float
    {
        $child = $item->itemable;
        $catalog = app(StoreCatalog::class);

        return $child ? round((float) ($catalog->activeOffer($child) ?? $child->price_coins ?? 0), 2) : 0.0;
    }

    public function destroyBundleItem(Request $request, Bundle $bundle, BundleItem $item): RedirectResponse
    {
        abort_unless((int) $item->bundle_id === (int) $bundle->id, 404);

        $item->delete();
        $this->syncBundleValue($bundle);
        $this->audit($request, $bundle, 'bundles.edit', ['item' => $item->id], []);

        return back()->with('status', 'العنصر اتشال من الباقة ✓');
    }

    /** القيمة الإجماليّة في العمود = مجموع عناصرها دائمًا — فلا يفترقان (18) */
    private function syncBundleValue(Bundle $bundle): void
    {
        $bundle->forceFill([
            'original_value' => app(PricingService::class)->bundleItemsValue($bundle->refresh()),
        ])->save();
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:coupons,code'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $coupon = Coupon::create($data);
        $this->audit($request, $coupon, 'coupons.create', [], $data);

        return back()->with('status', 'الكوبون اتحفظ ✓');
    }

    public function toggleCoupon(Request $request, Coupon $coupon): RedirectResponse
    {
        $old = ['is_active' => $coupon->is_active];
        $coupon->update(['is_active' => ! $coupon->is_active]);
        $this->audit($request, $coupon, 'coupons.edit', $old, ['is_active' => $coupon->is_active]);

        return back()->with('status', $coupon->is_active ? 'الكوبون اشتغل ✓' : 'الكوبون اتوقف ✓');
    }

    // ---------------------------------------------------------------- الطلبات

    public function showOrder(Order $order): View
    {
        return view('admin.store.partials.order-panel', [
            'order' => $order->load(['user', 'coupon', 'currency']),
        ]);
    }

    /**
     * ⛔ **بديل الاسترجاع الوحيد**: تصحيح خطأ تقنيّ بمرجع المعاملة الأصليّة **إلزاميًّا**،
     * ويُوسَم في سجلّ المعاملات — **ولا يوجد استرداد نقديّ** (19.4).
     * 🔒 ولمالك المنصّة وحده لأنّه إجراء ماليّ.
     */
    public function correctOrder(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->isPlatformOwner(), 403, 'التصحيح الماليّ لمالك المنصّة وحده.');

        $data = $request->validate([
            'original_transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->audit($request, $order, 'invoices.correction', ['status' => $order->status], $data);

        return back()->with('status', 'اتسجّل تصحيح خطأ تقنيّ موثّق — مش استرجاع نقديّ.');
    }

    private function audit(Request $request, $model, string $action, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
