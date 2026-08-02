@extends('layouts.app')

@section('title', 'رصيدي وشحن')

@section('content')
    <x-page-header
        title="رصيدي وشحن"
        subtitle="رصيدك وكلّ حركة عليه — في مكان واحد."
        :breadcrumbs="[['label' => 'المحفظة', 'url' => route('wallet.index')], ['label' => 'رصيدي وشحن']]">
        {{-- فعل رئيسيّ واحد، والباقي في «⋯» (2.15-أ-2) --}}
        <x-slot:action>
            @can('topup.create')
                <a href="{{ route('wallet.topup') }}"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">اشحن رصيدك</a>
            @endcan
            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm select-none"
                         style="background: var(--surface-raised)" aria-label="خيارات أخرى">⋯</summary>
                <div class="card absolute inset-inline-end-0 mt-2 w-56 p-2 text-sm z-30">
                    <a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('wallet.transactions') }}">المعاملات والفواتير</a>
                    <a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('wallet.tickets') }}">التذاكر 🎟️</a>
                    @can('topup.list')
                        <a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('wallet.topup.requests') }}">طلبات الشحن</a>
                    @endcan
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    {{-- الكارت العريض: عدد الكوينز بعدّاد تصاعديّ (19.2 · 2.17-أ) --}}
    <section class="card p-5 md:p-6 animate-fadeup">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="text-sm" style="color: var(--text-muted)">{{ $main?->name_ar ?? 'الرصيد' }}</div>
                {{-- ⭐ الرقم النهائيّ يظهر في كلّ الأحوال ولا يعلق العدّاد أبدًا (2.17-أ) --}}
                <div class="mt-1 text-4xl md:text-5xl font-extrabold"
                     data-count-to="{{ number_format($mainBalance, (int) ($main?->decimals ?? 0)) }}">{{ number_format($mainBalance, (int) ($main?->decimals ?? 0)) }}</div>
            </div>
            @can('topup.create')
                <a href="{{ route('wallet.topup') }}"
                   class="btn hidden md:inline-flex items-center rounded-xl px-5 py-3 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">اشحن رصيدك</a>
            @endcan
        </div>
    </section>

    {{-- ثلاثة كروت ثانويّة — والحدّ الأقصى أربعة في الشاشة (2.15-أ-3) --}}
    <section class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4">
        @foreach ($secondary as $currency)
            <x-kpi
                :label="$currency->name_ar"
                :value="number_format($balances[$currency->id] ?? 0, (int) $currency->decimals)"
                :icon="$currency->code === 'tickets' ? '🎟️' : ($currency->code === 'xp' ? '⭐' : '⏱️')" />
        @endforeach
    </section>

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
@endsection

@section('mobile_action')
    @can('topup.create')
        <a href="{{ route('wallet.topup') }}"
           class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">اشحن رصيدك</a>
    @endcan
@endsection
