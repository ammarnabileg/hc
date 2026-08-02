@props(['tabs' => [], 'current' => null])

{{-- تابات Sticky تحت الهيدر بـ12px — وعلى الموبايل رقائق أفقيّة متمرّرة (2.15-ج) --}}
<div class="sticky-bar -mx-4 md:mx-0 px-4 md:px-0 py-2 mb-4" style="background: var(--surface)">
    <div class="flex gap-2 overflow-x-auto no-scrollbar">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['url'] ?? '#' }}"
               class="shrink-0 rounded-full px-4 py-2 text-sm motion-standard"
               style="{{ ($current === ($tab['key'] ?? null))
                    ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                    : 'background: var(--surface-raised); color: var(--text)' }}">
                {{ $tab['label'] }}
                @if (! empty($tab['count']))
                    <span class="opacity-70">({{ $tab['count'] }})</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
