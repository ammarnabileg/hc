<?php

namespace App\Services\Images;

use App\Models\NameParticle;

/**
 * اسم العرض المختصر (12.14-ج) — قاعدة معتمَدة:
 * أوّل **كلمتين**، على أن تُعامَل **أدوات الاسم** جزءًا من الكلمة التي تليها
 * فلا تُحسَب وحدةً مستقلّة: عبد · عبدال · أبو · أبا · بن · ابن · آل · الـ
 * وبالإنجليزيّة abd · abdel · abdul · abo · abu · bin · ibn · al · el.
 *
 * أمثلة: «عبد الرحمن محمد علي» ⟵ «عبد الرحمن محمد» · «محمد أحمد علي» ⟵ «محمد أحمد».
 *
 * ولماذا خدمة مستقلّة؟ لأنّ الاسم يُختصَر في ثلاثة مواضع (الواجهة · قوالب
 * الاستوديو · الاستخراج كصورة)، والقاعدة **واحدة** ولا يجوز أن تتفرّع.
 */
class ShortName
{
    /** @var array<int,string>|null قائمة الأدوات — تُقرأ مرّة لكلّ طلب */
    private static ?array $particles = null;

    /** @return array<int,string> */
    public static function particles(): array
    {
        if (self::$particles === null) {
            self::$particles = NameParticle::query()
                ->where('is_active', true)
                ->pluck('particle')
                ->map(fn ($p) => self::normalize((string) $p))
                ->all();
        }

        return self::$particles;
    }

    /** لإعادة القراءة بعد تعديل القائمة من لوحة الإدارة */
    public static function flush(): void
    {
        self::$particles = null;
    }

    public static function of(?string $name, ?int $units = null): string
    {
        $units = $units ?: max(1, (int) setting('images.short_name.units', 2));
        $particles = self::particles();

        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $chunks = [];
        $buffer = [];

        foreach ($words as $word) {
            $buffer[] = $word;

            // الأداة تلتصق بما بعدها، فلا تُغلَق الوحدة إلّا على كلمة ليست أداة
            if (! in_array(self::normalize($word), $particles, true)) {
                $chunks[] = implode(' ', $buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $chunks[] = implode(' ', $buffer);
        }

        return implode(' ', array_slice($chunks, 0, $units));
    }

    /** توحيد الهمزات وحالة الأحرف حتى تتطابق «أبو» مع «ابو» و«Abu» مع «abu» */
    private static function normalize(string $word): string
    {
        return mb_strtolower(str_replace(['أ', 'إ', 'آ'], 'ا', $word));
    }
}
