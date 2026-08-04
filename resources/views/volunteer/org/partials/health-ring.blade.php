@php
    /** المؤشّر العامّ لصحّة القسم — SVG بأيدينا بلا أيّ مكتبة رسوم (2.16-ج) */
    $p = (int) max(0, min(100, $percent));
    $size = 76;
    $stroke = 7;
    $r = ($size / 2) - $stroke;
    $c = 2 * M_PI * $r;
    $dash = round($c * $p / 100, 2);
    $state = $p >= (int) setting('volunteer.health.ring.ok_percent', 70)
        ? 'ok'
        : ($p >= (int) setting('volunteer.health.ring.warn_percent', 45) ? 'warn' : 'danger');
    $mark = state_color($state);
@endphp

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}"
     class="shrink-0" role="img" aria-label="{{ setting('volunteer.org_health_ring.aria', 'مؤشّر صحّة القسم') }} {{ $p }}٪">
    <title>{{ setting('volunteer.org_health_ring.aria', 'مؤشّر صحّة القسم') }} {{ $p }}٪ — {{ $mark['label'] }}</title>
    <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ round($r, 2) }}"
            fill="none" stroke="var(--surface-sunken)" stroke-width="{{ $stroke }}" />
    <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ round($r, 2) }}"
            fill="none" stroke="var(--color-state-{{ $state }})" stroke-width="{{ $stroke }}" stroke-linecap="round"
            stroke-dasharray="{{ $dash }} {{ round($c - $dash, 2) }}"
            transform="rotate(-90 {{ $size / 2 }} {{ $size / 2 }})" />
    {{-- الرمز مع اللون دائمًا — اللون وحده لا يحمل المعنى (2.16-ب) --}}
    <text x="{{ $size / 2 }}" y="{{ $size / 2 - 4 }}" text-anchor="middle" dominant-baseline="central"
          font-size="17" font-weight="800" fill="var(--text)">{{ $p }}%</text>
    <text x="{{ $size / 2 }}" y="{{ $size / 2 + 14 }}" text-anchor="middle" dominant-baseline="central"
          font-size="11" fill="var(--color-state-{{ $state }})">{{ $mark['icon'] }}</text>
</svg>
