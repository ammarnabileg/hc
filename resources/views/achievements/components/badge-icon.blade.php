@php
    /** أيقونة الشارة — SVG مرسومة بهويّة المنصّة، وممنوع أيّ مكتبة أيقونات (2.16-ج). */
    $size = $size ?? 40;
    $unlocked = $unlocked ?? false;
@endphp

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" role="img"
     aria-label="{{ $unlocked ? 'شارة مفتوحة' : 'شارة مقفولة' }}"
     fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    {{-- قرص الميدالية --}}
    <circle cx="12" cy="9.2" r="5.8" />
    {{-- الشريطان --}}
    <path d="M8.2 13.9 6.1 20.8l5.9-2.8 5.9 2.8-2.1-6.9" />
    {{-- نجمة الوسط: ممتلئة للمفتوح، مفرَّغة للمقفول --}}
    <path d="m12 6.1 1.05 2.13 2.35.34-1.7 1.66.4 2.34L12 11.46l-2.1 1.11.4-2.34-1.7-1.66 2.35-.34Z"
          @if ($unlocked) fill="currentColor" @endif />

    @unless ($unlocked)
        {{-- قفل صغير: الشرط مكتوب بجواره صراحةً في الكارت — لا ألغاز --}}
        <path d="M18.6 17.4h3.2v3.4h-3.2z" />
        <path d="M19.4 17.4v-1a.8.8 0 0 1 1.6 0v1" />
    @endunless
</svg>
