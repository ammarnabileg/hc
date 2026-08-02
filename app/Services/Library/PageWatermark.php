<?php

namespace App\Services\Library;

use App\Models\Product;
use App\Models\User;

/**
 * العلامة المائيّة الديناميكيّة (20.3).
 *
 * قاعدةٌ حاسمة: **ملفٌّ مصدرٌ واحد + علامةٌ حيّة لكلّ قارئ** — تُرسَم لحظة العرض
 * كطبقة (Overlay) ولا تُخزَّن نسخة لكلّ مستخدم أبدًا.
 *
 * طبقتان لأنّ لكلٍّ دورًا:
 *  1) طبقة DOM فوق الصفحة تحمل **الاسم + الكود** بالعربيّة (هي الطبقة المعتمَدة في 20.3).
 *  2) ختمٌ لاتينيّ بالكود داخل بايتات الصورة نفسها — يُركَّب في الذاكرة لكلّ طلب،
 *     فيبقى أثر المالك حتى لو نُزِعت طبقة الـDOM بأدوات المتصفّح.
 */
class PageWatermark
{
    /** نصّ الطبقة الظاهرة: الاسم + الكود (20.3) */
    public function layerText(User $user): string
    {
        return str_replace(
            [':name', ':code'],
            [$user->shortName(), (string) $user->code],
            (string) setting('reader.watermark.template', ':name · #:code'),
        );
    }

    /** عدد تكرارات الطبقة على الصفحة */
    public function repeatCount(): int
    {
        return max(1, (int) setting('reader.watermark.repeat_count', 12));
    }

    /**
     * علامة المنصّة نفسها — **لوجو المنصّة، أو اسمها لو اللوجو غير متوفّر** (9).
     * تستعملها المعاينة المجّانيّة للسيرة قبل الخصم.
     *
     * @return array{logo:?string, text:string}
     */
    public function platformMark(): array
    {
        $logo = trim((string) setting('platform.identity.logo_path', ''));

        return [
            'logo' => $logo !== '' ? $logo : null,
            'text' => (string) setting('platform.identity.name', config('app.name')),
        ];
    }

    /**
     * الإعداد العامّ للمنصّة، و**المنتج يقرّر لنفسه** حين يُمرَّر (20.5).
     * فترتيب الحسم: المنتج أوّلًا ثمّ الإعداد العامّ.
     */
    public function enabled(?Product $product = null): bool
    {
        if (! (bool) setting('reader.watermark.enabled', true)) {
            return false;
        }

        return $product === null || (bool) ($product->watermark_enabled ?? true);
    }

    /**
     * ختم الكود داخل الصورة — في الذاكرة فقط، ولا يُكتَب على القرص أبدًا.
     * وإن تعذّر لأيّ سبب تعود الصورة كما هي بلا كسر (طبقة الـDOM باقية).
     */
    public function stamp(string $png, User $user, ?Product $product = null): string
    {
        if (! $this->enabled($product)) {
            return $png;
        }

        $mark = $this->asciiMark($user);

        if ($mark === '') {
            return $png;
        }

        $image = @imagecreatefromstring($png);

        if ($image === false) {
            return $png;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $alpha = (int) round(127 - (127 * ((int) setting('reader.watermark.opacity_percent', 12) / 100)));
        $ink = imagecolorallocatealpha($image, 90, 90, 90, max(0, min(127, $alpha)));

        $font = 5;
        $stepX = max(120, (int) round($width / 2.2));
        $stepY = max(80, (int) round($height / max(2, $this->repeatCount() / 2)));
        $row = 0;

        for ($y = (int) round($stepY / 2); $y < $height; $y += $stepY) {
            $offset = ($row % 2) * (int) round($stepX / 2);

            for ($x = 20 + $offset; $x < $width; $x += $stepX) {
                imagestring($image, $font, $x, $y, $mark, $ink);
            }

            $row++;
        }

        ob_start();
        imagepng($image);
        $body = (string) ob_get_clean();
        imagedestroy($image);

        return $body;
    }

    /** الجزء اللاتينيّ من العلامة (كود المستخدم) — GD لا يرسم العربيّة بلا خطّ مثبَّت */
    private function asciiMark(User $user): string
    {
        $code = preg_replace('/[^A-Za-z0-9\-_#]/', '', (string) $user->code) ?? '';

        return $code === '' ? '' : '#'.$code;
    }
}
