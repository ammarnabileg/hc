@props(['tabs' => [], 'current' => null])

{{-- تابات Sticky تحت الهيدر بـ12px — وعلى الموبايل رقائق أفقيّة متمرّرة (2.15-ج) --}}
{{-- `min-w-0` على الحاويتين: بدونه لا يصغر الصندوق تحت مقاس رقائقه فيمدّ الصفحة --}}
<div class="min-w-0 sticky-bar -mx-4 md:mx-0 px-4 md:px-0 py-2 mb-4" style="background: var(--surface)">
    <div class="min-w-0 flex gap-2 overflow-x-auto no-scrollbar">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['url'] ?? '#' }}"
               class="shrink-0 rounded-full px-4 py-2 text-sm motion-standard"
               style="{{ ($current === ($tab['key'] ?? null))
                    ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                    : 'background: var(--surface-raised); color: var(--text)' }}">
                {{ $tab['label'] }}
                {{-- isset لا empty: empty(0) === true فيُخفي عدّاد الصفر رغم أنّ 0 عددٌ صحيحٌ مقصود (20.1: «كلٌّ برقمه» دومًا) --}}
                @if (isset($tab['count']))
                    <span class="opacity-70">({{ $tab['count'] }})</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
