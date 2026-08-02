@php
    /**
     * قاموس أيقونات النوع (2.16-ج): مرسومة SVG بهويّة المنصّة — بلا أيّ مكتبة أيقونات.
     * سُمك خطّ موحّد 1.6 · زوايا مستديرة · شبكة 24 · وتدعم currentColor.
     */
    $size = $size ?? 20;
    $type = $type ?? 'pdf';
    $label = $label ?? '';
@endphp

<svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor"
     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
     @if ($label) role="img" aria-label="{{ $label }}" @endif>
    @switch($type)
        @case('course')
            {{-- تدريب: كتاب مفتوح --}}
            <path d="M12 6.5C10.4 5.2 8.4 4.8 6 5v12c2.4-.2 4.4.2 6 1.5 1.6-1.3 3.6-1.7 6-1.5V5c-2.4-.2-4.4.2-6 1.5Z"/>
            <path d="M12 6.5v12"/>
            @break

        @case('path')
            {{-- مسار: نقاط متّصلة صاعدة --}}
            <circle cx="6" cy="17.5" r="2"/>
            <circle cx="12" cy="12" r="2"/>
            <circle cx="18" cy="6.5" r="2"/>
            <path d="M7.6 16.1 10.4 13.4M13.6 10.6l2.8-2.7"/>
            @break

        @case('bundle')
            {{-- بندل: صندوق بشريط --}}
            <path d="M4 8.5 12 4.5l8 4v7L12 19.5l-8-4v-7Z"/>
            <path d="M4 8.5 12 12.5l8-4M12 12.5v7"/>
            @break

        @case('video')
            {{-- فيديو: شاشة بمثلّث تشغيل --}}
            <rect x="3.5" y="5.5" width="17" height="13" rx="2.5"/>
            <path d="m10.5 9.8 4.5 2.7-4.5 2.7V9.8Z"/>
            @break

        @case('audio')
            {{-- صوت: موجة نغمة --}}
            <path d="M9 15.5V6.8l8-1.3v8.6"/>
            <circle cx="7" cy="16.2" r="2.3"/>
            <circle cx="15" cy="14.6" r="2.3"/>
            @break

        @case('html')
            {{-- HTML: قوسا كود --}}
            <path d="m9 8.5-4 3.5 4 3.5M15 8.5l4 3.5-4 3.5M13.2 6l-2.4 12"/>
            @break

        @case('certificate')
            {{-- شهادة: وسام بشريط --}}
            <circle cx="12" cy="9.5" r="4.5"/>
            <path d="m9.4 13.4-1.1 6 3.7-2 3.7 2-1.1-6"/>
            @break

        @default
            {{-- PDF محميّ: ورقة بقفل --}}
            <path d="M14 3.5H7.5A1.5 1.5 0 0 0 6 5v14a1.5 1.5 0 0 0 1.5 1.5h9A1.5 1.5 0 0 0 18 19V7.5L14 3.5Z"/>
            <path d="M14 3.5v4h4"/>
            <rect x="9.5" y="12.5" width="5" height="4" rx="1"/>
            <path d="M10.8 12.5v-1.2a1.2 1.2 0 0 1 2.4 0v1.2"/>
    @endswitch
</svg>
