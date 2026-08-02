@php
    /**
     * حقل Override لحرب: الحقل الفارغ = «اتبع العامّ»،
     * والقيمة العامّة تظهر **Placeholder** لا قيمةً محفوظة (12.10-ج).
     */
    $type = $type ?? 'number';
    $isOverride = $value !== null && $value !== '';
    $value = is_array($value) ? implode(',', $value) : $value;
@endphp

<label class="text-xs">
    {{ $label }}
    @if ($isOverride)
        <span class="rounded-full px-1.5"
              style="background: color-mix(in srgb, var(--color-state-warn) 15%, transparent); color: var(--color-state-warn)">Override</span>
    @endif

    <input type="{{ $type === 'number' ? 'number' : 'text' }}" step="any"
           name="{{ $section }}[{{ $key }}]" value="{{ $value }}"
           placeholder="{{ $placeholder }}" @disabled($locked ?? false)
           class="w-full rounded-lg px-2 py-1.5 mt-1"
           style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
</label>
