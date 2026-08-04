@php
    /** مبدّل الكيان: كلّ شيء يُقرأ داخل عضويّة — فلا سلطة عابرة للكيانات (12.2.1) */
@endphp

@if ($roots->count() > 1)
    <form method="get" action="{{ $action }}">
        @foreach (request()->except(['entity', 'page']) as $key => $value)
            @if (! is_array($value))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <select name="entity" onchange="this.form.submit()" aria-label="{{ setting('volunteer.org_entity_switcher.aria', 'مبدّل الكيان') }}"
                class="rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            @foreach ($roots as $option)
                <option value="{{ $option->id }}" @selected($root && $root->id === $option->id)>{{ $option->name_ar }}</option>
            @endforeach
        </select>
    </form>
@endif
