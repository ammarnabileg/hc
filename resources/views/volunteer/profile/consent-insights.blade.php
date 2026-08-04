@extends('layouts.volunteer')

@section('title', setting('volunteer.profile_consent_insights.title', 'طلبات إظهار التواصل'))

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
        :title="setting('volunteer.profile_consent_insights.title', 'طلبات إظهار التواصل')"
        :subtitle="setting('volunteer.profile_consent_insights.subtitle', 'مؤشّر على الثقة الداخليّة — مش مجرّد عدّاد')"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.profile_consent_insights.label', 'الإدارة المركزيّة')], ['label' => setting('volunteer.profile_consent_insights.label_2', 'طلبات الإظهار')]]" />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('volunteer.profile_consent_insights.label_3', 'الطلبات')" :value="$metrics['total']" icon="envelope" :hint="setting('volunteer.profile_consent_insights.hint', 'آخر ').$metrics['days'].setting('volunteer.profile_consent_insights.hint_2', ' يوم')" />
        <x-kpi :label="setting('volunteer.profile_consent_insights.label_4', 'المرفوضة')" :value="$metrics['denied']" icon="blocked" />
        <x-kpi :label="setting('volunteer.profile_consent_insights.label_5', 'معدّل القبول')"
               :value="$metrics['acceptance_rate'] !== null ? $metrics['acceptance_rate'].'%' : '—'" icon="contribution" />
        <x-kpi :label="setting('volunteer.profile_consent_insights.label_6', 'متوسّط زمن الردّ')"
               :value="$metrics['avg_response_hours'] !== null ? $metrics['avg_response_hours'].setting('volunteer.profile_consent_insights.value', ' ساعة') : '—'" icon="clock" />
    </div>

    @if ($rows->isEmpty())
        <x-empty :message="setting('volunteer.profile_consent_insights.empty', 'مفيش طلبات في الفترة دي — التنسيق ماشي بسلاسة')" :action="setting('volunteer.profile_consent_insights.action', 'وسّع المدى')" :href="route('volunteer.profile.consent.insights', ['days' => 90])" />
    @else
        <section class="card p-4">
            <h2 class="font-bold text-sm mb-3">{{ setting('volunteer.profile_consent_insights.heading', 'سجلّ الطلبات') }}</h2>
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
                                {{ $row->reason ?: setting('volunteer.profile_consent_insights.text', 'من غير سبب مكتوب') }} · {{ $row->created_at->diffForHumans() }}
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
