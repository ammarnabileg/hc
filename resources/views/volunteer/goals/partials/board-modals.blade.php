@push('modals')
    {{--
      ⭐ بوب-أبا لوحة الهدف — **نسخةٌ من نموذجَي صفحة المهمّة** (`tasks/partials/action-modals`)
      بنفس الحقول ونفس المسارات ونفس الأرقام الاستشاريّة، والفرق الوحيد أنّ الهدف
      والأرقام تُصبّ لحظة الضغط من الكارت (السكربت في `goals/show`) لأنّ البوب-أب
      واحدٌ لكلّ اللوحة لا واحدٌ لكلّ مهمّة. أيّ حقلٍ يُضاف هناك يُضاف هنا.
    --}}

    {{-- تسليم — الساعة تقف هنا والتقييم على وقت التسليم (23-3.7) --}}
    <x-modal id="board-deliver" :title="setting('volunteer.tasks_action_modals.tooltip', 'تسليم المهمّة')">
        <p class="text-sm font-semibold mb-3" data-board-task-title></p>

        <form method="post" action="#" class="space-y-3">
            @csrf

            <x-form.input name="link" :label="setting('volunteer.tasks_action_modals.label', 'رابط المخرج')" type="url" :hint="setting('volunteer.tasks_action_modals.hint', 'أو اكتب المخرج نصًّا تحت.')" />

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.tasks_action_modals.field', 'المخرج نصًّا') }}</span>
                <textarea name="body" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>

            <x-form.input name="note" :label="setting('volunteer.tasks_action_modals.label_2', 'ملاحظة للمراجِع')" />

            <p class="text-xs" style="color: var(--text-muted)">
                {{ setting('volunteer.tasks_action_modals.text', 'بالتسليم دلوقتي أثرك على درجة الالتزام:') }}
                <bdi data-board-rep></bdi>
                · {{ setting('volunteer.tasks_action_modals.text_2', 'الساعة بتقف لحظة الضغط، وزمن المراجعة مش عليك.') }}
            </p>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                        style="background: var(--surface-raised)">{{ setting('volunteer.common.cancel', 'إلغاء') }}</button>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_action_modals.action', 'سلّم دلوقتي') }}</button>
            </div>
        </form>
    </x-modal>

    {{-- متعثّر بنوعيه (23-3.4) --}}
    <x-modal id="board-block" :title="setting('volunteer.tasks_action_modals.tooltip_2', 'تسجيل تعثّر')">
        <p class="text-sm font-semibold mb-3" data-board-task-title></p>

        <form method="post" action="#" class="space-y-3">
            @csrf

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.tasks_action_modals.field_2', 'نوع التعثّر') }}</span>
                <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="duration">{{ str_replace(':days', $maxBlockDays, (string) setting('volunteer.tasks_action_modals.option', 'مدّة محدَّدة (≤ :days أيّام)')) }}</option>
                    <option value="dependency">{{ setting('volunteer.tasks_action_modals.option_3', 'يعتمد على مهمّة (Blocked By)') }}</option>
                </select>
            </label>

            <x-form.input name="days" :label="setting('volunteer.tasks_action_modals.label_3', 'المدّة بالأيّام')" type="number" min="1" :max="$maxBlockDays"
                          :hint="setting('volunteer.tasks_action_modals.hint_2', 'الحدّ الأقصى ').$maxBlockDays.setting('volunteer.tasks_action_modals.hint_3', ' أيّام.')" />

            <x-form.input name="blocking_task_id" :label="setting('volunteer.tasks_action_modals.label_4', 'رقم المهمّة اللي مستنّيها')" type="number" min="1"
                          :hint="setting('volunteer.tasks_action_modals.hint_4', 'للنوع «يعتمد على» فقط.')" />

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.tasks_action_modals.field_3', 'السبب (إلزاميّ)') }}</span>
                <textarea name="reason" rows="3" required class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                          placeholder="{{ setting('volunteer.tasks_action_modals.placeholder', 'مستنّي ردّ مدرّب · مستنّي مهمّة رقم 412') }}"></textarea>
            </label>

            <p class="text-xs" style="color: var(--text-muted)">
                {{ setting('volunteer.tasks_action_modals.text_3', 'باقي لك') }} <bdi data-board-blocks-left></bdi> {{ setting('volunteer.tasks_action_modals.text_4', 'من مرّات التعثّر.') }}
                <strong data-board-halves hidden>{{ setting('volunteer.tasks_action_modals.strong', 'وتنبيه: الإعادة دي هتنصّف مكافأة درجة الالتزام على المهمّة.') }}</strong>
            </p>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                        style="background: var(--surface-raised)">{{ setting('volunteer.common.cancel', 'إلغاء') }}</button>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_action_modals.action_2', 'سجّل التعثّر') }}</button>
            </div>
        </form>
    </x-modal>
@endpush
