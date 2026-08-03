@php
    /**
     * التسجيلات والمبيعات عبر الوقت (12.3) — **مرسومة SVG بأيدينا بلا أيّ مكتبة رسوم خارجيّة**.
     * والزمن يجري من اليمين لليسار موافقةً لاتّجاه القراءة (RTL).
     */
    $n = max(1, count($points));
    $h = 200;
    $padTop = 16;
    $padBottom = 34;
    $gutter = 48;   // محور القيم على اليمين (جهة البداية في RTL)
    $padLeft = 14;
    $w = max(340, $n * 28 + $gutter + $padLeft);
    $innerW = $w - $gutter - $padLeft;
    $innerH = $h - $padTop - $padBottom;

    $series = [
        ['key' => 'signups', 'label' => 'مسجّلون', 'color' => 'var(--color-brand-500)'],
        ['key' => 'sales', 'label' => 'مبيعات', 'color' => 'var(--color-state-honor)'],
    ];

    $max = 1;
    foreach ($points as $point) {
        foreach ($series as $line) {
            $max = max($max, (int) $point[$line['key']]);
        }
    }

    $px = fn ($i) => round($padLeft + ($n <= 1 ? $innerW / 2 : $innerW * (1 - $i / ($n - 1))), 2);
    $py = fn ($v) => round($padTop + $innerH * (1 - $v / $max), 2);
    $every = (int) ceil($n / 6);
@endphp

<section class="card p-4 min-w-0">
    <div class="flex items-baseline justify-between gap-2 flex-wrap">
        <h3 class="font-bold text-sm">الحركة عبر الوقت</h3>
        <div class="flex items-center gap-3 text-xs" style="color: var(--text-muted)">
            @foreach ($series as $line)
                <span class="inline-flex items-center gap-1">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:9999px;background:{{ $line['color'] }}"></span>
                    {{ $line['label'] }}
                </span>
            @endforeach
        </div>
    </div>

    {{-- الرسم داخل حاوية متمرّرة أفقيًّا فلا تمرير أفقيّ للصفحة (2.15-ج) --}}
    <div class="mt-3 min-w-0 overflow-x-auto no-scrollbar">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" width="{{ $w }}" height="{{ $h }}" style="min-width: 100%"
             role="img" aria-label="التسجيلات والمبيعات خلال المدى المختار">
            <title>الحركة عبر الوقت</title>

            @foreach ([0, 0.5, 1] as $ratio)
                @php $gy = round($padTop + $innerH * $ratio, 2); @endphp
                <line x1="{{ $padLeft }}" y1="{{ $gy }}" x2="{{ $padLeft + $innerW }}" y2="{{ $gy }}"
                      stroke="var(--border)" stroke-width="1" />
                <text x="{{ $padLeft + $innerW + 6 }}" y="{{ $gy + 4 }}" font-size="10" fill="var(--text-muted)">
                    {{ number_format((int) round($max * (1 - $ratio))) }}
                </text>
            @endforeach

            @foreach ($series as $line)
                @php
                    $d = [];
                    foreach ($points as $i => $point) {
                        $d[] = ($i === 0 ? 'M' : 'L').$px($i).' '.$py((int) $point[$line['key']]);
                    }
                @endphp
                <path d="{{ implode(' ', $d) }}" fill="none" stroke="{{ $line['color'] }}" stroke-width="2"
                      stroke-linejoin="round" stroke-linecap="round" />

                @foreach ($points as $i => $point)
                    @if ((int) $point[$line['key']] > 0)
                        <circle cx="{{ $px($i) }}" cy="{{ $py((int) $point[$line['key']]) }}" r="2.5" fill="{{ $line['color'] }}">
                            <title>{{ $point['label'] }} — {{ $line['label'] }}: {{ number_format((int) $point[$line['key']]) }}</title>
                        </circle>
                    @endif
                @endforeach
            @endforeach

            @foreach ($points as $i => $point)
                @if ($i % $every === 0 || $i === $n - 1)
                    <text x="{{ $px($i) }}" y="{{ $h - 12 }}" font-size="10" text-anchor="middle" fill="var(--text-muted)">
                        {{ $point['short'] }}
                    </text>
                @endif
            @endforeach
        </svg>
    </div>
</section>
