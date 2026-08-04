@extends('layouts.app')

@section('title', setting('wallet.topup_requests.title', 'طلبات الشحن'))

@php
    // الحالات الأربع المعتمَدة — لا خامسة (19.5-ب-4)
    $states = [
        'pending_review' => ['label' => setting('wallet.topup_requests.state_pending', 'قيد التحقّق'), 'state' => 'warn'],
        'completed' => ['label' => setting('wallet.topup_requests.state_completed', 'مكتملة'), 'state' => 'ok'],
        'cancelled' => ['label' => setting('wallet.topup_requests.state_cancelled', 'ملغاة'), 'state' => 'danger'],
        'duplicate' => ['label' => setting('wallet.topup_requests.state_duplicate', 'مكرَّرة'), 'state' => 'idle'],
    ];
@endphp

@section('content')
    <x-page-header
        :title="setting('wallet.topup_requests.title', 'طلبات الشحن')"
        :subtitle="setting('wallet.topup_requests.subtitle', 'كلّ طلباتك وحالتها لحظةً بلحظة.')"
        :breadcrumbs="[['label' => setting('wallet.index.breadcrumb_root', 'المحفظة'), 'url' => route('wallet.index')], ['label' => setting('wallet.topup.title', 'شحن الحساب'), 'url' => route('wallet.topup')], ['label' => setting('wallet.topup_requests.title', 'طلبات الشحن')]]">
        <x-slot:action>
            @can('topup.create')
                <a href="{{ route('wallet.topup') }}"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.topup_requests.new_action', 'طلب شحن جديد') }}</a>
            @endcan
        </x-slot:action>
    </x-page-header>

    @forelse ($rows as $row)
        @php $meta = $states[$row->status] ?? $states['pending_review']; @endphp

        <article class="card p-4 md:p-5 mb-3">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <div class="font-bold">{{ $row->number }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ number_format((float) $row->transferred_amount, 2) }}
                        · {{ $row->transfer_method?->name_ar ?? setting('wallet.topup_requests.method_unknown', 'طريقة غير محدّدة') }}
                        {{ str_replace(':at', (string) $row->paid_at?->format('Y-m-d H:i'), (string) setting('wallet.topup_requests.paid_at', '· دُفِع في :at')) }}
                    </div>
                </div>
                <x-state-badge :state="$meta['state']" :label="$meta['label']" />
            </div>

            {{-- تسلسل زمنيّ: أُرسِل ← قيد التحقّق ← مكتمل/ملغى (19.5-ب-6) --}}
            <ol class="mt-4 space-y-2 text-sm">
                <li class="flex items-center gap-2">
                    <span aria-hidden="true" style="color: var(--color-state-ok)">●</span>
                    <span>{{ setting('wallet.topup_requests.step_sent', 'أُرسِل') }}</span>
                    <span class="text-xs" style="color: var(--text-muted)">{{ $row->created_at?->format('Y-m-d H:i') }}</span>
                </li>
                <li class="flex items-center gap-2">
                    <span aria-hidden="true" style="color: var(--color-state-{{ $row->status === 'pending_review' ? 'warn' : 'ok' }})">{{ $row->status === 'pending_review' ? '▲' : '●' }}</span>
                    <span>{{ setting('wallet.topup_requests.state_pending', 'قيد التحقّق') }}</span>
                </li>
                @if ($row->status !== 'pending_review')
                    <li class="flex items-center gap-2">
                        <span aria-hidden="true" style="color: var(--color-state-{{ $meta['state'] }})">{{ state_color($meta['state'])['icon'] }}</span>
                        <span>{{ $meta['label'] }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $row->reviewed_at?->format('Y-m-d H:i') }}</span>
                    </li>
                @endif
            </ol>

            @if ($row->status === 'completed' && $row->credited_amount)
                <p class="mt-3 text-sm">{{ str_replace(':amount', number_format((float) $row->credited_amount), (string) setting('wallet.topup_requests.credited_note', 'اتضاف لمحفظتك :amount كوينز ✓')) }}</p>
            @endif

            @if ($row->cancel_reason)
                {{-- السبب بصيغة محايدة تشرح وتعطي الخطوة التالية (2.17-ج) --}}
                <p class="mt-3 rounded-xl p-3 text-sm"
                   style="background: var(--surface-sunken)">{{ $row->cancel_reason }}</p>
            @endif

            @if (in_array($row->status, ['cancelled', 'duplicate'], true))
                @can('topup.create')
                    <a href="{{ route('wallet.topup', ['resend' => $row->id]) }}"
                       class="btn inline-flex items-center mt-3 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.topup_requests.resend_action', 'عدّل وأعد الإرسال') }}</a>
                @endcan
            @endif
        </article>
    @empty
        <x-empty :message="setting('wallet.topup_requests.empty_message', 'لسّه مابعتّش أيّ طلب شحن.')"
                 :action="setting('wallet.index.topup_action', 'اشحن رصيدك')" :href="route('wallet.topup')" />
    @endforelse

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
