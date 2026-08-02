@extends('layouts.app')

@section('title', 'المشروع التشغيليّ')

@php
    $entityName = $project?->entity?->name_ar ?? 'كيانك';
@endphp

@section('content')
    <x-page-header
        :title="'المشروع التشغيليّ لـ'.$entityName"
        subtitle="وعاء إيقاع العمل اليوميّ — وهو ما يحقّق «كلّ مهمّة مربوطة ببند» بلا خنق."
        :breadcrumbs="[['label' => 'الأهداف والمَعالِم', 'url' => route('volunteer.goals')], ['label' => 'المشروع التشغيليّ']]">
        <x-slot:action>
            {{-- شارة «دائم — لا يُغلَق» (23 — 1.8) --}}
            <x-state-badge state="honor" label="دائم — لا يُغلَق" />
            @if ($project)
                <x-state-badge :state="$project->status === 'active' ? 'ok' : 'idle'"
                               :label="$project->status === 'active' ? 'الاعتماد الأوّل تمّ' : 'بانتظار الاعتماد الأوّل'" />
            @endif
        </x-slot:action>
    </x-page-header>

    <x-filters :action="route('volunteer.project')">
        <input type="hidden" name="entity" value="{{ $entityId }}">

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">التكرار</span>
            <select name="recurrence" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($recurrences as $key => $label)
                    <option value="{{ $key }}" @selected($filters['recurrence'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الجمهور</span>
            <select name="audience" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($audiences as $key => $label)
                    <option value="{{ $key }}" @selected($filters['audience'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="ابحث باسم البند…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($items->isEmpty())
        <x-empty message="المشروع التشغيليّ جاهز — لسّه بلا بنود" action="شوف نوبتك" :href="route('volunteer.recurring')" />
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach ($items as $item)
                <article class="card p-4 animate-fadeup">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                        <button type="button" data-modal-open="item-{{ $item->id }}" class="font-bold text-right">
                            <x-icon name="refresh" size="16" /> {{ $item->name }}
                        </button>
                        <div class="flex items-center gap-1 shrink-0">
                            <x-state-badge state="ok" :label="$recurrences[$item->recurrence] ?? 'غير محدَّد'" />
                            @if ($item->missed_count > 0)
                                <x-state-badge state="danger" :label="'فائتة: '.$item->missed_count" />
                            @endif
                        </div>
                    </div>

                    <p class="text-xs mt-2" style="color: var(--text-muted)">{{ $item->brief ?: 'بلا بريف ثابت بعد.' }}</p>

                    <dl class="grid grid-cols-2 gap-2 text-xs mt-3">
                        <div>
                            <dt style="color: var(--text-muted)">شكل المخرجات</dt>
                            <dd>{{ $item->deliverable_spec ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt style="color: var(--text-muted)">الديدلاين النسبيّ</dt>
                            <dd>بعد التوليد بـ{{ (int) $item->relative_deadline_hours }} ساعة</dd>
                        </div>
                        <div>
                            <dt style="color: var(--text-muted)">قيمة VXP</dt>
                            <dd>{{ rtrim(rtrim(number_format((float) $item->vxp_pool, 2), '0'), '.') }}</dd>
                        </div>
                        <div>
                            <dt style="color: var(--text-muted)">الجمهور</dt>
                            <dd>
                                {{ $audiences[$item->audience_mode] ?? '—' }}
                                @if ($item->audience_mode === 'individual')
                                    <span style="color: var(--text-muted)">· {{ $item->assigned_user?->shortName() ?? 'بلا شخص' }}</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt style="color: var(--text-muted)">آخر توليد</dt>
                            <dd>{{ $item->last_generated_at?->format('Y/m/d H:i') ?? 'لسّه' }}</dd>
                        </div>
                        <div>
                            <dt style="color: var(--text-muted)">التوليد التالي</dt>
                            <dd>{{ $item->next_generation_at?->format('Y/m/d H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>
    @endif

    @push('modals')
        @foreach ($items as $item)
            <x-modal :id="'item-'.$item->id" :title="'سجلّ توليدات — '.$item->name">
                <div class="space-y-2 text-sm">
                    @forelse ($history[$item->id] ?? [] as $task)
                        @php
                            $state = match ($task->status) {
                                'approved' => 'ok',
                                'no_delivery' => 'danger',
                                'closed' => 'idle',
                                default => 'warn',
                            };
                        @endphp
                        <div class="grid grid-cols-3 gap-2 items-center rounded-xl px-3 py-2"
                             style="background: var(--surface-raised)">
                            <span class="text-xs">{{ \Illuminate\Support\Carbon::parse($task->created_at)->format('Y/m/d') }}</span>
                            <span class="text-xs">{{ $task->owner?->shortName() ?? 'اللوحة العامّة' }}</span>
                            <span class="text-xs">
                                <x-state-badge :state="$state" :label="$task->status === 'no_delivery' ? 'فائتة ⟵ مسار عدم التسليم' : $task->status" />
                            </span>
                        </div>
                    @empty
                        <p style="color: var(--text-muted)">لسّه مفيش توليدات للبند ده.</p>
                    @endforelse
                </div>
            </x-modal>
        @endforeach
    @endpush
@endsection

@section('mobile_action')
    <a href="{{ route('volunteer.recurring') }}"
       class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c; min-height: 44px">نوبتي من البنود المتكرّرة</a>
@endsection
