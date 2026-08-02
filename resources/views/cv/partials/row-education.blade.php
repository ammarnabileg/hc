<div class="card p-3 grid gap-2 sm:grid-cols-2" data-repeat-row draggable="true">
    <input type="text" name="data[education][{{ $i }}][degree]" value="{{ $row['degree'] ?? '' }}"
           placeholder="{{ setting('cv.field.degree_label', 'الدرجة العلميّة') }}"
           class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <input type="text" name="data[education][{{ $i }}][institution]" value="{{ $row['institution'] ?? '' }}"
           placeholder="{{ setting('cv.field.institution_label', 'المؤسّسة التعليميّة') }}"
           class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <input type="text" name="data[education][{{ $i }}][major]" value="{{ $row['major'] ?? '' }}"
           placeholder="{{ setting('cv.field.major_label', 'التخصّص') }}"
           class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <input type="text" name="data[education][{{ $i }}][gpa]" value="{{ $row['gpa'] ?? '' }}"
           placeholder="{{ setting('cv.field.gpa_label', 'المعدّل (اختياريّ)') }}"
           class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <div class="flex items-center gap-2 sm:col-span-2">
        <input type="text" name="data[education][{{ $i }}][from]" value="{{ $row['from'] ?? '' }}"
               placeholder="{{ setting('cv.field.from_label', 'من') }}"
               class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $field }}">
        <input type="text" name="data[education][{{ $i }}][to]" value="{{ $row['to'] ?? '' }}"
               placeholder="{{ setting('cv.field.to_label', 'إلى') }}"
               class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $field }}">
    </div>

    <div class="sm:col-span-2 flex items-center justify-between">
        <label class="flex items-center gap-2 text-xs" style="color: var(--text-muted)">
            <input type="checkbox" name="data[education][{{ $i }}][current]" value="1" @checked(! empty($row['current']))>
            {{ setting('cv.until_now_label', 'حتى الآن') }}
        </label>
        <button type="button" data-repeat-remove class="text-sm rounded-lg px-3 py-1.5"
                style="background: var(--surface-sunken); color: var(--text-muted)"
                aria-label="{{ setting('cv.row.remove_label', 'حذف') }}">✕</button>
    </div>
</div>
