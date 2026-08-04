@extends('layouts.volunteer')

@section('title', $task->title)

@section('content')
    @php
        $tabs = [
            ['key' => 'details', 'label' => setting('volunteer.common.details', 'التفاصيل')],
            ['key' => 'todos', 'label' => setting('volunteer.tasks_show.label', 'التودو'), 'count' => $todos->count()],
            ['key' => 'subtasks', 'label' => setting('volunteer.tasks_show.label_2', 'الصب-تاسكات'), 'count' => $subtasks->count()],
            ['key' => 'contributors', 'label' => setting('volunteer.tasks_show.label_3', 'المساهمون'), 'count' => $contributions->count()],
            ['key' => 'submissions', 'label' => setting('volunteer.tasks_show.label_4', 'التسليمات'), 'count' => $submissions->count()],
            ['key' => 'comments', 'label' => setting('volunteer.tasks_show.label_5', 'الكومنتات')],
            ['key' => 'arbitrations', 'label' => setting('volunteer.tasks_show.label_6', 'التحكيمات'), 'count' => $arbitrations->count()],
        ];

        $tabs = array_map(fn ($item) => $item + ['url' => route('volunteer.tasks.show', ['task' => $task, 'tab' => $item['key']])], $tabs);
    @endphp

    <x-page-header :title="$task->title"
                   :breadcrumbs="[
                       ['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => route('volunteer.overview')],
                       ['label' => setting('volunteer.tasks_show.label_7', 'مهامّي'), 'url' => route('volunteer.tasks.index')],
                       ['label' => '#'.$task->id],
                   ]">
        <x-slot:action>
            @if ($isOwner)
                {{-- فعل رئيسيّ واحد بارز، والباقي في «⋯» (2.15-أ-2) --}}
                <button type="button" data-modal-open="deliver-task"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_show.action', 'تسليم') }}</button>

                <details class="relative">
                    <summary class="list-none cursor-pointer rounded-xl px-3 py-2 text-sm"
                             style="background: var(--surface-raised)" aria-label="{{ setting('volunteer.tasks_show.aria', 'أفعال أخرى') }}">⋯</summary>
                    <div class="absolute z-40 mt-2 end-0 card p-2 w-56 space-y-1 text-sm">
                        <button type="button" data-modal-open="block-task" class="block w-full text-start rounded-lg px-2 py-1.5">{{ setting('volunteer.tasks_show.action_2', 'متعثّر') }}</button>
                        @if ($canExtend)
                            <button type="button" data-modal-open="extend-task" class="block w-full text-start rounded-lg px-2 py-1.5">{{ setting('volunteer.tasks_show.action_3', 'طلب تمديد') }}</button>
                        @endif
                        <button type="button" data-modal-open="apology-task" class="block w-full text-start rounded-lg px-2 py-1.5">{{ setting('volunteer.tasks_show.action_4', 'اعتذار') }}</button>
                        <button type="button" data-modal-open="subtasks-batch" class="block w-full text-start rounded-lg px-2 py-1.5">{{ setting('volunteer.tasks_show.action_5', 'Create Subtask (دفعة)') }}</button>
                        <button type="button" data-modal-open="invite-contributor" class="block w-full text-start rounded-lg px-2 py-1.5">{{ setting('volunteer.tasks_show.action_6', 'دعوة مساهم') }}</button>
                        @if ($subtasks->isNotEmpty())
                            <button type="button" data-modal-open="flag-task" class="block w-full text-start rounded-lg px-2 py-1.5">{{ setting('volunteer.tasks_show.action_7', 'رفع علم: متأخّر بسبب…') }}</button>
                        @endif
                    </div>
                </details>
            @endif
        </x-slot:action>
    </x-page-header>

    {{-- شريط الحالة: أيقونة الكيان · الحالة · العدّاد · VXP · المالك والمراجِع بشارات Rep --}}
    <div class="card p-4 mb-4 flex flex-wrap items-center gap-3 text-sm">
        <span aria-hidden="true" title="{{ $task->entity?->name_ar }}">{{ $task->entity?->icon }}<x-icon name="entity" size="14" /></span>

        <x-state-badge :state="\App\Services\Volunteer\Tasks\TaskStatus::state($task->status)"
                       :label="\App\Services\Volunteer\Tasks\TaskStatus::label($task->status)" />

        @include('volunteer.components.deadline-counter', ['task' => $task])

        @if ((float) $task->vxp_value > 0)
            <span style="color: var(--text-muted)"><x-icon name="spark" size="16" /> {{ rtrim(rtrim(number_format((float) $task->vxp_value, 2), '0'), '.') }} VXP</span>
        @endif

        @if ($task->owner)
            <span class="flex items-center gap-1">
                <span style="color: var(--text-muted)">{{ setting('volunteer.tasks_show.text', 'المالك:') }}</span> {{ $task->owner->shortName() }}
                @include('volunteer.components.rep-badge', ['user' => $task->owner])
            </span>
        @endif

        @if ($task->reviewer)
            <span class="flex items-center gap-1">
                <span style="color: var(--text-muted)">{{ setting('volunteer.tasks_show.text_2', 'المراجِع:') }}</span> {{ $task->reviewer->shortName() }}
                @include('volunteer.components.rep-badge', ['user' => $task->reviewer])
            </span>
        @endif

        @if ($task->delivered_at)
            <span class="text-xs cursor-help" style="color: var(--text-muted)"
                  title="{{ setting('volunteer.tasks_show.tooltip', 'الساعة وقفت لحظة التسليم — زمن المراجعة لا يُحمَّل عليك') }}">
                <x-icon name="blocked" size="16" /> {{ setting('volunteer.tasks_show.text_3', 'العدّاد وقف:') }} {{ \Illuminate\Support\Carbon::parse($task->delivered_at)->format('Y-m-d H:i') }}
            </span>
        @endif

        @if ($rewardMultiplier < 1)
            <x-state-badge state="warn" :label="setting('volunteer.tasks_show.label_8', 'المكافأة منصَّفة بعد التعثّر الثاني')" />
        @endif

        @if ($task->late_due_to_child)
            <x-state-badge state="warn" :label="setting('volunteer.tasks_show.label_9', 'متأخّر بسبب ابن')" />
        @endif
    </div>

    {{-- تابات Sticky، وعلى الموبايل رقائق أفقيّة (2.15-ج) — والمحتوى يُحمَّل للتاب المفتوح وحده --}}
    <x-tabs :tabs="$tabs" :current="$tab" />

    @if ($tab === 'details')
        <div class="card p-4 space-y-3 text-sm">
            <div>
                <div class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.tasks_show.text_4', 'البريف') }}</div>
                <p>{{ $task->brief ?: setting('volunteer.tasks_show.text_5', 'بلا بريف مكتوب.') }}</p>
            </div>
            <div>
                <div class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.output_format', 'شكل المخرجات') }}</div>
                <p>{{ $task->deliverable_spec ?: setting('volunteer.tasks_show.text_6', 'غير محدَّد.') }}</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <div class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.item', 'البند') }}</div>
                    <p>{{ $task->work_item?->name ?? '—' }}</p>
                </div>
                <div>
                    <div class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.tasks_show.text_7', 'يعتمد على (Blocked By)') }}</div>
                    <p>
                        @if ($task->blocked_by_task)
                            <a class="underline" href="{{ route('volunteer.tasks.show', $task->blocked_by_task) }}">
                                #{{ $task->blocked_by_task->id }} — {{ $task->blocked_by_task->title }}
                            </a>
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>

            @if ($blocks->isNotEmpty())
                <div>
                    <div class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.tasks_show.text_8', 'سجلّ التعثّر') }}</div>
                    <ul class="space-y-1">
                        @foreach ($blocks as $block)
                            <li>
                                <x-state-badge state="warn" :label="$block->type === 'duration' ? $block->days.setting('volunteer.tasks_show.label_10', ' أيّام') : setting('volunteer.tasks_show.label_11', 'يعتمد على مهمّة')" />
                                {{ $block->reason }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @elseif ($tab === 'todos')
        <div class="card p-4">
            {{-- التودو شخصيّ: بلا اعتماد وبلا أثر على أيّ درجة (23-2.1) --}}
            <p class="text-xs mb-3" style="color: var(--text-muted)">
                {{ setting('volunteer.tasks_show.text_9', 'تقسيمة شخصيّة ليك — بلا اعتماد وبلا أثر على أيّ درجة. عايز بندًا يتوزّع؟ اعمله صب-تاسك.') }}
            </p>

            @if ($isOwner)
                <form method="post" action="{{ route('volunteer.tasks.todos.store', $task) }}" class="flex gap-2 mb-3">
                    @csrf
                    <input type="text" name="body" required placeholder="{{ setting('volunteer.tasks_show.placeholder', 'بند جديد…') }}"
                           class="flex-1 rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_show.action_8', 'إضافة') }}</button>
                </form>
            @endif

            @forelse ($todos as $todo)
                <div class="flex items-center gap-2 py-1.5" style="border-top: 1px solid var(--border)">
                    @if ($isOwner)
                        <form method="post" action="{{ route('volunteer.tasks.todos.toggle', [$task, $todo]) }}">
                            @csrf
                            <button type="submit" class="text-lg" aria-label="{{ setting('volunteer.tasks_show.aria_2', 'تبديل الحالة') }}">
                                {{ $todo->is_done ? '☑' : '☐' }}
                            </button>
                        </form>
                    @else
                        <span class="text-lg" aria-hidden="true">{{ $todo->is_done ? '☑' : '☐' }}</span>
                    @endif

                    <span class="flex-1 text-sm {{ $todo->is_done ? 'line-through opacity-60' : '' }}">{{ $todo->body }}</span>

                    @if ($isOwner)
                        <form method="post" action="{{ route('volunteer.tasks.todos.destroy', [$task, $todo]) }}">
                            @csrf @method('delete')
                            <button type="submit" class="text-xs" style="color: var(--text-muted)" aria-label="{{ setting('volunteer.tasks_show.aria_3', 'حذف') }}">✕</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.tasks_show.text_10', 'لسّه مفيش بنود — ابدأ بأوّل خطوة.') }}</p>
            @endforelse
        </div>
    @elseif ($tab === 'subtasks')
        <div class="card p-4">
            <p class="text-xs mb-3" style="color: var(--text-muted)">
                {{ str_replace(':hours', $mergeWindowHours, (string) setting('volunteer.tasks_show.text_11', 'القيد: أقصى ديدلاين للأبناء + نافذة دمجك (:hours ساعة) ≤ ديدلاينك')) }}
                @if ($childDeadlineLimit)
                    — {{ setting('volunteer.tasks_show.text_13', 'يعني') }} {{ $childDeadlineLimit->format('Y-m-d H:i') }} {{ setting('volunteer.tasks_show.text_14', 'كحدّ أقصى.') }}
                @endif
            </p>

            @forelse ($subtasks as $subtask)
                <div class="py-2" style="border-top: 1px solid var(--border)">
                    <a class="flex items-center justify-between gap-2 text-sm" href="{{ route('volunteer.tasks.show', $subtask) }}">
                        <span class="truncate">{{ $subtask->title }}</span>
                        <span class="flex items-center gap-2 shrink-0">
                            @if ($subtask->batch_status === 'pending_review')
                                <x-state-badge state="warn" :label="setting('volunteer.tasks_show.label_12', 'بانتظار مراجعة الأبلاين')" />
                            @endif
                            @include('volunteer.components.deadline-counter', ['task' => $subtask])
                        </span>
                    </a>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.tasks_show.text_15', 'مفيش صب-تاسكات — فكّك شغلك لو محتاج.') }}</p>
            @endforelse
        </div>
    @elseif ($tab === 'contributors')
        <div class="card p-4">
            @forelse ($contributions as $contribution)
                <div class="py-2 text-sm" style="border-top: 1px solid var(--border)">
                    <div class="flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2">
                            {{ $contribution->contributor?->shortName() }}
                            @include('volunteer.components.rep-badge', ['user' => $contribution->contributor])
                        </span>
                        <x-state-badge :state="$contribution->status === 'approved' ? 'ok' : ($contribution->status === 'returned' ? 'danger' : 'warn')"
                                       :label="$contribution->status" />
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ $contribution->item_title }} · {{ setting('volunteer.tasks_show.text_16', 'الديدلاين الداخليّ') }}
                        {{ \Illuminate\Support\Carbon::parse($contribution->internal_deadline_at)->format('Y-m-d H:i') }}
                        · <x-icon name="spark" size="16" /> {{ rtrim(rtrim(number_format((float) $contribution->vxp_value, 2), '0'), '.') }} VXP
                    </div>
                </div>
            @empty
                <p class="text-sm mb-3" style="color: var(--text-muted)">{{ setting('volunteer.tasks_show.text_17', 'مفيش مساهمين على المهمّة دي.') }}</p>
                @if ($isOwner)
                    <button type="button" data-modal-open="invite-contributor"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_show.action_6', 'دعوة مساهم') }}</button>
                @endif
            @endforelse
        </div>
    @elseif ($tab === 'submissions')
        <div class="card p-4">
            @forelse ($submissions as $submission)
                <div class="py-2 text-sm" style="border-top: 1px solid var(--border)">
                    <div class="flex items-center justify-between gap-2">
                        <span>{{ setting('volunteer.tasks_show.text_18', 'نسخة') }} {{ $submission->version }} — {{ $submission->user?->shortName() }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $submission->created_at->diffForHumans() }}</span>
                    </div>
                    @if ($submission->link)
                        <a class="text-xs underline" href="{{ $submission->link }}" rel="noopener">{{ setting('volunteer.tasks_show.link', 'المخرج') }}</a>
                    @endif
                    @if ($submission->review_result)
                        <div class="mt-1">
                            <x-state-badge :state="$submission->review_result === 'approved' ? 'ok' : 'danger'"
                                           :label="$submission->review_result === 'approved' ? setting('volunteer.tasks_show.label_13', 'معتمد') : setting('volunteer.tasks_show.label_14', 'مُرجَع')" />
                            @if ($submission->review_feedback)
                                <span class="text-xs" style="color: var(--text-muted)">{{ $submission->review_feedback }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.tasks_show.text_19', 'لسّه مفيش تسليمات — أوّل تسليم بيوقف العدّاد.') }}</p>
            @endforelse
        </div>
    @elseif ($tab === 'comments')
        <div class="card p-4">
            <p class="text-sm" style="color: var(--text-muted)">
                {{ setting('volunteer.tasks_show.text_20', 'النقاش بنطاق المهمّة بيظهر هنا — ولسّه مفيش كومنتات.') }}
            </p>
        </div>
    @else
        <div class="card p-4">
            @forelse ($arbitrations as $arbitration)
                <div class="py-2 text-sm" style="border-top: 1px solid var(--border)">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate">{{ $arbitration->claim }}</span>
                        <x-state-badge :state="$arbitration->status === 'decided' ? 'ok' : 'warn'" :label="$arbitration->status" />
                    </div>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.tasks_show.text_21', 'مفيش تحكيمات على المهمّة دي') }} <x-icon name="check" size="16" /></p>
            @endforelse
        </div>
    @endif

    @if ($isOwner)
        @include('volunteer.tasks.partials.action-modals')
    @endif
@endsection

@section('mobile_action')
    @if ($isOwner)
        <button type="button" data-modal-open="deliver-task"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_show.action', 'تسليم') }}</button>
    @endif
@endsection

@push('scripts')
    <script>
        /* فتح البوب-أب المطلوب مباشرةً (?action=deliver|block) — يجي من سحب الكانبان */
        const action = new URLSearchParams(window.location.search).get('action');
        const map = { deliver: 'deliver-task', block: 'block-task' };
        if (action && map[action]) {
            const modal = document.getElementById(map[action]);
            if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
        }

        /* دفعة الصب-تاسكات: إضافة صفّ جديد بنفس شكل الصفّ الأوّل */
        const rows = document.querySelector('[data-subtask-rows]');
        document.querySelector('[data-add-subtask-row]')?.addEventListener('click', () => {
            const first = rows.querySelector('[data-subtask-row]');
            const clone = first.cloneNode(true);
            const index = rows.querySelectorAll('[data-subtask-row]').length;
            clone.querySelectorAll('input, textarea').forEach((field) => {
                field.value = '';
                field.name = field.name.replace(/subtasks\[\d+\]/, `subtasks[${index}]`);
            });
            rows.appendChild(clone);
        });
    </script>
@endpush
