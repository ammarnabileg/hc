@php
    /**
     * «دعوة مساهم» (23 — القسم 4) — قابل للتضمين من صفحة المهمّة:
     *   @include('volunteer.contributions.partials.invite-form', ['task' => $task])
     *
     * والمعاينة قبل الإرسال شرط: مهامّ المدعوّ المفتوحة · مسلَّماته · مساهماته ·
     * **ورصيدي بعد الخصم** — ويُمنَع الإرسال إن لم يكفِ.
     */
    $service = app(\App\Services\Volunteer\Contributions\ContributionService::class);
    $latest = $service->latestInternalDeadline($task);
    $maxCheckpoints = $service->maxCheckpoints();
@endphp

<form method="post" action="{{ route('volunteer.contributions.store', $task) }}"
      class="space-y-3" data-invite-form data-preview-url="{{ route('volunteer.contributions.preview', $task) }}">
    @csrf

    <x-form.input name="code" :label="setting('volunteer.contributions_invite_form.label', 'كود المساهم')" data-invite-code :hint="setting('volunteer.contributions_invite_form.hint', 'اكتب الكود ثمّ اضغط «معاينة قبل الإرسال».')" />
    <x-form.input name="item_title" :label="setting('volunteer.contributions_invite_form.label_2', 'عنوان البند')" />

    <label class="block">
        <span class="block text-sm mb-1">{{ setting('volunteer.contributions_invite_form.field', 'تعليمات التسليم / شكل المخرجات') }} <span style="color: var(--color-state-danger)">*</span></span>
        <textarea name="deliverable_spec" rows="4" required class="w-full rounded-xl px-3 py-2 text-sm"
                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
    </label>

    <x-form.input name="internal_deadline_at" :label="setting('volunteer.contributions_invite_form.label_3', 'الديدلاين الداخليّ')" type="datetime-local"
                  :hint="setting('volunteer.contributions_invite_form.hint_2', 'لازم يكون قبل ديدلاين المهمّة بـ').$service->deadlineGapHours().setting('volunteer.contributions_invite_form.hint_3', ' ساعة — أقصى موعد: ').($latest?->format('Y-m-d H:i') ?? '—')" />

    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="vxp_value" :label="setting('volunteer.contributions_invite_form.label_4', 'قيمة VXP')" type="number" step="1" value="0" data-invite-vxp />
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('volunteer.contributions_invite_form.field_2', 'مصدر الـVXP') }}</span>
            <select name="vxp_source" data-invite-source class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="task_pool">{{ setting('volunteer.contributions_invite_form.option', 'وعاء المهمّة') }}</option>
                <option value="owner_balance">{{ setting('volunteer.contributions_invite_form.option_2', 'رصيدي الشخصيّ (يُخصَم كرصيد معلَّق)') }}</option>
            </select>
        </label>
    </div>

    {{-- نقطتا تفتيش كحدّ أقصى — مواعيد وسيطة سهلة في نفس الصفحة --}}
    <fieldset class="grid grid-cols-2 gap-3">
        <legend class="text-sm mb-1">{{ str_replace(':max', $maxCheckpoints, (string) setting('volunteer.contributions_invite_form.legend', 'نقاط التفتيش (:max كحدّ أقصى)')) }}</legend>
        @for ($i = 0; $i < $maxCheckpoints; $i++)
            <input type="datetime-local" name="checkpoints[]" class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        @endfor
    </fieldset>

    <button type="button" data-invite-preview class="rounded-xl px-4 py-2 text-sm w-full"
            style="border: 1px solid var(--border)">{{ setting('volunteer.contributions_invite_form.action', 'معاينة قبل الإرسال') }}</button>

    <div data-invite-result class="hidden card p-3 text-sm space-y-1"></div>

    <button type="submit" data-invite-submit class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.contributions_invite_form.action_2', 'إرسال الدعوة') }}</button>
</form>

@once
    @push('scripts')
        @php
            /** نصوص السكربت — تُمرَّر بـ`@json` فلا يبقى حرفٌ عربيّ محروق داخله (2.13-أ) */
            $jsText = [
                'open_tasks' => (string) setting('volunteer.contributions_invite.js_open_tasks', 'مهامّه المفتوحة:'),
                'delivered' => (string) setting('volunteer.contributions_invite.js_delivered', 'مسلَّماته:'),
                'contributions' => (string) setting('volunteer.contributions_invite.js_contributions', 'مساهماته:'),
                'balance_after' => (string) setting('volunteer.contributions_invite.js_balance_after', 'رصيدي بعد الخصم:'),
                'preview_failed' => (string) setting('volunteer.contributions_invite.js_preview_failed', 'تعذّرت المعاينة — جرّب تاني.'),
            ];
        @endphp

        <script>
            const T = @json($jsText);
            // معاينة حيّة قبل الإرسال — وتمنع الإرسال إن لم يكفِ الرصيد (23 — القسم 4)
            document.querySelectorAll('[data-invite-form]').forEach((form) => {
                const result = form.querySelector('[data-invite-result]');
                const submit = form.querySelector('[data-invite-submit]');

                form.querySelector('[data-invite-preview]').addEventListener('click', async () => {
                    const params = new URLSearchParams({
                        code: form.querySelector('[data-invite-code]').value,
                        vxp_value: form.querySelector('[data-invite-vxp]').value || 0,
                        vxp_source: form.querySelector('[data-invite-source]').value,
                    });

                    try {
                        const response = await fetch(`${form.dataset.previewUrl}?${params}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await response.json();

                        result.classList.remove('hidden');
                        result.innerHTML = `
                            <div>${data.invitee?.name ?? '—'} (#${data.invitee?.code ?? '—'})</div>
                            <div>${T.open_tasks} ${data.open_tasks}</div>
                            <div>${T.delivered} ${data.delivered_tasks}</div>
                            <div>${T.contributions} ${data.contributions}</div>
                            <div>${T.balance_after} <strong>${data.balance_after}</strong> VXP</div>
                            <div>${data.message}</div>`;

                        // العنصر الذي لا يُسمَح به يُخفى لا يُعطَّل (2.15-أ-7)
                        submit.classList.toggle('hidden', !data.sufficient);
                    } catch {
                        result.classList.remove('hidden');
                        result.textContent = T.preview_failed;
                    }
                });
            });
        </script>
    @endpush
@endonce
