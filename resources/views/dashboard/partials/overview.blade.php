{{-- نظرة عامّة: كروت KPI ثمّ كروت التدريبات الجارية ثمّ أقرب المواعيد (24.5) --}}

@php
    // الشبكة تتبع عدد الكروت الفعليّ — والحدّ من اللوحة يقصّ (2.15-أ-3 · 14-أ)
    $kpiColumns = count($kpis) >= 6 ? 'lg:grid-cols-3' : 'lg:grid-cols-4';
@endphp

{{-- على الموبايل: صفّ متمرّر أفقيًّا بدل الكروت مضغوطة (2.15-ج) --}}
<div class="flex gap-3 min-w-0 overflow-x-auto no-scrollbar pb-1 sm:grid sm:grid-cols-2 {{ $kpiColumns }} sm:overflow-visible">
    @foreach ($kpis as $kpi)
        <div class="min-w-[13rem] sm:min-w-0">
            <x-kpi :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon']" :hint="$kpi['hint']" :state="$kpi['state']" />
        </div>
    @endforeach
</div>

<section class="mt-6">
    <div class="flex items-baseline justify-between gap-2 mb-3">
        <h2 class="font-bold">تدريباتي الجارية</h2>
        @if (\Illuminate\Support\Facades\Route::has('learning.courses'))
            <a href="{{ route('learning.courses') }}" class="text-xs hover:underline" style="color: var(--color-brand-500)">كلّ تدريباتي</a>
        @endif
    </div>

    @if ($courses->isEmpty())
        <x-empty message="خلّصت كلّ تدريباتك الجارية — تحفة"
                 action="تصفّح المتجر"
                 :href="\Illuminate\Support\Facades\Route::has('store.index') ? route('store.index') : url('/')" />
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($courses as $row)
                @include('dashboard.components.course-card', ['row' => $row])
            @endforeach
        </div>
    @endif
</section>

<section class="mt-6">
    <h2 class="font-bold mb-3">أقرب المواعيد</h2>

    @if ($deadlines->isEmpty())
        <div class="card p-5 text-sm" style="color: var(--text-muted)">مفيش موعد قريب — خُد وقتك.</div>
    @else
        <ul class="card divide-y" style="border-color: var(--border)">
            @foreach ($deadlines as $row)
                <li class="flex flex-wrap items-center gap-3 p-4" style="border-color: var(--border)">
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $row['course']->name_ar }}</span>

                    {{-- عدّاد ملوّن برمزه، ويحدّث نفسه حيًّا مع بقاء النصّ الخادميّ بديلًا آمنًا (2.17-أ) --}}
                    <x-state-badge :state="$row['timer']->state"
                                   :label="$row['timer']->label"
                                   data-countdown="{{ $row['timer']->deadline?->toIso8601String() }}" />

                    <span class="text-xs" style="color: var(--text-muted)">{{ $row['percent'] }}% مكتمل</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
