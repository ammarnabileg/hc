<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\LibraryEntitlement;
use App\Models\Product;
use App\Models\ReadingProgress;
use App\Services\Library\EntitlementGuard;
use App\Services\Library\PageWatermark;
use App\Services\Library\PdfPageRenderer;
use App\Services\Library\ProductToc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * القارئ المحميّ (الدستور 20.3 · 24.5) — **الأمان أهمّ من الشكل**:
 *  - الصفحة تُخدَم صورةً مربوطةً بالجلسة: بلا تحميل وبلا رابط ملفّ مباشر.
 *  - الرابط لا يفتح لغير المالك (تحقّقٌ صريح من `library_entitlements`).
 *  - علامة مائيّة ديناميكيّة بكلّ صفحة تُرسَم لحظة العرض ولا تُخزَّن نسخة لكلّ مستخدم.
 */
class ReaderController extends Controller
{
    public function __construct(
        private readonly EntitlementGuard $guard,
        private readonly PdfPageRenderer $renderer,
        private readonly PageWatermark $watermark,
        private readonly ProductToc $toc,
    ) {}

    public function read(Request $request, Product $product): View
    {
        $user = $request->user();
        $entitlement = $this->requireEntitlement($request, $product);

        $pages = $this->renderer->pageCount($product);
        $progress = ReadingProgress::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        $start = max(1, min($pages, (int) ($progress->last_page ?? 1)));

        return view('library.read', [
            'product' => $product,
            'pages' => $pages,
            'startPage' => $start,
            'hasProgress' => $progress !== null && $progress->last_page > 1,
            'availability' => $this->guard->availability($entitlement),
            'watermarkText' => $this->watermark->layerText($user),
            'watermarkRepeat' => $this->watermark->repeatCount(),
            // العلامة المائيّة تُشغَّل/تُطفَأ **لكلّ منتج** من شاشة الحماية (20.5)
            'watermarkEnabled' => $this->watermark->enabled($product),
            'engineAvailable' => $this->renderer->isEngineAvailable(),
            // فهرس (TOC) — بجانب المصغّرات في القارئ (20.3)
            'toc' => $this->toc->entries($product, $pages),
            'pageUrl' => route('library.page', ['product' => $product->id, 'page' => '__PAGE__']),
            'thumbUrl' => route('library.thumb', ['product' => $product->id, 'page' => '__PAGE__']),
            'progressUrl' => route('library.progress', $product),
            'teaser' => false,
            'buyUrl' => null,
        ]);
    }

    public function page(Request $request, Product $product, int $page): Response
    {
        $this->requireEntitlement($request, $product);

        return $this->streamPage($request, $product, $page, (int) setting('reader.page.width_px', 1000));
    }

    public function thumb(Request $request, Product $product, int $page): Response
    {
        $this->requireEntitlement($request, $product);

        return $this->streamPage($request, $product, $page, (int) setting('reader.thumb.width_px', 160));
    }

    /** «تابع القراءة» (20.3): آخر صفحة يُحفَظ لصاحبها وحده */
    public function progress(Request $request, Product $product): JsonResponse
    {
        $this->requireEntitlement($request, $product);

        $page = max(1, min($this->renderer->pageCount($product), (int) $request->integer('page', 1)));

        ReadingProgress::updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $product->id],
            ['last_page' => $page],
        );

        return response()->json(['saved' => true, 'page' => $page]);
    }

    // ------------------------------------------------------------------ العيّنة

    /** صفحات عيّنة (Teaser): أوّل N صفحات ثمّ بلوك [شراء] (20.3) */
    public function teaser(Request $request, Product $product): View
    {
        $limit = $this->teaserLimit($product);

        if ($request->user() && $this->guard->owns($request->user(), $product)) {
            // المالك لا يُعرَض عليه عيّنة — يُفتَح له الملفّ كاملًا
            return $this->read($request, $product);
        }

        return view('library.read', [
            'product' => $product,
            'pages' => $limit,
            'startPage' => 1,
            'hasProgress' => false,
            'availability' => ['state' => 'idle', 'label' => (string) setting('reader.teaser.badge_label', 'صفحات عيّنة')],
            'watermarkText' => (string) setting('reader.teaser.watermark_text', 'عيّنة'),
            'watermarkRepeat' => $this->watermark->repeatCount(),
            'watermarkEnabled' => $this->watermark->enabled($product),
            'engineAvailable' => $this->renderer->isEngineAvailable(),
            // فهرس العيّنة مقصورٌ على صفحاتها — فلا يكشف ما بعد الحدّ
            'toc' => $this->toc->entries($product, $limit),
            'pageUrl' => route('library.teaser.page', ['product' => $product->id, 'page' => '__PAGE__']),
            'thumbUrl' => route('library.teaser.page', ['product' => $product->id, 'page' => '__PAGE__']),
            'progressUrl' => null,
            'teaser' => true,
            'buyUrl' => $this->buyUrl($product),
        ]);
    }

    /** رابط الشراء من المتجر — ومسار مجالٍ آخر لا يكسر شاشتنا لو تغيّر توقيعه */
    private function buyUrl(Product $product): string
    {
        if (Route::has('store.product')) {
            try {
                return route('store.product', ['type' => 'product', 'slug' => $product->slug, 'product' => $product->slug]);
            } catch (\Throwable) {
                // نكمل إلى المتجر العامّ
            }
        }

        return Route::has('store.index') ? route('store.index') : url('/');
    }

    public function teaserPage(Request $request, Product $product, int $page): Response
    {
        if ($request->user() && $this->guard->owns($request->user(), $product)) {
            return $this->streamPage($request, $product, $page, (int) setting('reader.page.width_px', 1000));
        }

        abort_if($page > $this->teaserLimit($product), 403, (string) setting(
            'reader.teaser.blocked_message',
            'دي آخر صفحة في العيّنة — اشترِ المنتج لتكمل.',
        ));

        return $this->streamPage($request, $product, $page, (int) setting('reader.page.width_px', 1000));
    }

    private function teaserLimit(Product $product): int
    {
        $limit = (int) $product->teaser_pages;

        abort_if($limit < 1, 404);

        return min($limit, $this->renderer->pageCount($product));
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * البوّابة الأمنيّة الوحيدة للقارئ: بلا إتاحة في `library_entitlements`
     * لا تُفتَح الصفحة ولا تُخدَم أيّ صورة.
     */
    private function requireEntitlement(Request $request, Product $product): LibraryEntitlement
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $entitlement = $this->guard->entitlementFor($user, $product);

        abort_if($entitlement === null, 403, (string) setting(
            'reader.forbidden_message',
            'الملفّ ده مش في مكتبتك — افتحه من المتجر الأوّل.',
        ));

        abort_unless($this->guard->isAvailableNow($entitlement), 403, (string) setting(
            'reader.unavailable_message',
            'العنصر ده لسّه خارج فترة إتاحته.',
        ));

        return $entitlement;
    }

    /**
     * تسليم الصفحة: الكاش بلا مستخدم، والعلامة المائيّة تُركَّب في الذاكرة
     * لكلّ طلب — فلا نسخة مخزَّنة لكلّ قارئ (20.3).
     */
    private function streamPage(Request $request, Product $product, int $page, int $width): Response
    {
        $pages = $this->renderer->pageCount($product);

        abort_if($page < 1 || $page > $pages, 404);

        $rendered = $this->renderer->renderPage($product, $page, $width);
        $body = $request->user()
            ? $this->watermark->stamp($rendered['body'], $request->user(), $product)
            : $rendered['body'];

        return response($body, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline',
            'Content-Length' => (string) strlen($body),
            // خاصّ بالجلسة ولا يُخزَّن في أيّ كاش وسيط
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Reader-Placeholder' => $rendered['placeholder'] ? '1' : '0',
        ]);
    }
}
