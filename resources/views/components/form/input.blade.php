@props(['name', 'label' => '', 'type' => 'text', 'value' => null, 'hint' => null])

<label class="block">
    <span class="block text-sm mb-1">{{ $label }}</span>
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
           value="{{ old($name, $value) }}"
           {{ $attributes->merge(['class' => 'w-full rounded-xl px-3 py-2 text-sm']) }}
           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    {{-- سطر واحد لكلّ شرح (2.15-أ-8) --}}
    @if ($hint)<span class="block text-xs mt-1" style="color: var(--text-muted)">{{ $hint }}</span>@endif
    @error($name)<span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>@enderror
</label>
