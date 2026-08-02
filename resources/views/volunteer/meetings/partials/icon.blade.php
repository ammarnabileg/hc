@props([])

@php
    /**
     * قاموس أيقونات مقفول (2.16-ج): أيقونة واحدة لكلّ مفهوم،
     * مرسومة SVG بهويّة المنصّة — بلا أيّ مكتبة أيقونات جاهزة،
     * وبسُمك خطّ موحّد وشبكة 24 وتدعم currentColor.
     */
    $name = $name ?? 'meeting';
    $size = $size ?? 16;
    $paths = [
        'meeting' => '<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'transaction' => '<path d="M4 8h13l-3-3M20 16H7l3 3"/>',
        'entity' => '<path d="M4 20V9l8-5 8 5v11"/><path d="M10 20v-6h4v6"/>',
        'objection' => '<path d="M12 4 3 20h18L12 4z"/><path d="M12 10v4M12 17h.01"/>',
        'escalation' => '<path d="M12 20V5M6 11l6-6 6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'attachment' => '<path d="M14 5 7.5 11.5a3.5 3.5 0 0 0 5 5L19 10a5.5 5.5 0 0 0-8-7.5L5 8.5"/>',
        'pin' => '<path d="M12 3v8M8 11h8l2 5H6l2-5zM12 16v5"/>',
        'up' => '<path d="M12 19V6M6 12l6-6 6 6"/>',
        'down' => '<path d="M12 5v13M6 12l6 6 6-6"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 6 .5l2-2a4 4 0 0 0-6-6l-1 1"/><path d="M14 10a4 4 0 0 0-6-.5l-2 2a4 4 0 0 0 6 6l1-1"/>',
        'download' => '<path d="M12 4v10M8 11l4 4 4-4M5 19h14"/>',
    ];
@endphp

<svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor"
     stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
     class="inline-block shrink-0 align-middle">
    {!! $paths[$name] ?? $paths['meeting'] !!}
</svg>
