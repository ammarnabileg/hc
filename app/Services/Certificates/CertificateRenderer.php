<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;

/**
 * مولّد صورة الشهادة على الخادم بـGD (8 · 12.5-ب) — **بلا أيّ خدمة أو مكتبة خارجيّة**.
 *
 * يرسم من **نسخة القالب المجمَّدة** في `template_snapshot` (12.5-ج): خلفيّة مرفوعة
 * + طبقات نصّ بإحداثيّاتها وخطّها ولونها ومحاذاتها — فلو تغيّر القالب لاحقًا
 * تبقى الشهادة القديمة بشكلها الذي صدرت به.
 */
class CertificateRenderer
{
    /** خطوط النظام المرشّحة حين لا يحدّد الأدمن خطًّا (الشبكة محجوبة فلا تنزيل) */
    private const FONT_CANDIDATES = [
        'resources/fonts/Cairo.ttf',
        '/usr/share/fonts/truetype/cairo/Cairo.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSerif.ttf',
    ];

    /** صورة الشهادة PNG — مع كاش على القرص لأنّ الرسم أغلى من القراءة (2.7) */
    public function png(Certificate $certificate): string
    {
        $disk = Storage::disk('local');
        $path = 'certificates/'.$certificate->code.'-'.$certificate->language.'.png';

        if ((bool) setting('certificates.render.cache_enabled', true) && $disk->exists($path)) {
            return (string) $disk->get($path);
        }

        $png = $this->draw($certificate);
        $disk->put($path, $png);

        return $png;
    }

    /** يمسح الصورة المخزَّنة — تُستدعى عند تغيّر حالة الشهادة */
    public function forget(Certificate $certificate): void
    {
        Storage::disk('local')->delete('certificates/'.$certificate->code.'-'.$certificate->language.'.png');
    }

    // ------------------------------------------------------------ الرسم

    private function draw(Certificate $certificate): string
    {
        $template = (array) ($certificate->template_snapshot ?? []);
        $data = (array) ($certificate->data_snapshot ?? []);

        $width = (int) ($template['width_px'] ?? setting('certificates.render.default_width_px', 1754));
        $height = (int) ($template['height_px'] ?? setting('certificates.render.default_height_px', 1240));

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);

        $background = $this->loadBackground($template['background_path'] ?? null);

        if ($background) {
            imagecopyresampled($image, $background, 0, 0, 0, 0, $width, $height, imagesx($background), imagesy($background));
            imagedestroy($background);
        } else {
            $this->drawDefaultBackground($image, $width, $height, $certificate);
        }

        foreach ($this->layers($template) as $layer) {
            $this->drawLayer($image, $layer, $data, $certificate, $width, $height);
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /** @return list<array<string,mixed>> */
    private function layers(array $template): array
    {
        $layers = $template['layers'] ?? null;

        return is_array($layers) && $layers !== [] ? array_values($layers) : $this->defaultLayers();
    }

    private function loadBackground(?string $path): ?\GdImage
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');
        $binary = $disk->exists($path) ? $disk->get($path) : (is_file($path) ? file_get_contents($path) : null);

        if (! $binary) {
            return null;
        }

        $image = @imagecreatefromstring($binary);

        return $image ?: null;
    }

    /**
     * التصميم الافتراضيّ الجاهز (12.5-ب): يعمل من أوّل يوم بلا رفع أيّ خلفيّة —
     * بهويّة المنصّة (2.10.1) وبإطار ذهبيّ لأنّ الذهبيّ لون الشرف لا لون الحالة (2.16).
     */
    private function drawDefaultBackground(\GdImage $image, int $width, int $height, Certificate $certificate): void
    {
        $paper = $this->color($image, (string) setting('certificates.render.paper_color', '#0b1512'));
        imagefilledrectangle($image, 0, 0, $width, $height, $paper);

        $brand = $this->color($image, (string) setting('certificates.render.brand_color', '#00d4b8'));
        $honor = $this->color($image, (string) setting('certificates.render.honor_color', '#d4af37'));

        $margin = (int) round($width * 0.035);
        $thickness = max(2, (int) round($width * 0.004));

        for ($i = 0; $i < $thickness; $i++) {
            imagerectangle($image, $margin + $i, $margin + $i, $width - $margin - $i, $height - $margin - $i, $honor);
        }

        $inner = $margin + ($thickness * 3);
        imagerectangle($image, $inner, $inner, $width - $inner, $height - $inner, $brand);

        // شريط علويّ خفيف يحمل شارة الاعتماد بصريًّا
        imagefilledrectangle($image, $inner, $inner, $width - $inner, $inner + (int) round($height * 0.012), $brand);

        // حالة الشهادة تُعرَض على الصورة نفسها إن لم تكن سارية (13.4-ق)
        if ($certificate->status !== 'valid') {
            $this->drawStatusRibbon($image, $width, $height, $certificate);
        }
    }

    private function drawStatusRibbon(\GdImage $image, int $width, int $height, Certificate $certificate): void
    {
        $label = $certificate->status === 'revoked'
            ? (string) setting('certificates.status.revoked_label', 'ملغاة')
            : (string) setting('certificates.status.expired_label', 'منتهية');

        $color = $this->color($image, $certificate->status === 'revoked'
            ? (string) setting('certificates.render.revoked_color', '#ef4444')
            : (string) setting('certificates.render.expired_color', '#94a3b8'));

        $size = (int) round($height * 0.035);
        $text = ArabicText::prepare($label);
        $font = $this->fontPath();

        if (! $font) {
            return;
        }

        $box = imagettfbbox($size, 0, $font, $text);
        $textWidth = abs($box[2] - $box[0]);
        imagettftext($image, $size, 0, (int) (($width - $textWidth) / 2), (int) ($height * 0.93), $color, $font, $text);
    }

    private function drawLayer(\GdImage $image, array $layer, array $data, Certificate $certificate, int $width, int $height): void
    {
        $type = (string) ($layer['type'] ?? 'text');

        if ($type === 'qr') {
            $this->drawQr($image, $layer, $certificate, $width, $height);

            return;
        }

        $value = $this->resolveValue($layer, $data);

        // الحقول الشرطيّة: الفارغ لا يظهر (12.5-ب)
        if (trim($value) === '') {
            return;
        }

        $font = $this->fontPath();

        if (! $font) {
            $this->drawFallbackText($image, $layer, $value, $width);

            return;
        }

        $size = (int) round(($layer['size'] ?? 28) * ($width / 1754));
        $size = max(8, $size);
        $angle = (float) ($layer['rotate'] ?? 0);
        $color = $this->color($image, (string) ($layer['color'] ?? setting('certificates.render.text_color', '#e8f5f2')));
        $x = (int) round(($layer['x'] ?? 0) * $width);
        $y = (int) round(($layer['y'] ?? 0) * $height);
        $align = (string) ($layer['align'] ?? 'center');
        $lineHeight = (int) round($size * (float) setting('certificates.render.line_height', 1.6));

        foreach (ArabicText::wrap($value, (int) ($layer['max_chars'] ?? setting('certificates.render.max_chars_per_line', 48))) as $index => $line) {
            $shaped = ArabicText::prepare($line);
            $box = imagettfbbox($size, $angle, $font, $shaped);
            $lineWidth = abs($box[2] - $box[0]);

            $drawX = match ($align) {
                'start' => $x - $lineWidth, // البداية في RTL = اليمين
                'end' => $x,
                default => $x - (int) round($lineWidth / 2),
            };

            imagettftext($image, $size, $angle, $drawX, $y + ($index * $lineHeight), $color, $font, $shaped);
        }
    }

    /** بديل آمن لو لم يوجد خطّ TTF على الخادم — لا تُترَك الشهادة فارغة أبدًا */
    private function drawFallbackText(\GdImage $image, array $layer, string $value, int $width): void
    {
        $color = $this->color($image, (string) ($layer['color'] ?? '#e8f5f2'));
        $x = (int) round(($layer['x'] ?? 0) * $width);
        $y = (int) round(($layer['y'] ?? 0) * imagesy($image));
        imagestring($image, 5, max(0, $x - 60), max(0, $y - 10), $value, $color);
    }

    private function drawQr(\GdImage $image, array $layer, Certificate $certificate, int $width, int $height): void
    {
        $side = (int) round(($layer['size'] ?? 0.14) * $width);
        $side = max(80, $side);

        $png = QrCode::png($this->verifyUrl($certificate), $side, (int) setting('certificates.render.qr_margin_modules', 4));
        $qr = imagecreatefromstring($png);

        if (! $qr) {
            return;
        }

        $x = (int) round(($layer['x'] ?? 0.5) * $width) - (int) round(imagesx($qr) / 2);
        $y = (int) round(($layer['y'] ?? 0.5) * $height) - (int) round(imagesy($qr) / 2);
        imagecopy($image, $qr, $x, $y, 0, 0, imagesx($qr), imagesy($qr));
        imagedestroy($qr);
    }

    public function verifyUrl(Certificate $certificate): string
    {
        return route('verify.certificate', ['code' => $certificate->code]);
    }

    private function resolveValue(array $layer, array $data): string
    {
        // «نصّ ثابت يكتبه الأدمن + العمود المختار» (12.5-ب)
        $static = (string) ($layer['text'] ?? '');
        $field = $layer['field'] ?? null;
        $dynamic = $field ? (string) ($data[$field] ?? '') : '';

        if ($field && $dynamic === '') {
            return '';
        }

        return trim($static.($static !== '' && $dynamic !== '' ? ' ' : '').$dynamic);
    }

    /** التصميم الافتراضيّ: مواضع نسبيّة كي يعمل على أيّ مقاس */
    private function defaultLayers(): array
    {
        return [
            ['type' => 'text', 'text' => (string) setting('certificates.render.heading', 'شهادة معتمدة'), 'x' => 0.5, 'y' => 0.24, 'size' => 62, 'color' => '#d4af37'],
            ['type' => 'text', 'text' => (string) setting('certificates.render.subheading', 'تشهد المنصّة بأنّ'), 'x' => 0.5, 'y' => 0.34, 'size' => 30, 'color' => '#9bb3ad'],
            ['type' => 'text', 'field' => 'holder_name', 'x' => 0.5, 'y' => 0.45, 'size' => 54, 'color' => '#e8f5f2'],
            ['type' => 'text', 'text' => (string) setting('certificates.render.completion_text', 'قد أتمّ بنجاح'), 'x' => 0.5, 'y' => 0.53, 'size' => 28, 'color' => '#9bb3ad'],
            ['type' => 'text', 'field' => 'certificate_name', 'x' => 0.5, 'y' => 0.62, 'size' => 40, 'color' => '#00d4b8'],
            ['type' => 'text', 'field' => 'accreditation_name', 'x' => 0.5, 'y' => 0.70, 'size' => 24, 'color' => '#9bb3ad'],
            ['type' => 'text', 'field' => 'issued_on', 'x' => 0.25, 'y' => 0.85, 'size' => 24, 'color' => '#9bb3ad'],
            ['type' => 'text', 'field' => 'country', 'x' => 0.25, 'y' => 0.89, 'size' => 24, 'color' => '#9bb3ad'],
            ['type' => 'text', 'field' => 'code', 'x' => 0.75, 'y' => 0.85, 'size' => 24, 'color' => '#9bb3ad'],
            ['type' => 'qr', 'x' => 0.85, 'y' => 0.80, 'size' => 0.12],
        ];
    }

    public function fontPath(): ?string
    {
        $configured = (string) setting('certificates.render.font_path', '');

        foreach (array_merge($configured !== '' ? [$configured] : [], self::FONT_CANDIDATES) as $candidate) {
            $path = str_starts_with($candidate, '/') ? $candidate : base_path($candidate);

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function color(\GdImage $image, string $hex): int
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            $hex = 'ffffff';
        }

        return (int) imagecolorallocate(
            $image,
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }
}
