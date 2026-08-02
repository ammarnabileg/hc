@php
    /**
     * منحنى صغير مرسوم **SVG بهويّة المنصّة** — بلا أيّ مكتبة رسم (2.16-ج).
     * الاتّجاه أهمّ من الرقم المجرّد (13.4-م-1)، ولذلك بلا محاور ولا شبكة.
     */
    $points = collect($series ?? [])->values();
    $values = $points->pluck('value')->map(fn ($v) => (float) $v);
    $min = $values->min() ?? 0;
    $max = $values->max() ?? 0;
    $span = ($max - $min) > 0 ? ($max - $min) : 1;
    $width = 300;
    $height = 64;
    $step = $points->count() > 1 ? $width / ($points->count() - 1) : $width;

    $coords = $points->map(function ($point, $index) use ($step, $height, $min, $span) {
        $x = round($index * $step, 2);
        $y = round($height - 4 - ((float) $point['value'] - $min) / $span * ($height - 8), 2);

        return $x.','.$y;
    })->implode(' ');

    $tone = $tone ?? 'var(--color-brand-500)';
@endphp

<div class="card p-4">
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-sm font-bold">{{ $title }}</h3>
        <span class="text-xs" style="color: var(--text-muted)">آخر {{ $days }} يوم</span>
    </div>

    @if ($points->count() < 2)
        <p class="text-xs" style="color: var(--text-muted)">لسّه بدري — أوّل حركة هتبان هنا.</p>
    @else
        <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none"
             class="w-full" style="height: {{ $height }}px" role="img" aria-label="{{ $title }}">
            <polyline points="{{ $coords }}" fill="none" stroke="{{ $tone }}" stroke-width="2"
                      stroke-linejoin="round" stroke-linecap="round" />
        </svg>
        <div class="mt-1 flex items-center justify-between text-xs" style="color: var(--text-muted)">
            <span>{{ rtrim(rtrim(number_format($values->first(), 2, '.', ''), '0'), '.') }}</span>
            <span>{{ rtrim(rtrim(number_format($values->last(), 2, '.', ''), '0'), '.') }}</span>
        </div>
    @endif
</div>
