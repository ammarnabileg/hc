@extends('layouts.volunteer')

@section('title', setting('volunteer.performance_vxp.title', 'VXP وترتيبي'))

@php
    // لقطة اللوحة لزرّ [استخراج كصورة] (12.14-هـ)
    $exportRows = collect($rows ?? [])->values()->map(fn ($row, $i) => [
        'rank' => $row['rank'] ?? $i + 1,
        'u' => $row['user']->id ?? null,
        'name' => $row['user']->name ?? '',
        'value' => number_format((float) ($row['vxp'] ?? 0), 1),
    ])->all();
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.performance_vxp.title', 'VXP وترتيبي')"
        :subtitle="setting('volunteer.performance_vxp.subtitle', 'رصيد الإنتاج التراكميّ وموقعك في الليدر بورد.')"
        :breadcrumbs="[['label' => setting('volunteer.performance_vxp.label', 'الأداء'), 'url' => route('volunteer.performance.vxp')], ['label' => setting('volunteer.performance_vxp.title', 'VXP وترتيبي')]]">
        {{-- ⭐ [استخراج كصورة] — ليدر بورد VXP والتطوّع (12.14-هـ) --}}
        <x-slot:action>
            <x-export-image :title="setting('volunteer.performance_vxp.tooltip', 'ليدر بورد VXP')" :subtitle="setting('volunteer.performance_vxp.subtitle_2', 'إنتاج التطوّع')" :rows="$exportRows" />
        </x-slot:action>
    </x-page-header>

    {{-- 4 كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('volunteer.performance_vxp.label_2', 'الرصيد التراكميّ')" :value="$balance" icon="spark" />
        <x-kpi :label="setting('volunteer.performance_vxp.label_3', 'ترتيبي')" :value="$rank ?: '—'" icon="badge" :hint="setting('volunteer.performance_vxp.hint', 'من ').$total.setting('volunteer.performance_vxp.hint_2', ' متطوّع')" />
        <x-kpi label="{{ setting('volunteer.performance_vxp.label_4', 'المكتسَب آخر') }} {{ $filters['days'] }} {{ setting('volunteer.common.days', 'يومًا') }}" :value="$earned" icon="chart" />
        <x-kpi :label="setting('volunteer.performance_vxp.label_5', 'مصادر نشطة')" :value="collect($sources)->where('value', '>', 0)->count()" icon="game" />
    </div>

    {{-- ⭐ تنويه ثابت (24.4) --}}
    <div class="card p-3 mb-4 text-sm flex items-start gap-2">
        <span aria-hidden="true"><x-icon name="lock" size="16" /></span>
        <p>VXP {{ setting('volunteer.performance_vxp.text', 'لا يتصفّر ولا يُخصَم آليًّا — الخصم بقرار محكّم أو معاملة يدويّة موثّقة فقط.') }}</p>
    </div>

    <x-filters :action="route('volunteer.performance.vxp')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.performance_vxp.field', 'النطاق') }}</span>
            <select name="scope" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                @foreach ($scopes as $key => $label)
                    <option value="{{ $key }}" @selected($filters['scope'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.period', 'الفترة') }}</span>
            <select name="days" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                @foreach ([30 => setting('volunteer.performance_vxp.foreach', '30 يومًا'), 90 => setting('volunteer.performance_vxp.foreach_2', '90 يومًا'), 3650 => setting('volunteer.performance_vxp.foreach_3', 'كلّي')] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['days'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('volunteer.performance_vxp.placeholder', 'ابحث بالاسم…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty :message="setting('volunteer.performance_vxp.empty', 'ابدأ أوّل مهمّة وهتظهر هنا')" :action="setting('volunteer.performance_vxp.action', 'افتح نوبتي')" :href="route('volunteer.recurring')" />
    @else
        <div class="card overflow-hidden">
            @foreach ($rows->take((int) setting('performance.vxp.rows', 50)) as $row)
                <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-center px-4 py-3"
                     style="border-bottom: 1px solid var(--border)">
                    <div class="flex items-center gap-2 md:col-span-2">
                        <span class="text-sm font-bold w-6">{{ $row['rank'] }}</span>
                        @if ($row['user'])
                            {{-- أفاتار بلا هالة (24.4) --}}
                            <x-avatar :user="$row['user']" size="8" />
                        @endif
                        <span class="text-sm truncate">{{ $row['user']?->shortName() ?? '—' }}</span>
                    </div>
                    <div class="text-xs truncate" style="color: var(--text-muted)">
                        {{ $row['membership']?->position?->name_ar ?? '—' }} · {{ $row['membership']?->entity?->name_ar ?? '—' }}
                    </div>
                    <div class="text-sm font-semibold">{{ rtrim(rtrim(number_format($row['balance'], 2), '0'), '.') }}</div>
                    <div class="text-xs" style="color: var(--color-state-ok)">+{{ rtrim(rtrim(number_format($row['gain'], 2), '0'), '.') }}</div>
                </div>
            @endforeach
        </div>

        @if ($me)
            {{-- ⭐ صفّي أنا مثبَّت أسفل القائمة دائمًا (24.4) --}}
            <div class="sticky bottom-0 mt-2 card p-3 grid grid-cols-1 md:grid-cols-5 gap-2 items-center"
                 style="border: 1px solid var(--color-brand-500)">
                <div class="flex items-center gap-2 md:col-span-2">
                    <span class="text-sm font-bold w-6">{{ $me['rank'] }}</span>
                    <x-avatar :user="$me['user']" size="8" />
                    <span class="text-sm font-semibold">{{ setting('volunteer.performance_vxp.field_2', 'أنا') }}</span>
                </div>
                <div class="text-xs truncate" style="color: var(--text-muted)">
                    {{ $me['membership']?->position?->name_ar ?? '—' }} · {{ $me['membership']?->entity?->name_ar ?? '—' }}
                </div>
                <div class="text-sm font-semibold">{{ rtrim(rtrim(number_format($me['balance'], 2), '0'), '.') }}</div>
                <div class="text-xs" style="color: var(--color-state-ok)">+{{ rtrim(rtrim(number_format($me['gain'], 2), '0'), '.') }}</div>
            </div>
        @endif
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
        @include('volunteer.performance.partials.line-chart', [
            'series' => $series,
            'title' => setting('volunteer.performance_vxp.title_2', 'تراكم VXP آخر ').$filters['days'].setting('volunteer.performance_vxp.include', ' يومًا'),
            'chartId' => 'vxp-curve',
            'unit' => 'VXP',
        ])

        <div class="card p-4">
            <h2 class="text-sm font-semibold mb-3">{{ setting('volunteer.performance_vxp.heading', 'مصادر نقاطي') }}</h2>
            @php $maxSource = max(1, collect($sources)->max('value')); @endphp
            <div class="space-y-2">
                @foreach ($sources as $source)
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span>{{ $source['label'] }}</span>
                            <span style="color: var(--text-muted)">{{ rtrim(rtrim(number_format($source['value'], 2), '0'), '.') }}</span>
                        </div>
                        <div class="h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                            <div class="h-full" style="width: {{ round(($source['value'] / $maxSource) * 100, 2) }}%; background: var(--color-brand-500)"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

@section('mobile_action')
    <a href="{{ route('volunteer.performance.rep') }}"
       class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('volunteer.performance_vxp.link', 'درجة الالتزام') }}</a>
@endsection
