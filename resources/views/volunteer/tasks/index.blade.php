@extends('layouts.volunteer')

@section('title', setting('volunteer.tasks.title', 'مهامّي'))

@section('content')
    @php
        $labels = \App\Services\Volunteer\Tasks\TaskStatus::labels();
        $isBoardView = $view === 'kanban';
    @endphp

    <x-page-header :title="setting('volunteer.tasks.title', 'مهامّي')" :subtitle="setting('volunteer.tasks.subtitle', 'كلّ ما عليك، بحالته وعدّاده.')"
                   :breadcrumbs="[
                       ['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => route('volunteer.overview')],
                       ['label' => setting('volunteer.tasks.title', 'مهامّي')],
                   ]">
        <x-slot:action>
            @if ($canCreate)
                @if ($createBlockedReason)
                    {{-- القيد يُشرَح لحظة كسره فقط (2.15-د) --}}
                    <button type="button" disabled
                            class="rounded-xl px-4 py-2 text-sm font-semibold opacity-50 cursor-not-allowed"
                            style="background: var(--surface-raised)"
                            title="{{ $createBlockedReason }}">{{ setting('volunteer.tasks.action', 'مهمّة جديدة') }}</button>
                @else
                    <button type="button" data-modal-open="new-task"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks.action', 'مهمّة جديدة') }}</button>
                @endif
            @endif
        </x-slot:action>
    </x-page-header>

    {{-- عدّاد سقف الانشغال كحبّة بارزة + السقف الشخصيّ الكلّي عبر العضويّات (23-3.1) --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        @php $capSymbols = state_color($summary['state']); $personalSymbols = state_color($summary['personal_state']); @endphp

        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-bold cursor-help"
              title="{{ setting('volunteer.tasks.tooltip', 'سقف انشغالك في العضويّة النشطة — يُحسب على المهامّ غير المكتملة والمساهمات') }}"
              style="background: color-mix(in srgb, var(--color-state-{{ $capSymbols['color'] }}) 18%, transparent);
                     color: var(--color-state-{{ $capSymbols['color'] }})">
            <span aria-hidden="true">{{ $capSymbols['icon'] }}</span>
            {{ setting('volunteer.tasks.text', 'سقف الانشغال') }} {{ $summary['display'] }}
        </span>

        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs cursor-help"
              title="{{ setting('volunteer.tasks.tooltip_2', 'السقف الشخصيّ الكلّي فوق سقوف العضويّات — يمنع السحب والإنشاء عند بلوغه') }}"
              style="background: color-mix(in srgb, var(--color-state-{{ $personalSymbols['color'] }}) 14%, transparent);
                     color: var(--color-state-{{ $personalSymbols['color'] }})">
            <span aria-hidden="true">{{ $personalSymbols['icon'] }}</span>
            {{ setting('volunteer.tasks.text_2', 'الشخصيّ الكلّي') }} {{ $summary['personal_display'] }}
        </span>

        <span class="flex-1"></span>

        {{-- تبديل العرض: قائمة/كانبان — وعلى الموبايل الكانبان يتحوّل قائمة تلقائيًّا --}}
        <div class="flex items-center gap-1 text-sm">
            <a href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}"
               class="rounded-xl px-3 py-1.5"
               style="{{ $isBoardView ? 'background: var(--surface-raised)' : 'background: var(--color-brand-500); color:#04201c' }}">{{ setting('volunteer.tasks.link', 'قائمة') }}</a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'kanban']) }}"
               class="rounded-xl px-3 py-1.5"
               style="{{ $isBoardView ? 'background: var(--color-brand-500); color:#04201c' : 'background: var(--surface-raised)' }}">{{ setting('volunteer.tasks.link_2', 'كانبان') }}</a>
        </div>
    </div>

    {{-- 3 فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4) --}}
    <x-filters :action="route('volunteer.tasks.index')">
        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.status', 'الحالة') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.tasks.option', 'كلّ الحالات') }}</option>
                @foreach ($labels as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>
                        {{ $label }} ({{ $counts[$key] ?? 0 }})
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.type', 'النوع') }}</span>
            <select name="type" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.tasks.option_2', 'كلّ الأنواع') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected((int) ($filters['type'] ?? 0) === $type->id)>{{ $type->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('volunteer.tasks.placeholder', 'عنوان أو رقم مهمّة') }}"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks.action_2', 'فلترة') }}</button>

        <x-slot:advanced>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="due_soon" value="1" @checked($filters['due_soon'])>
                {{ setting('volunteer.tasks.field', 'تقترب ديدلايناتها') }}
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($tasks->isEmpty())
        <x-empty :message="setting('volunteer.tasks.empty', 'مفيش مهامّ عليك دلوقتي — شوف لوحة المهام العامّة')"
                 :action="setting('volunteer.tasks.action_3', 'لوحة المهام العامّة')" :href="route('volunteer.tasks.board')" />
    @elseif ($isBoardView)
        {{-- كانبان بالسحب — وعلى الموبايل يتحوّل قائمة رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="md:flex md:gap-3 md:min-w-0 overflow-x-auto space-y-4 md:space-y-0" data-kanban>
            @foreach ($columns as $status)
                <section class="md:w-64 md:shrink-0" data-kanban-column="{{ $status }}">
                    <div class="text-sm font-semibold mb-2 flex items-center gap-2">
                        <x-state-badge :state="\App\Services\Volunteer\Tasks\TaskStatus::state($status)" :label="$labels[$status]" />
                        <span style="color: var(--text-muted)">{{ $counts[$status] ?? 0 }}</span>
                    </div>

                    <div class="space-y-2 rounded-xl p-2 min-h-16" style="background: var(--surface-sunken)">
                        @foreach ($tasks->where('status', $status) as $task)
                            @include('volunteer.tasks.partials.card', ['task' => $task, 'draggable' => true])
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @else
        <div class="space-y-2">
            @foreach ($tasks as $task)
                @include('volunteer.tasks.partials.card', ['task' => $task, 'draggable' => false])
            @endforeach
        </div>
    @endif

    @if ($canCreate && ! $createBlockedReason)
        @include('volunteer.tasks.partials.new-task-modal')
    @endif
@endsection

@section('mobile_action')
    @if ($canCreate && ! $createBlockedReason)
        <button type="button" data-modal-open="new-task"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks.action', 'مهمّة جديدة') }}</button>
    @elseif ($createBlockedReason)
        <p class="text-xs text-center" style="color: var(--text-muted)">{{ $createBlockedReason }}</p>
    @endif
@endsection

@push('scripts')
    {{--
        نصّ تحذير الإسناد يُحسَب هنا خارج <script> — فلا يبقى حرفٌ عربيّ محروق
        داخل السكربت نفسه، ويصل الجافاسكربت مُحلولًا جاهزًا عبر @json (2.13).
    --}}
    @php
        $ownerWarningTemplate = (string) setting('volunteer.tasks_new_task_modal.text_2', ':name عند :load من :cap — الإسناد فوق طاقته.');
    @endphp

    <script>
        /* كانبان بالسحب: الإفلات ينقل لصفحة المهمّة على الفعل الصحيح،
           لأنّ تغيير الحالة يمرّ بأفعالها المعتمدة (تسليم/تعثّر) لا بسحبٍ صامت. */
        document.querySelectorAll('[data-kanban] [data-task-card]').forEach((card) => {
            card.addEventListener('dragstart', (e) => e.dataTransfer.setData('text/plain', card.dataset.taskCard));
        });

        document.querySelectorAll('[data-kanban-column]').forEach((column) => {
            column.addEventListener('dragover', (e) => e.preventDefault());
            column.addEventListener('drop', (e) => {
                e.preventDefault();
                const id = e.dataTransfer.getData('text/plain');
                const target = column.dataset.kanbanColumn;
                const actions = { delivered: 'deliver', blocked: 'block' };
                if (!id) return;
                const action = actions[target];
                window.location = action
                    ? `/volunteer/tasks/${id}?action=${action}`
                    : `/volunteer/tasks/${id}`;
            });
        });

        /* الإسناد المباشر لا يخترق السقف بصمت: تحذير إلزاميّ يظهر لحظة اختيار
           عضوٍ تجاوز سقف دوره — بلا منع الإسناد (23-3.1). */
        (function () {
            const select = document.getElementById('new-task-owner');
            const warning = document.getElementById('new-task-owner-warning');
            if (!select || !warning) return;

            const template = @json($ownerWarningTemplate);

            const refresh = () => {
                const opt = select.options[select.selectedIndex];
                const cap = opt?.dataset.cap ?? '';
                const load = opt?.dataset.load ?? '';

                if (opt && opt.value && cap !== '' && Number(load) >= Number(cap)) {
                    warning.textContent = template
                        .replace(':name', opt.textContent.split('—')[0].trim())
                        .replace(':load', load)
                        .replace(':cap', cap);
                    warning.hidden = false;
                } else {
                    warning.hidden = true;
                }
            };

            select.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endpush
