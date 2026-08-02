<?php

namespace App\Services\Library;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/**
 * تحويل صفحة الـPDF إلى صورة **عند الطلب** مع كاش (20.3).
 *
 * لماذا هكذا: الملفّ الأصل يبقى خارج الويب تمامًا، والمتصفّح لا يرى إلّا صورةً
 * لصفحةٍ واحدة مربوطةٍ بالجلسة — فلا تحميل ولا رابط ملفّ مباشر.
 *
 * الكاش **بلا مستخدم**: نسخةٌ واحدة لكلّ (ملفّ + صفحة + عرض)، والعلامة المائيّة
 * تُركَّب فوقها لحظة العرض — فلا تُخزَّن نسخة لكلّ قارئ (20.3).
 *
 * لو محرّك الرسم غير متاح (Imagick/Ghostscript) نُخرج **بديلًا آمنًا**:
 * صورة placeholder مرسومة بـGD + رسالة واضحة في الواجهة — بلا كسر وبلا مكتبة خارجيّة.
 */
class PdfPageRenderer
{
    /** هل محرّك تحويل الـPDF متاح على هذا الخادم؟ */
    public function isEngineAvailable(): bool
    {
        if (! class_exists(\Imagick::class)) {
            return false;
        }

        try {
            $formats = \Imagick::queryFormats('PDF');
        } catch (\Throwable) {
            return false;
        }

        return $formats !== [];
    }

    /** عدد صفحات الملفّ — من المحرّك إن وُجد، وإلّا بقراءة بنية الـPDF نفسها */
    public function pageCount(Product $product): int
    {
        $path = $this->absolutePath($product);
        $fallback = max(1, (int) setting('reader.page_count.fallback', 1));

        if (! $path) {
            return $fallback;
        }

        if ($this->isEngineAvailable()) {
            try {
                $imagick = new \Imagick;
                $imagick->pingImage($path);
                $count = $imagick->getNumberImages();
                $imagick->clear();

                if ($count > 0) {
                    return $count;
                }
            } catch (\Throwable) {
                // نكمل بالقراءة البنيويّة بدل الفشل
            }
        }

        return max($this->countFromStructure($path), $fallback);
    }

    /**
     * صورة الصفحة (PNG) — من الكاش أو بالتوليد.
     *
     * @return array{body:string,placeholder:bool}
     */
    public function renderPage(Product $product, int $page, int $width): array
    {
        $path = $this->absolutePath($product);
        $cacheKey = $this->cachePath($product, $path, $page, $width);
        $disk = Storage::disk('local');

        if ($disk->exists($cacheKey)) {
            return [
                'body' => (string) $disk->get($cacheKey),
                'placeholder' => ! $this->isEngineAvailable(),
            ];
        }

        $body = $path && $this->isEngineAvailable()
            ? $this->rasterize($path, $page, $width)
            : null;

        $placeholder = $body === null;
        $body ??= $this->placeholder($page, $width);

        $disk->put($cacheKey, $body);

        return ['body' => $body, 'placeholder' => $placeholder];
    }

    /** مسح كاش منتج — يُستدعى حين يحدّث الأدمن الملفّ (20.3) */
    public function forget(Product $product): void
    {
        Storage::disk('local')->deleteDirectory($this->cacheDirectory($product));
    }

    // ------------------------------------------------------------------ داخليّ

    private function absolutePath(Product $product): ?string
    {
        if (! $product->file_path) {
            return null;
        }

        $disk = Storage::disk('local');

        return $disk->exists($product->file_path) ? $disk->path($product->file_path) : null;
    }

    private function cacheDirectory(Product $product): string
    {
        return trim((string) setting('reader.cache.directory', 'library/reader-cache'), '/').'/'.$product->id;
    }

    private function cachePath(Product $product, ?string $path, int $page, int $width): string
    {
        // بصمة الملفّ داخل المفتاح: تحديث الأدمن للملفّ يبطل الكاش تلقائيًّا (20.3)
        $stamp = $path ? substr(sha1($path.'|'.filemtime($path).'|'.filesize($path)), 0, 16) : 'missing';

        return $this->cacheDirectory($product)."/{$stamp}-p{$page}-w{$width}.png";
    }

    private function rasterize(string $path, int $page, int $width): ?string
    {
        try {
            $imagick = new \Imagick;
            $imagick->setResolution(
                (int) setting('reader.render.dpi', 150),
                (int) setting('reader.render.dpi', 150),
            );
            $imagick->readImage($path.'['.($page - 1).']');
            $imagick->setImageBackgroundColor('white');
            $imagick = $imagick->flattenImages();
            $imagick->setImageFormat('png');
            $imagick->thumbnailImage($width, 0);
            $body = $imagick->getImageBlob();
            $imagick->clear();

            return $body;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * البديل الآمن: ورقةٌ فارغة بحدودٍ ورقمِ صفحة — والرسالة النصّيّة تُعرَض
     * في الواجهة بالعربيّة (GD لا يرسم عربيًّا بلا خطّ مثبَّت).
     */
    private function placeholder(int $page, int $width): string
    {
        $ratio = (float) setting('reader.page.aspect_ratio', 1.414);
        $height = (int) round($width * $ratio);

        $image = imagecreatetruecolor($width, $height);
        $paper = imagecolorallocate($image, 245, 245, 242);
        $line = imagecolorallocate($image, 214, 214, 208);
        $ink = imagecolorallocate($image, 120, 120, 116);

        imagefilledrectangle($image, 0, 0, $width, $height, $paper);
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $line);

        // أسطرٌ رماديّة توحي بالنصّ — Skeleton بشكل المحتوى الحقيقيّ (2.15-د)
        $margin = (int) round($width * 0.12);
        $step = (int) round($height * 0.045);

        for ($y = (int) round($height * 0.2); $y < $height - $margin; $y += $step) {
            $end = $width - $margin - ($y % (3 * $step) === 0 ? (int) round($width * 0.2) : 0);
            imagefilledrectangle($image, $margin, $y, $end, $y + 3, $line);
        }

        imagestring($image, 5, $margin, (int) round($height * 0.1), (string) $page, $ink);

        ob_start();
        imagepng($image);
        $body = (string) ob_get_clean();
        imagedestroy($image);

        return $body;
    }

    /** قراءة عدد الصفحات من بنية الـPDF — بلا أيّ مكتبة خارجيّة */
    private function countFromStructure(string $path): int
    {
        $contents = (string) @file_get_contents($path);

        if ($contents === '') {
            return 0;
        }

        if (preg_match_all('/\/Count\s+(\d+)/', $contents, $matches)) {
            $counts = array_map('intval', $matches[1]);

            if ($counts !== []) {
                return max($counts);
            }
        }

        return preg_match_all('/\/Type\s*\/Page[^s]/', $contents);
    }
}
