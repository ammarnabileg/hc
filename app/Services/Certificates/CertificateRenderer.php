<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
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
    /**
     * صورة الشهادة PNG — مع كاش على القرص لأنّ الرسم أغلى من القراءة (2.7).
     *
     * @param  string|null  $language  النسخة المطلوبة إن كانت الشهادة بنسختين (12.5-ب)
     */
    public function png(Certificate $certificate, ?string $language = null): string
    {
        $language = $this->resolveLanguage($certificate, $language);
        $disk = Storage::disk('local');
        $path = $this->path($certificate, $language);

        if ((bool) setting('certificates.render.cache_enabled', true) && $disk->exists($path)) {
            return (string) $disk->get($path);
        }

        $png = $this->draw($certificate, $language);
        $disk->put($path, $png);

        return $png;
    }

    /**
     * ⭐ مسار الكاش يحمل **بصمة اللقطة المجمَّدة** لا الكود وحده (8 · 8.1 · 12.5-هـ).
     *
     * لماذا؟ لأنّ الكود قد يُعاد استعماله بعد حذفٍ أو إعادة بذر، فتُقدَّم صورةٌ
     * محفوظةٌ باسم شخصٍ آخر تحت نفس الكود — وصفحة التحقّق تقول شيئًا والصورة
     * المنزَّلة تقول شيئًا آخر. وذلك بذاته **مسار تزوير**، وغرض القسمين 8 و8.1
     * منعُه. فالبصمة تُشتقّ ممّا يُرسَم فعلًا: بيانات الشهادة المجمَّدة وقالبها
     * وحالتها — فأيّ اختلافٍ في المحتوى = ملفٌّ مختلف، ولا تصادم ممكن.
     */
    private function path(Certificate $certificate, string $language): string
    {
        return 'certificates/'.$certificate->code.'/'.$language.'-'.$this->fingerprint($certificate).'.png';
    }

    /** بصمة ما يُرسَم: اللقطة المجمَّدة + القالب المجمَّد + الحالة (الشريط يتغيّر بها) */
    public function fingerprint(Certificate $certificate): string
    {
        $payload = json_encode([
            'data' => $certificate->data_snapshot,
            'template' => $certificate->template_snapshot,
            'status' => $certificate->status,
            'hash' => $certificate->hash,
        ], JSON_UNESCAPED_UNICODE) ?: '';

        return substr(hash('sha256', $payload), 0, 20);
    }

    /** النسخة الأخرى لا تُعرَض إلّا إن فعّلها الأدمن لهذا النوع (12.5-ب) */
    private function resolveLanguage(Certificate $certificate, ?string $language): string
    {
        if (! $language || $language === $certificate->language) {
            return $certificate->language;
        }

        $type = $certificate->certificate_type;
        $enabled = match ($language) {
            'ar' => (bool) $type?->lang_ar_enabled,
            'en' => (bool) $type?->lang_en_enabled,
            default => false,
        };

        return $enabled ? $language : $certificate->language;
    }

    /**
     * يمسح كلّ صور هذا الكود — تُستدعى عند **الإصدار وإعادة الإصدار والإلغاء
     * والانتهاء**، لا عند تغيّر الحالة وحده. تمسح المجلّد كلّه لا بصمةً بعينها،
     * فلا يبقى ملفٌّ قديم يجيب عن رابطٍ حيّ.
     */
    public function forget(Certificate $certificate): void
    {
        $disk = Storage::disk('local');
        $disk->deleteDirectory('certificates/'.$certificate->code);

        // ملفّات ما قبل البصمة (`certificates/CODE-ar.png`) — تُمسَح مرّةً ولا تعود
        foreach (['ar', 'en'] as $language) {
            $disk->delete('certificates/'.$certificate->code.'-'.$language.'.png');
        }
    }

    // ------------------------------------------------------------ الرسم

    private function draw(Certificate $certificate, string $language): string
    {
        $template = $this->templateFor($certificate, $language);
        $data = $this->dataFor($certificate, $language);

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

        // ⭐ [2026-09-10] ختم/توقيع معتمِد — اختياريّ (سطر 2407 · 4644 · 4646: Toggle)
        if ($template['signature_enabled'] ?? false) {
            $this->drawStampOrSignature($image, (string) ($template['stamp_path'] ?? ''), $width, $height, 'stamp');
            $this->drawStampOrSignature($image, (string) ($template['signature_path'] ?? ''), $width, $height, 'signature');
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /**
     * القالب المستعمَل: **النسخة المجمَّدة** لغة الشهادة (12.5-ج)،
     * وللّغة الأخرى يُقرَأ قالبها الحاليّ لأنّها نسخةٌ عرضٍ لا نسخة إصدار.
     */
    private function templateFor(Certificate $certificate, string $language): array
    {
        $snapshot = (array) ($certificate->template_snapshot ?? []);

        if ($language === $certificate->language) {
            return $snapshot;
        }

        $template = CertificateTemplate::query()
            ->where('certificate_type_id', $certificate->certificate_type_id)
            ->where('language', $language)
            ->orderByDesc('is_default')
            ->orderByDesc('version')
            ->first();

        if (! $template) {
            return $snapshot;
        }

        return array_merge($snapshot, [
            'language' => $language,
            'width_px' => $template->width_px,
            'height_px' => $template->height_px,
            'background_path' => $template->background_path,
            'layers' => $template->layers ?? [],
        ]);
    }

    /**
     * بيانات الشهادة للنسخة المطلوبة (12.5-ب): اللقطة المجمَّدة كما هي،
     * وللنسخة الأخرى **يُبدَّل الاسم باسم تلك اللغة** — فالنسخة الإنجليزيّة
     * لا تُطبَع بالاسم العربيّ. والاسمان محفوظان في اللقطة نفسها (2.5-ج)،
     * فلا نعود للمستخدم بعد الإصدار ولا نكسر التجميد.
     */
    private function dataFor(Certificate $certificate, string $language): array
    {
        $data = (array) ($certificate->data_snapshot ?? []);

        if ($language === $certificate->language) {
            return $data;
        }

        $name = trim((string) ($data[$language === 'en' ? 'holder_name_en' : 'holder_name_ar'] ?? ''));

        if ($name === '') {
            return $data;
        }

        $title = trim((string) ($data['holder_title'] ?? ''));

        return [...$data, 'holder_name' => trim($title !== '' && setting('certificates.render.title_with_name', true)
            ? $title.' '.$name
            : $name)];
    }

    /** @return list<array<string,mixed>> */
    private function layers(array $template): array
    {
        $layers = $template['layers'] ?? null;

        return is_array($layers) && $layers !== [] ? array_values($layers) : $this->defaultLayers();
    }

    /**
     * ⭐ [2026-09-10] ختم/توقيع معتمِد (سطر 2407 · 4644): كانا حقلين مزروعين
     * (`signature_path`/`stamp_path`) بفورمٍ يحفظهما بلا أيّ راسمٍ يقرؤهما —
     * عمودان ومصادقةٌ بلا أثرٍ على الصورة الفعليّة. الموضع نسبيٌّ كطبقات
     * النصّ تمامًا، والمقاس يحفظ نسبة العرض للارتفاع فلا ينكمش الختم.
     */
    private function drawStampOrSignature(\GdImage $image, string $path, int $width, int $height, string $kind): void
    {
        if ($path === '') {
            return;
        }

        $mark = $this->loadBackground($path);

        if (! $mark) {
            return;
        }

        $markWidth = (int) round($width * (float) setting('certificates.render.'.$kind.'_width', 0.14));
        $markHeight = (int) round($markWidth * (imagesy($mark) / max(1, imagesx($mark))));

        $x = (int) round(($width * (float) setting('certificates.render.'.$kind.'_x', $kind === 'stamp' ? 0.18 : 0.82)) - ($markWidth / 2));
        $y = (int) round($height * (float) setting('certificates.render.'.$kind.'_y', 0.85)) - (int) round($markHeight / 2);

        imagecopyresampled($image, $mark, $x, $y, 0, 0, $markWidth, $markHeight, imagesx($mark), imagesy($mark));
        imagedestroy($mark);
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
        $y = (int) ($height * (float) setting('certificates.render.status_y', 0.13));
        imagettftext($image, $size, 0, (int) (($width - $textWidth) / 2), $y, $color, $font, $text);
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
        return GdEngine::layerValue($layer, $data);
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
            ['type' => 'text', 'field' => 'issued_on', 'x' => 0.22, 'y' => 0.85, 'size' => 24, 'color' => '#9bb3ad'],
            ['type' => 'text', 'field' => 'country', 'x' => 0.22, 'y' => 0.90, 'size' => 24, 'color' => '#9bb3ad'],
            ['type' => 'text', 'field' => 'code', 'x' => 0.5, 'y' => 0.90, 'size' => 24, 'color' => '#9bb3ad'],
            ['type' => 'qr', 'x' => 0.84, 'y' => 0.82, 'size' => 0.12],
        ];
    }

    public function fontPath(): ?string
    {
        $configured = (string) setting('certificates.render.font_path', '');

        return GdEngine::fontPath($configured !== '' ? $configured : null);
    }

    private function color(\GdImage $image, string $hex): int
    {
        return GdEngine::color($image, $hex);
    }
}
