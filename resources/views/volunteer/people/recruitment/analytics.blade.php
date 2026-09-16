@extends('layouts.volunteer')

@section('title', setting('volunteer.people_recruitment.analytics_title', 'قمع التطوّع'))

@php
    /**
     * قمع التطوّع (24.4 — تحليلات التطوّع): بدأ ⟵ أتمّ ⟵ مقابلة ⟵ مقبول ⟵
     * مُسكَّن، مع متوسّط زمن كلّ مرحلة — بصلاحيّة `recruitment_analytics.view`
     * المستقلّة عن لوحة المرشّحين (12.2.1-ب).
     */
    $durationsByFrom = collect($funnel['durations'])->keyBy('from_key');
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.people_recruitment.analytics_title', 'قمع التطوّع')"
        :subtitle="setting('volunteer.people_recruitment.analytics_subtitle', 'بدأ · أتمّ · مقابلة · مقبول · مُسكَّن، مع متوسّط زمن كلّ مرحلة.')"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.people_recruitment.label', 'التوظيف'), 'url' => route('volunteer.recruitment')], ['label' => setting('volunteer.people_recruitment.analytics_title', 'قمع التطوّع')]]">
        <x-slot:action>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('volunteer.recruitment') }}" class="rounded-xl px-4 py-2 text-sm"
                   style="background: var(--surface-sunken)">{{ setting('volunteer.people_recruitment.analytics_back', 'لوحة المرشّحين') }}</a>

                @if ($canExport)
                    <a href="{{ route('volunteer.recruitment.analytics.export', $filters) }}" class="rounded-xl px-4 py-2 text-sm"
                       style="background: var(--surface-sunken)">{{ setting('volunteer.people_recruitment.header_export', 'تصدير CSV') }}</a>
                @endif
            </div>
        </x-slot:action>
    </x-page-header>

    <x-filters :action="route('volunteer.recruitment.analytics')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment.analytics_field_days', 'الفترة (يوم)') }}</span>
            <select name="days" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                @foreach ([7, 30, 90] as $option)
                    <option value="{{ $option }}" @selected((int) $filters['days'] === $option)>{!! strtr(setting('admin.volunteer.analytics.akhr_v1_ywma', 'آخر :v1 يومًا'), [':v1' => e($option)]) !!}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment.field_2', 'القسم المناسب') }}</span>
            <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                @foreach ($tree as $root)
                    <optgroup label="{{ $root->name_ar }}">
                        <option value="{{ $root->id }}" @selected((int) $filters['entity'] === (int) $root->id)>{{ $root->name_ar }}</option>
                        @foreach ($root->children as $child)
                            <option value="{{ $child->id }}" @selected((int) $filters['entity'] === (int) $child->id)>— {{ $child->name_ar }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </label>
    </x-filters>

    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">{{ setting('volunteer.people_recruitment.analytics_funnel_title', 'القمع') }}</h2>

        @if ($funnel['total'] === 0)
            <x-empty :message="setting('volunteer.people_recruitment.analytics_empty', 'لا بيانات كافية في هذه الفترة، جرّب فترة أوسع.')" />
        @else
            <div class="space-y-3">
                @foreach ($funnel['stages'] as $stage)
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-semibold">{{ $stage['label'] }}</span>
                            <span style="color: var(--text-muted)">
                                {{ $stage['count'] }}
                                @if ($stage['pct_of_start'] !== null)
                                    ({{ $stage['pct_of_start'] }}%)
                                @endif
                            </span>
                        </div>
                        <div class="rounded-full overflow-hidden" style="background: var(--surface-sunken); height: 10px">
                            <div style="width: {{ $stage['pct_of_start'] ?? 0 }}%; background: var(--color-brand-500); height: 100%"></div>
                        </div>

                        @php $duration = $durationsByFrom->get($stage['key']); @endphp
                        @if ($duration)
                            <p class="text-xs mt-1" style="color: var(--text-muted)">
                                @if ($duration['avg_days'] !== null)
                                    {!! strtr(setting('volunteer.people_recruitment.analytics_avg_time', 'متوسّط الزمن حتّى :to: :days يومًا (:sample مرشّحًا)'), [
                                        ':to' => $duration['to_label'],
                                        ':days' => $duration['avg_days'],
                                        ':sample' => $duration['sample'],
                                    ]) !!}
                                @else
                                    {{ setting('volunteer.people_recruitment.analytics_no_sample', 'لا مرشّح أكمل هذه النقلة بعد.') }}
                                @endif
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
