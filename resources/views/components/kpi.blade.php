@props(['label' => '', 'value' => '', 'icon' => '', 'hint' => null, 'state' => null])

{{-- كارت KPI — والحدّ الأقصى أربعة في الشاشة (2.15-أ-3) --}}
<div class="card p-4 animate-fadeup">
    <div class="flex items-center justify-between">
        <span class="text-sm" style="color: var(--text-muted)">{{ $label }}</span>
        <span aria-hidden="true">{{ $icon }}</span>
    </div>
    {{-- عدّاد تصاعديّ (2.17-أ) — والرقم النهائيّ يظهر في كلّ الأحوال --}}
    <div class="mt-2 text-2xl font-extrabold" data-count-to="{{ $value }}">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 text-xs cursor-help" style="color: var(--text-muted)" title="{{ $hint }}">{{ $hint }}</div>
    @endif
    @if ($state)
        <div class="mt-2"><x-state-badge :state="$state" /></div>
    @endif
</div>
