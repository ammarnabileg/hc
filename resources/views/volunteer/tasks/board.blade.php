@extends('layouts.volunteer')

@section('title', 'لوحة المهام العامّة')

@section('content')
    @php
        $atCap = $summary['reason'] !== null;
        $capSymbols = state_color($summary['state']);
    @endphp

    {{--
     | ⛔ لا زرّ إضافة هنا: إضافة المهامّ العامّة للأدمن ومشرف عام التطوّع حصرًا (24.4).
     | والفعل الرئيسيّ الوحيد للقادة هو ترشيح بند.
    --}}
    <x-page-header title="لوحة المهام العامّة"
                   subtitle="مهامّ مفتوحة تسحبها بنفسك وتكسب VXP."
                   :breadcrumbs="[
                       ['label' => 'لوحة التطوّع', 'url' => route('volunteer.overview')],
                       ['label' => 'لوحة المهام العامّة'],
                   ]">
        <x-slot:action>
            @if ($canNominate)
                <button type="button" data-modal-open="nominate-item"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">رشّح بندًا</button>
            @endif
        </x-slot:action>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-bold"
              style="background: color-mix(in srgb, var(--color-state-{{ $capSymbols['color'] }}) 18%, transparent);
                     color: var(--color-state-{{ $capSymbols['color'] }})">
            <span aria-hidden="true">{{ $capSymbols['icon'] }}</span>
            @if ($summary['remaining'] === null)
                سحبك مفتوح — بلا سقف
            @else
                عندك مكان لـ{{ $summary['remaining'] }}
            @endif
        </span>

        <span class="text-xs" style="color: var(--text-muted)">سقف انشغالك {{ $summary['display'] }}</span>
    </div>

    <x-filters :action="route('volunteer.tasks.board')">
        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">النوع</span>
            <select name="type" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">كلّ الأنواع</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected((int) ($filters['type'] ?? 0) === $type->id)>{{ $type->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="عنوان المهمّة"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="due_soon" value="1" @checked($filters['due_soon'])>
            قرب الديدلاين
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">فلترة</button>
    </x-filters>

    @if ($atCap)
        {{-- بلغتَ السقف: الكروت تظلّ مقروءة والزرّ معطَّل بسطر يشرح (24.4) --}}
        <div class="card p-3 mb-4 text-sm" style="border-color: var(--color-state-danger)">
            <span aria-hidden="true">◉</span> {{ $summary['reason'] }}
        </div>
    @endif

    @if ($tasks->isEmpty())
        <x-empty message="مفيش مهامّ عامّة متاحة حاليًّا — تابع تاب الإشعارات"
                 action="إشعارات التطوّع"
                 :href="\Illuminate\Support\Facades\Route::has('volunteer.notifications') ? route('volunteer.notifications') : route('volunteer.overview')" />
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($tasks as $task)
                <article class="card p-4 flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="font-semibold text-sm">
                            <span aria-hidden="true" title="{{ $task->entity?->name_ar }}">{{ $task->entity?->icon ?? '🏛️' }}</span>
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
                                    style="background: var(--surface-raised)">اسحب المهمّة</button>
                            <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $summary['reason'] }}</p>
                        </div>
                    @else
                        <form method="post" action="{{ route('volunteer.tasks.claim', $task) }}">
                            @csrf
                            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">اسحب المهمّة</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($canNominate)
        @push('modals')
            <x-modal id="nominate-item" title="رشّح بندًا ليصير عامًّا">
                <form method="post" action="{{ route('volunteer.tasks.nominate') }}" class="space-y-3">
                    @csrf

                    <label class="block">
                        <span class="block text-sm mb-1">البند</span>
                        <select name="work_item_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">اختر بندًا…</option>
                            @foreach ($nominatable as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="block text-sm mb-1">السبب</span>
                        <textarea name="reason" rows="3" required class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>

                    <x-form.input name="suggested_vxp" label="القيمة المقترحة (VXP)" type="number" step="0.01" min="0" />

                    <p class="text-xs" style="color: var(--text-muted)">الترشيح يُرفَع لمشرف عام التطوّع — وهو صاحب القرار.</p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" data-modal-close class="rounded-xl px-4 py-2 text-sm"
                                style="background: var(--surface-raised)">إلغاء</button>
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">ارفع الترشيح</button>
                    </div>
                </form>
            </x-modal>
        @endpush
    @endif
@endsection
