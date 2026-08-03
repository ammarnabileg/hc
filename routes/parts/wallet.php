<?php

use App\Http\Controllers\Admin\WalletRatesController;
use App\Http\Controllers\GatewayWebhookController;
use App\Http\Controllers\Trainee\TopupController;
use App\Http\Controllers\Trainee\WalletController;
use App\Http\Controllers\Trainee\WalletOperationsController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| المحفظة والمعاملات وشحن الحساب (19 · 19.5 · 24.5)
|--------------------------------------------------------------------------
| كلّ مسار محكوم بصلاحيّته (12.2.1)، والعنصر الذي لا يملكه المستخدم يُخفى
| من الواجهة أصلًا فلا يصطدم بـ403.
*/

Route::middleware('auth')->prefix('wallet')->name('wallet.')->group(function () {

    // 🖥️ رصيدي وشحن
    Route::get('/', [WalletController::class, 'index'])
        ->middleware('permission:wallet.view')
        ->name('index');

    // 🖥️ التذاكر 🎟️
    Route::get('/tickets', [WalletController::class, 'tickets'])
        ->middleware('permission:wallet.view')
        ->name('tickets');

    // 🖥️ المعاملات والفواتير
    Route::get('/transactions', [WalletController::class, 'transactions'])
        ->middleware('permission:wallet.list')
        ->name('transactions');

    Route::get('/transactions/export', [WalletController::class, 'export'])
        ->middleware('permission:wallet.export')
        ->name('transactions.export');

    /*
    | 🖥️ تاب «المسحوبات» (19.2) — 🔒 صلاحيّات السحب والأرباح كلّها owner-only
    | في `permissions.json`، فالتاب لا يظهر أصلًا لغير مالك المنصّة (2.15-أ-7).
    */
    Route::get('/withdrawals', [WalletController::class, 'withdrawals'])
        ->middleware('permission:withdraw.list,earnings.view')
        ->name('withdrawals');

    /*
    | العمليّات المالِيّة الثلاث (19.3).
    | نقاط `quote/*` تحسب الملخّص اللحظيّ **في الخادم** فلا يُحسَب رقمٌ في المتصفّح.
    */
    Route::middleware('permission:transfer.create')->group(function () {
        Route::post('/transfer/quote', [WalletOperationsController::class, 'quoteTransfer'])->name('transfer.quote');
        Route::post('/transfer', [WalletOperationsController::class, 'transfer'])->name('transfer');
        Route::post('/exchange/quote', [WalletOperationsController::class, 'quoteExchange'])->name('exchange.quote');
        Route::post('/exchange', [WalletOperationsController::class, 'exchange'])->name('exchange');
    });

    Route::middleware('permission:withdraw.create')->group(function () {
        Route::post('/withdraw/quote', [WalletOperationsController::class, 'quoteWithdraw'])->name('withdraw.quote');
        Route::post('/withdraw', [WalletOperationsController::class, 'withdraw'])->name('withdraw');
    });

    // 🖥️ شحن الحساب — صفحة واحدة بتابين
    Route::middleware('permission:topup.create')->group(function () {
        Route::get('/topup', [TopupController::class, 'index'])->name('topup');
        Route::post('/topup/manual', [TopupController::class, 'storeManual'])->name('topup.manual');
        Route::post('/topup/gateway', [TopupController::class, 'startGateway'])->name('topup.gateway');

        // رابط الرجوع من البوّابة: عرضٌ فقط ولا يزيد رصيدًا أبدًا (19.5-ج-2)
        Route::get('/topup/return', [TopupController::class, 'gatewayReturn'])->name('topup.return');
    });

    // 🖥️ طلبات الشحن
    Route::get('/topup/requests', [TopupController::class, 'requests'])
        ->middleware('permission:topup.list')
        ->name('topup.requests');
});

/*
| 🔒 أسعار الصرف ورسوم العمليّات — لمالك المنصّة وحده (19.1).
| الصلاحيّة `exchange_rates.*` موسومة `is_owner_only` في المصفوفة، ومع ذلك
| يفحص الكنترولر الدور صراحةً: الحسّاس يُقفَل مرّتين لا مرّة.
*/
Route::middleware(['auth', 'admin.panel'])->prefix('admin/wallet')->name('admin.wallet.')->group(function () {
    Route::get('/rates', [WalletRatesController::class, 'index'])
        ->middleware('permission:exchange_rates.view')
        ->name('rates');

    Route::post('/rates', [WalletRatesController::class, 'save'])
        ->middleware('permission:exchange_rates.edit')
        ->name('rates.save');
});

/*
| حدّ نداءات الويب هوك (2.13 — القيمة إعداد لا رقم محروق).
|
| ⭐ **لماذا حدٌّ أصلًا؟** المسار بلا CSRF وبلا `auth`، فبلا حدٍّ يصير بابَ تعدادٍ
|    مجّانيّ لـ`invoice_id` ولمحاولات التوقيع — ألوف المحاولات في الدقيقة بلا ثمن.
|
| ⭐ **ولماذا 60 في الدقيقة لكلّ IP افتراضًا؟** نداءات فواتيرك المشروعة تأتي كلّها
|    من مدى IP واحد ومتتابعةً (كلّ فاتورة نداء، وإعادة الإرسال عند تأخّر الردّ
|    نداءٌ آخر — وهو سلوكٌ طبيعيّ لا خطأ). فحدٌّ ضيّق يُسقِط ذروةَ يوم عروضٍ
|    ويضيّع إشعارَ دفعٍ حقيقيّ. و60/دقيقة = **3600 نداءً في الساعة من البوّابة
|    وحدها** — أضعافُ أيّ ذروة دفعٍ واقعيّة على المنصّة، وفي الوقت نفسه يجعل
|    تعداد `invoice_id` (يحتاج عشرات الألوف من المحاولات) بلا جدوى عمليّة.
|    والقيمة والنافذة والرسالة كلّها من لوحة الأدمن، فتُشَدّ أو تُرخى بلا نشر.
|
| والنداء المحجوب هنا لا يُكتَب في السجلّ الخام عمدًا: صفٌّ لكلّ نداءٍ محجوب
| يحوّل حدَّ النداءات نفسه إلى بابِ إغراقٍ لقاعدة البيانات.
*/
RateLimiter::for('gateway-webhook', fn (Request $request) => Limit::perMinutes(
    max((int) setting('topup.gateway.webhook.rate_window_minutes', 1), 1),
    max((int) setting('topup.gateway.webhook.rate_limit', 60), 1),
)->by((string) $request->ip())->response(fn () => response()->json([
    'ok' => false,
    'message' => (string) setting('topup.gateway.webhook.rate_limit_message', ''),
], 429)));

/*
| ويب هوك البوّابة: بلا CSRF وبلا auth — لأنّ المنادي خادم فواتيرك لا متصفّح.
| وحمايته من الهاش الموقَّع (19.5-ج-2) **فوق مفتاح تاجرٍ مضبوط** — والمفتاح
| الفارغ يُبطِل الحماية كلّها فيُرفَض النداء ويُسجَّل بسببه. وكلّ نداء يُسجَّل خامًا.
*/
Route::post('/webhooks/fawaterk', [GatewayWebhookController::class, 'fawaterk'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->middleware('throttle:gateway-webhook')
    ->name('webhooks.fawaterk');
