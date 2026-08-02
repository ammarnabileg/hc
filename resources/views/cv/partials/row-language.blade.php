<div class="flex items-center gap-2" data-repeat-row draggable="true">
    <input type="text" name="data[languages][{{ $i }}][language]" value="{{ $row['language'] ?? '' }}"
           placeholder="{{ setting('cv.field.language_label', 'اللغة') }}"
           class="grow rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

    <select name="data[languages][{{ $i }}][level]" class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">
        <option value="">—</option>
        @foreach ((array) setting('cv.options.language_levels', ['مبتدئ', 'متوسّط', 'جيّد جدًّا', 'إتقان تامّ', 'لغة أمّ']) as $level)
            <option value="{{ $level }}" @selected(($row['level'] ?? '') === $level)>{{ $level }}</option>
        @endforeach
    </select>

    <button type="button" data-repeat-remove class="text-sm rounded-lg px-3 py-1.5"
            style="background: var(--surface-sunken); color: var(--text-muted)"
            aria-label="{{ setting('cv.row.remove_label', 'حذف') }}">✕</button>
</div>
