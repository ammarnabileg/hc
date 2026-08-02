@php
    $rows = $data['education'] ?? [];
    $field = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
@endphp

<div class="card p-4" data-step-panel="education" hidden>
    <form data-step-form="education" onsubmit="return false" data-repeat>
        <div class="space-y-3" data-repeat-list data-sortable>
            @foreach ($rows as $i => $row)
                @include('cv.partials.row-education', ['i' => $i, 'row' => $row, 'field' => $field])
            @endforeach
        </div>

        <template>
            @include('cv.partials.row-education', ['i' => '__I__', 'row' => [], 'field' => $field])
        </template>

        <button type="button" data-repeat-add class="btn mt-3 rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--surface-sunken)">{{ setting('cv.education.add_label', 'إضافة تعليم جديد') }}</button>

        @if (empty($rows))
            <p class="text-xs mt-2" style="color: var(--text-muted)">{{ setting('cv.education.empty_hint', 'أضف أعلى مؤهّل وصلت له.') }}</p>
        @endif
    </form>
</div>
