@php
    /**
     * حقل بحفظ تلقائيّ و«اتحفظ ✓» بجواره (2.17-ب).
     * يعمل بلا JS كذلك: الفورم الصغير يُرسَل بزرّه — فالصفحة لا تنكسر (2.1).
     *
     * المتغيّرات: $field · $label · $hint · $slot (الكونترول) · $action (اختياريّ)
     */
    $action ??= route('settings.field');
    $hint ??= null;
    $keywords ??= $label;
@endphp

<div class="py-3" data-settings-item data-keywords="{{ $keywords }}" style="border-bottom: 1px solid var(--border)">
    <form method="post" action="{{ $action }}" data-autosave-form class="flex flex-wrap items-end gap-2">
        @csrf
        @method('PATCH')
        <input type="hidden" name="field" value="{{ $field }}">

        <label class="block flex-1 min-w-48">
            <span class="block text-sm mb-1">{{ $label }}</span>
            {!! $control !!}
            @if ($hint)
                <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ $hint }}</span>
            @endif
        </label>

        <button type="submit" class="btn rounded-xl px-3 py-2 text-xs motion-standard" data-autosave-submit
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('account.settings.save_action', 'حفظ') }}</button>

        {{-- «اتحفظ ✓» يظهر بجوار الحقل لحظة الحفظ (2.17-ب) --}}
        <span class="text-xs opacity-0 motion-standard" data-saved-flag style="color: var(--color-state-ok)">{{ setting('account.settings.saved_flag', 'اتحفظ ✓') }}</span>
    </form>
</div>
