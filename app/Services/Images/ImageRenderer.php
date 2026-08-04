<?php

namespace App\Services\Images;

use App\Models\GeneratedImage;
use App\Models\ImageTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * مولّد الصور على الخادم بـGD (12.14-و).
 *
 * لماذا الخادم؟ لأنّ النتيجة **واحدة على كلّ الأجهزة** ولا تتغيّر بخطوط المتصفّح،
 * **وبلا خدمة خارجيّة وبلا تراخيص (0 تكلفة)** — وهو شرط منصوص لا اختيار معماريّ.
 *
 * ⭐ كاش بمفتاح (القالب + المستخدم + البيانات) ويتجدّد عند تغيّر أيّها،
 * ⭐ وحدّ للتوليد في الدقيقة يمنع استنزاف المعالج بضغطة متكرّرة.
 */
class ImageRenderer
{
    use Concerns\DrawsWithGd;

    public function __construct(private readonly ImageTemplateFields $fields) {}

    /** مفتاح الكاش: القالب + المستخدم + البيانات — تغيّر أيّها يغيّر المفتاح */
    public function cacheKey(ImageTemplate $template, ?User $user, array $data): string
    {
        return hash('sha256', implode('|', [
            $template->id,
            $template->updated_at?->timestamp,
            $user?->id ?? 0,
            json_encode($data, JSON_UNESCAPED_UNICODE),
        ]));
    }

    /**
     * توليد الصورة (أو إرجاع نسخة الكاش) وإرجاع مسارها على قرص `public`.
     *
     * @throws RuntimeException عند تجاوز حدّ التوليد في الدقيقة
     */
    public function render(ImageTemplate $template, ?User $user, ?User $actor = null): string
    {
        $data = $user ? $this->fields->values($user) : [];
        $key = $this->cacheKey($template, $user, $data);

        $cached = GeneratedImage::query()->where('cache_key', $key)->first();

        if ($cached && Storage::disk('public')->exists($cached->path)) {
            return $cached->path;
        }

        $this->throttle($actor ?? $user);

        $binary = $this->draw($template, $user, $data);
        $path = 'generated/'.$template->id.'/'.$key.'.png';
        Storage::disk('public')->put($path, $binary);

        GeneratedImage::updateOrCreate(
            ['cache_key' => $key],
            [
                'image_template_id' => $template->id,
                'user_id' => $user?->id,
                'path' => $path,
                'data_snapshot' => $data,
                'generated_at' => now(),
            ],
        );

        return $path;
    }

    /** الرسم الخام — يرجع محتوى PNG */
    public function draw(ImageTemplate $template, ?User $user, array $data): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException(setting('images.image_renderer.draw_1', 'امتداد GD مش متاح على الخادم — التوليد متوقّف.'));
        }

        $width = max(1, (int) $template->width_px);
        $height = max(1, (int) $template->height_px);

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $this->allocate($canvas, '#030d17'));

        $this->drawFrame($canvas, $template, $width, $height);

        foreach ((array) $template->layers as $layer) {
            if (! ($layer['visible'] ?? true)) {
                continue;
            }

            match ($layer['type'] ?? 'text') {
                'avatar' => $this->drawAvatar($canvas, $layer, $user),
                'image' => $this->drawImageLayer($canvas, $layer),
                default => $this->drawText($canvas, $layer, $data),
            };
        }

        // ⭐ كلّ صورة مستخرَجة تحمل تاريخ اللقطة وشعار المنصّة (12.14-هـ)
        $this->stampCanvas($canvas, $width, $height);

        ob_start();
        imagepng($canvas);
        $binary = (string) ob_get_clean();
        imagedestroy($canvas);

        return $binary;
    }

    // ------------------------------------------------------------------ داخليّ

    /** حدّ التوليدات في الدقيقة — قيمته إعداد لا رقم محروق */
    private function throttle(?User $user): void
    {
        $limit = (int) setting('images.rate_limit_per_minute', 30);

        if ($limit <= 0) {
            return;
        }

        $bucket = 'images:rate:'.($user?->id ?? 'guest').':'.now()->format('YmdHi');
        $count = (int) Cache::get($bucket, 0);

        if ($count >= $limit) {
            throw new RuntimeException(setting('images.image_renderer.throttle_1', 'وصلت لحدّ التوليد في الدقيقة — استنّى دقيقة وجرّب تاني.'));
        }

        // نافذة العدّاد أطول قليلًا من الدقيقة كي لا يضيع العدّ على حدّها — وهي إعداد (2.13)
        Cache::put($bucket, $count + 1, now()->addMinutes((int) setting('images.rate_limit_window_minutes', 2)));
    }

    private function drawFrame($canvas, ImageTemplate $template, int $width, int $height): void
    {
        if (! $template->frame_path || ! Storage::disk('public')->exists($template->frame_path)) {
            return;
        }

        $frame = $this->loadImage(Storage::disk('public')->path($template->frame_path));

        if (! $frame) {
            return;
        }

        imagecopyresampled($canvas, $frame, 0, 0, 0, 0, $width, $height, imagesx($frame), imagesy($frame));
        imagedestroy($frame);
    }

    /** طبقة صورة المستخدم: مربّع · دائرة · دائرة بحدّ — **وبلا هالة** */
    private function drawAvatar($canvas, array $layer, ?User $user): void
    {
        $w = (int) ($layer['w'] ?? 240);
        $h = (int) ($layer['h'] ?? 240);
        $x = (int) ($layer['x'] ?? 0);
        $y = (int) ($layer['y'] ?? 0);

        $source = null;

        if ($user?->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            $source = $this->loadImage(Storage::disk('public')->path($user->avatar_path));
        }

        // بديل عند غياب الصورة: أحرف الاسم الأولى بنمط المنصّة (12.14-ب)
        if (! $source) {
            $this->drawInitials($canvas, $x, $y, $w, $h, $user);

            return;
        }

        $target = imagecreatetruecolor($w, $h);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));

        [$sx, $sy, $sw, $sh] = $this->cropBox(imagesx($source), imagesy($source), $w, $h, (string) ($layer['fit'] ?? 'cover'));
        imagecopyresampled($target, $source, 0, 0, $sx, $sy, $w, $h, $sw, $sh);
        imagedestroy($source);

        if (($layer['shape'] ?? 'circle') !== 'square') {
            $this->maskCircle($target, $w, $h);
        }

        imagealphablending($canvas, true);
        imagecopy($canvas, $target, $x, $y, 0, 0, $w, $h);
        imagedestroy($target);

        if (($layer['shape'] ?? '') === 'circle_border' && (int) ($layer['border_width'] ?? 0) > 0) {
            imagesetthickness($canvas, (int) $layer['border_width']);
            imageellipse($canvas, $x + intdiv($w, 2), $y + intdiv($h, 2), $w, $h, $this->allocate($canvas, (string) ($layer['border_color'] ?? '#00d4b8')));
            imagesetthickness($canvas, 1);
        }
    }

    private function drawImageLayer($canvas, array $layer): void
    {
        $path = (string) ($layer['path'] ?? '');

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return;
        }

        $img = $this->loadImage(Storage::disk('public')->path($path));

        if (! $img) {
            return;
        }

        imagecopyresampled(
            $canvas, $img,
            (int) ($layer['x'] ?? 0), (int) ($layer['y'] ?? 0), 0, 0,
            (int) ($layer['w'] ?? imagesx($img)), (int) ($layer['h'] ?? imagesy($img)),
            imagesx($img), imagesy($img),
        );
        imagedestroy($img);
    }

    /**
     * طبقة نصّ: حقل من القائمة المقفولة أو نصّ ثابت،
     * مع **حدّ أحرف** وسلوك تجاوز (تصغير تلقائيّ أو قصّ) فلا يكسر اسمٌ طويل التصميم.
     */
    private function drawText($canvas, array $layer, array $data): void
    {
        $field = (string) ($layer['field'] ?? '');
        $text = $field !== '' ? (string) ($data[$field] ?? '') : (string) ($layer['text'] ?? '');

        if (trim($text) === '') {
            return;
        }

        $size = max(8, (int) ($layer['size'] ?? 32));
        $max = (int) ($layer['max_chars'] ?? 0);

        if ($max > 0 && mb_strlen($text) > $max) {
            if (($layer['overflow'] ?? 'shrink') === 'truncate') {
                $text = mb_substr($text, 0, max(1, $max - 1)).'…';
            } else {
                $size = max(10, (int) floor($size * $max / mb_strlen($text)));
            }
        }

        $color = $this->allocate($canvas, (string) ($layer['color'] ?? '#ffffff'));
        $x = (int) ($layer['x'] ?? 0);
        $y = (int) ($layer['y'] ?? 0);

        // نفس مسار الرسم المشترك: تشكيل عربيّ + محاذاة RTL (12.14-و)
        $this->writeText($canvas, $text, $x, $y, $size, $color, (string) ($layer['align'] ?? 'right'));
    }

    private function drawInitials($canvas, int $x, int $y, int $w, int $h, ?User $user): void
    {
        $bg = $this->allocate($canvas, (string) setting('images.avatar.fallback_bg', '#071825'));
        $fg = $this->allocate($canvas, (string) setting('images.avatar.fallback_fg', '#00d4b8'));

        imagefilledellipse($canvas, $x + intdiv($w, 2), $y + intdiv($h, 2), $w, $h, $bg);

        $initials = collect(preg_split('/\s+/u', (string) ($user?->name ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->take((int) setting('ux.avatar.initials_count', 2))
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->implode('');

        if ($initials === '') {
            return;
        }

        $size = max(12, intdiv($w, 3));

        $this->writeText($canvas, $initials, $x + intdiv($w, 2), $y + intdiv($h, 2) - intdiv($size, 2), $size, $fg, 'center');
    }
}
