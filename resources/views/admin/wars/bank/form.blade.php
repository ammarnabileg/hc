@php
    /** بوب-أب السؤال (24.2): النصّ · الإجابة · الصعوبة · المصدر · الحالة. */
    $options = (array) ($question?->options ?? []);
@endphp

<x-modal :id="$id" :title="$question ? setting('admin.wars.bank.form.tadyl_swal', 'تعديل سؤال') : setting('admin.wars.bank.form.swal_jdyd', 'سؤال جديد')">
    <form method="post" action="{{ route('admin.wars.bank.save') }}" class="space-y-4 text-sm" id="{{ $id }}-form">
        @csrf
        @if ($question)
            <input type="hidden" name="id" value="{{ $question->id }}">
        @endif

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.wars.bank.form.ns_alswal', 'نصّ السؤال') }}</span>
            <textarea name="text" rows="3" required maxlength="500"
                      class="w-full rounded-xl px-3 py-3 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('text', $question?->text) }}</textarea>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.wars.bank.form.alijaba', 'الإجابة') }}</span>
            <input type="text" name="answer" maxlength="160" value="{{ old('answer', $question?->answer) }}"
                   placeholder="{{ setting('admin.wars.bank.form.rqm_lltqdyr_aw_rqm_alkhyar_alshyh_ybda_mn', 'رقم للتقدير، أو رقم الخيار الصحيح (يبدأ من 0)') }}"
                   class="w-full rounded-xl px-3 py-3 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
            <span class="block text-xs mt-1" style="color: var(--text-muted)">
                {{ setting('admin.wars.bank.form.alijaba_alrqmya_balkaml_tdkhl_qma_hrb', 'الإجابة الرقميّة بالكامل تدخل قمع حرب التقدير تلقائيًّا (15.6).') }}
            </span>
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.wars.bank.form.alsmahya_llasyla_alrqmya', 'السماحيّة (للأسئلة الرقميّة)') }}</span>
                <input type="number" step="any" min="0" name="tolerance" value="{{ old('tolerance', $question?->tolerance) }}"
                       class="w-full rounded-xl px-3 py-3 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.wars.bank.form.alwhda', 'الوحدة') }}</span>
                <input type="text" name="unit" maxlength="32" value="{{ old('unit', $question?->unit) }}"
                       class="w-full rounded-xl px-3 py-3 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
            </label>
        </div>

        <fieldset>
            <legend class="text-sm mb-1">{{ setting('admin.wars.bank.form.alakhtyarat_atrkha_fargha_lswal_rqmy', 'الاختيارات (اتركها فارغة لسؤال رقميّ)') }}</legend>
            <div class="space-y-2">
                @for ($i = 0; $i < 4; $i++)
                    <input type="text" name="options[]" maxlength="160" value="{{ $options[$i] ?? '' }}"
                           placeholder="{{ strtr(setting('admin.wars.bank.form.akhtyar_v1', 'اختيار :v1'), [':v1' => e($i)]) }}"
                           class="w-full rounded-xl px-3 py-3 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                @endfor
            </div>
        </fieldset>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.wars.bank.form.alsawba', 'الصعوبة') }}</span>
                <select name="difficulty" class="w-full rounded-xl px-3 py-3 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                    @foreach ($difficulties as $key => $label)
                        <option value="{{ $key }}" @selected(($question?->difficulty ?? 'medium') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.wars.bank.form.almsdr', 'المصدر') }}</span>
                <select name="source" class="w-full rounded-xl px-3 py-3 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                    @foreach ($sources as $key => $label)
                        <option value="{{ $key }}" @selected(($question?->source ?? 'arena') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.wars.bank.form.alhala', 'الحالة') }}</span>
                <select name="status" class="w-full rounded-xl px-3 py-3 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(($question?->status ?? 'draft') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </form>

    <x-slot:footer>
        <div class="flex items-center justify-between gap-2">
            @if ($question)
                @can('wars_bank.delete')
                    <form method="post" action="{{ route('admin.wars.bank.delete', $question) }}">
                        @csrf
                        <button type="submit" class="rounded-xl px-4 py-2.5 text-sm motion-standard"
                                style="background: var(--surface-sunken); color: var(--color-state-danger); min-height: 44px">{{ setting('admin.wars.bank.form.ahdhf', 'احذف') }}</button>
                    </form>
                @endcan
            @else
                <span></span>
            @endif

            <div class="flex items-center gap-2">
                <button type="button" data-modal-close class="rounded-xl px-4 py-2.5 text-sm motion-standard"
                        style="background: var(--surface-sunken); color: var(--text); min-height: 44px">{{ setting('admin.wars.bank.form.ilgha', 'إلغاء') }}</button>
                <button type="submit" form="{{ $id }}-form"
                        class="btn rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('admin.wars.bank.form.ahfz', 'احفظ') }}</button>
            </div>
        </div>
    </x-slot:footer>
</x-modal>
