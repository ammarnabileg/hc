{{-- 🎓 الدورات التدريبيّة: اسم · جهة إصدار · تاريخ · رقم الشهادة · رابطها (الدستور 9) --}}
<div class="card p-3 grid gap-2 sm:grid-cols-2" data-repeat-row draggable="true">
    <div class="sm:col-span-2 flex items-center gap-2 text-xs" style="color: var(--text-muted)">
        <span data-drag-handle class="cursor-grab" style="min-height: 44px; display: inline-flex; align-items: center"
              aria-label="{{ setting('cv.row.reorder_label', 'اسحب لإعادة الترتيب') }}" title="{{ setting('cv.row.reorder_label', 'اسحب لإعادة الترتيب') }}">
            <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" fill="currentColor">
                <circle cx="4" cy="3" r="1.3" /><circle cx="10" cy="3" r="1.3" />
                <circle cx="4" cy="7" r="1.3" /><circle cx="10" cy="7" r="1.3" />
                <circle cx="4" cy="11" r="1.3" /><circle cx="10" cy="11" r="1.3" />
            </svg>
        </span>
    </div>

    <input type="text" name="data[courses][{{ $i }}][name]" value="{{ $row['name'] ?? '' }}"
           placeholder="{{ setting('cv.field.course_name_label', 'اسم الدورة') }}"
           class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <input type="text" name="data[courses][{{ $i }}][provider]" value="{{ $row['provider'] ?? '' }}"
           placeholder="{{ setting('cv.field.provider_label', 'جهة الإصدار') }}"
           class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <input type="text" name="data[courses][{{ $i }}][date]" value="{{ $row['date'] ?? '' }}"
           placeholder="{{ setting('cv.field.course_date_label', 'تاريخ الحصول') }}"
           class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <input type="text" name="data[courses][{{ $i }}][serial]" value="{{ $row['serial'] ?? '' }}"
           placeholder="{{ setting('cv.field.course_serial_label', 'رقم الشهادة (اختياريّ)') }}"
           class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <input type="url" name="data[courses][{{ $i }}][url]" value="{{ $row['url'] ?? '' }}" dir="ltr"
           placeholder="{{ setting('cv.field.course_url_label', 'رابط الشهادة (اختياريّ)') }}"
           class="sm:col-span-2 rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <div class="sm:col-span-2 text-end">
        <button type="button" data-repeat-remove class="text-sm rounded-lg px-3 py-1.5"
                style="background: var(--surface-sunken); color: var(--text-muted)"
                aria-label="{{ setting('cv.row.remove_label', 'حذف') }}">✕</button>
    </div>
</div>
