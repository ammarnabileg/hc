@php
    /**
     * خريطة حراريّة للحضور — نادي الخامسة صباحًا (7.2 · 14-ج).
     * الأسابيع تجري من اليمين لليسار (RTL)، ويوم النادي بلون الشرف ورمزه ★ (2.16).
     */
    $cell = 14;
    $gap = 4;
    $weeksCount = count($heatmap['columns']);
    $labelsW = 34;
    $mapW = $labelsW + $weeksCount * ($cell + $gap);
    $mapH = 7 * ($cell + $gap) + 6;
    $dayNames = [
        (string) setting('dashboard.chart.heatmap.day_sat', 'السبت'), '',
        (string) setting('dashboard.chart.heatmap.day_mon', 'الاثنين'), '',
        (string) setting('dashboard.chart.heatmap.day_wed', 'الأربعاء'), '',
        (string) setting('dashboard.chart.heatmap.day_fri', 'الجمعة'),
    ];
    $fillFor = fn (string $level) => match ($level) {
        'club' => 'var(--color-state-honor)',
        'present' => 'var(--color-state-ok)',
        default => 'var(--surface-sunken)',
    };
@endphp

<section class="card p-4 min-w-0">
    <div class="flex items-baseline justify-between gap-2">
        <h3 class="font-bold text-sm">{{ setting('dashboard.chart.heatmap.title', 'خريطة الحضور') }}</h3>
        <span class="text-xs" style="color: var(--text-muted)">{{ str_replace([':present', ':club'], [$heatmap['present'], $heatmap['club']], (string) setting('dashboard.chart.heatmap.summary', ':present يوم حضور · ★ :club في النادي')) }}</span>
    </div>

    <div class="mt-3 min-w-0 overflow-x-auto no-scrollbar">
        <svg viewBox="0 0 {{ $mapW }} {{ $mapH }}" width="{{ $mapW }}" height="{{ $mapH }}" role="img"
             aria-label="{{ setting('dashboard.chart.heatmap.aria_label', 'خريطة حضورك في الأسابيع الماضية') }}">
            <title>{{ setting('dashboard.chart.heatmap.title', 'خريطة الحضور') }}</title>

            @foreach ($dayNames as $d => $dayName)
                @if ($dayName !== '')
                    <text x="{{ $mapW - 2 }}" y="{{ $d * ($cell + $gap) + $cell - 2 }}" font-size="9"
                          text-anchor="end" fill="var(--text-muted)">{{ $dayName }}</text>
                @endif
            @endforeach

            @foreach ($heatmap['columns'] as $w => $week)
                @foreach ($week as $d => $day)
                    @continue($day['future'])
                    @php $x = ($weeksCount - 1 - $w) * ($cell + $gap); @endphp
                    <rect x="{{ $x }}" y="{{ $d * ($cell + $gap) }}" width="{{ $cell }}" height="{{ $cell }}" rx="3"
                          fill="{{ $fillFor($day['level']) }}"
                          fill-opacity="{{ $day['level'] === 'none' ? 1 : 0.85 }}">
                        <title>{{ $day['label'] }} — {{ $day['level'] === 'club' ? setting('dashboard.chart.heatmap.tooltip_club', 'نادي الخامسة ★') : ($day['level'] === 'present' ? setting('dashboard.chart.heatmap.tooltip_present', 'حضور ●') : setting('dashboard.chart.heatmap.tooltip_absent', 'بلا حضور ○')) }}</title>
                    </rect>
                    @if ($day['level'] === 'club')
                        <text x="{{ $x + $cell / 2 }}" y="{{ $d * ($cell + $gap) + $cell - 3 }}" font-size="9"
                              text-anchor="middle" fill="#04201c">★</text>
                    @endif
                @endforeach
            @endforeach
        </svg>
    </div>

    <ul class="mt-2 flex flex-wrap gap-3 text-xs" style="color: var(--text-muted)">
        <li><span aria-hidden="true" style="color: var(--color-state-ok)">●</span> {{ setting('dashboard.chart.heatmap.legend_present', 'حضور') }}</li>
        <li><span aria-hidden="true" style="color: var(--color-state-honor)">★</span> {{ setting('dashboard.chart.heatmap.legend_club', 'نادي الخامسة') }}</li>
        <li><span aria-hidden="true" style="color: var(--color-state-idle)">○</span> {{ setting('dashboard.chart.heatmap.legend_absent', 'بلا حضور') }}</li>
    </ul>
</section>
