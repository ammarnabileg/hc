@extends('layouts.volunteer')

@section('title', setting('volunteer.performance_evaluations.title', 'تقييماتي'))

@php
    $fmt = fn ($v) => ($v > 0 ? '+' : '').rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $tabs = [
        ['key' => 'received', 'label' => setting('volunteer.performance_evaluations.label', 'تقييماتي المستلَمة'), 'url' => route('volunteer.performance.evaluations', ['tab' => 'received'])],
    ];

    // تاب التقييم مخفيّ تمامًا لمن لا أبلاين له — لا معطَّل (2.15-أ-7)
    if ($upline) {
        $tabs[] = ['key' => 'given', 'label' => setting('volunteer.performance_evaluations.label_2', 'تقييمي لأبلايني'), 'url' => route('volunteer.performance.evaluations', ['tab' => 'given'])];
    }
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.performance_evaluations.title', 'تقييماتي')"
        :subtitle="setting('volunteer.performance_evaluations.subtitle', 'مؤشّر القيادة: من الداونلاين للأبلاين فقط — ومجهول تمامًا.')"
        :breadcrumbs="[['label' => setting('volunteer.performance_evaluations.label_3', 'الأداء'), 'url' => route('volunteer.performance.vxp')], ['label' => setting('volunteer.performance_evaluations.title', 'تقييماتي')]]" />

    <x-tabs :tabs="$tabs" :current="$tab" />

    @if ($errors->any())
        <div class="card p-3 mb-4" style="border: 1px solid var(--color-state-danger)">
            @foreach ($errors->all() as $error)
                <p class="text-sm">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if ($tab === 'given' && $upline)
        {{-- ------------------------------------------------ تقييمي لأبلايني --}}
        <div class="card p-4 mb-4 text-sm flex items-start gap-2">
            <span aria-hidden="true"><x-icon name="eye" size="16" /></span>
            <p>{{ setting('volunteer.performance_evaluations.text', 'مجهول تمامًا — بيظهر لأبلاينك متوسّطًا فقط، بلا أيّ كشف لهويّتك.') }}</p>
        </div>

        @if ($alreadyEvaluated)
            <div class="card p-6 text-center">
                <x-state-badge state="ok" :label="setting('volunteer.performance_evaluations.label_4', 'تمّ')" />
                <p class="text-sm mt-3">{{ setting('volunteer.performance_evaluations.text_2', 'قيّمت') }} {{ $upline->shortName() }} {{ setting('volunteer.performance_evaluations.text_3', 'هذا الأسبوع بالفعل — الفورم بيفتح تاني أوّل الأسبوع الجاي.') }}</p>
            </div>
        @else
            <form method="post" action="{{ route('volunteer.performance.evaluations.store') }}" class="card p-4 space-y-4">
                @csrf

                <div class="text-sm">
                    {{ setting('volunteer.performance_evaluations.text_4', 'تقييم') }} <span class="font-bold">{{ $upline->shortName() }}</span>
                    <span style="color: var(--text-muted)">· {{ setting('volunteer.performance_evaluations.text_5', 'أسبوع') }} {{ $weekStart->format('Y/m/d') }}</span>
                </div>

                @foreach ($criteria as $criterion)
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <label for="c-{{ $criterion->key }}">{{ $criterion->label_ar }}</label>
                            {{-- استجابة لحظيّة للرقم (2.17-ب) --}}
                            <output class="font-bold" data-slider-out="{{ $criterion->key }}">
                                {{ old('scores.'.$criterion->key, intdiv($maxScore, 2)) }}
                            </output>
                        </div>
                        {{-- منزلق بلا بوردر (24.4) --}}
                        <input id="c-{{ $criterion->key }}" type="range" name="scores[{{ $criterion->key }}]"
                               min="0" max="{{ $maxScore }}" step="0.5"
                               value="{{ old('scores.'.$criterion->key, intdiv($maxScore, 2)) }}"
                               data-slider="{{ $criterion->key }}"
                               class="w-full"
                               {{-- بلا `accent-color` (2.10.1-11) — المنزلق المخصّص في `app.css` --}}
                               style="border: none; outline: none; min-height: 44px">
                    </div>
                @endforeach

                <label class="block">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.performance_evaluations.field', 'ملاحظة (اختياريّة)') }}</span>
                    <textarea name="note" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('volunteer.performance_evaluations.action', 'إرسال التقييم') }}</button>
            </form>
        @endif

        @if ($given->isNotEmpty())
            <div class="card p-4 mt-4">
                <h2 class="text-sm font-semibold mb-2">{{ setting('volunteer.performance_evaluations.heading', 'تقييماتي المُرسَلة') }}</h2>
                @foreach ($given as $row)
                    <div class="flex items-center justify-between text-xs py-1" style="border-top: 1px solid var(--border)">
                        <span>{{ $row->evaluatee?->shortName() }}</span>
                        <span style="color: var(--text-muted)">{{ setting('volunteer.performance_evaluations.text_5', 'أسبوع') }} {{ $row->week_start->format('Y/m/d') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        {{-- ------------------------------------------------ تقييماتي المستلَمة --}}
        {{-- ⭐ سطر ثابت يشرح العتبة — لا يظهر المتوسّط إلا بعددٍ كافٍ من المقيّمين --}}
        <p class="text-xs mb-3" style="color: var(--text-muted)">
            {{ setting('volunteer.performance_evaluations.text_6', 'لا يظهر المتوسّط إلا بـ') }}{{ $minRaters }} {{ setting('volunteer.performance_evaluations.text_7', 'مقيّمين فأكثر — حمايةً للسرّيّة.') }}
        </p>

        @if (! $summary['visible'])
            <x-empty :message="setting('volunteer.performance_evaluations.empty', 'العيّنة أقلّ من ').$minRaters.setting('volunteer.performance_evaluations.empty_2', ' مقيّمين — المتوسّط محجوب حمايةً للسرّيّة')"
                     :action="setting('volunteer.performance_evaluations.action_2', 'ارجع للأداء')" :href="route('volunteer.performance.vxp')" />
        @else
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <x-kpi :label="setting('volunteer.performance_evaluations.label_5', 'المتوسّط العامّ')" :value="$summary['average'].' / '.$maxScore" icon="compass" />
                <x-kpi :label="setting('volunteer.performance_evaluations.label_6', 'عدد المقيّمين')" :value="$summary['raters']" icon="people" />
                <x-kpi :label="setting('volunteer.performance_evaluations.label_7', 'نافذة العرض')" :value="$windowWeeks.setting('volunteer.performance_evaluations.value', ' أسبوعًا')" icon="calendar" />
                <x-kpi :label="setting('volunteer.performance_evaluations.label_8', 'أثره على Rep')" :value="$fmt($myImpact)" icon="evaluation" />
            </div>

            @include('volunteer.performance.partials.line-chart', [
                'series' => $series,
                'title' => setting('volunteer.performance_evaluations.title_2', 'منحنى ').$windowWeeks.setting('volunteer.performance_evaluations.value', ' أسبوعًا'),
                'chartId' => 'leadership-curve',
            ])

            <div class="card p-4 mt-3">
                <h2 class="text-sm font-semibold mb-3">{{ setting('volunteer.performance_evaluations.heading_2', 'تفصيل المعايير') }}</h2>
                @foreach ($criteria as $criterion)
                    @php $value = $summary['per_criterion'][$criterion->key] ?? null; @endphp
                    <div class="mb-2">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span>{{ $criterion->label_ar }}</span>
                            <span style="color: var(--text-muted)">{{ $value !== null ? $value.' / '.$maxScore : '—' }}</span>
                        </div>
                        <div class="h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                            <div class="h-full" style="width: {{ $value !== null ? round(($value / $maxScore) * 100, 2) : 0 }}%; background: var(--color-brand-500)"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- جدول أثر المؤشّر على Rep — القيم من rep_rule() لا محروقة (13.4-ن-د) --}}
        <div class="card p-4 mt-3">
            <h2 class="text-sm font-semibold mb-3">{{ setting('volunteer.performance_evaluations.heading_3', 'أثر المؤشّر على درجة الالتزام') }}</h2>
            <div class="hidden md:grid grid-cols-2 gap-2 text-xs mb-1" style="color: var(--text-muted)">
                <span>{{ setting('volunteer.performance_evaluations.text_8', 'متوسّط المؤشّر /') }}{{ $maxScore }}</span><span>{{ setting('volunteer.performance_evaluations.label_8', 'أثره على Rep') }}</span>
            </div>
            @foreach ($impact as $row)
                <div class="grid grid-cols-2 gap-2 items-center py-2 text-sm" style="border-top: 1px solid var(--border)">
                    <span>{{ $row['range'] }}</span>
                    <span class="font-semibold"
                          style="color: var(--color-state-{{ $row['value'] > 0 ? 'ok' : ($row['value'] < 0 ? 'danger' : 'idle') }})">
                        {{ $fmt($row['value']) }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        // استجابة لحظيّة للرقم مع تحريك المنزلق (2.17-ب)
        document.querySelectorAll('[data-slider]').forEach((slider) => {
            const out = document.querySelector('[data-slider-out="' + slider.dataset.slider + '"]');
            if (!out) return;
            slider.addEventListener('input', () => { out.textContent = slider.value; });
        });
    </script>
@endpush
