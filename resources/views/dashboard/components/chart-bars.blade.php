@php
    /**
     * بارات التذاكر: مكتسب مقابل مصروف (7.1 · 14-ج) — SVG بيدنا.
     * المصروف بنمطٍ مخطّط لا بلونٍ فقط، فالشكل يفرّق حتى بلا ألوان (2.16-ب).
     */
    $groups = $bars;
    $count = max(1, count($groups));
    $longest = max(array_map(fn ($g) => mb_strlen((string) $g['label']), $groups) ?: [3]);
    $groupW = max(54, (int) ceil($longest * 5.2) + 14);
    $barW = 18;
    $barsH = 190;
    $padTop = 14;
    $padBottom = 40;
    $gutter = 40;
    $innerH = $barsH - $padTop - $padBottom;
    $barsW = max(300, $count * $groupW + $gutter + 10);
    $maxValue = max(1, max(array_merge(
        array_map(fn ($g) => (int) $g['earned'], $groups),
        array_map(fn ($g) => (int) $g['spent'], $groups),
    )));
    $totalEarned = array_sum(array_map(fn ($g) => (int) $g['earned'], $groups));
    $totalSpent = array_sum(array_map(fn ($g) => (int) $g['spent'], $groups));
@endphp

<section class="card p-4 min-w-0">
    <div class="flex items-baseline justify-between gap-2">
        <h3 class="font-bold text-sm">التذاكر: مكتسب ومصروف</h3>
        <span class="text-xs" style="color: var(--text-muted)">+{{ number_format($totalEarned) }} · −{{ number_format($totalSpent) }}</span>
    </div>

    <div class="mt-3 min-w-0 overflow-x-auto no-scrollbar">
        <svg viewBox="0 0 {{ $barsW }} {{ $barsH }}" width="{{ $barsW }}" height="{{ $barsH }}" role="img"
             aria-label="التذاكر المكتسبة مقابل المصروفة خلال المدى المختار">
            <title>التذاكر: مكتسب ومصروف</title>

            <defs>
                <pattern id="spentPattern" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                    <rect width="6" height="6" fill="var(--surface-sunken)" />
                    <line x1="0" y1="0" x2="0" y2="6" stroke="var(--color-state-idle)" stroke-width="3" />
                </pattern>
            </defs>

            @foreach ([0, 0.5, 1] as $ratio)
                @php $gy = round($padTop + $innerH * $ratio, 2); @endphp
                <line x1="10" y1="{{ $gy }}" x2="{{ $barsW - $gutter }}" y2="{{ $gy }}" stroke="var(--border)" stroke-width="1" />
                <text x="{{ $barsW - $gutter + 6 }}" y="{{ $gy + 4 }}" font-size="10" fill="var(--text-muted)">
                    {{ (int) round($maxValue * (1 - $ratio)) }}
                </text>
            @endforeach

            @foreach ($groups as $i => $group)
                @php
                    // المجموعات تجري من اليمين لليسار موافقةً لاتّجاه القراءة
                    $gx = $barsW - $gutter - ($i + 1) * $groupW + 6;
                    $earnedH = round($innerH * (int) $group['earned'] / $maxValue, 2);
                    $spentH = round($innerH * (int) $group['spent'] / $maxValue, 2);
                @endphp

                <rect x="{{ $gx + $barW + 4 }}" y="{{ round($padTop + $innerH - $earnedH, 2) }}"
                      width="{{ $barW }}" height="{{ max(1, $earnedH) }}" rx="3" fill="var(--color-brand-500)">
                    <title>{{ $group['label'] }} — مكتسب: {{ $group['earned'] }}</title>
                </rect>

                <rect x="{{ $gx }}" y="{{ round($padTop + $innerH - $spentH, 2) }}"
                      width="{{ $barW }}" height="{{ max(1, $spentH) }}" rx="3" fill="url(#spentPattern)">
                    <title>{{ $group['label'] }} — مصروف: {{ $group['spent'] }}</title>
                </rect>

                <text x="{{ $gx + $barW + 2 }}" y="{{ $barsH - 22 }}" font-size="9" text-anchor="middle" fill="var(--text-muted)">
                    {{ $group['label'] }}
                </text>
            @endforeach
        </svg>
    </div>

    <ul class="mt-2 flex flex-wrap gap-3 text-xs" style="color: var(--text-muted)">
        <li><span aria-hidden="true" style="color: var(--color-brand-500)">▮</span> مكتسب</li>
        <li><span aria-hidden="true" style="color: var(--color-state-idle)">▤</span> مصروف</li>
    </ul>

    {{--
      ⭐ **الميزان المنغلق** (ن0-2): على اللوحة ثلاثة أرقامٍ للتذاكر — رصيدٌ في
      الـKPI، ومكتسبٌ في الرادار، وحركةُ المدى هنا. وهي **ثلاثة مقادير** لا ثلاث
      إجابات لسؤالٍ واحد؛ فيقولها هذا السطر صراحةً بحسابٍ يقرأه المستخدم بعينه:
      **مكتسب − مصروف = رصيد**. وبلا هذا السطر تُقرَأ الأرقام تناقضًا.
    --}}
    @isset ($sheet)
        <p class="mt-2 text-xs tabular-nums" style="color: var(--text-muted)">
            {{ str_replace(
                [':earned', ':spent', ':balance'],
                [number_format($sheet['earned']), number_format($sheet['spent']), number_format($sheet['balance'])],
                (string) setting('dashboard.tickets.sheet_label', 'الميزان: مكتسب :earned − مصروف :spent = رصيد :balance'),
            ) }}
        </p>
    @endisset
</section>
