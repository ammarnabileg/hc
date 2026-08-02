<?php

namespace App\Services\Images;

use App\Models\ImageTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * رسم «الاستخراج كصورة» لأيّ لوحة في المنصّة (12.14-هـ · 12.14-و).
 *
 * ⭐ الرسم على الخادم بـPHP/GD بخطّ Cairo المضمَّن — بلا خدمة خارجيّة وبلا
 *   تراخيص، والنتيجة واحدة على كلّ الأجهزة.
 * ⭐ وقالب الاستوديو اختياريّ: لو اختاره المستخدم صار **فريمًا وطبقاتٍ** فوق
 *   اللوحة أو تحتها — وهذا معنى «رفع الطبقة وإنزالها» في الاستخراج (12.14-أ/ب).
 * ⭐ وكلّ صورة تحمل **تاريخ اللقطة وشعار المنصّة**.
 */
class BoardImageRenderer
{
    use Concerns\DrawsWithGd;

    public function __construct(private readonly TemplateLayers $layers) {}

    /**
     * @param  array{width:int,height:int,avatars:bool,frame:string}  $options
     *                                                                          frame: `above` (الفريم فوق المحتوى) أو `below` (تحته)
     */
    public function render(BoardSnapshot $snapshot, array $rows, array $options, ?ImageTemplate $template, ?User $actor): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('امتداد GD مش متاح على الخادم — الاستخراج كصورة متوقّف. كلّم الدعم.');
        }

        $this->throttle($actor);

        $width = max(320, (int) $options['width']);
        $height = max(320, (int) $options['height']);

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $this->background($canvas, $width, $height);

        // الفريم تحت المحتوى: الخلفيّة أوّلًا ثمّ اللوحة فوقها
        if ($template && $options['frame'] === 'below') {
            $this->drawTemplate($canvas, $template, $width, $height);
        }

        $this->drawBoard($canvas, $snapshot, $rows, $width, $height, (bool) $options['avatars']);

        // الفريم فوق المحتوى: يعلو اللوحة — كحالة الإطار الزخرفيّ
        if ($template && $options['frame'] === 'above') {
            $this->drawTemplate($canvas, $template, $width, $height);
        }

        $this->stampCanvas($canvas, $width, $height);

        ob_start();
        imagepng($canvas);
        $binary = (string) ob_get_clean();
        imagedestroy($canvas);

        return $binary;
    }

    /** كاش بمفتاح (اللوحة + الخيارات + القالب + المستخدم) — يتجدّد بتغيّر أيّها */
    public function cacheKey(BoardSnapshot $snapshot, array $rows, array $options, ?ImageTemplate $template, ?User $actor): string
    {
        return hash('sha256', implode('|', [
            $snapshot->kind,
            $snapshot->title,
            json_encode($rows, JSON_UNESCAPED_UNICODE),
            json_encode($options),
            $template?->id.':'.$template?->updated_at?->timestamp,
            $actor?->id ?? 0,
            // تاريخ اللقطة جزء من المفتاح، فصورة الأمس لا تُعاد اليوم (12.14-هـ)
            now()->format('Y-m-d'),
        ]));
    }

    public function cached(BoardSnapshot $snapshot, array $rows, array $options, ?ImageTemplate $template, ?User $actor): string
    {
        $key = $this->cacheKey($snapshot, $rows, $options, $template, $actor);
        $path = 'exports/boards/'.$key.'.png';
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $path;
        }

        $disk->put($path, $this->render($snapshot, $rows, $options, $template, $actor));

        return $path;
    }

    /** المقاسات الجاهزة — نفس قائمة الاستوديو فلا تتفرّع (12.14-أ) */
    public function presets(): array
    {
        return $this->layers->presets();
    }

    // ------------------------------------------------------------------ داخليّ

    private function throttle(?User $user): void
    {
        $limit = (int) setting('images.rate_limit_per_minute', 30);

        if ($limit <= 0) {
            return;
        }

        $bucket = 'images:export:'.($user?->id ?? 'guest').':'.now()->format('YmdHi');
        $count = (int) Cache::get($bucket, 0);

        if ($count >= $limit) {
            throw new RuntimeException('وصلت لحدّ الاستخراج في الدقيقة — استنّى دقيقة وجرّب تاني.');
        }

        // نافذة العدّاد أطول قليلًا من الدقيقة كي لا يضيع العدّ على حدّها — وهي إعداد (2.13)
        Cache::put($bucket, $count + 1, now()->addMinutes((int) setting('images.rate_limit_window_minutes', 2)));
    }

    private function background($canvas, int $width, int $height): void
    {
        $top = $this->rgb((string) setting('images.board.bg_top', '#04121d'));
        $bottom = $this->rgb((string) setting('images.board.bg_bottom', '#030d17'));

        // تدرّج رأسيّ هادئ بهويّة المنصّة — بلا صور خارجيّة
        for ($y = 0; $y < $height; $y++) {
            $t = $height > 1 ? $y / ($height - 1) : 0;
            $color = imagecolorallocate(
                $canvas,
                (int) round($top[0] + ($bottom[0] - $top[0]) * $t),
                (int) round($top[1] + ($bottom[1] - $top[1]) * $t),
                (int) round($top[2] + ($bottom[2] - $top[2]) * $t),
            );
            imageline($canvas, 0, $y, $width, $y, $color);
        }
    }

    private function drawTemplate($canvas, ImageTemplate $template, int $width, int $height): void
    {
        if ($template->frame_path && Storage::disk('public')->exists($template->frame_path)) {
            $frame = $this->loadImage(Storage::disk('public')->path($template->frame_path));

            if ($frame) {
                imagecopyresampled($canvas, $frame, 0, 0, 0, 0, $width, $height, imagesx($frame), imagesy($frame));
                imagedestroy($frame);
            }
        }

        // الطبقات الثابتة فقط (نصّ ثابت وصور) — حقول المستخدم لا معنى لها في لوحة
        foreach ((array) $template->layers as $layer) {
            if (! ($layer['visible'] ?? true) || ($layer['type'] ?? '') !== 'text') {
                continue;
            }

            $text = (string) ($layer['text'] ?? '');

            if (trim($text) === '') {
                continue;
            }

            $this->writeText(
                $canvas, $text,
                (int) ($layer['x'] ?? 0), (int) ($layer['y'] ?? 0),
                max(8, (int) ($layer['size'] ?? 32)),
                $this->allocate($canvas, (string) ($layer['color'] ?? '#ffffff')),
                (string) ($layer['align'] ?? 'right'),
            );
        }
    }

    /** جسم اللوحة: عنوان · سطر تعريفيّ · صفوف — بتخطيط RTL */
    private function drawBoard($canvas, BoardSnapshot $snapshot, array $rows, int $width, int $height, bool $avatars): void
    {
        $pad = (int) round($width * 0.07);
        $right = $width - $pad;

        $ink = $this->allocate($canvas, (string) setting('images.board.text', '#eaf2f8'));
        $muted = $this->allocate($canvas, (string) setting('images.board.muted', '#9fb3c8'));
        $brand = $this->allocate($canvas, (string) setting('images.board.brand', '#00d4b8'));

        $titleSize = max(18, (int) round($width * 0.045));
        $y = $pad;

        $this->writeText($canvas, $snapshot->title, $right, $y, $titleSize, $ink);
        $y += (int) round($titleSize * 1.6);

        if ($snapshot->subtitle !== '') {
            $subSize = max(12, (int) round($titleSize * 0.5));
            $this->writeText($canvas, $snapshot->subtitle, $right, $y, $subSize, $muted);
            $y += (int) round($subSize * 2.2);
        }

        $count = max(1, count($rows));
        // المساحة المتبقيّة تُقسَّم على الصفوف — والصفّ لا يتضخّم لو العدد قليل
        $available = $height - $y - (int) round($height * 0.09);
        $rowH = (int) max(56, min($available / $count, $width * 0.13));

        /*
         | ⭐ حجم النصّ محكومٌ بحدّين معًا: **عرض الصورة** فلا يتضخّم في الستوري،
         | و**ارتفاع الصفّ** فلا يتراكب الاسمُ والمحافظةُ حين تطول القائمة.
         */
        $textBox = $rowH - 16;                       // ما يتبقّى للسطرين داخل الصفّ
        $nameSize = max(12, (int) min($width * 0.036, $textBox / 2.15));
        $metaSize = max(9, (int) round($nameSize * 0.58));
        // فجوة السطرين تتبع حجم الاسم — لخطّ Cairo نزولاتٌ عميقة تحت خطّ الأساس
        $lineGap = (int) round($nameSize * 0.45);
        $blockH = $nameSize + $lineGap + $metaSize;
        $avatarSize = (int) min($rowH * 0.66, $width * 0.09);
        $gap = (int) round($pad * 0.4);

        // القائمة القصيرة على قماشٍ طويل (ستوري) تُتوسَّط بدل أن تعلق أعلى الصورة
        $y += max(0, (int) round(($available - ($rowH * $count)) / 2));

        foreach ($rows as $row) {
            $rowTop = $y;
            $center = $rowTop + intdiv($rowH, 2);

            // خلفيّة خفيفة للصفّ، وأوضح لصفّ صاحب الاستخراج (خيار «صفّي أنا»)
            $this->rowPlate($canvas, $pad, $rowTop, $width - $pad, $rowTop + $rowH - 8, (bool) ($row['me'] ?? false));

            $cursor = $right - $gap;

            // الترتيب — رقم بارز بلون الهويّة (والهويّة ليست حالة، 2.16)
            $rankText = '#'.(int) ($row['rank'] ?? 0);
            $this->writeText($canvas, $rankText, $cursor, $center - intdiv($nameSize, 2), $nameSize, $brand);
            $cursor -= $this->textWidth($rankText, $nameSize) + $gap;

            if ($avatars) {
                $this->drawRowAvatar($canvas, $row['user'] ?? null, $cursor - $avatarSize, $center - intdiv($avatarSize, 2), $avatarSize);
                $cursor -= $avatarSize + (int) round($gap * 0.9);
            }

            // القيمة على يسار الصفّ — نرسمها أوّلًا لنعرف المساحة الباقية للاسم
            $value = (string) ($row['value'] ?? '');
            $valueLeft = $pad + $gap;
            $this->writeText($canvas, $value, $valueLeft, $center - intdiv($nameSize, 2), $nameSize, $ink, 'left');

            // ⭐ الاسم لا يزاحم القيمة أبدًا: يُصغَّر ثمّ يُقصّ عند الحاجة (12.14-ج)
            $nameSpace = max(40, $cursor - ($valueLeft + $this->textWidth($value, $nameSize) + $gap));

            $textTop = $rowTop + (int) round(($rowH - 8 - $blockH) / 2);

            $this->writeFitted($canvas, (string) ($row['name'] ?? ''), $cursor, $textTop, $nameSize, $ink, $nameSpace);

            // ⭐ المحافظة تُطبَع دائمًا ولا يجوز إخفاؤها (12.14-د)
            if (($row['gov'] ?? '') !== '') {
                $this->writeFitted($canvas, (string) $row['gov'], $cursor, $textTop + $nameSize + $lineGap, $metaSize, $muted, $nameSpace);
            }

            $y += $rowH;
        }
    }

    /** كتابة تتّسع للمساحة: تصغير تلقائيّ ثمّ قصّ بثلاث نقاط (12.14-ج) */
    private function writeFitted($canvas, string $text, int $x, int $y, int $size, int $color, int $maxWidth): void
    {
        if (trim($text) === '') {
            return;
        }

        while ($size > 10 && $this->textWidth($text, $size) > $maxWidth) {
            $size--;
        }

        while (mb_strlen($text) > 4 && $this->textWidth($text, $size) > $maxWidth) {
            $text = mb_substr($text, 0, mb_strlen($text) - 2).'…';
        }

        $this->writeText($canvas, $text, $x, $y, $size, $color);
    }

    private function rowPlate($canvas, int $x1, int $y1, int $x2, int $y2, bool $highlight): void
    {
        $hex = $highlight
            ? (string) setting('images.board.row_me', '#0b2c33')
            : (string) setting('images.board.row', '#08192a');

        imagefilledrectangle($canvas, $x1, $y1, $x2, $y2, $this->allocate($canvas, $hex));

        if ($highlight) {
            // شريط جانبيّ يميّز صفّي — شكل مع اللون دائمًا (2.16-ب)
            imagefilledrectangle($canvas, $x2 - 6, $y1, $x2, $y2, $this->allocate($canvas, (string) setting('images.board.brand', '#00d4b8')));
        }
    }

    private function drawRowAvatar($canvas, ?User $user, int $x, int $y, int $size): void
    {
        $processor = app(AvatarProcessor::class);
        $path = $user ? $processor->pick($user, $size * 2) : null;
        $source = $path && Storage::disk('public')->exists($path)
            ? $this->loadImage(Storage::disk('public')->path($path))
            : null;

        if (! $source) {
            $this->initialsCircle($canvas, $x, $y, $size, (string) ($user?->name ?? ''));

            return;
        }

        $target = imagecreatetruecolor($size, $size);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));

        [$sx, $sy, $sw, $sh] = $this->cropBox(imagesx($source), imagesy($source), $size, $size, 'cover');
        imagecopyresampled($target, $source, 0, 0, $sx, $sy, $size, $size, $sw, $sh);
        imagedestroy($source);

        // ⭐ دائريّ **وبلا هالة** (2.10.1-16)
        $this->maskCircle($target, $size, $size);
        imagealphablending($canvas, true);
        imagecopy($canvas, $target, $x, $y, 0, 0, $size, $size);
        imagedestroy($target);
    }

    private function initialsCircle($canvas, int $x, int $y, int $size, string $name): void
    {
        $bg = $this->allocate($canvas, (string) setting('images.avatar.fallback_bg', '#071825'));
        $fg = $this->allocate($canvas, (string) setting('images.avatar.fallback_fg', '#00d4b8'));

        imagefilledellipse($canvas, $x + intdiv($size, 2), $y + intdiv($size, 2), $size, $size, $bg);

        $initials = collect(preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->take((int) setting('ux.avatar.initials_count', 2))->map(fn ($p) => mb_substr($p, 0, 1))->implode('');

        if ($initials === '') {
            return;
        }

        $this->writeText($canvas, $initials, $x + intdiv($size, 2), $y + intdiv($size, 4), max(10, intdiv($size, 3)), $fg, 'center');
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $hex): array
    {
        $parts = sscanf($hex, '#%02x%02x%02x') ?: [3, 13, 23];

        return [(int) $parts[0], (int) $parts[1], (int) $parts[2]];
    }
}
