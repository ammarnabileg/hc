<div class="card p-3 grid gap-2 sm:grid-cols-2" data-repeat-row draggable="true">
    <div class="sm:col-span-2 flex items-center gap-2 text-xs" style="color: var(--text-muted)">
        {{-- مقبض السحب لإعادة الترتيب (9) — أيقونة مرسومة بلا مكتبة --}}
        <span data-drag-handle class="cursor-grab" style="min-height: 44px; display: inline-flex; align-items: center"
              aria-label="{{ setting('cv.row.reorder_label', 'اسحب لإعادة الترتيب') }}" title="{{ setting('cv.row.reorder_label', 'اسحب لإعادة الترتيب') }}">
            <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" fill="currentColor">
                <circle cx="4" cy="3" r="1.3" /><circle cx="10" cy="3" r="1.3" />
                <circle cx="4" cy="7" r="1.3" /><circle cx="10" cy="7" r="1.3" />
                <circle cx="4" cy="11" r="1.3" /><circle cx="10" cy="11" r="1.3" />
            </svg>
        </span>
    </div>
    <input type="text" name="data[experience][{{ $i }}][title]" value="{{ $row['title'] ?? '' }}"
           placeholder="{{ setting('cv.field.job_title_label', 'المسمّى الوظيفيّ') }}"
           class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <input type="text" name="data[experience][{{ $i }}][company]" value="{{ $row['company'] ?? '' }}"
           placeholder="{{ setting('cv.field.company_label', 'الشركة') }}"
           class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <input type="text" name="data[experience][{{ $i }}][city]" value="{{ $row['city'] ?? '' }}"
           placeholder="{{ setting('cv.field.city_label', 'المدينة') }}"
           class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <div class="flex items-center gap-2">
        <input type="text" name="data[experience][{{ $i }}][from]" value="{{ $row['from'] ?? '' }}"
               placeholder="{{ setting('cv.field.from_label', 'من') }}"
               class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $field }}">
        <input type="text" name="data[experience][{{ $i }}][to]" value="{{ $row['to'] ?? '' }}"
               placeholder="{{ setting('cv.field.to_label', 'إلى') }}"
               class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $field }}">
    </div>

    <textarea name="data[experience][{{ $i }}][description]" rows="2"
              placeholder="{{ setting('cv.field.description_label', 'الوصف') }}"
              class="sm:col-span-2 rounded-xl px-3 py-2 text-sm" style="{{ $field }}">{{ $row['description'] ?? '' }}</textarea>

    {{-- حقل اللغة الثانية الاختياريّ — ثنائيّة بصفر تكلفة (9) --}}
    <textarea name="data[experience][{{ $i }}][description_en]" rows="2" dir="ltr" data-lang-en hidden
              placeholder="{{ setting('cv.field.description_en_label', 'Description (English) — optional') }}"
              class="sm:col-span-2 rounded-xl px-3 py-2 text-sm" style="{{ $field }}">{{ $row['description_en'] ?? '' }}</textarea>

    <div class="sm:col-span-2 flex items-center justify-between">
        <label class="flex items-center gap-2 text-xs" style="color: var(--text-muted)">
            <input type="checkbox" name="data[experience][{{ $i }}][current]" value="1" @checked(! empty($row['current']))>
            {{ setting('cv.until_now_label', 'حتى الآن') }}
        </label>
        <button type="button" data-repeat-remove class="text-sm rounded-lg px-3 py-1.5"
                style="background: var(--surface-sunken); color: var(--text-muted)"
                aria-label="{{ setting('cv.row.remove_label', 'حذف') }}">✕</button>
    </div>
</div>
