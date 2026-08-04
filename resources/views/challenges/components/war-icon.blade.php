@php
    /**
     * أيقونات الحروب — SVG بهويّة المنصّة ومرسومة بخطّ موحّد،
     * وممنوع أيّ مكتبة أيقونات جاهزة (2.16-ج). تدعم currentColor.
     */
    $type = $type ?? 'default';
    $size = $size ?? 44;
@endphp

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" role="img"
     aria-label="{{ $label ?? setting('challenges.war_icon.default_aria', 'أيقونة الحرب') }}"
     fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    @switch($type)
        @case('knowledge')
            {{-- سيفان متقاطعان: حرب المعلومات --}}
            <path d="M20 4v3.2L12.8 14.4 9.6 11.2 16.8 4H20Z" />
            <path d="M4 4v3.2l7.2 7.2 3.2-3.2L7.2 4H4Z" />
            <path d="m9.4 15 -2.6 2.6" />
            <path d="m14.6 15 2.6 2.6" />
            <path d="M4.8 17.6 7.6 20.4" />
            <path d="M19.2 17.6 16.4 20.4" />
            @break

        @case('focus')
            {{-- دوائر تركيز متمركزة: حرب التركيز --}}
            <circle cx="12" cy="12" r="8.5" />
            <circle cx="12" cy="12" r="4.5" />
            <circle cx="12" cy="12" r="1.1" fill="currentColor" stroke="none" />
            <path d="M12 1.8v2M12 20.2v2M1.8 12h2M20.2 12h2" />
            @break

        @case('survival')
            {{-- جمجمة: حرب البقاء --}}
            <path d="M12 3c-4.4 0-8 3.3-8 7.4 0 2.5 1.3 4.7 3.4 6v2.1c0 .8.7 1.5 1.5 1.5h6.2c.8 0 1.5-.7 1.5-1.5v-2.1c2.1-1.3 3.4-3.5 3.4-6C20 6.3 16.4 3 12 3Z" />
            <circle cx="9.1" cy="10.8" r="1.7" />
            <circle cx="14.9" cy="10.8" r="1.7" />
            <path d="M12 13.6v2.2" />
            <path d="M10.2 20.5v-2.2M13.8 20.5v-2.2" />
            @break

        @case('estimation')
            {{-- هدف وسهم: حرب التقدير --}}
            <circle cx="10.8" cy="13.2" r="8" />
            <circle cx="10.8" cy="13.2" r="4" />
            <circle cx="10.8" cy="13.2" r="0.9" fill="currentColor" stroke="none" />
            <path d="m10.8 13.2 8.4-8.4" />
            <path d="M14.6 4.4h4.8v4.8" />
            @break

        @default
            {{-- درع: التحدّي العامّ --}}
            <path d="M12 2.8 4.8 5.6v5.6c0 4.2 2.9 8 7.2 10.1 4.3-2.1 7.2-5.9 7.2-10.1V5.6L12 2.8Z" />
            <path d="m8.9 11.9 2.3 2.3 4.5-4.6" />
    @endswitch
</svg>
