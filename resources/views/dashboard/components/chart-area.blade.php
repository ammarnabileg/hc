@php
    /**
     * XP عبر الزمن (14-ج) — Area مرسومة SVG بيدنا، بلا أيّ مكتبة رسوم خارجيّة.
     * والزمن يجري من اليمين لليسار موافقةً لاتّجاه القراءة (RTL).
     */
    $n = max(1, count($points));
    $areaH = 190;
    $padTop = 16;
    $padBottom = 34;
    $gutter = 46; // فراغ محور القيم على اليمين (جهة البداية في RTL)
    $padLeft = 14;
    $areaW = max(320, $n * 30 + $gutter + $padLeft);
    $innerW = $areaW - $gutter - $padLeft;
    $innerH = $areaH - $padTop - $padBottom;
    $maxValue = max(1, max(array_map(fn ($p) => $p['value'], $points)));

    $px = fn ($i) => round($padLeft + ($n <= 1 ? $innerW / 2 : $innerW * (1 - $i / ($n - 1))), 2);
    $py = fn ($v) => round($padTop + $innerH * (1 - $v / $maxValue), 2);

    $line = [];
    foreach ($points as $i => $point) {
        $line[] = ($i === 0 ? 'M' : 'L').$px($i).' '.$py($point['value']);
    }
    $linePath = implode(' ', $line);
    $areaPath = $linePath.' L'.$px($n - 1).' '.round($padTop + $innerH, 2).' L'.$px(0).' '.round($padTop + $innerH, 2).' Z';
    $everyLabel = (int) ceil($n / 6);
    $totalXp = array_sum(array_map(fn ($p) => $p['value'], $points));
@endphp

<section class="card p-4 min-w-0">
    <div class="flex items-baseline justify-between gap-2">
        <h3 class="font-bold text-sm">XP عبر الزمن</h3>
        <span class="text-xs" style="color: var(--text-muted)">مجموع المدى: {{ number_format($totalXp) }} XP</span>
    </div>

    {{-- الرسوم داخل حاوية متمرّرة أفقيًّا، فلا تمرير أفقيّ للصفحة نفسها (2.15-ج) --}}
    <div class="mt-3 min-w-0 overflow-x-auto no-scrollbar">
        <svg viewBox="0 0 {{ $areaW }} {{ $areaH }}" width="{{ $areaW }}" height="{{ $areaH }}"
             style="min-width: 100%" role="img" aria-label="نقاط الخبرة المكتسبة يوميًّا خلال المدى المختار">
            <title>XP عبر الزمن</title>

            <defs>
                <linearGradient id="xpFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="var(--color-brand-500)" stop-opacity="0.35" />
                    <stop offset="100%" stop-color="var(--color-brand-500)" stop-opacity="0.02" />
                </linearGradient>
            </defs>

            @foreach ([0, 0.5, 1] as $ratio)
                @php $gy = round($padTop + $innerH * $ratio, 2); @endphp
                <line x1="{{ $padLeft }}" y1="{{ $gy }}" x2="{{ $padLeft + $innerW }}" y2="{{ $gy }}"
                      stroke="var(--border)" stroke-width="1" />
                <text x="{{ $padLeft + $innerW + 6 }}" y="{{ $gy + 4 }}" font-size="10" fill="var(--text-muted)">
                    {{ number_format((int) round($maxValue * (1 - $ratio))) }}
                </text>
            @endforeach

            <path d="{{ $areaPath }}" fill="url(#xpFill)" />
            <path d="{{ $linePath }}" fill="none" stroke="var(--color-brand-500)" stroke-width="2"
                  stroke-linejoin="round" stroke-linecap="round" />

            @foreach ($points as $i => $point)
                @if ($point['value'] > 0)
                    <circle cx="{{ $px($i) }}" cy="{{ $py($point['value']) }}" r="2.5" fill="var(--color-brand-500)">
                        <title>{{ $point['label'] }}: {{ number_format($point['value']) }} XP</title>
                    </circle>
                @endif
                @if ($i % $everyLabel === 0 || $i === $n - 1)
                    <text x="{{ $px($i) }}" y="{{ $areaH - 12 }}" font-size="10" text-anchor="middle" fill="var(--text-muted)">
                        {{ $point['short'] }}
                    </text>
                @endif
            @endforeach
        </svg>
    </div>
</section>
