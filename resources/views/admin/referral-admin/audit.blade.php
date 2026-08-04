@extends('layouts.admin')

@section('title', setting('admin.referral_admin.audit.tdqyq_shbka_2', 'تدقيق شبكة ').$referrer->name)

@php
    /**
     * تدقيق شبكة داعٍ (24.2) — الرقم الكاشف هو **أعلى عدد دعوات في يوم واحد**،
     * وليس إجماليّ الدعوات: فالإجماليّ الكبير قد يكون جهدًا حقيقيًّا، أمّا
     * التكدّس في يوم فمؤشّرٌ يستحقّ النظر. ولا نتّهم — نعرض ونترك القرار للأدمن.
     */
@endphp

@section('content')
    <x-page-header :title="setting('admin.referral_admin.audit.tdqyq_shbka', 'تدقيق شبكة: ').$referrer->name"
                   :subtitle="setting('admin.referral_admin.audit.alarqam_hna_mwshrat_la_ahkam_rajaha_qbl_ay', 'الأرقام هنا مؤشّرات لا أحكام — راجعها قبل أيّ قرار.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.referral_admin.audit.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')],
                       ['label' => setting('admin.referral_admin.audit.alryfyral_walsfra', 'الريفيرال والسفراء'), 'url' => route('admin.referrals.index')],
                       ['label' => setting('admin.referral_admin.audit.tdqyq', 'تدقيق')],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.referrals.index') }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.referral_admin.audit.rjwa', 'رجوع') }}</a>
        </x-slot:action>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('admin.referral_admin.audit.kl_aldawat', 'كلّ الدعوات')" :value="$audit['invites']" icon="envelope" />
        <x-kpi :label="setting('admin.referral_admin.audit.mfala', 'مفعَّلة')" :value="$audit['activated']" icon="check" />
        <x-kpi :label="setting('admin.referral_admin.audit.aala_ywm', 'أعلى يوم')" :value="$audit['busiest_day']" icon="calendar"
               :hint="setting('admin.referral_admin.audit.atba_altnbyh', 'عتبة التنبيه: ').$audit['threshold']" />
        <x-kpi :label="setting('admin.referral_admin.audit.allqb_alhaly', 'اللقب الحاليّ')" :value="$referrer->ambassador_title ?: setting('admin.referral_admin.audit.bla_lqb', 'بلا لقب')" icon="crown" />
    </div>

    @if ($audit['suspicious'])
        <div class="card p-4 mb-4" style="border-color: var(--color-state-warn)">
            <div class="flex items-center gap-2"><x-state-badge state="warn" :label="setting('admin.referral_admin.audit.nmt_ysthq_alnzr', 'نمط يستحقّ النظر')" /></div>
            <p class="text-sm mt-2" style="color: var(--text-muted)">
                {!! strtr(setting('admin.referral_admin.audit.fyh_ywm_wahd_atsjl_fyh_v1_dawa_raja_alhsabat', 'فيه يوم واحد اتسجّل فيه :v1 دعوة — راجع الحسابات دي قبل صرف مكافآتها.'), [':v1' => e($audit['busiest_day'])]) !!}
            </p>
        </div>
    @endif

    @if ($audit['rows']->isEmpty())
        <x-empty :message="setting('admin.referral_admin.audit.mafysh_dawat_msjla_llhsab_dh', 'مافيش دعوات مسجّلة للحساب ده.')" />
    @else
        <div class="card p-0 overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.audit.almdaw', 'المدعو') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.audit.altarykh', 'التاريخ') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.audit.alhala', 'الحالة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.audit.almkafaa', 'المكافأة') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($audit['rows'] as $referral)
                        @php $status = $service->statusOf($referral); @endphp
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3">{{ $referral->referred?->name ?? setting('admin.referral_admin.audit.lm_ysjl_bad', 'لم يسجّل بعد') }}</td>
                            <td class="px-4 py-3 text-xs">{{ $referral->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">
                                <x-state-badge :state="match ($status) { 'completed' => 'ok', 'waiting' => 'warn', default => 'idle' }"
                                               :label="$statuses[$status]" />
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $referral->payout_status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="grid gap-3 md:hidden">
            @foreach ($audit['rows'] as $referral)
                @php $status = $service->statusOf($referral); @endphp
                <article class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold">{{ $referral->referred?->name ?? setting('admin.referral_admin.audit.lm_ysjl_bad', 'لم يسجّل بعد') }}</p>
                        <x-state-badge :state="match ($status) { 'completed' => 'ok', 'waiting' => 'warn', default => 'idle' }"
                                       :label="$statuses[$status]" />
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        {{ $referral->created_at?->format('Y-m-d H:i') }} {{ setting('admin.referral_admin.audit.almkafaa_2', '· المكافأة:') }} {{ $referral->payout_status }}
                    </p>
                </article>
            @endforeach
        </div>
    @endif
@endsection
