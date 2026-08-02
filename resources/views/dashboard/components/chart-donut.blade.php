@php
    /**
     * دونات إكمال المسار (14-ج) — SVG بيدنا، ولكلّ شريحة رمزٌ في المفتاح
     * فلا يحمل اللونُ المعنى وحده (2.16-ب).
     */
    $segments = array_values(array_filter($donut['segments'], fn ($s) => $s['value'] > 0));
    $sum = array_sum(array_map(fn ($s) => $s['value'], $segments));
    $radius = 54;
    $circumference = 2 * M_PI * $radius;
    $offset = 0;
@endphp

<section class="card p-4 min-w-0">
    <h3 class="font-bold text-sm">إكمال المسار</h3>

    <div class="mt-3 flex flex-wrap items-center gap-5">
        <div class="overflow-x-auto no-scrollbar">
            <svg viewBox="0 0 140 140" width="140" height="140" role="img"
                 aria-label="نسبة إكمال تدريباتك {{ $donut['percent'] }}٪">
                <title>إكمال المسار — {{ $donut['percent'] }}٪</title>

                <circle cx="70" cy="70" r="{{ $radius }}" fill="none" stroke="var(--surface-sunken)" stroke-width="16" />

                @foreach ($segments as $segment)
                    @php
                        $len = $sum > 0 ? round($circumference * $segment['value'] / $sum, 2) : 0;
                        $dashOffset = round(-$offset, 2);
                        $offset += $len;
                    @endphp
                    <circle cx="70" cy="70" r="{{ $radius }}" fill="none" stroke="{{ $segment['color'] }}"
                            stroke-width="16" stroke-dasharray="{{ $len }} {{ round($circumference - $len, 2) }}"
                            stroke-dashoffset="{{ $dashOffset }}" transform="rotate(-90 70 70)">
                        <title>{{ $segment['label'] }}: {{ $segment['value'] }}</title>
                    </circle>
                @endforeach

                <text x="70" y="64" text-anchor="middle" font-size="22" font-weight="800" fill="var(--text)">{{ $donut['percent'] }}%</text>
                <text x="70" y="84" text-anchor="middle" font-size="10" fill="var(--text-muted)">من الدروس</text>
            </svg>
        </div>

        <ul class="text-xs space-y-2">
            @foreach ($donut['segments'] as $segment)
                <li class="flex items-center gap-2">
                    <span aria-hidden="true" style="color: {{ $segment['color'] }}">{{ $segment['icon'] }}</span>
                    <span>{{ $segment['label'] }}</span>
                    <span style="color: var(--text-muted)">{{ $segment['value'] }}</span>
                </li>
            @endforeach
            <li style="color: var(--text-muted)">{{ $donut['caption'] }}</li>
        </ul>
    </div>
</section>
