@php
    /**
     * كارت KPI إداريّ: الرقم + سهم ▲▼ ونسبة التغيّر + **تلوين صحّة المؤشّر** (12.3-2/18).
     * واللون لا يحمل المعنى وحده — معه رمز دائمًا من قاموس 2.16.
     */
    $delta = $card['delta'] ?? null;
    $arrow = $delta === null ? '' : ($delta > 0 ? '▲' : ($delta < 0 ? '▼' : '▬'));
@endphp

<div class="card p-4 animate-fadeup min-w-[13rem] sm:min-w-0">
    <div class="flex items-center justify-between">
        <span class="text-sm" style="color: var(--text-muted)">{{ $card['label'] }}</span>
        <span aria-hidden="true">{{ $card['icon'] }}</span>
    </div>

    {{-- عدّاد تصاعديّ — والرقم النهائيّ يظهر في كلّ الأحوال (2.17-أ) --}}
    <div class="mt-2 text-2xl font-extrabold" data-count-to="{{ $card['value'] }}">{{ number_format($card['value']) }}</div>

    <div class="mt-2 flex items-center gap-2 flex-wrap">
        @if ($compare && $delta !== null)
            {{-- الأرقام الثانويّة بالـHover لا بمساحة دائمة (2.15-د) --}}
            <span class="text-xs cursor-help" style="color: var(--text-muted)"
                  title="الفترة السابقة: {{ number_format($card['previous']) }}">
                {{ $arrow }} {{ abs($delta) }}%
            </span>
        @endif

        @if (! empty($card['state']))
            <x-state-badge :state="$card['state']" :label="match ($card['state']) {
                'ok' => 'صاعد',
                'danger' => 'هابط',
                default => 'ثابت',
            }" />
        @endif
    </div>

    <div class="mt-1 text-xs" style="color: var(--text-muted)">{{ $card['hint'] }}</div>
</div>
