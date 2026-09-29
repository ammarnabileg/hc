@props(['name', 'label' => '', 'type' => 'text', 'value' => null, 'hint' => null])

@php
    // يعمل أيضًا حين يُعرَض المكوّن خارج دورة الطلب (Partial في اختبار مثلًا)
    $hasError = isset($errors) && $errors->has($name);
    $errorId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $name).'-error';
@endphp

<label class="block">
    <span class="block text-sm mb-1">{{ $label }}</span>
    {{-- الخطأ مرتبطٌ بالحقل لقارئ الشاشة (aria-invalid · aria-describedby) لا سطرٌ أحمر بجواره فقط --}}
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
           value="{{ old($name, $value) }}"
           @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
           {{ $attributes->merge(['class' => 'w-full rounded-xl px-3 py-2 text-sm']) }}
           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    {{-- سطر واحد لكلّ شرح (2.15-أ-8) --}}
    @if ($hint)<span class="block text-xs mt-1" style="color: var(--text-muted)">{{ $hint }}</span>@endif
    @if ($hasError)
        <span id="{{ $errorId }}" class="field-error block mt-1">{{ $errors->first($name) }}</span>
    @endif
</label>
