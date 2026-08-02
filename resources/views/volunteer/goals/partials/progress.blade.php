@php
    /**
     * بار الإنجاز بالصعود الآليّ للنِّسَب (24.4).
     * التركوازيّ هنا لون هويّة لا حالة — والحالة تُقرأ من الرقم والوسم بجواره (2.16).
     */
    $percent = max(0, min(100, (float) ($percent ?? 0)));
    $label = $label ?? null;
    $closed = (int) ($closed ?? 0);
@endphp

<div class="mt-2">
    <div class="flex items-center justify-between text-xs mb-1" style="color: var(--text-muted)">
        <span>{{ $label ?? 'الإنجاز' }}</span>
        <span class="font-semibold" style="color: var(--text)">{{ rtrim(rtrim(number_format($percent, 1), '0'), '.') }}%</span>
    </div>

    <div class="h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)"
         role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full motion-standard" style="width: {{ $percent }}%; background: var(--color-brand-500)"></div>
    </div>

    @if ($closed > 0)
        {{-- ⭐ المُغلَقة مستبعَدة من المقام وتُوسَم صراحةً كي لا تبدو النسبة مجمَّلة --}}
        <div class="mt-1 text-xs flex items-center gap-1" style="color: var(--color-state-idle)">
            <span aria-hidden="true">○</span>
            <span>{{ $closed }} مهمّة مُغلَقة — مستبعَدة من حساب النسبة</span>
        </div>
    @endif
</div>
