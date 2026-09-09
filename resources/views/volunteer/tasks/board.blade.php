@extends('layouts.volunteer')

@section('title', setting('volunteer.tasks_board.title', 'لوحة المهام العامّة'))

@section('content')
    @php
        $atCap = $summary['reason'] !== null;
        $capSymbols = state_color($summary['state']);
    @endphp

    {{--
     | ⛔ لا زرّ إضافة هنا: إضافة المهامّ العامّة للأدمن ومشرف عام التطوّع حصرًا (24.4).
     | والفعل الرئيسيّ الوحيد للقادة هو ترشيح بند.
    --}}
    <x-page-header :title="setting('volunteer.tasks_board.title', 'لوحة المهام العامّة')"
                   :subtitle="setting('volunteer.tasks_board.subtitle', 'مهامّ مفتوحة تسحبها بنفسك وتكسب VXP.')"
                   :breadcrumbs="[
                       ['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => route('volunteer.overview')],
                       ['label' => setting('volunteer.tasks_board.title', 'لوحة المهام العامّة')],
                   ]">
        <x-slot:action>
            @if ($canNominate)
                <button type="button" data-modal-open="nominate-item"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_board.action', 'رشّح بندًا') }}</button>
            @endif
        </x-slot:action>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-bold"
              style="background: color-mix(in srgb, var(--color-state-{{ $capSymbols['color'] }}) 18%, transparent);
                     color: var(--color-state-{{ $capSymbols['color'] }})">
            <span aria-hidden="true">{{ $capSymbols['icon'] }}</span>
            @if ($summary['remaining'] === null)
                {{ setting('volunteer.tasks_board.text', 'سحبك مفتوح — بلا سقف') }}
            @else
                {{ setting('volunteer.tasks_board.text_2', 'عندك مكان لـ') }}{{ $summary['remaining'] }}
            @endif
        </span>

        <span class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.tasks_board.text_3', 'سقف انشغالك') }} {{ $summary['display'] }}</span>
    </div>

    <x-filters :action="route('volunteer.tasks.board')">
        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.type', 'النوع') }}</span>
            <select name="type" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.tasks_board.option', 'كلّ الأنواع') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected((int) ($filters['type'] ?? 0) === $type->id)>{{ $type->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('volunteer.tasks_board.placeholder', 'عنوان المهمّة') }}"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="due_soon" value="1" @checked($filters['due_soon'])>
            {{ setting('volunteer.tasks_board.field', 'قرب الديدلاين') }}
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_board.action_2', 'فلترة') }}</button>
    </x-filters>

    @if ($atCap)
        {{-- بلغتَ السقف: الكروت تظلّ مقروءة والزرّ معطَّل بسطر يشرح (24.4) --}}
        <div class="card p-3 mb-4 text-sm" style="border-color: var(--color-state-danger)">
            <span aria-hidden="true">◉</span> {{ $summary['reason'] }}
        </div>
    @endif

    @if ($canApprove && $nominations->isNotEmpty())
        {{--
         | ترشيحات معلَّقة — مشرف عام التطوّع والأدمن حصرًا (8.1):
         | «يستقبل ترشيحات القادة ويقرّر ما ينزل» — الاعتماد يحوّل جمهور
         | البند فعليًّا إلى `public_board`، والرفض يُبقيه على حاله.
        --}}
        <div class="card p-4 mb-4">
            <h2 class="font-bold text-sm mb-3">{{ setting('volunteer.tasks_board.nominations_title', 'ترشيحات معلَّقة') }}</h2>
            <div class="space-y-2">
                @foreach ($nominations as $item)
                    <div class="flex items-center justify-between gap-2 rounded-xl p-3" style="background: var(--surface-sunken)">
                        <div class="text-sm">
                            <span class="font-semibold">{{ $item->name }}</span>
                            @if ($item->work_package?->entity)
                                <span class="text-xs" style="color: var(--text-muted)">— {{ $item->work_package->entity->name_ar }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <form method="post" action="{{ route('volunteer.tasks.nominations.approve', $item) }}">
                                @csrf
                                <button type="submit" class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_board.nominations_approve', 'اعتماد') }}</button>
                            </form>
                            <form method="post" action="{{ route('volunteer.tasks.nominations.reject', $item) }}">
                                @csrf
                                <button type="submit" class="rounded-xl px-3 py-1.5 text-xs font-semibold"
                                        style="background: var(--surface-raised)">{{ setting('volunteer.tasks_board.nominations_reject', 'رفض') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($tasks->isEmpty())
        <x-empty :message="setting('volunteer.tasks_board.empty', 'مفيش مهامّ عامّة متاحة حاليًّا — تابع تاب الإشعارات')"
                 :action="setting('volunteer.tasks_board.action_3', 'إشعارات التطوّع')"
                 :href="\Illuminate\Support\Facades\Route::has('volunteer.notifications') ? route('volunteer.notifications') : route('volunteer.overview')" />
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($tasks as $task)
                <article class="card p-4 flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="font-semibold text-sm">
                            <span aria-hidden="true" title="{{ $task->entity?->name_ar }}">{{ $task->entity?->icon }}<x-icon name="entity" size="14" /></span>
                            {{ $task->title }}
                        </div>
                        @if ($task->task_type)
                            <span class="text-xs shrink-0" style="color: var(--text-muted)">{{ $task->task_type->name_ar }}</span>
                        @endif
                    </div>

                    @if ($task->deliverable_spec)
                        <p class="text-xs truncate" style="color: var(--text-muted)" title="{{ $task->deliverable_spec }}">
                            {{ $task->deliverable_spec }}
                        </p>
                    @endif

                    <div class="flex items-center justify-between gap-2">
                        <span class="text-lg font-extrabold" style="color: var(--color-brand-500)">
                            {{ rtrim(rtrim(number_format((float) $task->vxp_value, 2), '0'), '.') }} VXP
                        </span>
                        @include('volunteer.components.deadline-counter', ['task' => $task])
                    </div>

                    @if ($atCap)
                        <div>
                            <button type="button" disabled title="{{ $summary['reason'] }}"
                                    class="w-full rounded-xl px-4 py-2 text-sm font-semibold opacity-50 cursor-not-allowed"
                                    style="background: var(--surface-raised)">{{ setting('volunteer.tasks_board.action_4', 'اسحب المهمّة') }}</button>
                            <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $summary['reason'] }}</p>
                        </div>
                    @else
                        <form method="post" action="{{ route('volunteer.tasks.claim', $task) }}">
                            @csrf
                            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_board.action_4', 'اسحب المهمّة') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($canNominate)
        @push('modals')
            <x-modal id="nominate-item" :title="setting('volunteer.tasks_board.tooltip', 'رشّح بندًا ليصير عامًّا')">
                <form method="post" action="{{ route('volunteer.tasks.nominate') }}" class="space-y-3">
                    @csrf

                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('volunteer.common.item', 'البند') }}</span>
                        <select name="work_item_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">{{ setting('volunteer.tasks_board.option_2', 'اختر بندًا…') }}</option>
                            @foreach ($nominatable as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('volunteer.common.reason', 'السبب') }}</span>
                        <textarea name="reason" rows="3" required class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>

                    <x-form.input name="suggested_vxp" :label="setting('volunteer.tasks_board.label', 'القيمة المقترحة (VXP)')" type="number" step="0.01" min="0" />

                    <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.tasks_board.field_2', 'الترشيح يُرفَع لمشرف عام التطوّع — وهو صاحب القرار.') }}</p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                                style="background: var(--surface-raised)">{{ setting('volunteer.common.cancel', 'إلغاء') }}</button>
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.tasks_board.action_5', 'ارفع الترشيح') }}</button>
                    </div>
                </form>
            </x-modal>
        @endpush
    @endif
@endsection
