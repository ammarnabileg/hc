@extends('layouts.volunteer')

@section('title', setting('volunteer.overview_calendar.title', 'تقويم نشاطي'))

@section('content')
    @php
        $typeColors = [
            'task' => 'var(--color-brand-500)',
            'subtask' => 'var(--color-brand-300)',
            'contribution' => 'var(--color-state-honor)',
            'merge' => 'var(--color-state-warn)',
            'meeting' => 'var(--color-state-idle)',
        ];
        $weekDays = [setting('volunteer.overview_calendar.text', 'السبت'), setting('volunteer.overview_calendar.text_2', 'الأحد'), setting('volunteer.overview_calendar.text_3', 'الاثنين'), setting('volunteer.overview_calendar.text_4', 'الثلاثاء'), setting('volunteer.overview_calendar.text_5', 'الأربعاء'), setting('volunteer.overview_calendar.text_6', 'الخميس'), setting('volunteer.overview_calendar.text_7', 'الجمعة')];
    @endphp

    <x-page-header :title="setting('volunteer.overview_calendar.title', 'تقويم نشاطي')"
                   :subtitle="setting('volunteer.overview_calendar.subtitle', 'مهامّك واجتماعاتك ومواعيدك في مكان واحد.')"
                   :breadcrumbs="[
                       ['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => route('volunteer.overview')],
                       ['label' => setting('volunteer.overview_calendar.title', 'تقويم نشاطي')],
                   ]">
        <x-slot:action>
            <div class="flex items-center gap-2 text-sm">
                <a class="rounded-xl px-3 py-2" style="background: var(--surface-raised)"
                   href="{{ route('volunteer.calendar', ['m' => $month->copy()->subMonth()->format('Y-m')]) }}"
                   aria-label="{{ setting('volunteer.overview_calendar.aria', 'الشهر السابق') }}">‹</a>
                <a class="rounded-xl px-3 py-2" style="background: var(--surface-raised)"
                   href="{{ route('volunteer.calendar') }}">{{ setting('volunteer.overview_calendar.link', 'اليوم') }}</a>
                <a class="rounded-xl px-3 py-2" style="background: var(--surface-raised)"
                   href="{{ route('volunteer.calendar', ['m' => $month->copy()->addMonth()->format('Y-m')]) }}"
                   aria-label="{{ setting('volunteer.overview_calendar.aria_2', 'الشهر التالي') }}">›</a>
            </div>
        </x-slot:action>
    </x-page-header>

    <div class="text-sm mb-3" style="color: var(--text-muted)">
        {{ $month->translatedFormat('F Y') }} · {{ setting('volunteer.overview_calendar.text_8', 'نافذة النشاط') }} {{ $window->label() }}
    </div>

    <div class="grid lg:grid-cols-[1fr_18rem] gap-4">
        {{-- شبكة التقويم — مرسومة بيدنا بلا أيّ مكتبة خارجيّة --}}
        <div class="card p-3 min-w-0 overflow-x-auto">
            <div class="grid grid-cols-7 gap-1 min-w-[36rem]">
                @foreach ($weekDays as $label)
                    <div class="text-xs text-center py-1" style="color: var(--text-muted)">{{ $label }}</div>
                @endforeach

                @foreach ($days as $week)
                    @foreach ($week as $day)
                        <div class="rounded-xl p-1.5 min-h-24"
                             style="background: {{ $day['in_month'] ? 'var(--surface-sunken)' : 'transparent' }};
                                    border: 1px solid {{ $day['is_today'] ? 'var(--color-brand-500)' : 'var(--border)' }};
                                    opacity: {{ $day['in_month'] ? 1 : .45 }}">
                            <div class="text-[11px] mb-1" style="color: var(--text-muted)">
                                {{ $day['date']->day }}
                            </div>

                            @foreach ($day['events']->take((int) setting('volunteer.calendar.events_per_day', 3)) as $event)
                                @php $st = state_color($event['state']); @endphp
                                <a href="{{ $event['url'] ?? '#' }}"
                                   class="block truncate rounded-lg px-1.5 py-0.5 mb-1 text-[11px] motion-standard"
                                   @if ($event['outside_window'])
                                       title="{{ $event['type_label'] }}: {{ $event['title'] }} — {{ $window->outsideHint() }}"
                                   @else
                                       title="{{ $event['type_label'] }}: {{ $event['title'] }} — {{ $event['at']->format('H:i') }}"
                                   @endif
                                   style="background: color-mix(in srgb, {{ $typeColors[$event['type']] ?? 'var(--color-state-idle)' }} 18%, transparent);
                                          border: 1px solid var(--color-state-{{ $st['color'] }});
                                          color: var(--text);
                                          {{ $event['outside_window']
                                              ? 'background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgb(148 163 184 / .25) 4px, rgb(148 163 184 / .25) 8px);'
                                              : '' }}">
                                    <span aria-hidden="true">{{ $st['icon'] }}</span> {{ $event['title'] }}
                                </a>
                            @endforeach

                            @if ($day['events']->count() > 3)
                                <div class="text-[11px]" style="color: var(--text-muted)">
                                    +{{ $day['events']->count() - 3 }} {{ setting('volunteer.overview_calendar.text_9', 'كمان') }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- شريط «اليوم»: أحداث اليوم مرتّبة زمنيًّا + تظليل ما هو خارج نافذة النشاط --}}
        <div class="card p-3">
            <div class="text-sm mb-2">{{ setting('volunteer.overview_calendar.text_10', 'اليوم —') }} {{ now()->translatedFormat('l j F') }}</div>

            @if ($today->isEmpty())
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.overview_calendar.text_11', 'مفيش مواعيد في المدى ده.') }}</p>
            @else
                <ul class="space-y-2 mb-3">
                    @foreach ($today as $event)
                        @php $st = state_color($event['state']); @endphp
                        <li class="rounded-xl p-2 text-sm"
                            style="background: var(--surface-sunken); border-inline-start: 3px solid var(--color-state-{{ $st['color'] }})">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate">{{ $event['title'] }}</span>
                                <span class="text-xs" style="color: var(--text-muted)">{{ $event['at']->format('H:i') }}</span>
                            </div>
                            <div class="text-xs" style="color: var(--text-muted)">
                                {{ $event['type_label'] }}{{ $event['entity'] ? ' · '.$event['entity'] : '' }}
                            </div>
                            @if ($event['outside_window'])
                                <div class="text-xs mt-1 cursor-help" title="{{ $window->outsideHint() }}"
                                     style="color: var(--text-muted)"><x-icon name="hourglass" size="16" /> {{ $window->outsideHint() }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- شريط ساعات اليوم: الرماديّ المخطَّط = خارج نافذة النشاط ولا يُحتسَب تأخيرًا --}}
            <div class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.overview_calendar.text_12', 'ساعات اليوم') }}</div>
            <div class="grid grid-cols-12 gap-0.5">
                @foreach ($hours as $hour)
                    @php $inside = $window->containsHour($hour); @endphp
                    <div class="h-4 rounded-sm cursor-help"
                         title="{{ $hour }}:00 — {{ $inside ? setting('volunteer.overview_calendar.inside_window', 'داخل نافذة النشاط') : $window->outsideHint() }}"
                         style="background: {{ $inside ? 'color-mix(in srgb, var(--color-brand-500) 35%, transparent)' : 'var(--surface-sunken)' }};
                                {{ $inside ? '' : 'background-image: repeating-linear-gradient(45deg, transparent, transparent 3px, rgb(148 163 184 / .3) 3px, rgb(148 163 184 / .3) 6px);' }}"></div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
