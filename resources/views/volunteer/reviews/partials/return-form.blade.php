{{--
  الإرجاع (23 — 3.6): فيدباك مكتوب **إجباريّ** + تصنيف السبب من العشرة +
  **مهلة إصلاح مستقلّة** لها سلّمها المستقلّ — فلا خصم مزدوج على المنفّذ.
--}}
<form method="post" action="{{ $action }}" class="space-y-3">
    @csrf

    <label class="block">
        <span class="block text-sm mb-1">{{ setting('volunteer.reviews_return_form.field', 'الفيدباك المكتوب') }} <span style="color: var(--color-state-danger)">*</span></span>
        <textarea name="review_feedback" rows="4" required
                  placeholder="{{ setting('volunteer.reviews_return_form.placeholder', 'اكتب بالضبط إيه اللي ناقص وإزاي يتظبط') }}"
                  class="w-full rounded-xl px-3 py-2 text-sm"
                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
        @error('review_feedback')
            <span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>
        @enderror
    </label>

    <label class="block">
        <span class="block text-sm mb-1">{{ setting('volunteer.reviews_return_form.field_2', 'تصنيف السبب') }} <span style="color: var(--color-state-danger)">*</span></span>
        <select name="return_reason_code" required class="w-full rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            @foreach ($reasons as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <x-form.input name="fix_hours" :label="setting('volunteer.reviews_return_form.label', 'مهلة الإصلاح (ساعات)')" type="number" :value="$fixHours"
                  :hint="setting('volunteer.reviews_return_form.hint', 'مهلة مستقلّة بسلّمها المستقلّ — والديدلاين الأصليّ لا يُحتسَب مرّتين.')" />

    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
            style="background: var(--color-state-warn); color: #04201c">{{ setting('volunteer.reviews_return_form.action', 'إرجاع') }}</button>
</form>
