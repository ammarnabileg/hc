@php
    /**
     * إنبوت واحد بحفظ تلقائيّ فوريّ + كلمة **«تمّ التعديل»** تحته (23 — 1.4).
     *
     * ⚠️ القاعدة التي كُسِرت مرّةً في هذا المشروع: الحفظ التلقائيّ كان يخزّن
     * **مسودّةً لا ترجع للحقول**، فيضيع العمل ويظنّ صاحبه أنّه محفوظ. هنا القيمة
     * المعروضة تُقرأ من **الصفّ نفسه** (`$value`) الذي يكتب فيه `saveField` —
     * فما حُفِظ يعود إلى مكانه عند إعادة الفتح، لا إلى مخزنٍ جانبيّ.
     *
     * $subject · $id · $field · $label · $value · $editable · $edits · $type
     */
    $type ??= 'text';
    $editable ??= false;
    $edits ??= 0;
    $key = $subject.':'.$id.':'.$field;
@endphp

<div class="block" data-build-field data-subject="{{ $subject }}" data-id="{{ $id }}" data-field="{{ $field }}">
    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ $label }}</span>

    @if ($editable)
        @if ($type === 'textarea')
            <textarea data-build-input rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $value }}</textarea>
        @else
            <input type="{{ $type }}" @if ($type === 'number') step="any" min="0" @endif
                   value="{{ $value }}" data-build-input
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        @endif
    @else
        <div class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised); color: var(--text)">{{ $value !== '' ? $value : '—' }}</div>
    @endif

    <div class="flex items-center gap-2 mt-1">
        <span class="text-xs" data-build-status style="color: var(--color-state-ok)"></span>

        {{-- «تمّ التعديل» — بالضغط بوب-أب بكلّ تعديلات هذا الحقل بعينه --}}
        <button type="button" data-build-edited data-key="{{ $key }}"
                class="text-xs underline {{ $edits > 0 ? '' : 'hidden' }}"
                style="color: var(--color-state-warn)">{{ setting('volunteer.goals_build_field.action', 'تمّ التعديل') }}</button>
    </div>
</div>
