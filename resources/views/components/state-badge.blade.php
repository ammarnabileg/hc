@props(['state' => 'idle', 'label' => null])

@php
    // قاموس الحالة (2.16): لون + رمز دائمًا — واللون وحده لا يحمل المعنى
    $s = state_color($state);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs']) }}
      style="background: color-mix(in srgb, var(--color-state-{{ $s['color'] }}) 15%, transparent);
             color: var(--color-state-{{ $s['color'] }})">
    <span aria-hidden="true">{{ $s['icon'] }}</span>
    <span>{{ $label ?? $s['label'] }}</span>
</span>
