@props(['label' => '', 'value' => '', 'icon' => '', 'hint' => null, 'state' => null])

@php
    /*
     | كارت KPI — والحدّ الأقصى أربعة في الشاشة (2.15-أ-3).
     |
     | ⭐ `icon` صار **اسمًا من قاموس الأيقونات المقفول** (2.16-ج) لا إيموجي:
     | الإيموجي يرسمه خطّ نظام التشغيل فلا يتبع `currentColor` ولا سُمك الخطّ
     | ويختلف شكله بين المنصّات. وما لم يكن اسمًا لاتينيًّا (كرموز الحالة
     | ●▲◉○ المنصوصة في 2.16-ب) يُعرَض كما هو فلا تنكسر شاشةٌ قديمة.
     */
    $isDictionaryIcon = is_string($icon) && $icon !== '' && preg_match('/^[a-z][a-z0-9_-]*$/', $icon) === 1;
@endphp

<div class="card p-4 animate-fadeup">
    <div class="flex items-center justify-between">
        <span class="text-sm" style="color: var(--text-muted)">{{ $label }}</span>
        <span aria-hidden="true" style="color: var(--color-brand-500)">
            @if ($isDictionaryIcon)
                <x-icon :name="$icon" size="18" />
            @else
                {{ $icon }}
            @endif
        </span>
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
