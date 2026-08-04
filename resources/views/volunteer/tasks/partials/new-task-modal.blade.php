@push('modals')
    <x-modal id="new-task" :title="setting('volunteer.tasks_new_task_modal.tooltip', 'مهمّة جديدة')">
        <form method="post" action="{{ route('volunteer.tasks.store') }}" class="space-y-3">
            @csrf

            <x-form.input name="title" :label="setting('volunteer.tasks_new_task_modal.label', 'العنوان')" :hint="setting('volunteer.tasks_new_task_modal.hint', 'فعل + مفعول: «اكتب سكربت ريل المستوى الرابع»')" required />

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.tasks_new_task_modal.field', 'البريف') }}</span>
                <textarea name="brief" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.common.output_format', 'شكل المخرجات') }}</span>
                <textarea name="deliverable_spec" rows="2" required class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                          placeholder="{{ setting('volunteer.tasks_new_task_modal.placeholder', 'المخرج المطلوب بالضبط: صيغته ومكانه وطريقة تسليمه') }}"></textarea>
                <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('volunteer.tasks_new_task_modal.field_2', 'إلزاميّ — المخرج الواضح يمنع الإرجاع.') }}</span>
            </label>

            <x-form.input name="deadline_at" :label="setting('volunteer.tasks_new_task_modal.label_2', 'الديدلاين')" type="datetime-local" required />

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.tasks_new_task_modal.field_3', 'البند التابع للمشروع') }}</span>
                <select name="work_item_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('volunteer.tasks_new_task_modal.option', 'اختر بندًا…') }}</option>
                    @foreach ($workItems as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
                <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('volunteer.tasks_new_task_modal.field_4', 'إلزاميّ — كلّ مهمّة جديدة تتربط ببند.') }}</span>
            </label>

            {{-- خيارات متقدّمة مطويّة، والفورم يعمل كاملًا بدونها (2.15-د) --}}
            <details>
                <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('volunteer.tasks_new_task_modal.summary', 'خيارات متقدّمة') }}</summary>
                <div class="space-y-3 mt-3">
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('volunteer.common.type', 'النوع') }}</span>
                        <select name="task_type_id" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">{{ setting('volunteer.tasks_new_task_modal.option_2', 'بلا نوع') }}</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}">{{ $type->name_ar }}</option>
                            @endforeach
                        </select>
                    </label>

                    <x-form.input name="vxp_value" :label="setting('volunteer.tasks_new_task_modal.label_3', 'قيمة VXP')" type="number" step="0.01" min="0" />
                </div>
            </details>

            <p class="text-xs" style="color: var(--text-muted)">
                *«{{ setting('volunteer.tasks_new_task_modal.text', 'مهمّة عامّة» مش هنا — إضافتها للأدمن ومشرف عام التطوّع حصرًا.') }}
            </p>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                        style="background: var(--surface-raised)">{{ setting('volunteer.common.cancel', 'إلغاء') }}</button>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_new_task_modal.action', 'احفظ المهمّة') }}</button>
            </div>
        </form>
    </x-modal>
@endpush
