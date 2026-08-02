@php
    /**
     * منحنى مرسوم SVG بيدنا — **ممنوع أيّ مكتبة رسوم خارجيّة** (دليل البناء 4).
     * $series: [['label' => '08/01', 'value' => 3.5], …] — والقيم قد تكون null (محجوبة).
     */
    $series = collect($series ?? [])->values();
    $chartId = $chartId ?? 'chart-'.uniqid();
    $height = (int) ($height ?? 120);
    $zeroLine = (bool) ($zeroLine ?? false);
    $unit = $unit ?? '';

    $values = $series->pluck('value')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
    $max = $values->max() ?? 0;
    $min = $values->min() ?? 0;

    if ($zeroLine) {
        $max = max($max, 0);
        $min = min($min, 0);
    }

    if ($max === $min) {
        $max += 1;
        $min -= 1;
    }

    $width = 300;
    $pad = 6;
    $count = max(1, $series->count() - 1);

    $x = fn ($i) => round($pad + ($i / $count) * ($width - 2 * $pad), 2);
    $y = fn ($v) => round($height - $pad - ((((float) $v) - $min) / ($max - $min)) * ($height - 2 * $pad), 2);

    $points = [];
    foreach ($series as $i => $point) {
        if ($point['value'] === null) { continue; }
        $points[] = $x($i).','.$y($point['value']);
    }

    $line = implode(' ', $points);
    $area = $points === [] ? '' : $x(0).','.($height - $pad).' '.$line.' '.$x($series->count() - 1).','.($height - $pad);
@endphp

<figure class="card p-4">
    @isset($title)
        <figcaption class="text-sm font-semibold mb-2">{{ $title }}</figcaption>
    @endisset

    @if ($points === [])
        <p class="text-sm py-6 text-center" style="color: var(--text-muted)">لسّه مفيش بيانات في المدى ده</p>
    @else
        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full" style="height: {{ $height }}px"
             role="img" aria-label="{{ $title ?? 'منحنى' }}" preserveAspectRatio="none">
            <defs>
                <linearGradient id="fill-{{ $chartId }}" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="var(--color-brand-500)" stop-opacity=".28" />
                    <stop offset="100%" stop-color="var(--color-brand-500)" stop-opacity="0" />
                </linearGradient>
            </defs>

            @if ($zeroLine && $min < 0 && $max > 0)
                <line x1="{{ $pad }}" x2="{{ $width - $pad }}" y1="{{ $y(0) }}" y2="{{ $y(0) }}"
                      stroke="var(--border)" stroke-width="1" stroke-dasharray="3 3" />
            @endif

            <polygon points="{{ $area }}" fill="url(#fill-{{ $chartId }})" />
            <polyline points="{{ $line }}" fill="none" stroke="var(--color-brand-500)"
                      stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

            @foreach ($series as $i => $point)
                @continue($point['value'] === null)
                @if ($loop->last)
                    <circle cx="{{ $x($i) }}" cy="{{ $y($point['value']) }}" r="3" fill="var(--color-brand-500)" />
                @endif
            @endforeach
        </svg>

        <div class="flex items-center justify-between text-xs mt-1" style="color: var(--text-muted)">
            <span>{{ $series->first()['label'] ?? '' }}</span>
            <span>{{ rtrim(rtrim(number_format((float) $values->last(), 2), '0'), '.') }} {{ $unit }}</span>
            <span>{{ $series->last()['label'] ?? '' }}</span>
        </div>
    @endif
</figure>
