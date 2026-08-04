<?php

namespace App\Services\Admin\System;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Store\PricingService;
use App\Services\Store\StoreCatalog;
use App\Support\Scope\ScopeFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * المتجر في لوحة الإدارة (24.3-أوّلًا · 12.12): المنتجات والتصنيفات والبندلز
 * والكوبونات وOrder-bump والطلبات والفواتير والمكتبة الرقميّة وحمايتها.
 *
 * كلّ تاب هنا **سؤال واحد** بجدول 5–7 أعمدة وثلاثة فلاتر ظاهرة (2.15)،
 * والتفاصيل تُفتَح في بانل لا في صفحة جديدة.
 */
class StoreAdminService
{
    /** @return array<string, array{label:string, permission:string}> */
    public function tabs(): array
    {
        return [
            'products' => ['label' => 'المنتجات والتصنيفات', 'permission' => 'store_products.list'],
            'bundles' => ['label' => 'البندلز', 'permission' => 'bundles.list'],
            'coupons' => ['label' => 'الكوبونات وOrder-bump', 'permission' => 'coupons.list'],
            'orders' => ['label' => 'الطلبات والفواتير', 'permission' => 'orders.list'],
            'library' => ['label' => 'المكتبة الرقميّة والحماية', 'permission' => 'product_protection.view'],
        ];
    }

    /**
     * ⭐ التابات المرئيّة + عنصر «🔒 الماليّات» الذي **لا يظهر أصلًا لغير مالك المنصّة**
     * (عزل الحسّاس — 12.2.1 · 2.13-و).
     */
    public function tabsFor(User $user): array
    {
        return array_filter($this->tabs(), fn ($tab) => $user->allows($tab['permission']));
    }

    public function financeVisible(User $user): bool
    {
        return $user->isPlatformOwner();
    }

    /** المنتجات: بحث بالاسم/SKU · التصنيف · النوع · الحالة */
    public function products(array $filters): LengthAwarePaginator
    {
        return Product::query()
            ->with('product_category')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('name_ar', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%")
            ))
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('product_category_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($this->perPage())
            ->withQueryString();
    }

    /**
     * ⭐ **جدول البندلز بأعمدته التسعة** (24 حرفيًّا):
     * الغلاف · الاسم (ع/إ) · عدد العناصر · **القيمة الإجماليّة للعناصر** · **سعر
     * البندل** · نسبة التوفير المحسوبة · المشتريات · الحالة · إجراءات.
     *
     * وثلاثة من التسعة **تُحسَب في الاستعلام لا تُقرأ من عمودٍ مكتوب**:
     *  - `items_count` من `bundle_items`.
     *  - `items_value` — مجموع أسعار العناصر **الفعليّة** (Override إن وُجد وإلّا
     *    السعر الطبيعيّ) لا `bundles.original_value`؛ فالعمود مرآةٌ قد تتأخّر عن
     *    تغيير سعر تدريبٍ خارج شاشة الباقة، والرقم المعروض لا يجوز أن يتأخّر (18 · 2.9).
     *  - `purchases` من `order_items` **المدفوعة** — وعليها يقوم «باقي N مقعدًا»،
     *    فرقمٌ مخزَّن هناك يعني ندرةً كاذبة (21.1-د).
     *
     * والفلاتر أيضًا بنصّ 24: بحث بالاسم · الحالة · **نطاق السعر** · **الفرز
     * (الأعلى مبيعًا/الأحدث)**.
     */
    public function bundles(array $filters): LengthAwarePaginator
    {
        $itemsValue = BundleItem::query()
            ->selectRaw('coalesce(sum(coalesce(bundle_items.price_coins, 0)), 0)')
            ->whereColumn('bundle_items.bundle_id', 'bundles.id');

        $purchases = OrderItem::query()
            ->selectRaw('count(*)')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.purchasable_type', Bundle::class)
            ->whereColumn('order_items.purchasable_id', 'bundles.id')
            ->where('orders.status', 'paid');

        $query = Bundle::query()
            // أعمدة محسوبة — بلا علاقة جديدة على الموديل المشترك
            ->addSelect(['*',
                'items_count' => BundleItem::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('bundle_items.bundle_id', 'bundles.id'),
                'override_value' => $itemsValue,
                'purchases' => $purchases,
            ])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('name_ar', 'like', "%{$term}%")->orWhere('name_en', 'like', "%{$term}%")
            ))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            // نطاق السعر (24) — على **سعر البندل** لا على قيمة عناصره
            ->when($filters['price_min'] ?? null, fn ($q, $min) => $q->where('price_coins', '>=', (float) $min))
            ->when($filters['price_max'] ?? null, fn ($q, $max) => $q->where('price_coins', '<=', (float) $max));

        $query = match ($filters['sort'] ?? null) {
            'best_selling' => $query->orderByDesc('purchases'),
            default => $query->latest('id'),
        };

        $rows = $query->paginate($this->bundlesPerPage())->withQueryString();

        // القيمة الإجماليّة **الحقيقيّة** من الكتالوج (Override + السعر الطبيعيّ)
        $pricing = app(PricingService::class);

        $rows->getCollection()->transform(function (Bundle $bundle) use ($pricing) {
            $bundle->items_value = $pricing->bundleItemsValue($bundle);
            $bundle->savings_percent = $bundle->items_value > 0
                ? (int) round(max($bundle->items_value - (float) $bundle->price_coins, 0) / $bundle->items_value * 100)
                : 0;

            return $bundle;
        });

        return $rows;
    }

    /** خيارات فرز جدول البندلز (24) — من الإعدادات لا محروقة (2.13) */
    public function bundleSortOptions(): array
    {
        return (array) setting('store.admin.bundles.sort_options', []);
    }

    private function bundlesPerPage(): int
    {
        return max((int) setting('store.admin.bundles.per_page', 20), 1);
    }

    /**
     * ما يمكن ضمّه لباقة (18): تدريبات ومسارات ومنتجات — **بلا باقةٍ داخل باقة**.
     * ويصحب كلَّ خيارٍ **سعرُه الطبيعيّ** كي يصل الإنبوت به كقيمةٍ افتراضيّة.
     *
     * @return array<string, array<int, array{slug:string,title:string,price:float}>>
     */
    public function bundleItemOptions(): array
    {
        $catalog = app(StoreCatalog::class);

        $map = fn ($rows, string $titleColumn) => $rows
            ->map(fn ($row) => [
                'slug' => (string) $row->slug,
                'title' => (string) $row->{$titleColumn},
                'price' => round((float) ($catalog->activeOffer($row) ?? $row->price_coins ?? 0), 2),
            ])
            ->values()
            ->all();

        return [
            'course' => $map(Course::query()->where('status', 'published')->orderBy('name_ar')->get(), 'name_ar'),
            'path' => $map(LearningPath::query()->where('status', 'published')->orderBy('name_ar')->get(), 'name_ar'),
            'product' => $map(Product::query()->where('status', 'published')->orderBy('name_ar')->get(), 'name_ar'),
        ];
    }

    public function coupons(array $filters): LengthAwarePaginator
    {
        return Coupon::query()
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('code', 'like', "%{$term}%"))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'stopped', fn ($q) => $q->where('is_active', false))
            ->latest('id')
            ->paginate($this->perPage())
            ->withQueryString();
    }

    /** الطلبات — **بلا مسار استرجاع نقديّ** (19.4)، والتصحيح التقنيّ وحده البديل */
    /** الطلبات — ومع `$viewer` تُحصَر بنطاقه (12.2.1-ب) */
    public function orders(array $filters, ?User $viewer = null): LengthAwarePaginator
    {
        return Order::query()
            ->when($viewer !== null, fn ($q) => app(ScopeFilter::class)->apply($q, $viewer, 'orders.list'))
            ->with(['user'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('number', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"))
            ))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate($this->perPage())
            ->withQueryString();
    }

    /** المكتبة الرقميّة: نوع كلّ منتج وإعدادات حمايته (20.5) */
    public function protectedItems(array $filters): LengthAwarePaginator
    {
        return Product::query()
            ->whereIn('type', ['digital', 'protected_pdf'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name_ar', 'like', "%{$term}%"))
            ->when(($filters['protection'] ?? null) === 'flip', fn ($q) => $q->where('is_downloadable', false))
            ->when(($filters['protection'] ?? null) === 'download', fn ($q) => $q->where('is_downloadable', true))
            ->latest('id')
            ->paginate($this->perPage())
            ->withQueryString();
    }

    /** @return array<int, ProductCategory> */
    public function categories()
    {
        return ProductCategory::query()->orderBy('sort_order')->get();
    }

    /** أربعة كروت KPI بحدّ أقصى (2.15-أ-3) */
    public function kpis(): array
    {
        return [
            ['label' => 'منتجات منشورة', 'value' => Product::query()->where('status', 'published')->count(), 'icon' => '📦'],
            ['label' => 'بندلز نشطة', 'value' => Bundle::query()->where('status', 'published')->count(), 'icon' => '🎁'],
            ['label' => 'كوبونات مفعَّلة', 'value' => Coupon::query()->where('is_active', true)->count(), 'icon' => '🏷️'],
            ['label' => 'طلبات مكتملة', 'value' => Order::query()->where('status', 'paid')->count(), 'icon' => '🧾'],
        ];
    }

    /** أوضاع الحماية للمنتج الرقميّ (20.2 · 20.3) */
    public function protectionModes(): array
    {
        return [
            'download' => 'قابل للتحميل',
            'flip' => 'Flip-only محميّ (بلا تحميل)',
        ];
    }

    private function perPage(): int
    {
        return (int) setting('store.admin.per_page', 20);
    }
}
