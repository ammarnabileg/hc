<?php

use App\Http\Controllers\Admin\AdsController;
use App\Http\Controllers\Admin\ArticleAdminController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\ImageStudioController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\SettingsAdminController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\StoreAdminController;
use App\Http\Controllers\Admin\TopupAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| لوحة الإدارة — المتجر والماليّات والإحصائيّات والإعدادات والنظام (24.3)
|--------------------------------------------------------------------------
| الصلاحيّة على كلّ مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم يُخفى ولا يُعطَّل.
| 🔒 والمجموعة الماليّة ومفاتيح البوّابة لمالك المنصّة وحده — بحارسَي صلاحيّة ودور.
*/

Route::middleware(['auth', 'admin.panel'])->prefix('admin')->name('admin.')->group(function () {

    // ------------------------------------------------------------ المتجر
    Route::middleware('permission:store_products.list,bundles.list,coupons.list,orders.list')->group(function () {
        Route::get('/store', [StoreAdminController::class, 'index'])->name('store.index');
        Route::get('/store/orders/{order}', [StoreAdminController::class, 'showOrder'])->name('store.orders.show');
    });

    // ------------------------------------------------------------ عناصر البندل (18)
    Route::middleware('permission:bundles.view')
        ->get('/store/bundles/{bundle}', [StoreAdminController::class, 'showBundle'])->name('store.bundles.show');

    Route::middleware('permission:bundles.edit')->group(function () {
        Route::post('/store/bundles/{bundle}/items', [StoreAdminController::class, 'storeBundleItem'])->name('store.bundles.items.store');
        Route::delete('/store/bundles/{bundle}/items/{item}', [StoreAdminController::class, 'destroyBundleItem'])->name('store.bundles.items.destroy');
    });

    Route::middleware('permission:store_products.create')
        ->post('/store/products', [StoreAdminController::class, 'storeProduct'])->name('store.products.store');

    Route::middleware('permission:store_products.edit')->group(function () {
        Route::put('/store/products/{product}', [StoreAdminController::class, 'updateProduct'])->name('store.products.update');
        Route::post('/store/products/{product}/archive', [StoreAdminController::class, 'archiveProduct'])->name('store.products.archive');
    });

    Route::middleware('permission:product_protection.manage')
        ->post('/store/products/{product}/protection', [StoreAdminController::class, 'updateProtection'])->name('store.protection.update');

    Route::middleware('permission:product_categories.create')
        ->post('/store/categories', [StoreAdminController::class, 'storeCategory'])->name('store.categories.store');

    Route::middleware('permission:bundles.create')
        ->post('/store/bundles', [StoreAdminController::class, 'storeBundle'])->name('store.bundles.store');

    Route::middleware('permission:coupons.create')->group(function () {
        Route::post('/store/coupons', [StoreAdminController::class, 'storeCoupon'])->name('store.coupons.store');
        Route::post('/store/coupons/{coupon}/toggle', [StoreAdminController::class, 'toggleCoupon'])->name('store.coupons.toggle');
    });

    // ⛔ بديل الاسترجاع الوحيد: تصحيح خطأ تقنيّ موثّق 🔒 (19.4)
    Route::middleware('permission:finance.manage')
        ->post('/store/orders/{order}/correction', [StoreAdminController::class, 'correctOrder'])->name('store.orders.correction');

    // ------------------------------------------------------------ 🔒 الماليّات
    Route::middleware('permission:finance.view')->group(function () {
        Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('/finance/audit', [FinanceController::class, 'audit'])->name('finance.audit');
        Route::post('/finance/preview-policy', [FinanceController::class, 'previewRefundPolicy'])->name('finance.policy.preview');
    });

    Route::middleware('permission:finance.edit')->group(function () {
        Route::post('/finance/save', [FinanceController::class, 'save'])->name('finance.save');
        Route::post('/finance/refund-policy', [FinanceController::class, 'saveRefundPolicy'])->name('finance.refund-policy');
    });

    // ------------------------------------------------------------ طلبات الشحن
    Route::middleware('permission:topup_requests.list')->group(function () {
        Route::get('/topups', [TopupAdminController::class, 'index'])->name('topups.index');
        Route::get('/topups/methods', [TopupAdminController::class, 'methods'])->name('topups.methods');
        Route::get('/topups/gateway', [TopupAdminController::class, 'gateway'])->name('topups.gateway');
        Route::get('/topups/gateway/logs', [TopupAdminController::class, 'webhookLogs'])->name('topups.gateway.logs');
        Route::get('/topups/{topupRequest}', [TopupAdminController::class, 'show'])->name('topups.show');
    });

    Route::middleware('permission:topup_requests.approve')->group(function () {
        // ⛔ بوّابة إلزاميّة: مراجعة صورة الإيصال قبل أيّ اعتماد (19.5-ب-5)
        Route::post('/topups/{topupRequest}/reviewed', [TopupAdminController::class, 'markReviewed'])->name('topups.reviewed');
        Route::post('/topups/{topupRequest}/preview', [TopupAdminController::class, 'preview'])->name('topups.preview');
        Route::post('/topups/{topupRequest}/approve', [TopupAdminController::class, 'approve'])->name('topups.approve');
    });

    Route::middleware('permission:topup_requests.reject')
        ->post('/topups/{topupRequest}/cancel', [TopupAdminController::class, 'cancel'])->name('topups.cancel');

    Route::middleware('permission:topup.manage')->group(function () {
        Route::post('/topups/methods', [TopupAdminController::class, 'saveMethod'])->name('topups.methods.save');
        Route::delete('/topups/methods/{transferMethod}', [TopupAdminController::class, 'deleteMethod'])->name('topups.methods.delete');
        Route::post('/topups/offers', [TopupAdminController::class, 'saveOffer'])->name('topups.offers.save');
        Route::delete('/topups/offers/{topupOffer}', [TopupAdminController::class, 'deleteOffer'])->name('topups.offers.delete');
    });

    // 🔒 مفاتيح البوّابة لمالك المنصّة وحده (19.5-ج-5)
    Route::middleware('permission:payment_gateway.manage')->group(function () {
        Route::post('/topups/gateway', [TopupAdminController::class, 'saveGateway'])->name('topups.gateway.save');
        Route::post('/topups/gateway/test', [TopupAdminController::class, 'testGateway'])->name('topups.gateway.test');
    });

    // ------------------------------------------------------------ الإحصائيّات
    Route::middleware('permission:reports_users.view')->group(function () {
        Route::get('/stats', [StatsController::class, 'index'])->name('stats.index');
        Route::get('/stats/export', [StatsController::class, 'export'])->name('stats.export');
    });

    // ------------------------------------------------------------ الإعدادات والنظام
    Route::middleware('permission:settings_general.view')->group(function () {
        Route::get('/settings', [SettingsAdminController::class, 'index'])->name('settings.index');
        Route::get('/settings/search', [SettingsAdminController::class, 'search'])->name('settings.search');
        Route::get('/settings/audit', [SettingsAdminController::class, 'audit'])->name('settings.audit');
        Route::get('/settings/export', [SettingsAdminController::class, 'export'])->name('settings.export');
    });

    Route::middleware('permission:settings_general.edit')->group(function () {
        Route::post('/settings/field', [SettingsAdminController::class, 'saveField'])->name('settings.field');
        Route::post('/settings/reset', [SettingsAdminController::class, 'resetField'])->name('settings.reset');
        Route::post('/settings/undo', [SettingsAdminController::class, 'undoField'])->name('settings.undo');
        Route::post('/settings/import', [SettingsAdminController::class, 'import'])->name('settings.import');
    });

    // وضع الصيانة العامّ — ولا صيانة جزئيّة إطلاقًا (12.7-و-1)
    Route::middleware('permission:maintenance.manage')->group(function () {
        Route::post('/settings/maintenance/start', [MaintenanceController::class, 'start'])->name('settings.maintenance.start');
        Route::post('/settings/maintenance/extend', [MaintenanceController::class, 'extend'])->name('settings.maintenance.extend');
        Route::post('/settings/maintenance/lift', [MaintenanceController::class, 'lift'])->name('settings.maintenance.lift');
        // إلغاء صيانة مجدولة قبل موعدها (12.7-ج)
        Route::post('/settings/maintenance/unschedule', [MaintenanceController::class, 'unschedule'])->name('settings.maintenance.unschedule');
    });

    Route::middleware('permission:maintenance.view')
        ->get('/settings/maintenance/state', [MaintenanceController::class, 'state'])->name('settings.maintenance.state');

    // ------------------------------------------------------------ استوديو الصور
    Route::middleware('permission:image_templates.list')->group(function () {
        Route::get('/studio', [ImageStudioController::class, 'index'])->name('studio.index');
        Route::get('/studio/{template}/edit', [ImageStudioController::class, 'edit'])->name('studio.edit');
        Route::get('/studio/{template}/preview', [ImageStudioController::class, 'preview'])->name('studio.preview');
    });

    Route::middleware('permission:image_templates.create')->group(function () {
        Route::post('/studio', [ImageStudioController::class, 'store'])->name('studio.store');
        Route::post('/studio/{template}/duplicate', [ImageStudioController::class, 'duplicate'])->name('studio.duplicate');
    });

    Route::middleware('permission:image_templates.edit')->group(function () {
        Route::put('/studio/{template}', [ImageStudioController::class, 'update'])->name('studio.update');
        Route::post('/studio/{template}/layers/move', [ImageStudioController::class, 'moveLayer'])->name('studio.layers.move');
        Route::post('/studio/particles', [ImageStudioController::class, 'storeParticle'])->name('studio.particles.store');
        Route::delete('/studio/particles/{particle}', [ImageStudioController::class, 'deleteParticle'])->name('studio.particles.delete');
    });

    Route::middleware('permission:image_templates.archive')
        ->post('/studio/{template}/archive', [ImageStudioController::class, 'archive'])->name('studio.archive');

    Route::middleware('permission:image_templates.batch')
        ->post('/studio/{template}/batch', [ImageStudioController::class, 'batch'])->name('studio.batch');

    // ------------------------------------------------------------ المقالات
    Route::middleware('permission:articles.list')->group(function () {
        Route::get('/articles', [ArticleAdminController::class, 'index'])->name('articles.index');
        Route::get('/articles/{article}/edit', [ArticleAdminController::class, 'edit'])->name('articles.edit');
    });

    Route::middleware('permission:articles.create')->group(function () {
        Route::get('/articles/create', [ArticleAdminController::class, 'create'])->name('articles.create');
        Route::post('/articles', [ArticleAdminController::class, 'store'])->name('articles.store');
        Route::post('/articles/categories', [ArticleAdminController::class, 'storeCategory'])->name('articles.categories.store');
    });

    Route::middleware('permission:articles.edit')->group(function () {
        Route::put('/articles/{article}', [ArticleAdminController::class, 'update'])->name('articles.update');
        Route::post('/articles/{article}/submit', [ArticleAdminController::class, 'submit'])->name('articles.submit');
    });

    Route::middleware('permission:articles.review')
        ->post('/articles/{article}/review', [ArticleAdminController::class, 'review'])->name('articles.review');

    // ⭐ فصل النشر عن الكتابة شرطُ صحّةِ دورة النشر (21.2-ط)
    Route::middleware('permission:articles.publish')
        ->post('/articles/{article}/publish', [ArticleAdminController::class, 'publish'])->name('articles.publish');

    Route::middleware('permission:articles.archive')
        ->post('/articles/{article}/archive', [ArticleAdminController::class, 'archive'])->name('articles.archive');

    // ------------------------------------------------------------ الإعلان المدفوع
    Route::middleware('permission:ad_audiences.view')
        ->get('/ads', [AdsController::class, 'index'])->name('ads.index');

    Route::middleware('permission:ad_audiences.create')
        ->post('/ads/audiences', [AdsController::class, 'store'])->name('ads.audiences.store');

    Route::middleware('permission:ad_audiences.export')
        ->post('/ads/audiences/{audience}/export', [AdsController::class, 'export'])->name('ads.audiences.export');

    Route::middleware('permission:ad_pixels.manage')
        ->post('/ads/setting', [AdsController::class, 'saveSetting'])->name('ads.setting.save');
});
