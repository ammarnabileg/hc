@extends('layouts.admin')

@section('title', 'تدقيق شبكة '.$referrer->name)

@php
    /**
     * تدقيق شبكة داعٍ (24.2) — الرقم الكاشف هو **أعلى عدد دعوات في يوم واحد**،
     * وليس إجماليّ الدعوات: فالإجماليّ الكبير قد يكون جهدًا حقيقيًّا، أمّا
     * التكدّس في يوم فمؤشّرٌ يستحقّ النظر. ولا نتّهم — نعرض ونترك القرار للأدمن.
     */
@endphp

@section('content')
    <x-page-header :title="'تدقيق شبكة: '.$referrer->name"
                   subtitle="الأرقام هنا مؤشّرات لا أحكام — راجعها قبل أيّ قرار."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')],
                       ['label' => 'الريفيرال والسفراء', 'url' => route('admin.referrals.index')],
                       ['label' => 'تدقيق'],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.referrals.index') }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">رجوع</a>
        </x-slot:action>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi label="كلّ الدعوات" :value="$audit['invites']" icon="✉️" />
        <x-kpi label="مفعَّلة" :value="$audit['activated']" icon="✅" />
        <x-kpi label="أعلى يوم" :value="$audit['busiest_day']" icon="📅"
               :hint="'عتبة التنبيه: '.$audit['threshold']" />
        <x-kpi label="اللقب الحاليّ" :value="$referrer->ambassador_title ?: 'بلا لقب'" icon="👑" />
    </div>

    @if ($audit['suspicious'])
        <div class="card p-4 mb-4" style="border-color: var(--color-state-warn)">
            <div class="flex items-center gap-2"><x-state-badge state="warn" label="نمط يستحقّ النظر" /></div>
            <p class="text-sm mt-2" style="color: var(--text-muted)">
                فيه يوم واحد اتسجّل فيه {{ $audit['busiest_day'] }} دعوة — راجع الحسابات دي قبل صرف مكافآتها.
            </p>
        </div>
    @endif

    @if ($audit['rows']->isEmpty())
        <x-empty message="مافيش دعوات مسجّلة للحساب ده." />
    @else
        <div class="card p-0 overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">المدعو</th>
                        <th class="text-start px-4 py-3 font-semibold">التاريخ</th>
                        <th class="text-start px-4 py-3 font-semibold">الحالة</th>
                        <th class="text-start px-4 py-3 font-semibold">المكافأة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($audit['rows'] as $referral)
                        @php $status = $service->statusOf($referral); @endphp
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3">{{ $referral->referred?->name ?? 'لم يسجّل بعد' }}</td>
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
                        <p class="text-sm font-semibold">{{ $referral->referred?->name ?? 'لم يسجّل بعد' }}</p>
                        <x-state-badge :state="match ($status) { 'completed' => 'ok', 'waiting' => 'warn', default => 'idle' }"
                                       :label="$statuses[$status]" />
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        {{ $referral->created_at?->format('Y-m-d H:i') }} · المكافأة: {{ $referral->payout_status }}
                    </p>
                </article>
            @endforeach
        </div>
    @endif
@endsection
