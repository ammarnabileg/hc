@php
    $rows = $data['courses'] ?? [];
    $field = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
@endphp

{{-- 🎓 الدورات التدريبيّة (الدستور 9): دبلومات · شهادات · كورسات أونلاين --}}
<div class="card p-4" data-step-panel="courses" hidden>
    <form data-step-form="courses" onsubmit="return false" data-repeat>
        <div class="space-y-3" data-repeat-list data-sortable>
            @foreach ($rows as $i => $row)
                @include('cv.partials.row-course', ['i' => $i, 'row' => $row, 'field' => $field])
            @endforeach
        </div>

        <template>
            @include('cv.partials.row-course', ['i' => '__I__', 'row' => [], 'field' => $field])
        </template>

        <button type="button" data-repeat-add class="btn mt-3 rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--surface-sunken)">{{ setting('cv.courses.add_label', 'إضافة دورة جديدة') }}</button>

        @if (empty($rows))
            <p class="text-xs mt-2" style="color: var(--text-muted)">{{ setting('cv.courses.empty_hint', 'الدورات اللي خدتها بره المنصّة كمان بتتحسب.') }}</p>
        @endif
    </form>
</div>
