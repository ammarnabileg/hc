@props(['columns' => null, 'label' => null])

@php
    /*
     | جدول بحدّ أعمدةٍ حقيقيّ (2.15-أ-5): الافتراضيّ 5–7 أعمدة من الإعداد
     | `ux.tables.default_columns`، والباقي **موجود ومخفيّ** يفتحه سويتش
     | «وضع متقدّم» أعلى الصفحة — لا حذف ولا فقدان بيانات.
     |
     | وعلى الموبايل: الغلاف يمنع أيّ تمرير أفقيّ للصفحة نفسها (2.15-ج)،
     | فالتمرير — إن لزم — داخل الجدول وحده لا في جسم الصفحة.
     */
    $cap = $columns !== null ? max(1, (int) $columns) : view_mode()->defaultColumns();
    $showAll = advanced_mode();
@endphp

<div class="w-full max-w-full overflow-x-auto" @if ($label) aria-label="{{ $label }}" @endif>
    <table {{ $attributes->merge(['class' => 'w-full text-sm']) }}
           @if (! $showAll) data-columns-cap="{{ $cap }}" @endif>
        {{ $slot }}
    </table>
</div>

@if (! $showAll)
    <p class="mt-2 text-[11px]" style="color: var(--text-muted)">
        بنعرض أهمّ {{ $cap }} أعمدة — «وضع متقدّم» أعلى الصفحة بيفتح الباقي.
    </p>
@endif
