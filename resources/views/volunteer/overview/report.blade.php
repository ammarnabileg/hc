@extends('layouts.volunteer')

@section('title', 'تقريري الأسبوعيّ')

@section('content')
    @php
        $trend = $netRep <=> $previousRep;
        $repState = $netRep > 0 ? 'ok' : ($netRep < 0 ? 'danger' : 'idle');
        $values = collect($curve)->pluck('running');
        $min = min($values->min(), 0);
        $max = max($values->max(), 0);
        $span = ($max - $min) ?: 1;
    @endphp

    <x-page-header title="تقريري الأسبوعيّ"
                   subtitle="{{ $start->format('Y-m-d') }} ← {{ $end->format('Y-m-d') }}"
                   :breadcrumbs="[
                       ['label' => 'لوحة التطوّع', 'url' => route('volunteer.overview')],
                       ['label' => 'تقريري الأسبوعيّ'],
                   ]">
        <x-slot:action>
            <div class="flex items-center gap-2 text-sm">
                <a class="rounded-xl px-3 py-2" style="background: var(--surface-raised)"
                   href="{{ route('volunteer.report', ['w' => $offset + 1]) }}" aria-label="الأسبوع السابق">‹</a>
                <a class="rounded-xl px-3 py-2" style="background: var(--surface-raised)"
                   href="{{ route('volunteer.report') }}">هذا الأسبوع</a>
                <a class="rounded-xl px-3 py-2 {{ $offset === 0 ? 'opacity-40 pointer-events-none' : '' }}"
                   style="background: var(--surface-raised)"
                   href="{{ route('volunteer.report', ['w' => max(0, $offset - 1)]) }}" aria-label="الأسبوع التالي">›</a>
            </div>
        </x-slot:action>
    </x-page-header>

    {{-- صفّ 4 كروت: صافي Rep · VXP · مهامّ · حضور — ⛔ وبلا أيّ مؤشّر لمخاطر الفقدان (24.4) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <x-kpi label="صافي Rep للأسبوع" :value="$netRep" :state="$repState"
               :icon="$trend > 0 ? '↑' : ($trend < 0 ? '↓' : '→')"
               :hint="'الأسبوع اللي فات: '.$previousRep" />
        <x-kpi label="VXP المكتسَب" :value="$vxp" icon="spark" />
        <x-kpi label="مهامّ مُسلَّمة" :value="$tasksDone" icon="check"
               :hint="$tasksLate.' منها اتسلّمت متأخّرة'" />
        <x-kpi label="حضور الاجتماعات" :value="$attendance.'%'" icon="calendar" />
    </div>

    {{-- منحنى Rep اليوميّ — مرسوم بيدنا بـSVG بلا أيّ مكتبة خارجيّة --}}
    <div class="card p-4 mb-5">
        <div class="text-sm mb-3">منحنى Rep اليوميّ (تراكميّ داخل الأسبوع)</div>

        @if ($values->sum() == 0 && $events->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">أسبوع هادي — مفيش حركات مسجّلة.</p>
        @else
            <svg viewBox="0 0 700 160" class="w-full" style="height: 160px" role="img"
                 aria-label="منحنى درجة الالتزام اليوميّ خلال الأسبوع">
                <line x1="0" y1="{{ 150 - ((0 - $min) / $span) * 130 }}" x2="700"
                      y2="{{ 150 - ((0 - $min) / $span) * 130 }}"
                      stroke="var(--border)" stroke-width="1" />

                <polyline fill="none" stroke="var(--color-brand-500)" stroke-width="3"
                          stroke-linejoin="round" stroke-linecap="round"
                          points="@foreach ($curve as $i => $point){{ 30 + $i * 106 }},{{ 150 - (($point['running'] - $min) / $span) * 130 }} @endforeach" />

                @foreach ($curve as $i => $point)
                    <circle cx="{{ 30 + $i * 106 }}" cy="{{ 150 - (($point['running'] - $min) / $span) * 130 }}"
                            r="4" fill="var(--color-brand-400)">
                        <title>{{ $point['label'] }} — اليوم: {{ $point['value'] }} · التراكميّ: {{ $point['running'] }}</title>
                    </circle>
                    <text x="{{ 30 + $i * 106 }}" y="158" text-anchor="middle" font-size="11"
                          fill="var(--text-muted)">{{ $point['label'] }}</text>
                @endforeach
            </svg>
        @endif
    </div>

    {{-- جدول أحداث الأسبوع: 4 أعمدة — وعلى الموبايل كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
    <div class="card p-4 mb-5">
        <div class="text-sm mb-3">أحداث الأسبوع</div>

        @if ($events->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">مفيش حركات في الأسبوع ده.</p>
        @else
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                    <tr style="color: var(--text-muted)">
                        <th class="text-start font-normal py-2">التاريخ</th>
                        <th class="text-start font-normal py-2">الحدث</th>
                        <th class="text-start font-normal py-2">القيمة</th>
                        <th class="text-start font-normal py-2">المرجع</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($events as $event)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="py-2" title="{{ $event->created_at->format('Y-m-d H:i') }}">
                                {{ $event->created_at->diffForHumans() }}
                            </td>
                            <td class="py-2">{{ $event->reason ?? $event->source }}</td>
                            <td class="py-2">
                                <x-state-badge :state="$event->amount >= 0 ? 'ok' : 'danger'"
                                               :label="($event->amount >= 0 ? '+' : '').rtrim(rtrim(number_format((float) $event->amount, 2), '0'), '.').' '.strtoupper($event->code ?? '')" />
                            </td>
                            <td class="py-2" style="color: var(--text-muted)">{{ $event->source }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="md:hidden space-y-2">
                @foreach ($events as $event)
                    <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm">{{ $event->reason ?? $event->source }}</span>
                            <x-state-badge :state="$event->amount >= 0 ? 'ok' : 'danger'"
                                           :label="($event->amount >= 0 ? '+' : '').rtrim(rtrim(number_format((float) $event->amount, 2), '0'), '.')" />
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $event->created_at->diffForHumans() }} · {{ $event->source }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ما يستحقّ انتباهك --}}
    <div class="card p-4">
        <div class="text-sm mb-3">ما يستحقّ انتباهك</div>

        <div class="grid md:grid-cols-3 gap-4 text-sm">
            <div>
                <div class="text-xs mb-2" style="color: var(--text-muted)">مهامّ تقترب ديدلايناتها</div>
                @forelse ($attention['due_soon'] as $task)
                    <a class="flex items-center justify-between gap-2 py-1"
                       href="{{ route('volunteer.tasks.show', $task) }}">
                        <span class="truncate">{{ $task->title }}</span>
                        @include('volunteer.components.deadline-counter', ['task' => $task])
                    </a>
                @empty
                    <p style="color: var(--text-muted)">مفيش حاجة قربت.</p>
                @endforelse
            </div>

            <div>
                <div class="text-xs mb-2" style="color: var(--text-muted)">نوافذ دمج مفتوحة</div>
                @forelse ($attention['merge_windows'] as $task)
                    <a class="flex items-center justify-between gap-2 py-1"
                       href="{{ route('volunteer.tasks.show', $task) }}">
                        <span class="truncate">{{ $task->title }}</span>
                        @include('volunteer.components.deadline-counter', ['at' => $task->merge_window_at, 'state' => 'warn'])
                    </a>
                @empty
                    <p style="color: var(--text-muted)">مفيش نافذة دمج مفتوحة.</p>
                @endforelse
            </div>

            <div>
                <div class="text-xs mb-2" style="color: var(--text-muted)">
                    باب الاعتراض مفتوح ({{ $attention['objection_days'] }} أيّام)
                </div>
                @forelse ($attention['objectionable'] as $transaction)
                    <div class="flex items-center justify-between gap-2 py-1">
                        <span class="truncate">{{ $transaction->reason ?? $transaction->source }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">
                            {{ $transaction->created_at->diffForHumans() }}
                        </span>
                    </div>
                @empty
                    <p style="color: var(--text-muted)">مفيش معاملات جديدة.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
