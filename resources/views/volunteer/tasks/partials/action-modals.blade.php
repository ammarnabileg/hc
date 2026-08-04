@push('modals')
    {{-- تسليم — الساعة تقف هنا والتقييم على وقت التسليم (23-3.7) --}}
    <x-modal id="deliver-task" :title="setting('volunteer.tasks_action_modals.tooltip', 'تسليم المهمّة')">
        <form method="post" action="{{ route('volunteer.tasks.deliver', $task) }}" class="space-y-3">
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
                {{ $repOnDelivery >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($repOnDelivery, 3), '0'), '.') }}
                — {{ setting('volunteer.tasks_action_modals.text_2', 'الساعة بتقف لحظة الضغط، وزمن المراجعة مش عليك.') }}
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
    <x-modal id="block-task" :title="setting('volunteer.tasks_action_modals.tooltip_2', 'تسجيل تعثّر')">
        <form method="post" action="{{ route('volunteer.tasks.block', $task) }}" class="space-y-3">
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
                {{ setting('volunteer.tasks_action_modals.text_3', 'باقي لك') }} {{ $blocksLeft }} {{ setting('volunteer.tasks_action_modals.text_4', 'من مرّات التعثّر.') }}
                @if ($nextBlockHalves)
                    <strong>{{ setting('volunteer.tasks_action_modals.strong', 'وتنبيه: الإعادة دي هتنصّف مكافأة درجة الالتزام على المهمّة.') }}</strong>
                @endif
            </p>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                        style="background: var(--surface-raised)">{{ setting('volunteer.common.cancel', 'إلغاء') }}</button>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_action_modals.action_2', 'سجّل التعثّر') }}</button>
            </div>
        </form>
    </x-modal>

    {{-- طلب تمديد: قبل الديدلاين ومرّة واحدة (23-3.5) --}}
    @if ($canExtend)
        <x-modal id="extend-task" :title="setting('volunteer.tasks_action_modals.tooltip_3', 'طلب تمديد')">
            <form method="post" action="{{ route('volunteer.tasks.extension', $task) }}" class="space-y-3">
                @csrf

                <x-form.input name="new_deadline" :label="setting('volunteer.tasks_action_modals.label_5', 'التاريخ الجديد')" type="datetime-local" required />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('volunteer.common.reason', 'السبب') }}</span>
                    <textarea name="reason" rows="3" required class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.tasks_action_modals.text_5', 'التمديد مرّة واحدة للمهمّة، وقبل الديدلاين فقط.') }}</p>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                            style="background: var(--surface-raised)">{{ setting('volunteer.common.cancel', 'إلغاء') }}</button>
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_action_modals.action_3', 'ابعت الطلب') }}</button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- اعتذار --}}
    <x-modal id="apology-task" :title="setting('volunteer.tasks_action_modals.tooltip_4', 'اعتذار عن المهمّة')">
        <form method="post" action="{{ route('volunteer.tasks.apology', $task) }}" class="space-y-3">
            @csrf

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.common.reason', 'السبب') }}</span>
                <textarea name="reason" rows="3" required class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>

            <p class="text-xs" style="color: var(--text-muted)">
                {{ setting('volunteer.tasks_action_modals.text_6', 'الاعتذار المقبول أثره') }} {{ rtrim(rtrim(number_format(rep_rule('task.apology_accepted'), 2), '0'), '.') }}
                {{ setting('volunteer.tasks_action_modals.text_7', 'على درجة الالتزام — والقرار لمراجعك.') }}
            </p>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                        style="background: var(--surface-raised)">{{ setting('volunteer.common.cancel', 'إلغاء') }}</button>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_action_modals.action_4', 'ابعت الاعتذار') }}</button>
            </div>
        </form>
    </x-modal>

    {{-- Create Subtask دفعةً — بفحص القيد عند الحفظ (23-2.3) --}}
    <x-modal id="subtasks-batch" :title="setting('volunteer.tasks_action_modals.tooltip_5', 'Create Subtask — دفعة')">
        <form method="post" action="{{ route('volunteer.tasks.subtasks.store', $task) }}" class="space-y-3">
            @csrf

            <p class="text-xs" style="color: var(--text-muted)">
                {{ str_replace(':hours', $mergeWindowHours, (string) setting('volunteer.tasks_action_modals.text_8', 'القيد: أقصى ديدلاين للأبناء + نافذة دمجك (:hours ساعة) ≤ ديدلاينك')) }}
                @if ($childDeadlineLimit)
                    — {{ setting('volunteer.tasks_action_modals.text_10', 'يعني') }} {{ $childDeadlineLimit->format('Y-m-d H:i') }} {{ setting('volunteer.tasks_action_modals.text_11', 'كحدّ أقصى.') }}
                @endif
            </p>

            <div class="space-y-3" data-subtask-rows>
                <div class="rounded-xl p-3 space-y-2" style="background: var(--surface-sunken)" data-subtask-row>
                    <input type="text" name="subtasks[0][title]" placeholder="{{ setting('volunteer.tasks_action_modals.placeholder_2', 'العنوان') }}"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    <textarea name="subtasks[0][deliverable_spec]" rows="2" placeholder="{{ setting('volunteer.common.output_format', 'شكل المخرجات') }}"
                              class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface); border: 1px solid var(--border); color: var(--text)"></textarea>
                    <div class="flex gap-2">
                        <input type="datetime-local" name="subtasks[0][deadline_at]"
                               class="flex-1 rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                        <input type="number" step="0.01" min="0" name="subtasks[0][vxp_value]" placeholder="VXP"
                               class="w-24 rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </div>
                </div>
            </div>

            <button type="button" data-add-subtask-row class="rounded-xl px-3 py-1.5 text-xs"
                    style="background: var(--surface-raised)">+ {{ setting('volunteer.tasks_action_modals.action_5', 'صفّ جديد') }}</button>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                        style="background: var(--surface-raised)">{{ setting('volunteer.common.cancel', 'إلغاء') }}</button>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_action_modals.action_6', 'حفظ للمراجعة') }}</button>
            </div>
        </form>
    </x-modal>

    {{-- دعوة مساهم (23-4) --}}
    <x-modal id="invite-contributor" :title="setting('volunteer.tasks_action_modals.tooltip_6', 'دعوة مساهم')">
        <form method="post" action="{{ route('volunteer.tasks.contributors.store', $task) }}" class="space-y-3">
            @csrf

            <x-form.input name="code" :label="setting('volunteer.tasks_action_modals.label_6', 'كود المتطوّع')" required />
            <x-form.input name="item_title" :label="setting('volunteer.common.item', 'البند')" required />
            <x-form.input name="internal_deadline_at" :label="setting('volunteer.tasks_action_modals.label_7', 'الديدلاين الداخليّ')" type="datetime-local" required
                          :hint="setting('volunteer.tasks_action_modals.hint_5', 'لازم يسبق ديدلاين المهمّة بـ24 ساعة على الأقلّ.')" />
            <x-form.input name="vxp_value" label="VXP" type="number" step="0.01" min="0" />

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.common.output_format', 'شكل المخرجات') }}</span>
                <textarea name="deliverable_spec" rows="2" required class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                        style="background: var(--surface-raised)">{{ setting('volunteer.common.cancel', 'إلغاء') }}</button>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_action_modals.action_7', 'ابعت الدعوة') }}</button>
            </div>
        </form>
    </x-modal>

    {{-- رفع علم «متأخّر بسبب…» (23-3.9-4) --}}
    @if ($subtasks->isNotEmpty())
        <x-modal id="flag-task" :title="setting('volunteer.tasks_action_modals.tooltip_7', 'رفع علم: متأخّر بسبب…')">
            <form method="post" action="{{ route('volunteer.tasks.flag', $task) }}" class="space-y-3">
                @csrf

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('volunteer.tasks_action_modals.field_4', 'الصب-تاسك المتأخّر') }}</span>
                    <select name="child_task_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($subtasks as $subtask)
                            <option value="{{ $subtask->id }}">#{{ $subtask->id }} — {{ $subtask->title }}</option>
                        @endforeach
                    </select>
                </label>

                <p class="text-xs" style="color: var(--text-muted)">
                    {{ setting('volunteer.tasks_action_modals.text_12', 'مرّة واحدة للمهمّة، وقبل فوات نافذتك — والنظام بيتأكّد إنّ الابن متأخّر فعلًا.') }}
                </p>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                            style="background: var(--surface-raised)">{{ setting('volunteer.common.cancel', 'إلغاء') }}</button>
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_action_modals.action_8', 'ارفع العلم') }}</button>
                </div>
            </form>
        </x-modal>
    @endif
@endpush
