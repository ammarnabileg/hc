<?php

namespace App\Services\Certificates;

/**
 * محرّك GD المشترك (12.14 · 12.5-ب): «محرّك واحد وصفر ازدواج».
 *
 * مصمّم الشهادات (12.5-ب) هو **المحرّك المرجعيّ** الذي تنصّ عليه 12.14 —
 * والاستوديو (وأيّ رسمٍ آخر بـGD) يستهلك هذه الدوالّ بدل تكرار منطقها.
 * لون Hex وإيجاد خطّ TTF كانا يُكتَبان من جديد في كلّ نظامٍ برسمه الخاصّ؛
 * صارا هنا مرّةً واحدة، وأيّ نظامٍ ثالث يرسم بـGD يستهلكها كذلك.
 */
class GdEngine
{
    /** خطوط النظام المرشّحة حين لا يوجد خطّ مضبوط أو مفقودًا من القرص (2.13) */
    private const FONT_CANDIDATES = [
        'resources/fonts/Cairo.ttf',
        '/usr/share/fonts/truetype/cairo/Cairo.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSerif.ttf',
    ];

    /**
     * أوّل خطّ TTF موجود فعلًا على القرص: **المفضَّل** (مسار مطلق جاهز من
     * المستدعي) أوّلًا، ثمّ خطوط النظام المرشّحة — فلا يخرج الرسم بلا خطٍّ
     * أبدًا ما دام أحد المرشّحين موجودًا.
     */
    public static function fontPath(?string $preferred = null): ?string
    {
        $candidates = array_merge($preferred && trim($preferred) !== '' ? [$preferred] : [], self::FONT_CANDIDATES);

        foreach ($candidates as $candidate) {
            $path = str_starts_with($candidate, '/') ? $candidate : base_path($candidate);

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** لون GD من Hex — 3 أو 6 أرقام، وأبيض عند قيمة تالفة بدل كسر الرسم */
    public static function color(\GdImage $image, string $hex): int
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

    /** مكوّنات Hex الثلاثة خامًا — لما يحتاج الرسم شفافيّةً (`imagecolorallocatealpha`) لا لونًا جاهزًا */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            $hex = 'ffffff';
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** صيغة Hex صالحة للتخزين — `#` + 6 أرقام بالضبط (لا 3 أرقام هنا، هذا تحقّق حفظ لا رسم) */
    public static function isValidHex(string $hex): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', trim($hex));
    }

    /**
     * قيمة الطبقة النهائيّة: «نصّ ثابت يكتبه الأدمن + عمود مختار» (12.5-ب) —
     * وحقلٌ مربوط فارغ القيمة يُخفي الطبقة كلّها بدل عرض نصف جملة (12.5-ب).
     */
    public static function layerValue(array $layer, array $data): string
    {
        $static = (string) ($layer['text'] ?? '');
        $field = $layer['field'] ?? null;
        $dynamic = $field ? (string) ($data[$field] ?? '') : '';

        if ($field && $dynamic === '') {
            return '';
        }

        return trim($static.($static !== '' && $dynamic !== '' ? ' ' : '').$dynamic);
    }
}
