@php
    /**
     * حلقة تقدّم التدريب (14-ب) — SVG بيدنا بلا أيّ مكتبة رسوم (2.16-ج).
     * التركوازيّ هنا لونُ تقدّمٍ لا لونُ حالة (2.16).
     */
    $ringPercent = (int) max(0, min(100, $percent ?? 0));
    $ringSize = (int) ($size ?? 68);
    $ringStroke = 6;
    $ringR = ($ringSize / 2) - $ringStroke;
    $ringC = 2 * M_PI * $ringR;
    $ringDash = round($ringC * $ringPercent / 100, 2);
@endphp

<svg width="{{ $ringSize }}" height="{{ $ringSize }}" viewBox="0 0 {{ $ringSize }} {{ $ringSize }}"
     class="shrink-0" role="img" aria-label="{{ str_replace(':percent', $ringPercent, (string) setting('dashboard.progress_ring.aria_label', 'نسبة الإكمال :percent٪')) }}">
    <title>{{ str_replace(':percent', $ringPercent, (string) setting('dashboard.progress_ring.aria_label', 'نسبة الإكمال :percent٪')) }}</title>
    <circle cx="{{ $ringSize / 2 }}" cy="{{ $ringSize / 2 }}" r="{{ round($ringR, 2) }}"
            fill="none" stroke="var(--surface-sunken)" stroke-width="{{ $ringStroke }}" />
    <circle cx="{{ $ringSize / 2 }}" cy="{{ $ringSize / 2 }}" r="{{ round($ringR, 2) }}"
            fill="none" stroke="var(--color-brand-500)" stroke-width="{{ $ringStroke }}" stroke-linecap="round"
            stroke-dasharray="{{ $ringDash }} {{ round($ringC - $ringDash, 2) }}"
            transform="rotate(-90 {{ $ringSize / 2 }} {{ $ringSize / 2 }})" />
    <text x="{{ $ringSize / 2 }}" y="{{ $ringSize / 2 }}" text-anchor="middle" dominant-baseline="central"
          font-size="{{ round($ringSize * 0.26) }}" font-weight="800" fill="var(--text)">{{ $ringPercent }}%</text>
</svg>
