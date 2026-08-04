@php
    /**
     * خريطة حراريّة تقويميّة — SVG مرسومة بالكامل هنا،
     * وممنوع أيّ مكتبة رسوم خارجيّة (2.16-ج · قواعد المجال).
     * الأعمدة أسابيع والصفوف أيّام (السبت أوّل الأسبوع)، والاتّجاه من اليمين لليسار.
     */
    use Carbon\CarbonImmutable;

    $cell = 13;
    $gap = 3;
    $step = $cell + $gap;

    $start = CarbonImmutable::parse($from->format('Y-m-d'));
    $end = CarbonImmutable::parse($to->format('Y-m-d'));

    // العمود يبدأ من السبت الذي يسبق أوّل يوم أو يساويه
    $rowOf = fn (CarbonImmutable $d) => ($d->dayOfWeek + 1) % 7;
    $gridStart = $start->subDays($rowOf($start));
    $weeks = (int) floor($gridStart->diffInDays($end) / 7) + 1;

    $width = $weeks * $step;
    $height = 7 * $step + 18;

    $monthLabels = [];
    $lastMonth = null;
@endphp

<div class="min-w-0 overflow-x-auto no-scrollbar">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}"
         role="img" aria-label="{{ setting('streaks.heatmap.aria_label', 'خريطة أيّامي النشطة') }}" style="max-width: 100%">

        @for ($w = 0; $w < $weeks; $w++)
            @for ($r = 0; $r < 7; $r++)
                @php
                    $date = $gridStart->addDays($w * 7 + $r);
                    $key = $date->toDateString();
                    $inRange = $date->gte($start) && $date->lte($end);
                    $day = $heatmap[$key] ?? null;

                    $fill = match (true) {
                        ! $inRange => 'transparent',
                        (bool) ($day['club'] ?? false) => 'var(--color-state-honor)',
                        // اليوم المحميّ بدرع تجميد يظهر بحاله الخاصّ لا كيومٍ نشط (7.2)
                        (bool) ($day['freeze'] ?? false) => 'var(--color-state-warn)',
                        (bool) ($day['active'] ?? false) => 'var(--color-brand-500)',
                        default => 'var(--surface-sunken)',
                    };

                    // اسم الشهر يظهر مرّة واحدة فوق أوّل عمود فيه
                    if ($inRange && $r === 0 && $date->month !== $lastMonth) {
                        $lastMonth = $date->month;
                        $monthLabels[] = ['w' => $w, 'label' => $date->translatedFormat('M')];
                    }
                @endphp

                @if ($inRange)
                    {{-- ضغطة يوم ⟵ تفاصيله (والعنوان يظهر بالمرور أيضًا) --}}
                    <rect x="{{ ($weeks - 1 - $w) * $step }}" y="{{ $r * $step + 16 }}"
                          width="{{ $cell }}" height="{{ $cell }}" rx="3"
                          fill="{{ $fill }}" stroke="var(--border)" stroke-width="0.5">
                        <title>{{ $date->translatedFormat('j F Y') }} — {{ match (true) {
                            (bool) ($day['club'] ?? false) => setting('streaks.heatmap.tooltip_club', 'نادي الخامسة ★'),
                            (bool) ($day['freeze'] ?? false) => setting('streaks.heatmap.tooltip_freeze', 'يوم محميّ بدرع ▲'),
                            (bool) ($day['active'] ?? false) => setting('streaks.heatmap.tooltip_active', 'يوم نشط ●'),
                            default => setting('streaks.heatmap.tooltip_idle', 'بلا نشاط ○'),
                        } }}</title>
                    </rect>
                @endif
            @endfor
        @endfor

        @foreach ($monthLabels as $label)
            <text x="{{ ($weeks - 1 - $label['w']) * $step + $cell }}" y="10"
                  text-anchor="end" font-size="9" fill="var(--text-muted)">{{ $label['label'] }}</text>
        @endforeach
    </svg>
</div>

{{-- المفتاح: رمز مع كلّ لون دائمًا (2.16-ب) --}}
<div class="flex flex-wrap items-center gap-4 mt-3 text-xs" style="color: var(--text-muted)">
    <span class="inline-flex items-center gap-1">
        <span class="inline-block w-3 h-3 rounded" style="background: var(--surface-sunken)"></span> ○ {{ setting('streaks.heatmap.legend_idle', 'بلا نشاط') }}
    </span>
    <span class="inline-flex items-center gap-1">
        <span class="inline-block w-3 h-3 rounded" style="background: var(--color-brand-500)"></span> ● {{ setting('streaks.heatmap.legend_active', 'يوم نشط') }}
    </span>
    <span class="inline-flex items-center gap-1">
        <span class="inline-block w-3 h-3 rounded" style="background: var(--color-state-honor)"></span> ★ {{ setting('streaks.heatmap.legend_club', 'نادي الخامسة') }}
    </span>
    <span class="inline-flex items-center gap-1">
        <span class="inline-block w-3 h-3 rounded" style="background: var(--color-state-warn)"></span> ▲ {{ setting('streaks.heatmap.legend_freeze', 'يوم محميّ بدرع') }}
    </span>
</div>
