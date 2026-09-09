<?php

use App\Http\Controllers\Admin\AdsController;
use App\Http\Controllers\Admin\ArticleAdminController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\ImageStudioController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\SettingsAdminController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\StoreAdminController;
use App\Http\Controllers\Admin\TopupAdminController;
use App\Http\Controllers\Admin\WithdrawAdminController;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Services\Admin\System\StatsService;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| لوحة الإدارة — المتجر والماليّات والإحصائيّات والإعدادات والنظام (24.3)
|--------------------------------------------------------------------------
| الصلاحيّة على كلّ مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم يُخفى ولا يُعطَّل.
| 🔒 والمجموعة الماليّة ومفاتيح البوّابة لمالك المنصّة وحده — بحارسَي صلاحيّة ودور.
*/

/*
| ⭐⭐ **الحصر على الخادم لمفاتيح المزايا (24.3).**
|
| الحارس يُلحَق بمجموعة `web` كلّها من هنا — لا بمسارٍ مسار. ولماذا؟ لأنّ
| «إطفاء ميزة» يعني إطفاءها في **كلّ** مداخلها: صفحتها وبوب-أباتها ونقاط
| الـXHR فيها. ولو عُلِّق الحارس يدويًّا على كلّ مسار لبقي المنسيُّ مفتوحًا،
| وصار المفتاح يلمع في اللوحة والباب مفتوح — «إعدادٌ بلا أثر أسوأ من غيابه».
|
| والحارس يستنبط ميزةَ الطلب من **اسم المسار** عبر `FeatureCatalog`، ويمرّ
| صامتًا لكلّ مسارٍ لا ميزةَ له (ومنه كلّ `admin.*` — فلا تُقفَل شاشةُ المفاتيح
| على نفسها).
|
| ⚠️ **ولماذا `afterResolving` لا `Route::pushMiddlewareToGroup` وحدها؟** لأنّ
| نواة الـHTTP تُزامِن مجموعاتها إلى الراوتر عند حلّها (`syncMiddlewareToRouter`)
| فتَستبدِل مجموعة `web` كاملةً — وأيّ دفعٍ سابقٍ من ملفّ مسارات **يُمحى بصمت**.
| وحارسٌ يُمحى بصمت أسوأ من غيابه: الشاشة تَعِد بإطفاءٍ لا يقع. فنسجّل الإلحاق
| على النواة نفسها ليبقى بعد كلّ مزامنة، ونُبقي الدفع المباشر لحالة نواةٍ محلولةٍ
| سلفًا (الطرفيّة والاختبارات) — الطريقان معًا لا أحدهما.
*/
app()->afterResolving(HttpKernel::class, function (HttpKernel $kernel) {
    $kernel->appendMiddlewareToGroup('web', EnsureFeatureEnabled::class);
});

if (app()->resolved(HttpKernel::class)) {
    app(HttpKernel::class)->appendMiddlewareToGroup('web', EnsureFeatureEnabled::class);
}

Route::middleware(['auth', 'admin.panel'])->prefix('admin')->name('admin.')->group(function () {

    // ------------------------------------------------------------ المتجر
    Route::middleware('permission:store_products.list,bundles.list,coupons.list,orders.list')->group(function () {
        Route::get('/store', [StoreAdminController::class, 'index'])->name('store.index');
        Route::get('/store/orders/{order}', [StoreAdminController::class, 'showOrder'])->name('store.orders.show');
    });

    /*
    |--------------------------------------------------------------------------
    | ⭐ شاشة البندلز واللاندنج (18 · 24 ← «🖥️ البندلز»)
    |--------------------------------------------------------------------------
    | 🔒 ولا مسار هنا يفتح التسعير: `pricing.edit` («مالك المنصّة فقط» — 12.2.2)
    |    يُفرَض **داخل المتحكّم** لا على المسار، لأنّ مسؤول التسويق والمتجر
    |    (12.2.3-6) يملك `bundles.edit` ويجب أن يصل الشاشة ويحفظ باقي أقسامها —
    |    فالحصر على **الحقل** لا على الباب، وحقول السعر تُنزَع من حمولته.
    |    وكذلك حقلا [كود مخصّص]: بيد **مالك المنصّة** وحده داخل المتحكّم.
    */
    Route::middleware('permission:bundles.view')
        ->get('/store/bundles/{bundle}', [StoreAdminController::class, 'showBundle'])->name('store.bundles.show');

    Route::middleware('permission:bundles.edit')->group(function () {
        Route::put('/store/bundles/{bundle}', [StoreAdminController::class, 'updateBundle'])->name('store.bundles.update');
        Route::post('/store/bundles/{bundle}/items', [StoreAdminController::class, 'storeBundleItem'])->name('store.bundles.items.store');
        Route::put('/store/bundles/{bundle}/items/{item}', [StoreAdminController::class, 'updateBundleItem'])->name('store.bundles.items.update');
        Route::delete('/store/bundles/{bundle}/items/{item}', [StoreAdminController::class, 'destroyBundleItem'])->name('store.bundles.items.destroy');

        // بلوك إعدادات الشاشة و↺ Reset (24)
        Route::post('/store/bundles-settings', [StoreAdminController::class, 'updateBundleSettings'])->name('store.bundles.settings');
        Route::post('/store/bundles-settings/reset', [StoreAdminController::class, 'resetBundleSettings'])->name('store.bundles.settings.reset');
    });

    // «تكرار بندل» من هيدر 24 — إنشاءٌ فيحتاج صلاحيّة الإنشاء لا التعديل
    Route::middleware('permission:bundles.create')
        ->post('/store/bundles/{bundle}/duplicate', [StoreAdminController::class, 'duplicateBundle'])->name('store.bundles.duplicate');

    Route::middleware('permission:bundles.archive')
        ->post('/store/bundles/{bundle}/archive', [StoreAdminController::class, 'archiveBundle'])->name('store.bundles.archive');

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

    // ------------------------------------------------------------ طلبات السحب (19.2 · 19.3)
    Route::middleware('permission:withdraw.list')->group(function () {
        Route::get('/withdrawals', [WithdrawAdminController::class, 'index'])->name('withdrawals.index');
        Route::get('/withdrawals/{withdrawal}', [WithdrawAdminController::class, 'show'])->name('withdrawals.show');
    });

    // 🔒 الاعتماد والرفض لمالك المنصّة وحده — is_owner_only في المصفوفة
    Route::middleware('permission:withdraw.approve')
        ->post('/withdrawals/{withdrawal}/approve', [WithdrawAdminController::class, 'approve'])->name('withdrawals.approve');

    Route::middleware('permission:withdraw.reject')
        ->post('/withdrawals/{withdrawal}/reject', [WithdrawAdminController::class, 'reject'])->name('withdrawals.reject');

    /*
     | ------------------------------------------------------------ الإحصائيّات
     |
     | ⭐⭐ **الباب بسعة محتواه** (12.8 · 12.2.1-أ).
     |
     | كان الحارس `permission:reports_users.view` **وحدها**، فصاحبُ
     | `reports_training.view` — وسطرُه في 12.2.2 يقول «**دائمًا**» لا «مالك
     | المنصّة فقط» وله تابٌّ منصوصٌ في 12.8 — يُردّ بـ403 قبل أن يُسأل عن تابِّه.
     | وعولج مؤقّتًا بإخفاء البند من السايد بار: إخفاءٌ يمنع رسالة الخطأ ولا يعطي
     | صاحبَ الحقّ حقَّه، والنصّ يقول «**بلا صلاحيّة: التاب نفسه لا يظهر**»
     | (24.3-خامسًا) — التابّ لا الصفحة.
     |
     | فالقائمة تُشتقّ من `StatsService::tabs()` نفسها (دلالة الفاصلة «أيٌّ من»)،
     | ثمّ `tabsFor()` تُري كلَّ واحدٍ تابَّه وحده. ومَن لا تابَّ له لا يملك
     | مفتاحًا في القائمة فيُردّ عند الباب كما كان.
     */
    Route::middleware(StatsService::routeGate())->group(function () {
        Route::get('/stats', [StatsController::class, 'index'])->name('stats.index');
        Route::get('/stats/export', [StatsController::class, 'export'])->name('stats.export');
    });

    // ------------------------------------------------------------ الإعدادات والنظام
    Route::middleware('permission:settings_general.view')->group(function () {
        Route::get('/settings', [SettingsAdminController::class, 'index'])->name('settings.index');
        Route::get('/settings/search', [SettingsAdminController::class, 'search'])->name('settings.search');
        // دفعةُ مفاتيح مجموعة — التحميل الكسول لكارت المجموعة (2.15-ب)
        Route::get('/settings/batch', [SettingsAdminController::class, 'groupBatch'])->name('settings.batch');
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

    /*
    | ------------------------------------------------------------ 🖥️ مفاتيح المزايا (24.3)
    | «**البديل الوحيد للصيانة الجزئيّة الملغاة** (12.7-و)» — فالإطفاء لميزةٍ
    | بعينها يتمّ من هنا **فقط**، والصيانة تبقى عامّةً للمنصّة كلّها.
    */
    Route::middleware('permission:feature_toggles.view,feature_toggles.list')->group(function () {
        Route::get('/settings/features/audit', [FeatureFlagController::class, 'audit'])->name('features.audit');
        Route::get('/settings/features/export', [FeatureFlagController::class, 'export'])->name('features.export');
    });

    Route::middleware('permission:feature_toggles.edit')->group(function () {
        Route::post('/settings/features/toggle', [FeatureFlagController::class, 'toggle'])->name('features.toggle');
        Route::post('/settings/features/scope', [FeatureFlagController::class, 'scope'])->name('features.scope');
        Route::post('/settings/features/reset', [FeatureFlagController::class, 'reset'])->name('features.reset');
        Route::post('/settings/features/settings', [FeatureFlagController::class, 'saveSettings'])->name('features.settings');
    });

    Route::middleware('permission:feature_toggles.manage')->group(function () {
        Route::post('/settings/features/reset-all', [FeatureFlagController::class, 'resetAll'])->name('features.reset-all');
        Route::post('/settings/features/import', [FeatureFlagController::class, 'import'])->name('features.import');
    });

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
