<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bundle;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Admin\System\StoreAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            // 🔒 عنصر الماليّات لا يظهر أصلًا لغير مالك المنصّة (12.2.1)
            'financeVisible' => $this->store->financeVisible($user),
            'rows' => match ($tab) {
                'bundles' => $this->store->bundles($filters),
                'coupons' => $this->store->coupons($filters),
                'orders' => $this->store->orders($filters),
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
            'price_coins' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,published,archived'],
        ]);

        $product = Product::create($data + ['slug' => Str::slug($data['name_ar']).'-'.Str::lower(Str::random(5))]);
        $this->audit($request, $product, 'store_products.create', [], $data);

        return back()->with('status', 'المنتج اتحفظ ✓');
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'price_coins' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,published,archived'],
        ]);

        $old = $product->only(array_keys($data));
        $product->update($data);
        $this->audit($request, $product, 'store_products.edit', $old, $data);

        return back()->with('status', 'التعديل اتحفظ ✓');
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
     * إعدادات حماية المنتج الرقميّ (20.5): قابل للتحميل ⇄ Flip-only محميّ،
     * وعدد صفحات العيّنة — **ولا سجلّ فتح فرديّ لأيّ ملفّ** (مرفوض صراحةً في 20.5).
     */
    public function updateProtection(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'protection' => ['required', 'in:download,flip'],
            'teaser_pages' => ['required', 'integer', 'min:0', 'max:200'],
        ]);

        $old = ['is_downloadable' => $product->is_downloadable, 'teaser_pages' => $product->teaser_pages];
        $new = ['is_downloadable' => $data['protection'] === 'download', 'teaser_pages' => $data['teaser_pages']];

        $product->update($new);
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

    public function storeBundle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'price_coins' => ['required', 'numeric', 'min:0'],
            'original_value' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,published,archived'],
        ]);

        $bundle = Bundle::create($data + ['slug' => Str::slug($data['name_ar']).'-'.Str::lower(Str::random(5))]);
        $this->audit($request, $bundle, 'bundles.create', [], $data);

        return back()->with('status', 'البندل اتحفظ ✓');
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
