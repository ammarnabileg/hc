@php
    /**
     * بوب-أب «ولّد مهمّة تنفيذ» لبندٍ واحدٍ من بنود المحضر (23-0.3).
     * حقولُه هي حقول فورم المهمّة الجديدة نفسها — فالمهمّة المتولّدة مهمّةٌ
     * عاديّة بعقدها كاملًا: بندٌ إلزاميّ · شكل مخرجات · ديدلاين · إسناد.
     */
    $defaultSpec = $minutesTasks->defaultType()?->default_deliverable_spec;
@endphp

<x-modal :id="'minutes-task-'.$item['index']"
         title="{{ setting('volunteer.meetings_minutes_task.tooltip', 'ولّد مهمّة «تنفيذ» من بند المحضر') }}">
    <form method="post" action="{{ route('volunteer.meetings.minutes.tasks', $meeting) }}" class="space-y-3">
        @csrf
        <input type="hidden" name="minutes_item" value="{{ $item['index'] }}">

        <p class="text-xs rounded-xl px-3 py-2" style="background: var(--surface-sunken); color: var(--text-muted)">
            {{ setting('volunteer.meetings_minutes_task.text', 'البند:') }} {{ $item['text'] }}
        </p>

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.meetings_minutes_task.field', 'عنوان المهمّة') }}</span>
            <input type="text" name="title" maxlength="180" value="{{ mb_substr($item['text'], 0, 180) }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('volunteer.meetings_minutes_task.field_2', 'متعبّي من نصّ البند — عدّله لو محتاج.') }}</span>
        </label>

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.output_format', 'شكل المخرجات') }}</span>
            <textarea name="deliverable_spec" rows="2" required
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $defaultSpec }}</textarea>
            <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('volunteer.tasks_new_task_modal.field_2', 'إلزاميّ — المخرج الواضح يمنع الإرجاع.') }}</span>
        </label>

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.meetings_minutes_task.field_3', 'الديدلاين') }}</span>
            <input type="datetime-local" name="deadline_at" required
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.tasks_new_task_modal.field_3', 'البند التابع للمشروع') }}</span>
            <select name="work_item_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.tasks_new_task_modal.option', 'اختر بندًا…') }}</option>
                @foreach ($workItems as $workItem)
                    <option value="{{ $workItem->id }}">{{ $workItem->name }}</option>
                @endforeach
            </select>
            <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('volunteer.tasks_new_task_modal.field_4', 'إلزاميّ — كلّ مهمّة جديدة تتربط ببند.') }}</span>
        </label>

        {{-- خيارات متقدّمة مطويّة، والفورم يعمل كاملًا بدونها (2.15-د) --}}
        <details>
            <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('volunteer.tasks_new_task_modal.summary', 'خيارات متقدّمة') }}</summary>
            <div class="space-y-3 mt-3">
                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.tasks_new_task_modal.field', 'البريف') }}</span>
                    <textarea name="brief" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.tasks_new_task_modal.label_3', 'قيمة VXP') }}</span>
                    <input type="number" name="vxp_value" step="0.01" min="0"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.tasks_new_task_modal.label_4', 'إسناد لعضو فريق') }}</span>
                    <select name="owner_id" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">{{ setting('volunteer.tasks_new_task_modal.option_3', 'أنا (بلا إسناد)') }}</option>
                        @foreach ($teamMembers as $member)
                            <option value="{{ $member['user']->id }}">
                                {{ $member['user']->shortName() }} — {{ strtr((string) setting('volunteer.tasks_new_task_modal.option_4', ':p1 مهمّة حاليًّا'), [':p1' => (string) $member['load']]) }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        </details>

        <p class="text-xs" style="color: var(--text-muted)">
            {{ setting('volunteer.meetings_minutes_task.text_2', 'النوع «تنفيذ» — والمهمّة بعد التوليد عاديّة تمامًا: عدّاد ومراجعة وتصعيد كإخوتها.') }}
        </p>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                    style="background: var(--surface-raised)">{{ setting('volunteer.common.cancel', 'إلغاء') }}</button>
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.meetings_minutes_task.action', 'ولّد المهمّة') }}</button>
        </div>
    </form>
</x-modal>
