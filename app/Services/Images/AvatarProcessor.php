<?php

namespace App\Services\Images;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * معالجة الأفاتار عند الرفع (الدستور 2.7).
 *
 * **قصّ مربّع إجباريّ** ثمّ توليد **ثلاث نسخ** لاستخدام كلٍّ في مكانه:
 * 500 للعرض الكبير والبروفايل · 150 للكروت والقوائم · 50 للأفاتار المصغّر
 * وأكوام الصور. لماذا على الخادم؟ لأنّ القصّ في المتصفّح يمكن تخطّيه،
 * والأداء أولويّة عليا فلا نرسل صورة 4 ميجا في كارت 40 بكسل.
 *
 * ⭐ ولا هالة حول الأفاتار (2.10.1-16) — المكوّن `x-avatar` يفرض ذلك بصريًّا،
 *   وهذه الخدمة تكتفي بالبكسل.
 */
class AvatarProcessor
{
    /** @return array<int,string> المقاسات المعتمَدة من الإعدادات — لا أرقام محروقة (2.13) */
    public function sizes(): array
    {
        $configured = setting('account.avatar.sizes');

        $sizes = is_array($configured) && $configured !== []
            ? array_map('intval', $configured)
            : [500, 150, 50];

        rsort($sizes);

        return array_values(array_filter($sizes, fn (int $s) => $s > 0));
    }

    /**
     * توليد النسخ الثلاث من الملفّ الأصليّ على قرص `public`.
     *
     * @return array<string,string> ['500' => path, '150' => path, '50' => path]
     */
    public function generate(User $user, string $originalPath): array
    {
        $disk = Storage::disk('public');

        if (! function_exists('imagecreatetruecolor') || ! $disk->exists($originalPath)) {
            return [];
        }

        $source = $this->load($disk->path($originalPath));

        if (! $source) {
            return [];
        }

        /*
         | 1) قصّ مربّع إجباريّ (2.7-1). والقصّ **بيد المستخدم** في الشاشة:
         | يسحب ويكبّر ويؤكّد، فيصل هنا مربّعًا جاهزًا ويمرّ هذا السطر بلا أثر.
         | ويبقى القصّ من المنتصف **شبكةَ أمان** لما يصل غير مربّع (رفعٌ بلا
         | جافاسكربت أو من خارج الشاشة) — فالإجباريّة لا تُترَك للمتصفّح وحده.
         */
        $square = $this->cropSquare($source);
        imagedestroy($source);

        $paths = [];
        $folder = 'avatars/'.$user->id;

        foreach ($this->sizes() as $size) {
            $canvas = imagecreatetruecolor($size, $size);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas, $square, 0, 0, 0, 0, $size, $size, imagesx($square), imagesy($square));

            ob_start();
            imagepng($canvas, null, 6);
            $binary = (string) ob_get_clean();
            imagedestroy($canvas);

            $path = $folder.'/'.$size.'.png';
            $disk->put($path, $binary);
            $paths[(string) $size] = $path;
        }

        imagedestroy($square);

        return $paths;
    }

    /** حفظ النسخ على المستخدم — والقديمة تُمسَح فلا يتراكم التخزين */
    public function apply(User $user, string $originalPath): array
    {
        $old = (array) ($user->avatar_sizes ?? []);
        $fresh = $this->generate($user, $originalPath);

        if ($fresh === []) {
            return [];
        }

        $user->forceFill(['avatar_sizes' => $fresh])->save();

        foreach ($old as $path) {
            if (is_string($path) && $path !== '' && ! in_array($path, $fresh, true)) {
                Storage::disk('public')->delete($path);
            }
        }

        return $fresh;
    }

    /**
     * المقاس المناسب لكلّ سياق: نأخذ أصغر نسخة تكفي العرض المطلوب،
     * فلا يُحمَّل 500 بكسل في كارت 40 — وهذا نصّ 2.7 حرفيًّا.
     */
    public function pick(?User $user, int $renderedPx): ?string
    {
        $available = (array) ($user?->avatar_sizes ?? []);

        if ($available === []) {
            return $user?->avatar_path;
        }

        $sizes = array_map('intval', array_keys($available));
        sort($sizes);

        foreach ($sizes as $size) {
            if ($size >= $renderedPx) {
                return $available[(string) $size] ?? $user?->avatar_path;
            }
        }

        return $available[(string) end($sizes)] ?? $user?->avatar_path;
    }

    // ------------------------------------------------------------------ داخليّ

    /** القصّ المربّع: أكبر مربّع ممكن من المنتصف */
    private function cropSquare($source)
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $side = min($w, $h);

        $square = imagecreatetruecolor($side, $side);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagecopy($square, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $side, $side);

        return $square;
    }

    private function load(string $path)
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
}
