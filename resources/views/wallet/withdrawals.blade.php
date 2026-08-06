@extends('layouts.app')

@section('title', setting('wallet.tabs.withdrawals', 'المسحوبات'))

@php
    $num = fn ($v, $d = 2) => number_format((float) $v, $d);
@endphp

@section('content')
    <x-page-header
        :title="setting('wallet.tabs.withdrawals', 'المسحوبات')"
        :subtitle="setting('wallet.withdrawals.subtitle', 'أرباحك وطلبات سحبها وحالة كلّ طلب.')"
        :breadcrumbs="[['label' => setting('wallet.index.breadcrumb_root', 'المحفظة'), 'url' => route('wallet.index')], ['label' => setting('wallet.tabs.withdrawals', 'المسحوبات')]]">
        <x-slot:action>
            @if ($canWithdraw)
                <button type="button" data-modal-open="wallet-withdraw"
                        class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.withdraw.title', 'سحب الأرباح') }}</button>
            @endif
        </x-slot:action>
    </x-page-header>

    @include('wallet.components.tabs', ['current' => 'withdrawals'])

    {{-- «متاح للسحب» في صدر التاب كما ينصّ 19.2 --}}
    <section class="card p-5 md:p-6 animate-fadeup">
        <div class="text-sm" style="color: var(--text-muted)">{{ setting('wallet.withdraw.available', 'متاح للسحب') }}</div>
        <div class="mt-1 text-4xl font-extrabold"
             data-count-to="{{ $num($earnings['ready'] ?? 0) }}">{{ '$'.$num($earnings['ready'] ?? 0) }}</div>
        <p class="mt-2 text-xs" style="color: var(--text-muted)">
            {{ str_replace(
                [':fee', ':minfee', ':min'],
                [
                    rtrim(rtrim(number_format($withdrawLimits['fee_percent'], 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($withdrawLimits['min_fee'], 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($withdrawLimits['min_amount'], 2, '.', ''), '0'), '.'),
                ],
                (string) setting('wallet.withdrawals.limits_note', 'رسوم السحب :fee% بحدّ أدنى $:minfee، وأقلّ سحب $:min.'),
            ) }}
        </p>
    </section>

    @if ($canEarnings && $earnings)
        <section class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
            <x-kpi :label="setting('wallet.earnings.ready', 'جاهزة للسحب')" :value="'$'.$num($earnings['ready'])" icon="money" />
            <x-kpi :label="setting('wallet.earnings.in_transit', 'قيد التحويل')" :value="'$'.$num($earnings['in_transit'])" icon="hourglass" />
            <x-kpi :label="setting('wallet.earnings.received', 'مستلمة')" :value="'$'.$num($earnings['received'])" icon="check" />
            <x-kpi :label="setting('wallet.earnings.total', 'إجماليّة')" :value="'$'.$num($earnings['total'])" icon="chart" />
        </section>
    @endif

    <section class="mt-5">
        <h2 class="font-bold mb-3">{{ setting('wallet.withdrawals.table_title', 'جدول المسحوبات') }}</h2>

        @if ($rows->isEmpty())
            <x-empty :message="setting('wallet.withdrawals.empty_message', 'لسّه مافيش مسحوبات — أوّل أرباحك على بُعد دعوة واحدة.')" />
        @else
            {{-- سطح المكتب: جدول ستّة أعمدة (2.15-أ-5) ومنه عمود صورة الفاتورة (19.2) --}}
            <div class="card hidden md:block overflow-hidden">
                <table class="w-full text-sm"
                   {{-- حدّ الأعمدة الافتراضيّ من الإعدادات، و«وضع متقدّم» يرفعه (2.15-أ-5) --}}
                   @unless (advanced_mode()) data-columns-cap="{{ view_mode()->defaultColumns() }}" @endunless>
                    <thead>
                        <tr style="background: var(--surface-sunken)">
                            <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.withdrawals.col_number', 'رقم الطلب') }}</th>
                            <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.withdrawals.col_date', 'التاريخ') }}</th>
                            <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.withdrawals.col_amount', 'القيمة') }}</th>
                            <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.withdrawals.col_fee', 'الرسوم') }}</th>
                            <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.withdrawals.col_status', 'الحالة') }}</th>
                            <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.withdrawals.col_receipt', 'صورة الفاتورة') }}</th>
                        </tr>
                    </thead>
                    <tbody data-wallet-wd-desktop>
                        @include('wallet.partials.withdrawals-rows-desktop', ['rows' => $rows])
                    </tbody>
                </table>
            </div>

            {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
            <div class="md:hidden space-y-3" data-wallet-wd-mobile>
                @include('wallet.partials.withdrawals-rows-mobile', ['rows' => $rows])
            </div>

            {{-- تمرير تدريجيّ بلا ترقيم صفحات (13.1 · قرار §25) --}}
            @include('partials.load-more', [
                'hasMore' => $hasMore,
                'moreUrl' => route('wallet.withdrawals.more', ['offset' => $nextOffset]),
                'nextOffset' => $nextOffset,
                'pageSize' => $pageSize,
                'targetSelector' => '[data-wallet-wd-desktop], [data-wallet-wd-mobile]',
            ])
        @endif
    </section>

    @include('wallet.components.operations')
@endsection

@section('mobile_action')
    @if ($canWithdraw)
        <button type="button" data-modal-open="wallet-withdraw"
                class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.withdraw.title', 'سحب الأرباح') }}</button>
    @endif
@endsection
