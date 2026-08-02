@extends('layouts.app')

@section('title', 'تقييماتي')

@php
    $fmt = fn ($v) => ($v > 0 ? '+' : '').rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $tabs = [
        ['key' => 'received', 'label' => 'تقييماتي المستلَمة', 'url' => route('volunteer.performance.evaluations', ['tab' => 'received'])],
    ];

    // تاب التقييم مخفيّ تمامًا لمن لا أبلاين له — لا معطَّل (2.15-أ-7)
    if ($upline) {
        $tabs[] = ['key' => 'given', 'label' => 'تقييمي لأبلايني', 'url' => route('volunteer.performance.evaluations', ['tab' => 'given'])];
    }
@endphp

@section('content')
    <x-page-header
        title="تقييماتي"
        subtitle="مؤشّر القيادة: من الداونلاين للأبلاين فقط — ومجهول تمامًا."
        :breadcrumbs="[['label' => 'الأداء', 'url' => route('volunteer.performance.vxp')], ['label' => 'تقييماتي']]" />

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
            <span aria-hidden="true">🕶️</span>
            <p>مجهول تمامًا — بيظهر لأبلاينك متوسّطًا فقط، بلا أيّ كشف لهويّتك.</p>
        </div>

        @if ($alreadyEvaluated)
            <div class="card p-6 text-center">
                <x-state-badge state="ok" label="تمّ" />
                <p class="text-sm mt-3">قيّمت {{ $upline->shortName() }} هذا الأسبوع بالفعل — الفورم بيفتح تاني أوّل الأسبوع الجاي.</p>
            </div>
        @else
            <form method="post" action="{{ route('volunteer.performance.evaluations.store') }}" class="card p-4 space-y-4">
                @csrf

                <div class="text-sm">
                    تقييم <span class="font-bold">{{ $upline->shortName() }}</span>
                    <span style="color: var(--text-muted)">· أسبوع {{ $weekStart->format('Y/m/d') }}</span>
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
                               style="border: none; outline: none; accent-color: var(--color-brand-500); min-height: 44px">
                    </div>
                @endforeach

                <label class="block">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">ملاحظة (اختياريّة)</span>
                    <textarea name="note" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">إرسال التقييم</button>
            </form>
        @endif

        @if ($given->isNotEmpty())
            <div class="card p-4 mt-4">
                <h2 class="text-sm font-semibold mb-2">تقييماتي المُرسَلة</h2>
                @foreach ($given as $row)
                    <div class="flex items-center justify-between text-xs py-1" style="border-top: 1px solid var(--border)">
                        <span>{{ $row->evaluatee?->shortName() }}</span>
                        <span style="color: var(--text-muted)">أسبوع {{ $row->week_start->format('Y/m/d') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        {{-- ------------------------------------------------ تقييماتي المستلَمة --}}
        {{-- ⭐ سطر ثابت يشرح العتبة — لا يظهر المتوسّط إلا بعددٍ كافٍ من المقيّمين --}}
        <p class="text-xs mb-3" style="color: var(--text-muted)">
            لا يظهر المتوسّط إلا بـ{{ $minRaters }} مقيّمين فأكثر — حمايةً للسرّيّة.
        </p>

        @if (! $summary['visible'])
            <x-empty :message="'العيّنة أقلّ من '.$minRaters.' مقيّمين — المتوسّط محجوب حمايةً للسرّيّة'"
                     action="ارجع للأداء" :href="route('volunteer.performance.vxp')" />
        @else
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <x-kpi label="المتوسّط العامّ" :value="$summary['average'].' / '.$maxScore" icon="🧭" />
                <x-kpi label="عدد المقيّمين" :value="$summary['raters']" icon="👥" />
                <x-kpi label="نافذة العرض" :value="$windowWeeks.' أسبوعًا'" icon="📆" />
                <x-kpi label="أثره على Rep"
                       :value="$fmt(rep_rule(app(App\Services\Volunteer\Goals\RepService::class)->leadershipRuleKeyFor($summary['average'])))"
                       icon="⚖️" />
            </div>

            @include('volunteer.performance.partials.line-chart', [
                'series' => $series,
                'title' => 'منحنى '.$windowWeeks.' أسبوعًا',
                'chartId' => 'leadership-curve',
            ])

            <div class="card p-4 mt-3">
                <h2 class="text-sm font-semibold mb-3">تفصيل المعايير</h2>
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
            <h2 class="text-sm font-semibold mb-3">أثر المؤشّر على درجة الالتزام</h2>
            <div class="hidden md:grid grid-cols-2 gap-2 text-xs mb-1" style="color: var(--text-muted)">
                <span>متوسّط المؤشّر /{{ $maxScore }}</span><span>أثره على Rep</span>
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
