@php
    /**
     * جيج درجة الالتزام — قوس SVG مرسوم بيدنا بلا أيّ مكتبة (دليل البناء 4).
     * المدى من `min` إلى `max` (من جدول العملات)، وعليه علامتان: الإنذار والمؤشّر الأحمر
     * (قيمتاهما من `rep_rule()` — لا رقم محروق).
     */
    $min = (float) ($min ?? -10);
    $max = (float) ($max ?? 10);
    $score = max($min, min($max, (float) ($score ?? 0)));
    $marks = collect($marks ?? []);
    $state = $state ?? 'ok';
    $badge = state_color($state);

    $w = 260; $h = 150; $cx = 130; $cy = 130; $r = 108;

    // زاوية من 180° (أقصى اليسار) إلى 0° — والقوس نصف دائرة
    $angle = fn ($v) => 180 - ((($v) - $min) / max(0.0001, $max - $min)) * 180;
    $point = function ($v, $radius) use ($angle, $cx, $cy) {
        $a = deg2rad($angle($v));
        return [round($cx + $radius * cos($a), 2), round($cy - $radius * sin($a), 2)];
    };

    [$sx, $sy] = $point($min, $r);
    [$ex, $ey] = $point($max, $r);
    [$px, $py] = $point($score, $r);

    $large = ($angle($min) - $angle($score)) > 180 ? 1 : 0;
    $needleEnd = $point($score, $r - 26);
@endphp

<div class="card p-4 text-center">
    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full max-w-xs mx-auto" style="height: {{ $h }}px"
         role="img" aria-label="درجة الالتزام {{ $score }} من {{ $max }}">
        {{-- القوس الخلفيّ --}}
        <path d="M {{ $sx }} {{ $sy }} A {{ $r }} {{ $r }} 0 0 1 {{ $ex }} {{ $ey }}"
              fill="none" stroke="var(--surface-sunken)" stroke-width="14" stroke-linecap="round" />

        {{-- القوس المملوء حتى الرقم الحاليّ --}}
        <path d="M {{ $sx }} {{ $sy }} A {{ $r }} {{ $r }} 0 {{ $large }} 1 {{ $px }} {{ $py }}"
              fill="none" stroke="var(--color-state-{{ $badge['color'] }})" stroke-width="14" stroke-linecap="round" />

        {{-- العلامتان: الإنذار والمؤشّر الأحمر --}}
        @foreach ($marks as $mark)
            @php [$mx1, $my1] = $point($mark['value'], $r + 10); [$mx2, $my2] = $point($mark['value'], $r - 10); @endphp
            <line x1="{{ $mx1 }}" y1="{{ $my1 }}" x2="{{ $mx2 }}" y2="{{ $my2 }}"
                  stroke="var(--color-state-{{ $mark['color'] ?? 'danger' }})" stroke-width="2" />
            @php [$lx, $ly] = $point($mark['value'], $r + 20); @endphp
            <text x="{{ $lx }}" y="{{ $ly }}" text-anchor="middle" font-size="10"
                  fill="var(--text-muted)">{{ $mark['label'] }}</text>
        @endforeach

        {{-- المؤشّر --}}
        <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $needleEnd[0] }}" y2="{{ $needleEnd[1] }}"
              stroke="var(--text)" stroke-width="3" stroke-linecap="round" />
        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="5" fill="var(--text)" />

        <text x="{{ $cx }}" y="{{ $cy - 24 }}" text-anchor="middle" font-size="30" font-weight="800"
              fill="var(--text)">{{ rtrim(rtrim(number_format($score, 2), '0'), '.') ?: '0' }}</text>
    </svg>

    <div class="flex items-center justify-center gap-2 text-xs" style="color: var(--text-muted)">
        <span>{{ (int) $min }}</span>
        <x-state-badge :state="$state" />
        <span>+{{ (int) $max }}</span>
    </div>
</div>
