@extends('layouts.admin')

@section('title', setting('admin.volunteer.analytics.thlylat_alttwa', 'تحليلات التطوّع'))

@section('content')
    <x-page-header
        :title="setting('admin.volunteer.analytics.thlylat_alttwa', 'تحليلات التطوّع')"
        :subtitle="setting('admin.volunteer.analytics.tsrb_ahmal_sha_alkyanat_tqryr_saa_mada_qrar', 'تسرّب · أحمال · صحّة الكيانات · تقرير سعة، مادّة قرار لا أرقام زينة.')"
        :breadcrumbs="[['label' => setting('admin.volunteer.analytics.alttwa', 'التطوّع'), 'url' => route('admin.volunteer.index')], ['label' => setting('admin.volunteer.analytics.althlylat', 'التحليلات')]]" />

    @include('admin.volunteer.partials.tabs', ['current' => 'analytics'])

    <x-filters :action="route('admin.volunteer.analytics')">
        <div>
            <label class="block text-xs mb-1" for="f-days" style="color: var(--text-muted)">{{ setting('admin.volunteer.analytics.almda_ywm', 'المدى (يوم)') }}</label>
            <select id="f-days" name="days" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ([7, 30, 90] as $option)
                    <option value="{{ $option }}" @selected($days === $option)>{!! strtr(setting('admin.volunteer.analytics.akhr_v1_ywma', 'آخر :v1 يومًا'), [':v1' => e($option)]) !!}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">{{ setting('admin.volunteer.analytics.tbq', 'طبّق') }}</button>
    </x-filters>

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <x-kpi :label="setting('admin.volunteer.analytics.mttwawn_nshtwn', 'متطوّعون نشطون')" :value="$kpis['active']" icon="contribution" />
        <x-kpi :label="setting('admin.volunteer.analytics.shwaghr', 'شواغر')" :value="$kpis['vacancies']" icon="placement" />
        <x-kpi :label="setting('admin.volunteer.analytics.tjawzat', 'تجاوزات')" :value="$kpis['overflows']" icon="warning" />
        <x-kpi :label="setting('admin.volunteer.analytics.khrwj_fy_almda', 'خروج في المدى')" :value="$kpis['exits']" icon="exit" />
    </section>

    <div class="grid lg:grid-cols-2 gap-4 mt-4">
        <section class="card p-4 md:p-5">
            <h2 class="font-bold mb-3">{{ setting('admin.volunteer.analytics.asbab_altsrb', 'أسباب التسرّب') }}</h2>
            @forelse ($attrition as $row)
                <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                    <span>{{ $row['label'] }}</span>
                    <span class="font-bold">{{ $row['count'] }}</span>
                </div>
            @empty
                <x-empty :message="setting('admin.volunteer.analytics.la_byanat_kafya_fy_almda_dh', 'لا بيانات كافية في المدى ده.')" />
            @endforelse
        </section>

        <section class="card p-4 md:p-5">
            <h2 class="font-bold mb-3">{{ setting('admin.volunteer.analytics.sha_alkyanat', 'صحّة الكيانات') }}</h2>
            <div class="text-sm space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span>{{ setting('admin.volunteer.analytics.kyanat_tht_alhd_aladna_aqtrah_dmj_aw_ilgha', 'كيانات تحت الحدّ الأدنى (اقتراح دمج أو إلغاء طبقة)') }}</span>
                    <x-state-badge :state="$health['unhealthy']->count() ? 'warn' : 'ok'" :label="$health['unhealthy']->count()" />
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span>{{ setting('admin.volunteer.analytics.tjawz_ntaq_alishraf_tnbyh_la_mna', 'تجاوز نطاق الإشراف (تنبيه لا منع)') }}</span>
                    <x-state-badge :state="$health['overflows']->count() ? 'danger' : 'ok'" :label="$health['overflows']->count()" />
                </div>
            </div>
        </section>
    </div>

    @if ($canViewFunnel)
        @php $durationsByFrom = collect($funnel['durations'])->keyBy('from_key'); @endphp
        <section class="card p-4 md:p-5 mt-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="font-bold">{{ setting('admin.volunteer.analytics.qmc_alttwa', 'قمع التطوّع') }}</h2>
                @if ($canExportFunnel)
                    <a href="{{ route('admin.volunteer.analytics.recruitment-funnel.export', ['days' => $days]) }}"
                       class="rounded-xl px-3 py-1.5 text-xs" style="background: var(--surface-sunken)">{{ setting('admin.volunteer.analytics.tsdyr_csv', 'تصدير CSV') }}</a>
                @endif
            </div>

            @if ($funnel['total'] === 0)
                <x-empty :message="setting('admin.volunteer.analytics.la_byanat_kafya_fy_almda_dh', 'لا بيانات كافية في المدى ده.')" />
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
                                        {!! strtr(setting('admin.volunteer.analytics.qmc_mtwsst_alzmn', 'متوسّط الزمن حتّى :to: :days يومًا (:sample مرشّحًا)'), [
                                            ':to' => $duration['to_label'],
                                            ':days' => $duration['avg_days'],
                                            ':sample' => $duration['sample'],
                                        ]) !!}
                                    @else
                                        {{ setting('admin.volunteer.analytics.qmc_bla_ayna', 'لا مرشّح أكمل هذه النقلة بعد.') }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">{{ setting('admin.volunteer.analytics.tqryr_alsaa_alakthr_tkhma_walakthr_fragha', 'تقرير السعة: الأكثر تخمةً والأكثر فراغًا') }}</h2>
        @forelse ($capacity->sortByDesc('percent')->take((int) setting('volunteer.analytics.top_rows', 10)) as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <span class="truncate">{{ $row['entity']->name_ar }}</span>
                <span class="flex items-center gap-2">
                    <span class="text-xs" style="color: var(--text-muted)">{{ $row['members'] }}/{{ $row['cap'] }}</span>
                    <x-state-badge :state="$row['state']" :label="$row['percent'].'%'" />
                </span>
            </div>
        @empty
            <x-empty :message="setting('admin.volunteer.analytics.mfysh_kyanat_lsh', 'مفيش كيانات لسّه.')" />
        @endforelse
    </section>

    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">{{ setting('admin.volunteer.analytics.alahmal', 'الأحمال') }}</h2>
        @forelse ($loads as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <span class="truncate">{{ $row['membership']->user?->name }} — {{ $row['membership']->entity?->name_ar }}</span>
                <span class="flex items-center gap-2">
                    <span class="font-bold">{{ $row['open'] }}</span>
                    <x-state-badge :state="$row['state']" :label="$row['cap'] ? setting('admin.volunteer.analytics.sqf', 'سقف ').$row['cap'] : setting('admin.volunteer.analytics.bla_sqf', 'بلا سقف')" />
                </span>
            </div>
        @empty
            <x-empty :message="setting('admin.volunteer.analytics.mfysh_ahmal_mftwha', 'مفيش أحمال مفتوحة.')" />
        @endforelse
    </section>

    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">{{ setting('admin.volunteer.analytics.rqaba_almanhyn_maamlat_alslwk', 'رقابة المانحين (معاملات السلوك)') }}</h2>
        @forelse ($granters as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <span class="truncate">{{ $row->granted_by?->name ?? '—' }}</span>
                <span class="text-xs" style="color: var(--text-muted)">{{ $row->c }} {{ setting('admin.volunteer.analytics.maamla_makwsa', 'معاملة · معكوسة') }} {{ $row->reversed }}</span>
            </div>
        @empty
            <x-empty :message="setting('admin.volunteer.analytics.mfysh_maamlat_slwk_fy_almda_dh', 'مفيش معاملات سلوك في المدى ده.')" />
        @endforelse
    </section>

    @include('admin.volunteer.partials.settings-card', [
        'title' => setting('admin.volunteer.analytics.iadadat_althlylat_walmwshrat', 'إعدادات التحليلات والمؤشّرات'),
        'rows' => $settings,
        'action' => route('admin.volunteer.analytics.settings.save'),
        'resetAction' => route('admin.volunteer.reset', 'volunteer_analytics'),
        'lockedKeys' => ['volunteer.analytics.retention_risk_visible_to_volunteer'],
    ])
@endsection
