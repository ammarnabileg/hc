<?php

namespace App\Services\Geo;

/**
 * أعلام الدول **مرسومةً SVG داخل الحزمة** — لا مكتبة أيقونات ولا صورة من شبكة.
 *
 * لماذا هذا الصنف أصلًا؟ لأنّ 2.5-ب ينصّ على **«Select لأكواد الدول (بالأعلام،
 * بشكل احترافيّ)»**، وأمام ذلك ثلاثة طرق كلّها مرفوضة هنا:
 *  ⛔ **مكتبة أيقونات جاهزة** — قاعدة مالك صريحة تمنعها.
 *  ⛔ **صور من شبكة خارجيّة** (flagcdn وأمثاله) — كُسِرت هذه القاعدة قبلًا في
 *     هذا المشروع فخرجت ورقة السيرة الذاتيّة بخطٍّ بديل حين تعذّر الأصل؛
 *     والصفحة التي تعتمد على مضيفٍ غريب تسقط حين يسقط، ويُسرَّب زوّارها إليه.
 *  ⛔ **إيموجي العلم** (🇪🇬) — ليس رسمًا نملكه: ويندوز لا يرسمه إطلاقًا فيظهر
 *     حرفان مكان العلم، وشكله يتبدّل بين الأنظمة فلا هويّة بصريّة واحدة.
 *
 * فالعلم هنا **هندسةٌ نرسمها**: كلّ دولة سطرٌ واحد بلغةٍ صغيرة من طبقات مفصولة
 * بـ`|`، تُترجَم إلى عناصر SVG خالصة. لا خطّ ولا صورة ولا طلب شبكة — والمُخرَج
 * `inline` داخل الصفحة نفسها فلا طلب إضافيّ أصلًا.
 *
 * لغة الطبقات (كلّ الإحداثيّات **نسبة مئويّة** من مساحة العلم 3:2):
 *  · `h:#a,#b,#c` شرائط أفقيّة متساوية · `v:` رأسيّة متساوية
 *  · `hw:#a 2,#b 1` شرائط أفقيّة بأوزان · `vw:` رأسيّة بأوزان
 *  · `bg:#a` خلفيّة · `rect:#a,x,y,w,h` · `poly:#a,x1,y1,x2,y2,…`
 *  · `disc:#a,cx,cy,r` · `ring:#a,cx,cy,r,t` · `star:#a,cx,cy,r` (خماسيّة)
 *  · `star6:#a,cx,cy,r` (سداسيّة) · `crescent:#a,cx,cy,r` (هلال)
 *  · `cross:#a,t,x` صليب اسكندنافيّ · `plus:#a,t` صليب مركزيّ · `saltire:#a,t`
 *  · `union:x,y,w,h` علم الاتّحاد مرسومًا في مستطيل (للمملكة وأقاليمها)
 *
 * وما لا هندسةَ له يخرج **لوحةً بحرفَي الكود** — رسمٌ منّا كذلك، لا فراغ.
 */
class FlagLibrary
{
    /** نسبة العلم 3:2 — والإحداثيّات تُحسَب على 60×40 */
    private const W = 60.0;

    private const H = 40.0;

    /** @var array<string, string>|null جدول الهندسة محمَّلًا مرّةً لكلّ طلب */
    private ?array $shapes = null;

    /** هل لهذه الدولة هندسة مرسومة (لا لوحة الحروف الاحتياطيّة)؟ */
    public function has(string $iso2): bool
    {
        return isset($this->shapes()[mb_strtoupper(trim($iso2))]);
    }

    /** عدد الأعلام المرسومة — يقرؤه الاختبار فلا يصير الجدول ناقصًا بصمت. */
    public function count(): int
    {
        return count($this->shapes());
    }

    /**
     * علم الدولة SVG جاهزًا للطباعة داخل الصفحة.
     *
     * @param  int  $width  العرض بالبكسل — والارتفاع ثلثاه حفاظًا على النسبة
     */
    public function svg(string $iso2, int $width = 22): string
    {
        $iso2 = mb_strtoupper(trim($iso2));
        $height = (int) round($width * 2 / 3);
        $spec = $this->shapes()[$iso2] ?? null;

        $body = $spec === null ? $this->plate($iso2) : $this->render($spec);

        // `clipPath` بمعرّفٍ فريد لكلّ علم: بلاه يتسرّب قصُّ علمٍ إلى الذي بعده
        $id = 'flag-'.mb_strtolower($iso2 !== '' ? $iso2 : 'xx');

        return '<svg class="flag" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.self::W.' '.self::H.'"'
            .' role="img" aria-hidden="true" focusable="false" preserveAspectRatio="xMidYMid slice">'
            .'<defs><clipPath id="'.$id.'"><rect x="0" y="0" width="'.self::W.'" height="'.self::H.'" rx="2.5"/></clipPath></defs>'
            .'<g clip-path="url(#'.$id.')">'.$body.'</g>'
            .'<rect x=".35" y=".35" width="'.(self::W - 0.7).'" height="'.(self::H - 0.7).'" rx="2.3" fill="none"'
            .' stroke="rgba(0,0,0,.18)" stroke-width=".7"/>'
            .'</svg>';
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<string, string> */
    private function shapes(): array
    {
        return $this->shapes ??= (array) require database_path('data/flags.php');
    }

    private function render(string $spec): string
    {
        $out = '';

        foreach (explode('|', $spec) as $layer) {
            $layer = trim($layer);

            if ($layer === '') {
                continue;
            }

            [$kind, $args] = array_pad(explode(':', $layer, 2), 2, '');
            $parts = array_map('trim', explode(',', (string) $args));

            $out .= match ($kind) {
                'bg' => $this->rect($parts[0], 0, 0, 100, 100),
                'h' => $this->bands($parts, false),
                'v' => $this->bands($parts, true),
                'hw' => $this->weighted($parts, false),
                'vw' => $this->weighted($parts, true),
                'rect' => $this->rect($parts[0], (float) $parts[1], (float) $parts[2], (float) $parts[3], (float) $parts[4]),
                'poly' => $this->poly($parts),
                'disc' => $this->disc($parts[0], (float) $parts[1], (float) $parts[2], (float) $parts[3]),
                'ring' => $this->ring($parts[0], (float) $parts[1], (float) $parts[2], (float) $parts[3], (float) $parts[4]),
                'star' => $this->star($parts[0], (float) $parts[1], (float) $parts[2], (float) $parts[3]),
                'star6' => $this->star6($parts[0], (float) $parts[1], (float) $parts[2], (float) $parts[3]),
                'crescent' => $this->crescent($parts[0], (float) $parts[1], (float) $parts[2], (float) $parts[3]),
                'cross' => $this->cross($parts[0], (float) $parts[1], (float) $parts[2]),
                'plus' => $this->cross($parts[0], (float) $parts[1], 50),
                'saltire' => $this->saltire($parts[0], (float) $parts[1]),
                'union' => $this->union((float) $parts[0], (float) $parts[1], (float) $parts[2], (float) $parts[3]),
                default => '',
            };
        }

        return $out;
    }

    /** شرائط متساوية — أفقيّة أو رأسيّة. @param  array<int, string>  $colors */
    private function bands(array $colors, bool $vertical): string
    {
        return $this->weighted(array_map(fn (string $c) => $c.' 1', $colors), $vertical);
    }

    /** شرائط بأوزان: «#لون وزن». @param  array<int, string>  $parts */
    private function weighted(array $parts, bool $vertical): string
    {
        $colors = [];
        $total = 0.0;

        foreach ($parts as $part) {
            [$color, $weight] = array_pad(preg_split('/\s+/', trim($part)) ?: [], 2, '1');
            $weight = max(0.0001, (float) $weight);
            $colors[] = [$color, $weight];
            $total += $weight;
        }

        $out = '';
        $at = 0.0;

        foreach ($colors as [$color, $weight]) {
            $size = $weight / $total * 100;

            $out .= $vertical
                ? $this->rect($color, $at, 0, $size, 100)
                : $this->rect($color, 0, $at, 100, $size);

            $at += $size;
        }

        return $out;
    }

    private function rect(string $color, float $x, float $y, float $w, float $h): string
    {
        return '<rect x="'.$this->x($x).'" y="'.$this->y($y).'" width="'.$this->x($w).'" height="'.$this->y($h)
            .'" fill="'.$this->color($color).'"/>';
    }

    /** @param  array<int, string>  $parts */
    private function poly(array $parts): string
    {
        $color = array_shift($parts);
        $points = [];

        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            $points[] = $this->x((float) $parts[$i]).','.$this->y((float) $parts[$i + 1]);
        }

        return $points === [] ? '' : '<polygon points="'.implode(' ', $points).'" fill="'.$this->color($color).'"/>';
    }

    private function disc(string $color, float $cx, float $cy, float $r): string
    {
        return '<circle cx="'.$this->x($cx).'" cy="'.$this->y($cy).'" r="'.$this->r($r)
            .'" fill="'.$this->color($color).'"/>';
    }

    private function ring(string $color, float $cx, float $cy, float $r, float $t): string
    {
        return '<circle cx="'.$this->x($cx).'" cy="'.$this->y($cy).'" r="'.$this->r($r)
            .'" fill="none" stroke="'.$this->color($color).'" stroke-width="'.$this->r($t).'"/>';
    }

    private function star(string $color, float $cx, float $cy, float $r): string
    {
        return $this->polygonOfStar($color, $cx, $cy, $r, 5, 0.42);
    }

    private function star6(string $color, float $cx, float $cy, float $r): string
    {
        // نجمة داوود: مثلّثان متعاكسان — لا نجمةٌ سداسيّة مصمَتة
        $up = $this->triangle($color, $cx, $cy, $r, false);
        $down = $this->triangle($color, $cx, $cy, $r, true);

        return '<g fill="none" stroke="'.$this->color($color).'" stroke-width="'.$this->r(2.4).'">'.$up.$down.'</g>';
    }

    private function triangle(string $color, float $cx, float $cy, float $r, bool $flipped): string
    {
        $points = [];

        for ($i = 0; $i < 3; $i++) {
            $angle = deg2rad($i * 120 + ($flipped ? 60 : 0) - 90);
            $points[] = ($this->x($cx) + cos($angle) * $this->r($r)).','.($this->y($cy) + sin($angle) * $this->r($r));
        }

        return '<polygon points="'.implode(' ', $points).'"/>';
    }

    private function polygonOfStar(string $color, float $cx, float $cy, float $r, int $points, float $inner): string
    {
        $coords = [];
        $x = $this->x($cx);
        $y = $this->y($cy);
        $outer = $this->r($r);

        for ($i = 0; $i < $points * 2; $i++) {
            $radius = $i % 2 === 0 ? $outer : $outer * $inner;
            $angle = deg2rad($i * (180 / $points) - 90);
            $coords[] = round($x + cos($angle) * $radius, 2).','.round($y + sin($angle) * $radius, 2);
        }

        return '<polygon points="'.implode(' ', $coords).'" fill="'.$this->color($color).'"/>';
    }

    /**
     * الهلال: قرصٌ ملوّن يقضمه قرصٌ ثانٍ **بلون ما تحته** — ولأنّنا لا نعرف ما
     * تحته، نستعمل `mask` فيصير القضم شفّافًا حقيقيًّا فوق أيّ خلفيّة.
     */
    private function crescent(string $color, float $cx, float $cy, float $r): string
    {
        $id = 'm'.substr(md5($color.$cx.$cy.$r), 0, 6);
        $x = $this->x($cx);
        $y = $this->y($cy);
        $radius = $this->r($r);

        return '<mask id="'.$id.'">'
            .'<circle cx="'.$x.'" cy="'.$y.'" r="'.$radius.'" fill="#fff"/>'
            .'<circle cx="'.($x + $radius * 0.36).'" cy="'.$y.'" r="'.($radius * 0.82).'" fill="#000"/>'
            .'</mask>'
            .'<circle cx="'.$x.'" cy="'.$y.'" r="'.$radius.'" fill="'.$this->color($color).'" mask="url(#'.$id.')"/>';
    }

    /** صليب اسكندنافيّ: عارضة أفقيّة في الوسط وعارضة رأسيّة مزاحة نحو السارية. */
    private function cross(string $color, float $t, float $x): string
    {
        return $this->rect($color, 0, 50 - $t / 2, 100, $t)
            .$this->rect($color, $x - $t / 2 * (self::H / self::W), 0, $t * (self::H / self::W), 100);
    }

    private function saltire(string $color, float $t): string
    {
        $w = $this->r($t);

        return '<g stroke="'.$this->color($color).'" stroke-width="'.$w.'">'
            .'<line x1="0" y1="0" x2="'.self::W.'" y2="'.self::H.'"/>'
            .'<line x1="'.self::W.'" y1="0" x2="0" y2="'.self::H.'"/>'
            .'</g>';
    }

    /** علم الاتّحاد مرسومًا داخل مستطيل — تشترك فيه المملكة وأقاليمها. */
    private function union(float $x, float $y, float $w, float $h): string
    {
        $id = 'u'.substr(md5($x.$y.$w.$h), 0, 6);
        $px = $this->x($x);
        $py = $this->y($y);
        $pw = $this->x($w);
        $ph = $this->y($h);
        $unit = min($pw, $ph);

        return '<defs><clipPath id="'.$id.'"><rect x="'.$px.'" y="'.$py.'" width="'.$pw.'" height="'.$ph.'"/></clipPath></defs>'
            .'<g clip-path="url(#'.$id.')">'
            .'<rect x="'.$px.'" y="'.$py.'" width="'.$pw.'" height="'.$ph.'" fill="#012169"/>'
            .'<g stroke="#FFFFFF" stroke-width="'.($unit * 0.30).'">'
            .'<line x1="'.$px.'" y1="'.$py.'" x2="'.($px + $pw).'" y2="'.($py + $ph).'"/>'
            .'<line x1="'.($px + $pw).'" y1="'.$py.'" x2="'.$px.'" y2="'.($py + $ph).'"/>'
            .'</g>'
            .'<g stroke="#C8102E" stroke-width="'.($unit * 0.13).'">'
            .'<line x1="'.$px.'" y1="'.$py.'" x2="'.($px + $pw).'" y2="'.($py + $ph).'"/>'
            .'<line x1="'.($px + $pw).'" y1="'.$py.'" x2="'.$px.'" y2="'.($py + $ph).'"/>'
            .'</g>'
            .'<rect x="'.($px + $pw / 2 - $unit * 0.20).'" y="'.$py.'" width="'.($unit * 0.40).'" height="'.$ph.'" fill="#FFFFFF"/>'
            .'<rect x="'.$px.'" y="'.($py + $ph / 2 - $unit * 0.20).'" width="'.$pw.'" height="'.($unit * 0.40).'" fill="#FFFFFF"/>'
            .'<rect x="'.($px + $pw / 2 - $unit * 0.12).'" y="'.$py.'" width="'.($unit * 0.24).'" height="'.$ph.'" fill="#C8102E"/>'
            .'<rect x="'.$px.'" y="'.($py + $ph / 2 - $unit * 0.12).'" width="'.$pw.'" height="'.($unit * 0.24).'" fill="#C8102E"/>'
            .'</g>';
    }

    /** الاحتياطيّ: لوحةٌ مرسومة بحرفَي الكود — لا صورة ولا أيقونة مستوردة. */
    private function plate(string $iso2): string
    {
        $label = htmlspecialchars($iso2 !== '' ? mb_substr($iso2, 0, 2) : '؟', ENT_QUOTES, 'UTF-8');

        return '<rect x="0" y="0" width="'.self::W.'" height="'.self::H.'" fill="#E8EDF2"/>'
            .'<rect x="0" y="0" width="'.self::W.'" height="'.(self::H / 3).'" fill="#CBD5E1"/>'
            .'<text x="'.(self::W / 2).'" y="'.(self::H * 0.72).'" text-anchor="middle"'
            .' font-size="'.(self::H * 0.5).'" font-weight="700" fill="#334155">'.$label.'</text>';
    }

    private function x(float $percent): float
    {
        return round($percent / 100 * self::W, 2);
    }

    private function y(float $percent): float
    {
        return round($percent / 100 * self::H, 2);
    }

    /** نصف القطر يُقاس على الارتفاع فلا يتشوّه مع نسبة 3:2 */
    private function r(float $percent): float
    {
        return round($percent / 100 * self::H, 2);
    }

    /** لا يدخل في الـSVG إلّا لونٌ سداسيّ — فلا يُحقَن شيء من جدول الهندسة. */
    private function color(string $value): string
    {
        $value = trim($value);

        return preg_match('/^#[0-9A-Fa-f]{3,8}$/', $value) === 1 ? $value : '#94A3B8';
    }
}
