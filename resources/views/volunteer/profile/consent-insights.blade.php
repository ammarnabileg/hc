@extends('layouts.app')

@section('title', 'طلبات إظهار التواصل')

@php
    use App\Services\Volunteer\Profile\ConsentFlow;
@endphp

@section('content')
    {{--
      سجلّ الطلبات ومؤشّرات الثقة (13.4-م-2 · 13.4-ك):
      **عدد المرفوضة** (شخص يُزعَج أو أبلاين يتخطّى حدوده) · **معدّل قبول الطلبات**
      كمؤشّر على الثقافة المؤسّسيّة · و**متوسّط زمن الردّ** (البطء يعطّل التنسيق كالرفض).
    --}}
    <x-page-header
        title="طلبات إظهار التواصل"
        subtitle="مؤشّر على الثقة الداخليّة — مش مجرّد عدّاد"
        :breadcrumbs="[['label' => 'الرئيسيّة', 'url' => route('dashboard')], ['label' => 'الإدارة المركزيّة'], ['label' => 'طلبات الإظهار']]" />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi label="الطلبات" :value="$metrics['total']" icon="📨" :hint="'آخر '.$metrics['days'].' يوم'" />
        <x-kpi label="المرفوضة" :value="$metrics['denied']" icon="🚫" />
        <x-kpi label="معدّل القبول"
               :value="$metrics['acceptance_rate'] !== null ? $metrics['acceptance_rate'].'%' : '—'" icon="🤝" />
        <x-kpi label="متوسّط زمن الردّ"
               :value="$metrics['avg_response_hours'] !== null ? $metrics['avg_response_hours'].' ساعة' : '—'" icon="⏱️" />
    </div>

    @if ($rows->isEmpty())
        <x-empty message="مفيش طلبات في الفترة دي — التنسيق ماشي بسلاسة" action="وسّع المدى" :href="route('volunteer.profile.consent.insights', ['days' => 90])" />
    @else
        <section class="card p-4">
            <h2 class="font-bold text-sm mb-3">سجلّ الطلبات</h2>
            {{-- على الموبايل كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
            <ul class="space-y-3">
                @foreach ($rows as $row)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div class="min-w-0">
                            <div class="font-semibold">
                                {{ $row->requester?->shortName() }} ← {{ $row->owner?->shortName() }}
                                <span style="color: var(--text-muted)">· {{ ConsentFlow::fieldLabel($row->field) }}</span>
                            </div>
                            <div class="text-xs" style="color: var(--text-muted)">
                                {{ $row->reason ?: 'من غير سبب مكتوب' }} · {{ $row->created_at->diffForHumans() }}
                            </div>
                        </div>
                        <x-state-badge
                            :state="match ($row->status) { 'granted' => 'ok', 'denied' => 'danger', 'pending' => 'warn', default => 'idle' }"
                            :label="ConsentFlow::outcomeLabel($row)" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
