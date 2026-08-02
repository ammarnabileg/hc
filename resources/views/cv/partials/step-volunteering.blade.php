@php
    $rows = $data['volunteering'] ?? [];
    $field = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
@endphp

{{-- 💖 الخبرة التطوّعيّة (الدستور 9): منظمات · عمل مجتمعيّ · مبادرات --}}
<div class="card p-4" data-step-panel="volunteering" hidden>
    <form data-step-form="volunteering" onsubmit="return false" data-repeat>
        <div class="space-y-3" data-repeat-list data-sortable>
            @foreach ($rows as $i => $row)
                @include('cv.partials.row-volunteering', ['i' => $i, 'row' => $row, 'field' => $field])
            @endforeach
        </div>

        <template>
            @include('cv.partials.row-volunteering', ['i' => '__I__', 'row' => [], 'field' => $field])
        </template>

        <button type="button" data-repeat-add class="btn mt-3 rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--surface-sunken)">{{ setting('cv.volunteering.add_label', 'إضافة تجربة تطوّعيّة') }}</button>

        @if (empty($rows))
            <p class="text-xs mt-2" style="color: var(--text-muted)">{{ setting('cv.volunteering.empty_hint', 'أيّ مبادرة أو عمل مجتمعيّ بيفرق — سجّله.') }}</p>
        @endif
    </form>
</div>
