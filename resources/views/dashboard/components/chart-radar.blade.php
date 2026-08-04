@php
    /**
     * رادار مسارات الإنجاز الخمسة (10 · 10.1 · 14-ج) — SVG بيدنا.
     * نصف القطر = مستوى المسار نسبةً إلى السقف المعروض، والعتبات من الإعدادات.
     */
    $axes = $radar['axes'];
    $count = max(1, count($axes));
    $cx = 150;
    $cy = 132;
    $rMax = 92;
    $point = function (int $i, float $ratio) use ($count, $cx, $cy, $rMax) {
        $angle = -M_PI / 2 + (2 * M_PI * $i / $count);
        return round($cx + $rMax * $ratio * cos($angle), 2).' '.round($cy + $rMax * $ratio * sin($angle), 2);
    };
    $ring = fn (float $ratio) => implode(' ', array_map(fn ($i) => $point($i, $ratio), range(0, $count - 1)));
    $shape = implode(' ', array_map(fn ($i) => $point($i, max(0.06, (float) $axes[$i]['ratio'])), range(0, $count - 1)));
@endphp

<section class="card p-4 min-w-0">
    <div class="flex items-baseline justify-between gap-2">
        <h3 class="font-bold text-sm">{{ setting('dashboard.chart.radar.title', 'مسارات الإنجاز الخمسة') }}</h3>
        <span class="text-xs" style="color: var(--text-muted)">{{ str_replace(':level', $radar['max_level'], (string) setting('dashboard.chart.radar.max_level', 'السقف المعروض: مستوى :level')) }}</span>
    </div>

    <div class="mt-3 min-w-0 overflow-x-auto no-scrollbar">
        <svg viewBox="0 0 300 250" width="300" height="250" role="img"
             aria-label="{{ setting('dashboard.chart.radar.aria_label', 'مستوياتك في مسارات الإنجاز الخمسة') }}">
            <title>{{ setting('dashboard.chart.radar.svg_title', 'رادار الإنجازات') }}</title>

            @foreach ([0.25, 0.5, 0.75, 1] as $ratio)
                <polygon points="{{ $ring($ratio) }}" fill="none" stroke="var(--border)" stroke-width="1" />
            @endforeach

            @foreach ($axes as $i => $axis)
                <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ explode(' ', $point($i, 1))[0] }}"
                      y2="{{ explode(' ', $point($i, 1))[1] }}" stroke="var(--border)" stroke-width="1" />
            @endforeach

            <polygon points="{{ $shape }}" fill="var(--color-brand-500)" fill-opacity="0.22"
                     stroke="var(--color-brand-500)" stroke-width="2" stroke-linejoin="round" />

            @foreach ($axes as $i => $axis)
                @php [$dx, $dy] = explode(' ', $point($i, max(0.06, (float) $axis['ratio']))); @endphp
                <circle cx="{{ $dx }}" cy="{{ $dy }}" r="3" fill="var(--color-brand-500)">
                    <title>{{ str_replace([':label', ':level', ':value', ':unit'], [$axis['label'], $axis['level'], number_format($axis['value']), $axis['unit']], (string) setting('dashboard.chart.radar.axis_tooltip', ':label: مستوى :level — :value :unit')) }}</title>
                </circle>
            @endforeach

            @foreach ($axes as $i => $axis)
                @php
                    $angle = -M_PI / 2 + (2 * M_PI * $i / $count);
                    $lx = round($cx + ($rMax + 16) * cos($angle), 2);
                    $ly = round($cy + ($rMax + 16) * sin($angle), 2);
                    $anchor = abs($lx - $cx) < 6 ? 'middle' : ($lx > $cx ? 'start' : 'end');
                @endphp
                <text x="{{ $lx }}" y="{{ $ly }}" font-size="10" text-anchor="{{ $anchor }}" fill="var(--text-muted)">
                    {{ $axis['label'] }}
                </text>
                <text x="{{ $lx }}" y="{{ $ly + 12 }}" font-size="10" font-weight="700" text-anchor="{{ $anchor }}" fill="var(--text)">
                    {{ str_replace(':level', $axis['level'], (string) setting('dashboard.chart.radar.axis_level', 'مستوى :level')) }}
                </text>
            @endforeach
        </svg>
    </div>
</section>
