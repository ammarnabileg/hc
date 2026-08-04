@extends('layouts.admin')

@section('title', setting('admin.guidance.analytics.thlylat_almnshwr', 'تحليلات المنشور'))

@section('content')
    {{-- تحليلات عميقة: نسبة القراءة ومَن قرأ ومَن أقرّ (12.6-أ) --}}
    <x-page-header
        :title="setting('admin.guidance.analytics.thlylat', 'تحليلات: ').$announcement->title"
        :subtitle="setting('admin.guidance.analytics.myn_qra_wmyn_aqr_waliqrar_mhswb_mra_wahda', 'مين قرأ ومين أقرّ — والإقرار محسوب مرّة واحدة لكلّ منشور.')"
        :breadcrumbs="[
            ['label' => setting('admin.guidance.analytics.altalymat', 'التعليمات'), 'url' => route('admin.guidance.index')],
            ['label' => setting('admin.guidance.analytics.thlylat_2', 'تحليلات')],
        ]">
        <x-slot:action>
            @can('announcements.export')
                {{-- تصدير التحليلات (12.6-أ) — الفعل الرئيسيّ الوحيد في الشاشة --}}
                <a href="{{ route('admin.guidance.analytics.export', $announcement) }}"
                   class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">
                    <x-icon name="download" size="16" /> {{ setting('admin.guidance.analytics.tsdyr_csv', 'تصدير CSV') }}
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
            : setting('admin.guidance.analytics.lsh_mafysh_qraat', 'لسّه مافيش قراءات');
    @endphp

    {{-- 4 كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('admin.guidance.analytics.nsba_alqraa', 'نسبة القراءة')" :value="($stats['rate'] ?? 0).'%'" icon="eye" />
        <x-kpi :label="setting('admin.guidance.analytics.qraat', 'قراءات')" :value="$stats['reads'] ?? 0" icon="article" />
        <x-kpi :label="setting('admin.guidance.analytics.iqrarat', 'إقرارات')" :value="$stats['acks'] ?? 0" icon="check" />
        <x-kpi :label="setting('admin.guidance.analytics.afdl_twqyt', 'أفضل توقيت')" :value="$bestLabel" icon="clock" />
    </div>

    @if ($bestTime['sample'] > 0)
        <div class="card p-4 mb-4">
            <div class="text-sm font-semibold flex items-center gap-2"><x-icon name="chart" size="16" /> {{ setting('admin.guidance.analytics.saaat_alqraa', 'ساعات القراءة') }}</div>
            <p class="text-xs mt-1" style="color: var(--text-muted)">
                {!! strtr(setting('admin.guidance.analytics.mhswba_mn_v1_qraa_fy_akhr_v2_ywma_abath_fy', 'محسوبة من :v1 قراءة في آخر :v2 يومًا — ابعث في الساعة دي يوصلك أعلى قراءة.'), [':v1' => e($bestTime['sample']), ':v2' => e(setting('announcements.analytics.best_time_days', 90))]) !!}
            </p>
            <div class="mt-3 flex items-end gap-1 min-w-0 overflow-x-auto" style="min-height: 4rem">
                @php $peak = max(1, max($bestTime['hours'])); @endphp
                @foreach ($bestTime['hours'] as $hour => $count)
                    <div class="flex flex-col items-center gap-1 shrink-0" style="width: 1.5rem"
                         title="{{ strtr(setting('admin.guidance.analytics.v1_00_v2_qraa', ':v1:00 — :v2 قراءة'), [':v1' => e(str_pad((string) $hour, 2, '0', STR_PAD_LEFT)), ':v2' => e($count)]) }}">
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
            <div class="text-sm font-semibold">{{ setting('admin.guidance.analytics.ntyja_alasttlaa', 'نتيجة الاستطلاع') }}</div>
            <div class="text-xs mt-1" style="color: var(--text-muted)">
                {{ $poll['public'] ? setting('admin.guidance.analytics.alntyja_marwda_llmstkhdmyn', 'النتيجة معروضة للمستخدمين.') : ($poll['closed'] ? setting('admin.guidance.analytics.kant_mkhfya_walasttlaa_atqfl_fbant_lhm', 'كانت مخفيّة، والاستطلاع اتقفل فبانت لهم.') : setting('admin.guidance.analytics.alntyja_mkhfya_an_almstkhdmyn_lhd_ma', 'النتيجة مخفيّة عن المستخدمين لحدّ ما الاستطلاع يقفل — وما بتوصلش متصفّحهم أصلًا.')) }}
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
        <x-empty :message="setting('admin.guidance.analytics.mhdsh_fth_almnshwr_lsh', 'محدّش فتح المنشور لسّه.')" />
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
                        <x-state-badge state="ok" :label="setting('admin.guidance.analytics.aqr', 'أقرّ')" />
                    @else
                        <x-state-badge state="idle" :label="setting('admin.guidance.analytics.qra_fqt', 'قرأ فقط')" />
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
           style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.analytics.tsdyr_csv', 'تصدير CSV') }}</a>
    @endcan
@endsection
