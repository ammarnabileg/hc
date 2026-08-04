@php
    /**
     * QR مرسوم SVG من مصفوفة مولّدنا الداخليّ (8.1) — بلا أيّ مكتبة خارجيّة،
     * ويفتح **صفحة التحقّق** فلا يمثّلنا أحدٌ ببطاقة قديمة (13.4-ر-ج).
     */
    $modules = count($qr);
    $quiet = (int) setting('volunteer_card.qr.quiet_modules', 2);
    $side = $modules + $quiet * 2;
@endphp

<svg viewBox="0 0 {{ $side }} {{ $side }}" width="{{ $size ?? 108 }}" height="{{ $size ?? 108 }}"
     shape-rendering="crispEdges" role="img" aria-label="{{ setting('volunteer_card.qr.aria_label_1', 'امسح للتحقّق من البطاقة') }}">
    <title>{{ setting('volunteer_card.qr.text_1', 'امسح للتحقّق من البطاقة') }}</title>
    <rect width="{{ $side }}" height="{{ $side }}" fill="#ffffff" />
    @foreach ($qr as $y => $row)
        @foreach ($row as $x => $dark)
            @if ($dark)
                <rect x="{{ $x + $quiet }}" y="{{ $y + $quiet }}" width="1" height="1" fill="#000000" />
            @endif
        @endforeach
    @endforeach
</svg>
