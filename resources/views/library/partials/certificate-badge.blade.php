@php
    /** الشهادة وسامٌ على الرفّ لا كارتًا كباقي العناصر (20.1 — Peak-End) */
    $valid = $item['availability']['state'] === 'honor';
@endphp

<article class="card p-3 flex flex-col items-center text-center gap-2 animate-fadeup" data-library-item="certificate-badge">
    <span class="rounded-full p-3" style="background: {{ $valid ? 'color-mix(in srgb, var(--color-state-honor) 16%, transparent)' : 'var(--surface-sunken)' }}; color: {{ $valid ? 'var(--color-state-honor)' : 'var(--text-muted)' }}">
        @include('library.components.type-icon', ['type' => 'certificate', 'size' => 34])
    </span>

    <h3 class="text-sm font-semibold leading-6 line-clamp-2" title="{{ $item['title'] }}">{{ $item['title'] }}</h3>

    <x-state-badge :state="$item['availability']['state']" :label="$item['availability']['label']" />

    <a href="{{ $item['action_url'] }}" target="_blank" rel="noopener"
       class="btn w-full flex items-center justify-center rounded-xl px-3 py-2 text-sm font-semibold motion-standard"
       style="background: var(--color-brand-500); color: #04201c">{{ $item['action_label'] }}</a>
</article>
