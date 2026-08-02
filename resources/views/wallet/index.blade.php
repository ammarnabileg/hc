@extends('layouts.app')

@section('title', 'رصيدي وشحن')

@php
    $num = fn ($v, $d = 2) => number_format((float) $v, $d);
    // أيقونات SVG بهويّة المنصّة — ممنوع أيّ مكتبة أيقونات جاهزة
    $icon = 'w-4 h-4 shrink-0';
@endphp

@section('content')
    <x-page-header
        title="رصيدي وشحن"
        subtitle="رصيدك وأرباحك وكلّ حركة عليه — في مكان واحد."
        :breadcrumbs="[['label' => 'المحفظة', 'url' => route('wallet.index')], ['label' => 'رصيدي وشحن']]">
        {{--
          ⭐ حسم التعارض: 19.2 يوجب أربعة أفعال، و2.15 يوجب فعلًا رئيسيًّا واحدًا.
          فالحلّ: [شحن] ظاهرٌ وحده، والثلاثة الباقية في قائمة إجراءات ثانويّة.
        --}}
        <x-slot:action>
            @can('topup.create')
                <a href="{{ route('wallet.topup') }}"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">اشحن رصيدك</a>
            @endcan
            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm select-none"
                         style="background: var(--surface-raised)" aria-label="إجراءات أخرى">⋯</summary>
                <div class="card absolute inset-inline-end-0 mt-2 w-60 p-2 text-sm z-30">
                    @if ($canTransfer)
                        <button type="button" data-modal-open="wallet-transfer"
                                class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-start hover:opacity-80">
                            <svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 12h13" /><path d="M13 6l6 6-6 6" />
                            </svg>
                            إرسال حوالة
                        </button>
                        <button type="button" data-modal-open="wallet-exchange"
                                class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-start hover:opacity-80">
                            <svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 8h13l-3-3" /><path d="M20 16H7l3 3" />
                            </svg>
                            تحويل العملة
                        </button>
                    @endif
                    @if ($canWithdraw)
                        <button type="button" data-modal-open="wallet-withdraw"
                                class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-start hover:opacity-80">
                            <svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 3v11" /><path d="M8 10l4 4 4-4" /><path d="M4 19h16" />
                            </svg>
                            سحب الأرباح
                        </button>
                    @endif
                    <a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('wallet.tickets') }}">التذاكر 🎟️</a>
                    @can('topup.list')
                        <a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('wallet.topup.requests') }}">طلبات الشحن</a>
                    @endcan
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    {{-- ثلاثة تابات (19.2): رصيدي / المعاملات / المسحوبات --}}
    @include('wallet.components.tabs', ['current' => 'balance'])

    {{-- الكارت العريض: عدد الكوينز بعدّاد تصاعديّ (19.2 · 2.17-أ) --}}
    <section class="card p-5 md:p-6 animate-fadeup">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="text-sm" style="color: var(--text-muted)">{{ $main?->name_ar ?? 'الرصيد' }}</div>
                {{-- ⭐ الرقم النهائيّ يظهر في كلّ الأحوال ولا يعلق العدّاد أبدًا (2.17-أ) --}}
                <div class="mt-1 text-4xl md:text-5xl font-extrabold"
                     data-count-to="{{ number_format($mainBalance, (int) ($main?->decimals ?? 0)) }}">{{ number_format($mainBalance, (int) ($main?->decimals ?? 0)) }}</div>
            </div>
            <div class="flex items-center gap-2">
                @can('topup.create')
                    <a href="{{ route('wallet.topup') }}"
                       class="btn hidden md:inline-flex items-center rounded-xl px-5 py-3 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">اشحن رصيدك</a>
                @endcan
                @if ($canTransfer)
                    <button type="button" data-modal-open="wallet-exchange"
                            class="btn hidden md:inline-flex items-center rounded-xl px-5 py-3 text-sm font-semibold motion-standard"
                            style="background: var(--surface-raised); color: var(--text)">تحويل</button>
                @endif
            </div>
        </div>
    </section>

    {{-- ثلاثة كروت ثانويّة كما ينصّ 19.2: التذاكر / XP / الساعات --}}
    <section class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4">
        @foreach ($secondary as $currency)
            <x-kpi
                :label="$currency->name_ar"
                :value="number_format($balances[$currency->id] ?? 0, (int) $currency->decimals)"
                :icon="$currency->code === 'tickets' ? '🎟️' : ($currency->code === 'xp' ? '⭐' : '⏱️')"
                :hint="$currency->code === 'hours' ? 'عملة جايّة قدّام — بنعرضها من دلوقتي.' : null" />
        @endforeach
    </section>

    {{-- سكشن الأرباح: أربعة كروت + زرّ سحب (19.2) — 🔒 لمالك المنصّة وحده --}}
    @if ($canEarnings && $earnings)
        <section class="mt-5">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="font-bold">أرباحي</h2>
                @if ($canWithdraw)
                    <button type="button" data-modal-open="wallet-withdraw"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">سحب الأرباح</button>
                @endif
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <x-kpi label="جاهزة للسحب" :value="'$'.$num($earnings['ready'])" icon="💵" />
                <x-kpi label="قيد التحويل" :value="'$'.$num($earnings['in_transit'])" icon="⏳" />
                <x-kpi label="مستلمة" :value="'$'.$num($earnings['received'])" icon="✅" />
                <x-kpi label="إجماليّة" :value="'$'.$num($earnings['total'])" icon="📊" />
            </div>
        </section>
    @endif

    {{-- آخر 5 حركات — والتفاصيل الكاملة في صفحة المعاملات --}}
    <section class="card mt-5 p-4 md:p-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="font-bold">آخر الحركات</h2>
            <a class="text-sm underline" href="{{ route('wallet.transactions') }}">كلّ المعاملات</a>
        </div>

        @forelse ($recent as $row)
            <div class="flex items-center justify-between gap-3 py-3 {{ $loop->last ? '' : 'border-b' }}"
                 style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="text-sm font-semibold truncate">
                        {{ \App\Http\Controllers\Trainee\WalletController::SOURCE_LABELS[$row->source] ?? $row->source }}
                    </div>
                    <div class="text-xs mt-0.5" style="color: var(--text-muted)"
                         title="{{ $row->created_at?->format('Y-m-d H:i') }}">
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
            <x-empty message="لسّه مافيش حركة على محفظتك — أوّل شحنة مستنّياك."
                     action="اشحن رصيدك" :href="route('wallet.topup')" />
        @endforelse
    </section>

    @include('wallet.components.operations')
@endsection

@section('mobile_action')
    @can('topup.create')
        <a href="{{ route('wallet.topup') }}"
           class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">اشحن رصيدك</a>
    @endcan
@endsection
