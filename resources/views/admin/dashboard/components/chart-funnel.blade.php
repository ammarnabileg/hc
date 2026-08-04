@php
    /**
     * قمع التحويل: مسجّل ⟵ معتمَد ⟵ مشترٍ (12.3-7) — **SVG بأيدينا بلا مكتبة خارجيّة**.
     * ونسبة كلّ مرحلة إلى ما قبلها مكتوبة بجوارها، فالرقم يشرح نفسه.
     */
    $top = max(1, (int) ($stages[0]['value'] ?? 1));
    $rowH = 42;
    $h = count($stages) * $rowH + 12;
    $w = 340;
    $barMax = 220;
@endphp

<section class="card p-4 min-w-0">
    <h3 class="font-bold text-sm">{{ setting('admin.dashboard.components.chart_funnel.qma_althwyl', 'قمع التحويل') }}</h3>

    <div class="mt-3 min-w-0 overflow-x-auto no-scrollbar">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" width="{{ $w }}" height="{{ $h }}" style="min-width: 100%"
             role="img" aria-label="{{ setting('admin.dashboard.components.chart_funnel.qma_althwyl_mn_msjl_ila_mshtr', 'قمع التحويل من مسجّل إلى مشترٍ') }}">
            <title>{{ setting('admin.dashboard.components.chart_funnel.qma_althwyl', 'قمع التحويل') }}</title>

            @foreach ($stages as $i => $stage)
                @php
                    $value = (int) $stage['value'];
                    $width = max(2, round($barMax * ($value / $top), 2));
                    // في RTL يبدأ الشريط من اليمين ويمتدّ لليسار
                    $x = $w - 100 - $width;
                    $y = 10 + $i * $rowH;
                    $prev = $i === 0 ? null : (int) $stages[$i - 1]['value'];
                    $rate = $prev ? round(($value / max(1, $prev)) * 100) : null;
                @endphp

                <text x="{{ $w - 8 }}" y="{{ $y + 18 }}" font-size="11" text-anchor="end" fill="var(--text-muted)">
                    {{ $stage['label'] }}
                </text>

                <rect x="{{ $x }}" y="{{ $y + 6 }}" width="{{ $width }}" height="18" rx="9"
                      fill="var(--color-brand-500)" opacity="{{ 1 - $i * 0.22 }}">
                    <title>{{ $stage['label'] }}: {{ number_format($value) }}</title>
                </rect>

                <text x="{{ $x - 8 }}" y="{{ $y + 19 }}" font-size="11" text-anchor="end" fill="var(--text)">
                    {{ number_format($value) }}@if ($rate !== null) <tspan fill="var(--text-muted)">({{ $rate }}%)</tspan>@endif
                </text>
            @endforeach
        </svg>
    </div>
</section>
