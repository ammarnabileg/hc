@props(['size' => 64, 'label' => null])

{{--
  ⭐ ختم الإنجاز المشترك (الفكرة #9): شكلٌ هندسيّ واحد مستوحًى من دائرة الهويّة
  (النقطة الحمراء) يظهر في لحظة الإنجاز وفي بطاقة المشاركة، فترتبط لحظات الفخر
  بعلامةٍ تُتذكَّر. زخرفةٌ لا بديل عن كود التحقّق: الكود يبقى مكتوبًا بجواره دائمًا.
  SVG مرسوم هنا بلا أصول خارجيّة (2.16-ج)، ويُقرأ لقارئ الشاشة فقط حين يُعطى label.
--}}
@php
    $size = (int) $size;
    $ticks = 24;
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" class="achievement-stamp" data-achievement-stamp
     @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif
     {{ $attributes->except(['size', 'label']) }}>
    {{-- الطوق الخارجيّ المسنَّن: 24 سنًّا بزوايا متساوية --}}
    <g stroke="var(--brand)" stroke-width="2" stroke-linecap="round">
        @for ($i = 0; $i < $ticks; $i++)
            @php $a = deg2rad($i * 360 / $ticks); @endphp
            <line x1="{{ round(32 + 27 * cos($a), 2) }}" y1="{{ round(32 + 27 * sin($a), 2) }}"
                  x2="{{ round(32 + 30 * cos($a), 2) }}" y2="{{ round(32 + 30 * sin($a), 2) }}" />
        @endfor
    </g>
    <circle cx="32" cy="32" r="23" fill="none" stroke="var(--brand)" stroke-width="1.5" />
    {{-- النقطة الحمراء: قلب الهويّة --}}
    <circle cx="32" cy="32" r="16" fill="var(--brand)" />
    <path d="m24 32 5.5 5.5L40 27" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
</svg>
