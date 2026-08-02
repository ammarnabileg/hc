@php
    $rows = $data['experience'] ?? [];
    $field = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
@endphp

<div class="card p-4" data-step-panel="experience" hidden>
    <form data-step-form="experience" onsubmit="return false" data-repeat>
        <div class="space-y-3" data-repeat-list>
            @foreach ($rows as $i => $row)
                @include('cv.partials.row-experience', ['i' => $i, 'row' => $row, 'field' => $field])
            @endforeach
        </div>

        <template>
            @include('cv.partials.row-experience', ['i' => '__I__', 'row' => [], 'field' => $field])
        </template>

        <button type="button" data-repeat-add class="btn mt-3 rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--surface-sunken)">{{ setting('cv.experience.add_label', 'إضافة خبرة عمل') }}</button>

        @if (empty($rows))
            <p class="text-xs mt-2" style="color: var(--text-muted)">{{ setting('cv.experience.empty_hint', 'ابدأ بآخر وظيفة اشتغلتها.') }}</p>
        @endif
    </form>
</div>
