@php
    /**
     * أيقونات مجال التعلّم — **مرسومة SVG بهويّة المنصّة، وممنوع أيّ مكتبة أيقونات** (2.16-ج).
     * سُمك خطّ موحّد 1.6 وشبكة 24 وتتبع currentColor، فتصلح على الفاتح والداكن معًا.
     */
    $name = $name ?? 'comment';
    $box = $box ?? 16;

    $paths = [
        'comment' => '<path d="M20 12a7 7 0 0 1-7 7H8l-4 3v-4.6A7 7 0 0 1 4 12a7 7 0 0 1 7-7h2a7 7 0 0 1 7 7z"/>',
        'reply' => '<path d="M10 9V5l-6 6 6 6v-4h4a5 5 0 0 1 5 5v1"/>',
        'heart' => '<path d="M12 20s-7-4.4-7-9.2A4 4 0 0 1 12 8a4 4 0 0 1 7-.8c0 .3 0 .5 0 .8C19 15.6 12 20 12 20z"/>',
        'eye-off' => '<path d="M3 3l18 18"/><path d="M10.6 6.3A9.6 9.6 0 0 1 12 6.2c5 0 9 5.8 9 5.8a17 17 0 0 1-3 3.5"/><path d="M6.3 8A17 17 0 0 0 3 12s4 5.8 9 5.8a8.6 8.6 0 0 0 3.5-.8"/><path d="M9.9 10.1a3 3 0 0 0 4.2 4.2"/>',
        'eye' => '<path d="M3 12s4-5.8 9-5.8S21 12 21 12s-4 5.8-9 5.8S3 12 3 12z"/><circle cx="12" cy="12" r="2.6"/>',
        'trash' => '<path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/><path d="M10 11v6M14 11v6"/>',
        'note' => '<path d="M6 3h9l5 5v13H6z"/><path d="M15 3v5h5"/><path d="M9 12h7M9 16h5"/>',
        'download' => '<path d="M12 4v10"/><path d="M8 11l4 4 4-4"/><path d="M5 19h14"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5.2l3.2 2"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'quiz' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 9h6M9 13h6M9 17h3"/>',
        'send' => '<path d="M4 12l16-8-6 16-2.5-6L4 12z"/>',
    ];
@endphp

<svg viewBox="0 0 24 24" width="{{ $box }}" height="{{ $box }}" fill="none" stroke="currentColor"
     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
     class="inline-block align-[-0.15em]">
    {!! $paths[$name] ?? $paths['comment'] !!}
</svg>
