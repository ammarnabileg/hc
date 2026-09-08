<?php

namespace App\Services\Images\Concerns;

use App\Services\Certificates\ArabicText;
use App\Services\Certificates\GdEngine;

/**
 * أدوات الرسم المشتركة بـGD (12.14-و) — **محرّك واحد وصفر ازدواج**:
 * قوالب الاستوديو والاستخراج كصورة يرسمان بنفس الدوالّ بنفس الخطّ
 * فتخرج النتيجة واحدةً على كلّ الأجهزة.
 */
trait DrawsWithGd
{
    /**
     * خطّ Cairo المضمَّن — ومساره إعداد فلا يُحرَق في الكود (2.13). ولو غاب من
     * القرص فخطوط النظام المرشّحة نفسها التي يرتدّ إليها مصمّم الشهادات
     * (12.14 — نفس المحرّك) بدل الردّ الفوريّ بلا خطّ.
     */
    public function fontPath(): ?string
    {
        $path = (string) setting('images.font.path', 'fonts/Cairo-Regular.ttf');

        return GdEngine::fontPath(public_path($path));
    }

    /**
     * ⭐ تاريخ اللقطة وشعار المنصّة على كلّ صورة مستخرَجة (12.14-هـ)
     * — فلا يُنشَر ترتيبٌ قديم على أنّه حاليّ.
     */
    public function stampCanvas($canvas, int $width, int $height): void
    {
        $parts = [];

        if (setting('images.watermark.show_date', true)) {
            $parts[] = now()->format('Y/m/d');
        }

        if (setting('images.watermark.show_logo', true)) {
            $parts[] = (string) setting('platform.identity.name', config('app.name'));
        }

        if ($parts === []) {
            return;
        }

        $text = implode(' · ', $parts);
        $color = $this->allocate($canvas, (string) setting('images.watermark.color', '#9fb3c8'));
        $size = max(10, (int) round($height * 0.018));

        $this->writeText($canvas, $text, 24, $height - 24 - $size, $size, $color, 'left');
    }

    protected function allocate($canvas, string $hex): int
    {
        return GdEngine::color($canvas, $hex);
    }

    protected function loadImage(string $path)
    {
        $info = @getimagesize($path);

        return match ($info[2] ?? null) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        } ?: null;
    }

    /** قصّ دائريّ **بلا هالة** (2.10.1-16) */
    protected function maskCircle($image, int $w, int $h): void
    {
        $mask = imagecreatetruecolor($w, $h);
        imagealphablending($mask, false);
        imagesavealpha($mask, true);
        imagefill($mask, 0, 0, imagecolorallocatealpha($mask, 0, 0, 0, 127));
        imagefilledellipse($mask, intdiv($w, 2), intdiv($h, 2), $w, $h, imagecolorallocatealpha($mask, 255, 255, 255, 0));

        for ($px = 0; $px < $w; $px++) {
            for ($py = 0; $py < $h; $py++) {
                $alpha = (imagecolorat($mask, $px, $py) >> 24) & 0x7F;

                if ($alpha > 0) {
                    imagesetpixel($image, $px, $py, imagecolorallocatealpha($image, 0, 0, 0, 127));
                }
            }
        }

        imagedestroy($mask);
    }

    /** @return array{0:int,1:int,2:int,3:int} */
    protected function cropBox(int $srcW, int $srcH, int $dstW, int $dstH, string $fit): array
    {
        $srcRatio = $srcW / max(1, $srcH);
        $dstRatio = $dstW / max(1, $dstH);

        if ($fit === 'contain') {
            return [0, 0, $srcW, $srcH];
        }

        if ($srcRatio > $dstRatio) {
            $w = (int) round($srcH * $dstRatio);

            return [intdiv($srcW - $w, 2), 0, $w, $srcH];
        }

        $h = (int) round($srcW / $dstRatio);

        return [0, intdiv($srcH - $h, 2), $srcW, $h];
    }

    /**
     * ⭐ تشكيل النصّ العربيّ قبل الرسم (12.14 — **نفس محرّك 12.5-ب**: مصمّم
     * الشهادات هو المحرّك المرجعيّ، وهذه الوحدة تستهلكه بدل تكرار خوارزميّة
     * تشكيلٍ مستقلّة — صفر ازدواج حرفيًّا لا تعليقًا فقط).
     *
     * **لماذا؟** لأنّ GD يرسم الحروف كما تُعطى له: بلا وصل وبلا ترتيب من اليمين،
     * فتخرج «محمد» أربعة حروف مفكوكة مقلوبة. و`ArabicText::prepare()` (خطّ
     * Cairo نفسه في الاستوديو والشهادات) يحوّل كلّ حرف إلى صورته المتّصلة
     * ويرتّب السطر بصريًّا.
     */
    protected function shapeRtl(string $text): string
    {
        return ArabicText::prepare($text);
    }

    /** عرض النصّ بالبكسل — لمحاذاة RTL الصحيحة */
    protected function textWidth(string $text, int $size): int
    {
        return $this->rawWidth($this->shapeRtl($text), $size);
    }

    /** كتابة نصّ بمحاذاة right|center|left — والافتراضيّ right لأنّ المنصّة RTL */
    protected function writeText($canvas, string $text, int $x, int $y, int $size, int $color, string $align = 'right'): void
    {
        if (trim($text) === '') {
            return;
        }

        $shaped = $this->shapeRtl($text);
        $font = $this->fontPath();

        if ($font === null || ! function_exists('imagettftext')) {
            imagestring($canvas, 5, $align === 'right' ? max(0, $x - (int) (mb_strlen($shaped) * 8)) : $x, $y, $shaped, $color);

            return;
        }

        $width = $this->rawWidth($shaped, $size);

        $x = match ($align) {
            'center' => $x - intdiv($width, 2),
            'left' => $x,
            default => $x - $width,
        };

        imagettftext($canvas, $size, 0, $x, $y + $size, $color, $font, $shaped);
    }

    protected function rawWidth(string $shaped, int $size): int
    {
        $font = $this->fontPath();

        if ($font === null || ! function_exists('imagettfbbox')) {
            return (int) (mb_strlen($shaped) * $size * 0.55);
        }

        $box = imagettfbbox($size, 0, $font, $shaped);

        return (int) abs($box[2] - $box[0]);
    }
}
