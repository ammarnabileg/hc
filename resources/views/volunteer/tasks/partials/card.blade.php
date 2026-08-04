@php
    /** كارت/صفّ المهمّة — نفس الشكل في القائمة والكانبان (24.4-2) */
    $subtaskTotal = \App\Models\Task::where('parent_task_id', $task->id)->count();
    $subtaskDone = $subtaskTotal
        ? \App\Models\Task::where('parent_task_id', $task->id)->where('status', 'approved')->count()
        : 0;
    $contributors = \App\Models\TaskContribution::where('task_id', $task->id)->count();
@endphp

<a href="{{ route('volunteer.tasks.show', $task) }}"
   class="card p-3 block motion-standard hover:opacity-95"
   data-task-card="{{ $task->id }}" @if ($draggable ?? false) draggable="true" @endif>

    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="font-semibold text-sm truncate">
                <span aria-hidden="true" title="{{ $task->entity?->name_ar }}">{{ $task->entity?->icon }}<x-icon name="entity" size="14" /></span>
                {{ $task->title }}
            </div>
            <div class="text-xs mt-1 flex flex-wrap items-center gap-2" style="color: var(--text-muted)">
                <span>#{{ $task->id }}</span>
                @if ($task->task_type)
                    <span>· {{ $task->task_type->name_ar }}</span>
                @endif
                @if ($task->work_item)
                    <span>· {{ $task->work_item->name }}</span>
                @endif
            </div>
        </div>

        <x-state-badge :state="\App\Services\Volunteer\Tasks\TaskStatus::state($task->status)"
                       :label="\App\Services\Volunteer\Tasks\TaskStatus::label($task->status)" />
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
        @include('volunteer.components.deadline-counter', ['task' => $task])

        @if ((float) $task->vxp_value > 0)
            <span style="color: var(--text-muted)"><x-icon name="spark" size="16" /> {{ rtrim(rtrim(number_format((float) $task->vxp_value, 2), '0'), '.') }} VXP</span>
        @endif

        @if ($contributors > 0)
            <span style="color: var(--text-muted)">{{ setting('volunteer.tasks_card.link', 'مساهمون:') }} {{ $contributors }}</span>
        @endif

        @if ($task->late_due_to_child)
            <x-state-badge state="warn" :label="setting('volunteer.tasks_card.label', 'متأخّر بسبب ابن')" />
        @endif
    </div>

    @if ($subtaskTotal > 0)
        <div class="mt-2">
            <div class="text-xs mb-1" style="color: var(--text-muted)">
                {{ setting('volunteer.tasks_card.link_2', 'الصب-تاسكات') }} {{ $subtaskDone }}/{{ $subtaskTotal }}
            </div>
            <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                <div class="h-full" style="width: {{ (int) round($subtaskDone / $subtaskTotal * 100) }}%; background: var(--color-brand-500)"></div>
            </div>
        </div>
    @endif
</a>
