@php
    $rows = $data['languages'] ?? [];
    $field = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
@endphp

<div class="card p-4" data-step-panel="skills" hidden>
    <form data-step-form="skills" onsubmit="return false" data-repeat>
        <label class="block mb-4">
            <span class="block text-sm mb-1">{{ setting('cv.field.skills_label', 'أضف مهاراتك (افصل بفاصلة)') }}</span>
            <textarea name="data[skills]" rows="2" class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $field }}"
                      placeholder="{{ setting('cv.field.skills_placeholder', 'Excel, تحليل بيانات, مبيعات B2B') }}">{{ $data['skills'] ?? '' }}</textarea>
        </label>

        <span class="block text-sm mb-2">{{ setting('cv.field.languages_label', 'اللغات') }}</span>

        <div class="space-y-2" data-repeat-list data-sortable>
            @foreach ($rows as $i => $row)
                @include('cv.partials.row-language', ['i' => $i, 'row' => $row, 'field' => $field])
            @endforeach
        </div>

        <template>
            @include('cv.partials.row-language', ['i' => '__I__', 'row' => [], 'field' => $field])
        </template>

        <button type="button" data-repeat-add class="btn mt-3 rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--surface-sunken)">{{ setting('cv.languages.add_label', 'إضافة لغة أخرى') }}</button>
    </form>
</div>
