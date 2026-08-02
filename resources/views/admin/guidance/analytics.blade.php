@extends('layouts.app')

@section('title', 'تحليلات المنشور')

@section('content')
    {{-- تحليلات عميقة: نسبة القراءة ومَن قرأ ومَن أقرّ (12.6-أ) --}}
    <x-page-header
        :title="'تحليلات: '.$announcement->title"
        subtitle="مين قرأ ومين أقرّ — والإقرار محسوب مرّة واحدة لكلّ منشور."
        :breadcrumbs="[
            ['label' => 'التعليمات', 'url' => route('admin.guidance.index')],
            ['label' => 'تحليلات'],
        ]">
        <x-slot:action>
            @can('announcements.export')
                {{-- تصدير التحليلات (12.6-أ) — الفعل الرئيسيّ الوحيد في الشاشة --}}
                <a href="{{ route('admin.guidance.analytics.export', $announcement) }}"
                   class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">
                    <x-icon name="download" size="16" /> تصدير CSV
                </a>
            @endcan
        </x-slot:action>
    </x-page-header>

    @php
        /* «أفضل توقيت» (12.6-أ): من لحظات القراءة نفسها — امتى يفتحون فعلًا. */
        $weekdayNames = (array) setting('announcements.analytics.weekday_labels', ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']);
        $bestHour = $bestTime['best_hour'];
        $bestLabel = $bestTime['sample'] > 0
            ? ($weekdayNames[$bestTime['best_day']] ?? '—').' · '.str_pad((string) $bestHour, 2, '0', STR_PAD_LEFT).':00'
            : 'لسّه مافيش قراءات';
    @endphp

    {{-- 4 كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi label="نسبة القراءة" :value="($stats['rate'] ?? 0).'%'" icon="eye" />
        <x-kpi label="قراءات" :value="$stats['reads'] ?? 0" icon="article" />
        <x-kpi label="إقرارات" :value="$stats['acks'] ?? 0" icon="check" />
        <x-kpi label="أفضل توقيت" :value="$bestLabel" icon="clock" />
    </div>

    @if ($bestTime['sample'] > 0)
        <div class="card p-4 mb-4">
            <div class="text-sm font-semibold flex items-center gap-2"><x-icon name="chart" size="16" /> ساعات القراءة</div>
            <p class="text-xs mt-1" style="color: var(--text-muted)">
                محسوبة من {{ $bestTime['sample'] }} قراءة في آخر {{ setting('announcements.analytics.best_time_days', 90) }} يومًا — ابعث في الساعة دي يوصلك أعلى قراءة.
            </p>
            <div class="mt-3 flex items-end gap-1 overflow-x-auto" style="min-height: 4rem">
                @php $peak = max(1, max($bestTime['hours'])); @endphp
                @foreach ($bestTime['hours'] as $hour => $count)
                    <div class="flex flex-col items-center gap-1 shrink-0" style="width: 1.5rem"
                         title="{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00 — {{ $count }} قراءة">
                        <div class="w-full rounded-t"
                             style="height: {{ max(2, (int) round(($count / $peak) * 48)) }}px; background: {{ $hour === $bestHour ? 'var(--color-brand-500)' : 'var(--surface-raised)' }}"></div>
                        <span class="text-[10px]" style="color: var(--text-muted)">{{ $hour }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($poll)
        <div class="card p-4 mb-4">
            <div class="text-sm font-semibold">نتيجة الاستطلاع</div>
            <div class="text-xs mt-1" style="color: var(--text-muted)">
                {{ $poll['public'] ? 'النتيجة معروضة للمستخدمين.' : ($poll['closed'] ? 'كانت مخفيّة، والاستطلاع اتقفل فبانت لهم.' : 'النتيجة مخفيّة عن المستخدمين لحدّ ما الاستطلاع يقفل — وما بتوصلش متصفّحهم أصلًا.') }}
            </div>
            <div class="mt-3 space-y-2">
                @foreach ($poll['options'] as $index => $option)
                    @php
                        $count = $poll['tally']['counts'][$index] ?? 0;
                        $share = $poll['tally']['total'] > 0 ? (int) round(($count / $poll['tally']['total']) * 100) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs">
                            <span>{{ $option }}</span>
                            <span style="color: var(--text-muted)">{{ $count }} ({{ $share }}%)</span>
                        </div>
                        <div class="h-1 rounded-full overflow-hidden mt-1" style="background: var(--surface-sunken)">
                            <div class="h-full" style="width: {{ $share }}%; background: var(--color-brand-500)"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($readers->isEmpty())
        <x-empty message="محدّش فتح المنشور لسّه." />
    @else
        <div class="space-y-2">
            @foreach ($readers as $read)
                <div class="card p-3 flex items-center gap-3">
                    <x-avatar :user="$read->user" size="8" />
                    <div class="flex-1 min-w-0">
                        <div class="text-sm truncate">{{ $read->user?->name }}</div>
                        <div class="text-xs" style="color: var(--text-muted)">
                            <span title="{{ $read->read_at }}">{{ $read->read_at?->diffForHumans() ?? '—' }}</span>
                        </div>
                    </div>
                    @if ($read->acknowledged_at)
                        <x-state-badge state="ok" label="أقرّ" />
                    @else
                        <x-state-badge state="idle" label="قرأ فقط" />
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection

@section('mobile_action')
    @can('announcements.export')
        <a href="{{ route('admin.guidance.analytics.export', $announcement) }}"
           class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c">تصدير CSV</a>
    @endcan
@endsection
