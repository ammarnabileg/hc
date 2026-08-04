@extends('layouts.volunteer')

@section('title', setting('volunteer.profile_report.title', 'تقرير أداء — ').$owner->shortName())

@section('content')
    {{--
      تقرير الترقية (13.4-م-1): محتواه محدَّد بالنصّ، ومعه **رقم مرجع وتاريخ إصدار**،
      ومسجَّل في الأوديت لحظة فتحه — فقرار الترقية موثّق بمصدره.
      والحفظ PDF من زرّ الطباعة: **بلا أيّ مكتبة خارجيّة** (قاعدة البناء).
    --}}
    <x-page-header
        :title="setting('volunteer.profile_report.tooltip', 'تقرير أداء للترقية')"
        :subtitle="$owner->name.' · '.$owner->code"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.profile_report.label', 'بروفايل المتطوّع')], ['label' => setting('volunteer.profile_report.label_2', 'تقرير')]]">
        <x-slot:action>
            <button type="button" onclick="window.print()"
                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.profile_report.action', 'اطبع / احفظ PDF') }}</button>
        </x-slot:action>
    </x-page-header>

    <section class="card p-5 print-sheet">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4 pb-4" style="border-bottom: 1px solid var(--border)">
            <div>
                <h2 class="font-extrabold text-lg">{{ $owner->name }}</h2>
                <p class="text-sm" style="color: var(--text-muted)">
                    {{ $membership?->position?->name_ar }}
                    @if ($membership?->entity) · {{ $membership->entity->name_ar }} @endif
                </p>
            </div>
            <div class="text-sm text-end">
                <div>{{ setting('volunteer.profile_report.text', 'رقم المرجع:') }} <span class="font-mono">{{ $reference }}</span></div>
                <div style="color: var(--text-muted)">{{ setting('volunteer.profile_report.text_2', 'تاريخ الإصدار:') }} {{ $issued_at->format('Y-m-d H:i') }}</div>
                <div style="color: var(--text-muted)">{{ setting('volunteer.profile_report.text_3', 'أصدره:') }} {{ $issued_by->shortName() }}</div>
            </div>
        </div>

        <dl class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div>
                <dt style="color: var(--text-muted)">{{ str_replace(':days', $days, (string) setting('volunteer.profile_report.text_4', 'معدّل درجة الالتزام (آخر :days يوم)')) }}</dt>
                <dd class="font-bold text-lg">{{ $rep_average }}</dd>
            </div>
            <div>
                <dt style="color: var(--text-muted)">{{ setting('volunteer.profile_report.text_6', 'درجة الالتزام الحاليّة') }}</dt>
                <dd class="font-bold text-lg">{{ $rep_now }}</dd>
            </div>
            <div>
                <dt style="color: var(--text-muted)">{{ setting('volunteer.profile_report.text_7', 'نسبة حضور الاجتماعات') }}</dt>
                <dd class="font-bold text-lg">{{ $attendance['percent'] !== null ? $attendance['percent'].'%' : '—' }}</dd>
            </div>
            <div>
                <dt style="color: var(--text-muted)">{{ setting('volunteer.profile_report.text_8', 'المهامّ') }}</dt>
                <dd class="font-bold text-lg">{{ $tasks['approved'] }} / {{ $tasks['total'] }}</dd>
            </div>
            <div>
                <dt style="color: var(--text-muted)">{{ setting('volunteer.profile_report.text_9', 'المهامّ المتأخّرة') }}</dt>
                <dd class="font-bold text-lg">{{ $tasks['late'] }}</dd>
            </div>
            <div>
                <dt style="color: var(--text-muted)">{{ setting('volunteer.profile_report.text_10', 'متوسّط التقييم') }}</dt>
                <dd class="font-bold text-lg">{{ $evaluation !== null ? $evaluation.' / '.$evaluation_scale : '—' }}</dd>
            </div>
        </dl>

        <div class="mt-5 pt-4 text-sm" style="border-top: 1px solid var(--border)">
            <span style="color: var(--text-muted)">{{ setting('volunteer.profile_report.text_11', 'الأبلاين الموصي:') }}</span>
            <span class="font-bold">{{ $recommender?->name ?? setting('volunteer.profile_report.text_12', 'مفيش أبلاين مباشر') }}</span>
            @if ($recommender_position)
                <span style="color: var(--text-muted)">· {{ $recommender_position }}</span>
            @endif
        </div>

        <p class="mt-4 text-xs" style="color: var(--text-muted)">
            {{ setting('volunteer.profile_report.text_13', 'التقرير مسجَّل في سجلّ التدقيق برقم') }} {{ $audit_id }} — {{ setting('volunteer.profile_report.text_14', 'أرقامه من مصادرها الواحدة بلا حساب موازٍ.') }}
        </p>
    </section>
@endsection

@push('head')
    <style>
        @media print {
            header, aside, nav, .no-print { display: none !important; }
            .print-sheet { border: none !important; box-shadow: none !important; }
            @page { size: A4; margin: 14mm; }
        }
    </style>
@endpush
