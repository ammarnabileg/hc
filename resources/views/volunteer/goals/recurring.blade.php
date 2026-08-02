@extends('layouts.app')

@section('title', 'نوبتي من البنود المتكرّرة')

@section('content')
    <x-page-header
        title="نوبتي من البنود المتكرّرة"
        :subtitle="'أسبوع '.$week->format('Y/m/d').' — حملك الحاليّ: '.$load.' مهمّة مفتوحة'"
        :breadcrumbs="[['label' => 'المشروع التشغيليّ', 'url' => route('volunteer.project')], ['label' => 'نوبتي']]">
        <x-slot:action>
            <span class="rounded-full px-3 py-1 text-xs font-bold"
                  style="background: var(--surface-raised); border: 1px solid var(--border)">
                هذا الأسبوع: {{ $tasks->count() }}
            </span>
        </x-slot:action>
    </x-page-header>

    <div class="flex items-center justify-between gap-2 mb-4">
        <a href="{{ route('volunteer.recurring', ['week' => $weekOffset - 1]) }}"
           class="btn rounded-xl px-3 py-2 text-sm motion-standard"
           style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text); min-height: 44px">الأسبوع السابق ›</a>

        <a href="{{ route('volunteer.recurring') }}" class="text-sm" style="color: var(--color-brand-500)">هذا الأسبوع</a>

        <a href="{{ route('volunteer.recurring', ['week' => $weekOffset + 1]) }}"
           class="btn rounded-xl px-3 py-2 text-sm motion-standard"
           style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text); min-height: 44px">‹ الأسبوع التالي</a>
    </div>

    @if ($tasks->isEmpty())
        <x-empty message="مفيش نوبة عليك في الفترة دي" action="افتح المشروع التشغيليّ" :href="route('volunteer.project')" />
    @else
        {{-- تايم-لاين أسبوعيّ: عمود لكلّ يوم على الديسكتوب، وقائمة رأسيّة على الموبايل --}}
        <div class="space-y-3 md:space-y-0 md:grid md:grid-cols-7 md:gap-2">
            @foreach ($days as $day)
                <section class="card p-3" style="{{ $day['is_today'] ? 'border: 1px solid var(--color-brand-500)' : '' }}">
                    <header class="text-xs font-bold mb-2 flex items-center justify-between">
                        <span>{{ $day['date']->translatedFormat('l') }}</span>
                        <span style="color: var(--text-muted)">{{ $day['date']->format('m/d') }}</span>
                    </header>

                    @forelse ($day['tasks'] as $task)
                        @php $state = $rollup->deadlineState($task->deadline_at); @endphp
                        <article class="rounded-xl p-2 mb-2" style="background: var(--surface-raised)">
                            <div class="text-xs font-semibold">{{ $task->title }}</div>

                            <div class="flex items-center gap-1 mt-1 flex-wrap">
                                <x-state-badge :state="$state"
                                               :label="$task->deadline_at?->format('m/d H:i') ?? 'بلا ديدلاين'" />
                                <span class="text-xs" style="color: var(--text-muted)">
                                    {{ rtrim(rtrim(number_format((float) $task->vxp_value, 2), '0'), '.') }} VXP
                                </span>
                            </div>

                            <div class="text-xs mt-1">
                                <x-state-badge :state="$task->status === 'approved' ? 'ok' : ($task->status === 'no_delivery' ? 'danger' : 'warn')"
                                               :label="$statuses[$task->status] ?? $task->status" />
                            </div>

                            @if ($task->assigned_by_balancer)
                                {{-- ملاحظة الموازن: الدور يلفّ للأقلّ حملًا لا بالتساوي الأعمى (23 — 1.8) --}}
                                <p class="text-xs mt-2 leading-relaxed" style="color: var(--color-brand-500)">
                                    وُجِّه إليك لأنّك الأقلّ حملًا حاليًّا
                                </p>
                            @endif
                        </article>
                    @empty
                        <p class="text-xs" style="color: var(--text-muted)">—</p>
                    @endforelse
                </section>
            @endforeach
        </div>
    @endif
@endsection
