@php
    /**
     * قيمة الحركة بإشارتها (24.5-ب: القيمة ± بلونها ورمزها).
     * اللون من القاموس المقفول (2.16) ومعه رمزٌ دائمًا، لأنّ اللون وحده لا يحمل المعنى.
     */
    $value = (float) ($value ?? 0);
    $decimals = (int) ($decimals ?? 0);
    $state = $value > 0 ? 'ok' : ($value < 0 ? 'danger' : 'idle');
    $s = state_color($state);
    $sign = $value > 0 ? '+' : ($value < 0 ? '−' : '');
@endphp

<span class="inline-flex items-center gap-1 font-bold whitespace-nowrap"
      style="color: var(--color-state-{{ $s['color'] }})">
    <span aria-hidden="true">{{ $s['icon'] }}</span>
    <span>{{ $sign.number_format(abs($value), $decimals) }}</span>
</span>
