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
use App\Services\Admin\System\StoreAdminService;
use App\Services\Library\ProductToc;
use App\Services\Library\ReadingAnalytics;
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
        ];

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
            'price_coins' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,published,archived'],
        ]);

        $bundle = Bundle::create($data + [
            'slug' => Str::slug($data['name_ar']).'-'.Str::lower(Str::random(5)),
            'original_value' => 0, // تُحسَب من العناصر فور إضافتها
        ]);
        $this->audit($request, $bundle, 'bundles.create', [], $data);

        return back()->with('status', 'البندل اتحفظ ✓ — ضيف عناصره وهتتحسب قيمته تلقائيًّا.');
    }

    // ---------------------------------------------------------------- عناصر البندل (18)

    /** شاشة عناصر الباقة: القائمة + إضافة عنصر بسعره الطبيعيّ افتراضيًّا */
    public function showBundle(Bundle $bundle): View
    {
        return view('admin.store.bundle', [
            'bundle' => $bundle,
            'items' => app(StoreCatalog::class)->includes('bundle', $bundle),
            'options' => $this->store->bundleItemOptions(),
            'totalValue' => app(PricingService::class)->bundleItemsValue($bundle),
        ]);
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

        $row->forceFill([
            // ما ساوى السعر الطبيعيّ ليس Override — فلا نجمّد سعرًا سيتغيّر لاحقًا
            'price_coins' => ($override === null || abs($override - $natural) < 0.001) ? null : $override,
            'sort_order' => $row->sort_order ?? BundleItem::query()->where('bundle_id', $bundle->id)->count(),
        ])->save();

        $this->syncBundleValue($bundle);
        $this->audit($request, $bundle, 'bundles.edit', [], $data);

        return back()->with('status', 'العنصر اتضاف للباقة ✓');
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
