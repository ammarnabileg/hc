@php
    /**
     * أيقونات SVG بهويّة المنصّة — **بلا أيّ مكتبة أيقونات جاهزة** (2.16-ج).
     * $name: اسم الأيقونة · $size: القياس بالبكسل (الافتراضيّ 20)
     */
    $size = $size ?? 20;
    $paths = [
        'course' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H11v16H5.5A1.5 1.5 0 0 1 4 18.5z"/><path d="M20 5.5A1.5 1.5 0 0 0 18.5 4H13v16h5.5a1.5 1.5 0 0 0 1.5-1.5z"/>',
        'path' => '<path d="M6 4v6a3 3 0 0 0 3 3h6a3 3 0 0 1 3 3v4"/><circle cx="6" cy="4" r="1.6"/><circle cx="18" cy="20" r="1.6"/>',
        'article' => '<path d="M5 4h11l3 3v13H5z"/><path d="M8 10h8M8 14h8M8 18h5"/>',
        'event' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'free' => '<path d="M12 3.5 14.4 9l6 .5-4.6 3.9 1.4 5.9L12 16.2 6.8 19.3l1.4-5.9L3.6 9.5 9.6 9z"/>',
        'crown' => '<path d="M4 17h16M4 17 3 8l5 3.5L12 5l4 6.5L21 8l-1 9z"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'arrow' => '<path d="M14 6l-6 6 6 6"/>',
        'envelope' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="m3.5 7.5 8.5 6 8.5-6"/>',
        'ticket' => '<path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1.5a2.5 2.5 0 0 0 0 5V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-1.5a2.5 2.5 0 0 0 0-5z"/><path d="M13 6v12"/>',
        'top' => '<path d="M12 19V6M6 12l6-6 6 6"/>',
        'spark' => '<path d="M12 4v4M12 16v4M4 12h4M16 12h4M6.5 6.5 9 9M15 15l2.5 2.5M17.5 6.5 15 9M9 15l-2.5 2.5"/>',
    ];
@endphp

<svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor"
     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0">
    {!! $paths[$name] ?? $paths['spark'] !!}
</svg>
