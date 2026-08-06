<?php

use App\Http\Controllers\Trainee\CheckoutController;
use App\Http\Controllers\Trainee\StoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| المتجر (16 · 17 · 18 · 24.5)
|--------------------------------------------------------------------------
| التصفّح والشراء بالكوينز. والصلاحيّات (12.2.1) على كلّ مسار محميّ:
| التصفّح لمن يملك `store_products.list` أو `orders.create` — والمتدرّب يملك
| الثانية بنطاق SELF — والشراء لمن يملك `orders.create`.
*/

// صفحة سياسة الاسترجاع صفحة مستقلّة دائمة ومتاحة للجميع (19.4)
Route::get('/store/refund-policy', [StoreController::class, 'refundPolicy'])
    ->name('store.refund-policy');

// ⭐ صفحة العنصر عامّة ومفهرسة (21.1-أ): الزائر يعاين قبل التسجيل — والشراء يظلّ محميًّا
Route::get('/store/{type}/{slug}', [StoreController::class, 'product'])
    ->whereIn('type', ['product', 'bundle', 'course', 'path'])
    ->name('store.product');

Route::middleware(['auth', 'permission:store_products.list,orders.create'])->group(function () {
    Route::get('/store', [StoreController::class, 'index'])->name('store.index');
    Route::get('/store/bundles', [StoreController::class, 'bundles'])->name('store.bundles');

    // ⭐ تمرير تدريجيّ (13.1 · قرار §25 — ⛔ ممنوع ترقيم الصفحات): شريحة Fragment وحدها
    Route::get('/store/more', [StoreController::class, 'indexMore'])->name('store.index.more');
    Route::get('/store/bundles/more', [StoreController::class, 'bundlesMore'])->name('store.bundles.more');
});

Route::middleware(['auth', 'permission:orders.create'])->group(function () {
    // ملخّص محسوب في الخادم للبوب-أب (كوبون/Bump) — ولا سعر يأتي من المتصفّح
    Route::post('/store/quote', [CheckoutController::class, 'quote'])->name('store.quote');
    Route::post('/store/checkout', [CheckoutController::class, 'checkout'])->name('store.checkout');

    /*
    | السلّة **اختياريّة** وصفحة مراجعة الطلب (17): بوب-أب الشراء المباشر يبقى
    | المسار الافتراضيّ لعنصرٍ واحد، والسلّة لمَن يشتري أكثر من عنصر.
    | ⭐ ولا مسار منها يقبل سعرًا — الجلسة تحفظ (النوع + الـslug) فقط.
    */
    Route::get('/store/cart', [CheckoutController::class, 'cart'])->name('store.cart');
    Route::post('/store/cart/add', [CheckoutController::class, 'addToCart'])->name('store.cart.add');
    Route::post('/store/cart/remove', [CheckoutController::class, 'removeFromCart'])->name('store.cart.remove');
    Route::post('/store/cart/quote', [CheckoutController::class, 'cartQuote'])->name('store.cart.quote');
    Route::post('/store/cart/checkout', [CheckoutController::class, 'cartCheckout'])->name('store.cart.checkout');
});
