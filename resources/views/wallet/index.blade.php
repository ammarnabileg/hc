@extends('layouts.app')

@section('title', setting('wallet.index.title', 'رصيدي وشحن'))

@php
    $num = fn ($v, $d = 2) => number_format((float) $v, $d);
    // أيقونات SVG بهويّة المنصّة — ممنوع أيّ مكتبة أيقونات جاهزة
    $icon = 'w-4 h-4 shrink-0';
@endphp

@section('content')
    <x-page-header
        :title="setting('wallet.index.title', 'رصيدي وشحن')"
        :subtitle="setting('wallet.index.subtitle', 'رصيدك وأرباحك وكلّ حركة عليه — في مكان واحد.')"
        :breadcrumbs="[['label' => setting('wallet.index.breadcrumb_root', 'المحفظة'), 'url' => route('wallet.index')], ['label' => setting('wallet.index.title', 'رصيدي وشحن')]]">
        {{--
          ⭐ حسم التعارض: 19.2 يوجب أربعة أفعال، و2.15 يوجب فعلًا رئيسيًّا واحدًا.
          فالحلّ: [شحن] ظاهرٌ وحده، والثلاثة الباقية في قائمة إجراءات ثانويّة.
        --}}
        <x-slot:action>
            @can('topup.create')
                <a href="{{ route('wallet.topup') }}"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.index.topup_action', 'اشحن رصيدك') }}</a>
            @endcan
            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm select-none"
                         style="background: var(--surface-raised)" aria-label="{{ setting('wallet.index.more_actions_aria', 'إجراءات أخرى') }}">⋯</summary>
                <div class="card absolute inset-inline-end-0 mt-2 w-60 p-2 text-sm z-30">
                    @if ($canTransfer)
                        <button type="button" data-modal-open="wallet-transfer"
                                class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-start hover:opacity-80">
                            <svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 12h13" /><path d="M13 6l6 6-6 6" />
                            </svg>
                            {{ setting('wallet.transfer.title', 'إرسال حوالة') }}
                        </button>
                        <button type="button" data-modal-open="wallet-exchange"
                                class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-start hover:opacity-80">
                            <svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 8h13l-3-3" /><path d="M20 16H7l3 3" />
                            </svg>
                            {{ setting('wallet.exchange.title', 'تحويل العملة') }}
                        </button>
                    @endif
                    @if ($canWithdraw)
                        <button type="button" data-modal-open="wallet-withdraw"
                                class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-start hover:opacity-80">
                            <svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 3v11" /><path d="M8 10l4 4 4-4" /><path d="M4 19h16" />
                            </svg>
                            {{ setting('wallet.withdraw.title', 'سحب الأرباح') }}
                        </button>
                    @endif
                    <a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('wallet.tickets') }}">{{ setting('wallet.tickets.title', 'التذاكر') }} <x-icon name="ticket" size="16" /></a>
                    @can('topup.list')
                        <a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('wallet.topup.requests') }}">{{ setting('wallet.topup_requests.title', 'طلبات الشحن') }}</a>
                    @endcan
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    {{-- ثلاثة تابات (19.2): رصيدي / المعاملات / المسحوبات --}}
    @include('wallet.components.tabs', ['current' => 'balance'])

    {{--
      ⭐ بطاقة الرصيد العريضة — حرفيًّا من ملف الهويّة (`.wallet-balance`):
      خلفيّة هادئة + رقمٌ ضخم على جهة، وأفعال الشحن/التحويل مكدّسة على الجهة
      الأخرى (19.2 · 2.17-أ: الرقم النهائيّ ظاهرٌ دائمًا).
    --}}
    <div class="wallet-balance spread">
        <div>
            <span class="eyebrow">{{ $main?->name_ar ?? setting('wallet.index.balance_label', 'الرصيد') }}</span>
            <div class="balance-number" data-count-to="{{ number_format($mainBalance, (int) ($main?->decimals ?? 0)) }}">
                {{ number_format($mainBalance, (int) ($main?->decimals ?? 0)) }}
            </div>
        </div>
        <div class="stack" style="gap: 12px">
            @can('topup.create')
                <a href="{{ route('wallet.topup') }}" class="btn btn-p inline-flex items-center gap-2">
                    <x-icon name="wallet" size="16" /> {{ setting('wallet.index.topup_action', 'اشحن رصيدك') }}
                </a>
            @endcan
            <div class="cluster">
                @if ($canTransfer)
                    <button type="button" data-modal-open="wallet-transfer" class="btn text">{{ setting('wallet.transfer.title', 'إرسال حوالة') }}</button>
                    <button type="button" data-modal-open="wallet-exchange" class="btn text">{{ setting('wallet.exchange.title', 'تحويل العملة') }}</button>
                @endif
                @if ($canWithdraw)
                    <button type="button" data-modal-open="wallet-withdraw" class="btn text">{{ setting('wallet.withdraw.title', 'سحب الأرباح') }}</button>
                @endif
            </div>
        </div>
    </div>

    {{-- ثلاثة عملات ثانويّة كما ينصّ 19.2: التذاكر / XP / الساعات — صفّ فواصل لا كروت --}}
    <div class="grid3 mb-8">
        @foreach ($secondary as $currency)
            @php
                $currencyIcon = $currency->code === 'tickets' ? 'ticket' : ($currency->code === 'xp' ? 'xp' : 'clock');
            @endphp
            <div class="divider-row">
                <div class="kpi-label"><x-icon :name="$currencyIcon" size="16" /> {{ $currency->name_ar }}</div>
                <div class="kpi-value">{{ number_format($balances[$currency->id] ?? 0, (int) $currency->decimals) }}</div>
                @if ($currency->code === 'hours')
                    <span class="small muted">{{ setting('wallet.index.hours_hint', 'عملة جايّة قدّام — بنعرضها من دلوقتي.') }}</span>
                @endif
            </div>
        @endforeach
    </div>

    {{-- سكشن الأرباح: أربعة أرقام + فعل نصّيّ (19.2) — 🔒 لمالك المنصّة وحده --}}
    @if ($canEarnings && $earnings)
        <section class="pt-4">
            <div class="spread">
                <h2>{{ setting('wallet.index.earnings_title', 'أرباحي') }}</h2>
                @if ($canWithdraw)
                    <button type="button" data-modal-open="wallet-withdraw" class="btn text inline-flex items-center gap-2">
                        {{ setting('wallet.withdraw.title', 'سحب الأرباح') }} <x-icon name="left" size="16" />
                    </button>
                @endif
            </div>
            <div class="earnings">
                <div><p class="small mb-2">{{ setting('wallet.earnings.ready', 'جاهزة للسحب') }}</p><strong>${{ $num($earnings['ready']) }}</strong></div>
                <div><p class="small mb-2">{{ setting('wallet.earnings.in_transit', 'قيد التحويل') }}</p><strong>${{ $num($earnings['in_transit']) }}</strong></div>
                <div><p class="small mb-2">{{ setting('wallet.earnings.received', 'مستلمة') }}</p><strong>${{ $num($earnings['received']) }}</strong></div>
                <div><p class="small mb-2">{{ setting('wallet.earnings.total', 'إجماليّة') }}</p><strong>${{ $num($earnings['total']) }}</strong></div>
            </div>
        </section>
    @endif

    {{-- آخر الحركات — والتفاصيل الكاملة في صفحة المعاملات --}}
    <section class="pt-4">
        <div class="spread mb-3">
            <h2>{{ setting('wallet.index.recent_title', 'آخر الحركات') }}</h2>
            <a class="btn text" href="{{ route('wallet.transactions') }}">{{ setting('wallet.index.all_transactions', 'كلّ المعاملات') }}</a>
        </div>

        @forelse ($recent as $row)
            <div class="divider-row spread">
                <div class="min-w-0">
                    <div class="text-sm font-semibold truncate">
                        {{ \App\Http\Controllers\Trainee\WalletController::sourceLabels()[$row->source] ?? $row->source }}
                    </div>
                    <div class="small muted mt-0.5" title="{{ $row->created_at?->format('Y-m-d H:i') }}">
                        {{ $row->created_at?->diffForHumans() }} · {{ $row->currency?->name_ar }}
                    </div>
                </div>
                @include('wallet.components.amount', [
                    'value' => $row->applied_amount ?? $row->amount,
                    'decimals' => (int) ($row->currency?->decimals ?? 0),
                ])
            </div>
        @empty
            {{-- الحالة الفارغة = سطر واحد + زرّ واحد، وتشجّع ولا تعاتب (2.15-د · 2.17-ج) --}}
            <x-empty :message="setting('wallet.index.recent_empty', 'لسّه مافيش حركة على محفظتك — أوّل شحنة مستنّياك.')"
                     :action="setting('wallet.index.topup_action', 'اشحن رصيدك')" :href="route('wallet.topup')" />
        @endforelse
    </section>

    @include('wallet.components.operations')
@endsection

@section('mobile_action')
    @can('topup.create')
        <a href="{{ route('wallet.topup') }}"
           class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.index.topup_action', 'اشحن رصيدك') }}</a>
    @endcan
@endsection
