<?php

namespace App\Services\Admin\System;

use Illuminate\Support\HtmlString;

/**
 * رسوم SVG مكتوبة بأيدينا — **ممنوع أيّ مكتبة خارجيّة** (قاعدة البناء · 2.16).
 *
 * كلّ رسم هنا يتبع ثلاث قواعد:
 *  - يستعمل `currentColor` وتوكنز الهويّة فيتبدّل مع الوضع الداكن/الفاتح تلقائيًّا.
 *  - لا يعتمد اللونَ وحده حاملًا للمعنى — ومعه دائمًا رقمٌ أو نصّ.
 *  - يخرج بحجم مرن (`viewBox` + `width:100%`) فلا يسبّب تمريرًا أفقيًّا على الموبايل.
 */
class SvgChart
{
    /**
     * رسم خطّيّ لسلسلة زمنيّة، ومعه سلسلة مقارنة اختياريّة بخطّ متقطّع.
     *
     * @param  array<int, array{label:string, value:float}>  $series
     * @param  array<int, array{label:string, value:float}>  $compare
     */
    public function line(array $series, array $compare = [], int $height = 160): HtmlString
    {
        if ($series === []) {
            return $this->emptyBox('لا بيانات في هذه الفترة');
        }

        $w = 600;
        $h = $height;
        $pad = 24;
        $max = max(1, max(array_map(fn ($p) => (float) $p['value'], array_merge($series, $compare))));

        $path = $this->points($series, $w, $h, $pad, $max);
        $comparePath = $compare !== [] ? $this->points($compare, $w, $h, $pad, $max) : null;

        $grid = '';
        for ($i = 0; $i <= 3; $i++) {
            $y = $pad + ($h - 2 * $pad) * $i / 3;
            $grid .= '<line x1="'.$pad.'" y1="'.round($y, 1).'" x2="'.($w - $pad).'" y2="'.round($y, 1).'" stroke="currentColor" stroke-opacity=".12" />';
        }

        $svg = '<svg viewBox="0 0 '.$w.' '.$h.'" style="width:100%;height:auto" role="img" aria-label="رسم خطّيّ">'
            .$grid
            .($comparePath ? '<polyline points="'.$comparePath.'" fill="none" stroke="currentColor" stroke-opacity=".35" stroke-dasharray="5 4" stroke-width="2" />' : '')
            .'<polyline points="'.$path.'" fill="none" stroke="var(--color-brand-500)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />'
            .'</svg>';

        return new HtmlString($svg);
    }

    /**
     * أعمدة أفقيّة — أنسب للعربيّة وللموبايل من الأعمدة الرأسيّة الضيّقة.
     *
     * @param  array<int, array{label:string, value:float}>  $rows
     */
    public function bars(array $rows, int $limit = 8): HtmlString
    {
        $rows = array_slice($rows, 0, $limit);

        if ($rows === []) {
            return $this->emptyBox('لا بيانات في هذه الفترة');
        }

        $max = max(1, max(array_map(fn ($r) => (float) $r['value'], $rows)));
        $rowH = 30;
        $w = 600;
        $h = count($rows) * $rowH + 8;
        $body = '';

        foreach (array_values($rows) as $i => $row) {
            $y = $i * $rowH + 6;
            $barW = max(2, (int) round(($row['value'] / $max) * ($w - 220)));

            $body .= '<text x="'.($w - 4).'" y="'.($y + 13).'" text-anchor="end" font-size="12" fill="currentColor" fill-opacity=".8">'
                .e($row['label']).'</text>'
                .'<rect x="'.($w - 210 - $barW).'" y="'.$y.'" width="'.$barW.'" height="16" rx="6" fill="var(--color-brand-500)" fill-opacity=".85" />'
                .'<text x="'.($w - 216 - $barW).'" y="'.($y + 13).'" text-anchor="end" font-size="11" fill="currentColor" fill-opacity=".7">'
                .e($this->number($row['value'])).'</text>';
        }

        return new HtmlString('<svg viewBox="0 0 '.$w.' '.$h.'" style="width:100%;height:auto" role="img" aria-label="أعمدة">'.$body.'</svg>');
    }

    /**
     * قمع التحويل بنقاط التسرّب — كلّ مرحلة برقمها ونسبتها ونسبة الفاقد.
     *
     * @param  array<int, array{label:string, value:float}>  $stages
     */
    public function funnel(array $stages): HtmlString
    {
        if ($stages === []) {
            return $this->emptyBox('لا بيانات في هذه الفترة');
        }

        $w = 600;
        $stepH = 46;
        $h = count($stages) * $stepH + 8;
        $top = max(1, (float) $stages[0]['value']);
        $body = '';
        $previous = null;

        foreach (array_values($stages) as $i => $stage) {
            $y = $i * $stepH + 6;
            $ratio = $stage['value'] / $top;
            $barW = max(24, (int) round($ratio * ($w - 200)));
            $drop = $previous !== null && $previous > 0
                ? round(100 - ($stage['value'] / $previous * 100), 1)
                : null;

            $body .= '<rect x="'.($w - 190 - $barW).'" y="'.$y.'" width="'.$barW.'" height="26" rx="8" fill="var(--color-brand-500)" fill-opacity="'.(0.9 - $i * 0.12).'" />'
                .'<text x="'.($w - 4).'" y="'.($y + 18).'" text-anchor="end" font-size="12" fill="currentColor">'.e($stage['label']).'</text>'
                .'<text x="'.($w - 196 - $barW).'" y="'.($y + 18).'" text-anchor="end" font-size="11" fill="currentColor" fill-opacity=".75">'
                .e($this->number($stage['value']).' · '.round($ratio * 100, 1).'%')
                .($drop !== null && $drop > 0 ? e(' · تسرّب '.$drop.'%') : '')
                .'</text>';

            $previous = (float) $stage['value'];
        }

        return new HtmlString('<svg viewBox="0 0 '.$w.' '.$h.'" style="width:100%;height:auto" role="img" aria-label="قمع تحويل">'.$body.'</svg>');
    }

    /**
     * خريطة حراريّة (يوم × ساعة أو أيّ مصفوفة) — والتدرّج إعداد لا لون محروق.
     *
     * @param  array<int, array<int, float>>  $matrix
     * @param  array<int, string>  $rowLabels
     * @param  array<int, string>  $colLabels
     */
    public function heatmap(array $matrix, array $rowLabels, array $colLabels): HtmlString
    {
        if ($matrix === []) {
            return $this->emptyBox('لا بيانات في هذه الفترة');
        }

        $cell = 22;
        $labelW = 54;
        $cols = count($colLabels);
        $w = $labelW + $cols * $cell + 6;
        $h = count($rowLabels) * $cell + 22;
        $max = 1.0;

        foreach ($matrix as $row) {
            foreach ($row as $value) {
                $max = max($max, (float) $value);
            }
        }

        $body = '';

        foreach ($colLabels as $c => $label) {
            if ($c % 3 === 0) {
                $body .= '<text x="'.($w - $labelW - $c * $cell - $cell / 2).'" y="12" text-anchor="middle" font-size="9" fill="currentColor" fill-opacity=".6">'.e($label).'</text>';
            }
        }

        foreach ($rowLabels as $r => $rowLabel) {
            $y = 18 + $r * $cell;
            $body .= '<text x="'.($w - 4).'" y="'.($y + 15).'" text-anchor="end" font-size="10" fill="currentColor" fill-opacity=".7">'.e($rowLabel).'</text>';

            foreach ($colLabels as $c => $colLabel) {
                $value = (float) ($matrix[$r][$c] ?? 0);
                $opacity = $value <= 0 ? 0.06 : 0.15 + 0.85 * ($value / $max);

                $body .= '<rect x="'.($w - $labelW - ($c + 1) * $cell).'" y="'.$y.'" width="'.($cell - 3).'" height="'.($cell - 3).'" rx="4"'
                    .' fill="var(--color-brand-500)" fill-opacity="'.round($opacity, 3).'">'
                    .'<title>'.e($rowLabel.' · '.$colLabel.' · '.$this->number($value)).'</title></rect>';
            }
        }

        return new HtmlString('<svg viewBox="0 0 '.$w.' '.$h.'" style="width:100%;height:auto" role="img" aria-label="خريطة حراريّة">'.$body.'</svg>');
    }

    // ------------------------------------------------------------------ داخليّ

    private function points(array $series, int $w, int $h, int $pad, float $max): string
    {
        $count = max(1, count($series) - 1);
        $step = ($w - 2 * $pad) / $count;

        return implode(' ', array_map(function ($point, $i) use ($w, $h, $pad, $max, $step) {
            // RTL: أوّل نقطة على اليمين فيقرأ الزمن من اليمين لليسار كالنصّ
            $x = $w - $pad - $i * $step;
            $y = $h - $pad - ((float) $point['value'] / $max) * ($h - 2 * $pad);

            return round($x, 1).','.round($y, 1);
        }, $series, array_keys($series)));
    }

    private function number(float $value): string
    {
        return $value >= 1000
            ? rtrim(rtrim(number_format($value / 1000, 1, '.', ''), '0'), '.').' ألف'
            : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function emptyBox(string $message): HtmlString
    {
        return new HtmlString(
            '<div class="text-xs py-6 text-center" style="color: var(--text-muted)">'.e($message).'</div>'
        );
    }
}
