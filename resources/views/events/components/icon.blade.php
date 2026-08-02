@php
    /**
     * أيقونات المجال — **مرسومة SVG بهويّة المنصّة، وممنوع أيّ مكتبة أيقونات** (2.16-ج).
     * سُمك خطّ موحّد 1.6 وشبكة 24 وتدعم currentColor.
     */
    $name = $name ?? 'link';
    $box = $box ?? 16;

    $paths = [
        'online' => '<rect x="3" y="5" width="14" height="11" rx="2"/><path d="M17 9l4-2v10l-4-2"/>',
        'offline' => '<path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'hybrid' => '<circle cx="8" cy="12" r="5"/><circle cx="16" cy="12" r="5"/>',
        'pin' => '<path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'link' => '<path d="M10 13a4 4 0 0 0 6 .5l2-2a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 11a4 4 0 0 0-6-.5l-2 2A4 4 0 0 0 11.7 18l1-1"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'ticket' => '<path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8z"/><path d="M14 6v12" stroke-dasharray="2 2"/>',
        'share' => '<circle cx="6" cy="12" r="2.5"/><circle cx="17" cy="6" r="2.5"/><circle cx="17" cy="18" r="2.5"/><path d="M8.3 10.8l6.4-3.5M8.3 13.2l6.4 3.5"/>',
        'copy' => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/>',
        'certificate' => '<circle cx="12" cy="10" r="5"/><path d="M9 14.5L8 21l4-2 4 2-1-6.5"/>',
        'gift' => '<rect x="3" y="9" width="18" height="12" rx="2"/><path d="M3 13h18M12 9v12"/><path d="M12 9S9.5 4 7.5 5.5 9 9 12 9zM12 9s2.5-5 4.5-3.5S15 9 12 9z"/>',
        'users' => '<circle cx="9" cy="9" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><path d="M16 6.4A3.2 3.2 0 0 1 16 12M17 19a5.5 5.5 0 0 0-2-4.3"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5.2l3.2 2"/>',
        'key' => '<circle cx="8" cy="12" r="3.5"/><path d="M11.5 12H21M18 12v3M15 12v2.5"/>',
    ];
@endphp

<svg viewBox="0 0 24 24" width="{{ $box }}" height="{{ $box }}" fill="none" stroke="currentColor"
     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
     class="inline-block align-[-0.15em]">
    {!! $paths[$name] ?? $paths['link'] !!}
</svg>
