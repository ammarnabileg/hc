@extends('layouts.app')

@section('title', 'المسحوبات')

@php
    $num = fn ($v, $d = 2) => number_format((float) $v, $d);
@endphp

@section('content')
    <x-page-header
        title="المسحوبات"
        subtitle="أرباحك وطلبات سحبها وحالة كلّ طلب."
        :breadcrumbs="[['label' => 'المحفظة', 'url' => route('wallet.index')], ['label' => 'المسحوبات']]">
        <x-slot:action>
            @if ($canWithdraw)
                <button type="button" data-modal-open="wallet-withdraw"
                        class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">سحب الأرباح</button>
            @endif
        </x-slot:action>
    </x-page-header>

    @include('wallet.components.tabs', ['current' => 'withdrawals'])

    {{-- «متاح للسحب» في صدر التاب كما ينصّ 19.2 --}}
    <section class="card p-5 md:p-6 animate-fadeup">
        <div class="text-sm" style="color: var(--text-muted)">متاح للسحب</div>
        <div class="mt-1 text-4xl font-extrabold"
             data-count-to="{{ $num($earnings['ready'] ?? 0) }}">{{ '$'.$num($earnings['ready'] ?? 0) }}</div>
        <p class="mt-2 text-xs" style="color: var(--text-muted)">
            رسوم السحب {{ rtrim(rtrim(number_format($withdrawLimits['fee_percent'], 2, '.', ''), '0'), '.') }}%
            بحدّ أدنى ${{ rtrim(rtrim(number_format($withdrawLimits['min_fee'], 2, '.', ''), '0'), '.') }}،
            وأقلّ سحب ${{ rtrim(rtrim(number_format($withdrawLimits['min_amount'], 2, '.', ''), '0'), '.') }}.
        </p>
    </section>

    @if ($canEarnings && $earnings)
        <section class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
            <x-kpi label="جاهزة للسحب" :value="'$'.$num($earnings['ready'])" icon="💵" />
            <x-kpi label="قيد التحويل" :value="'$'.$num($earnings['in_transit'])" icon="⏳" />
            <x-kpi label="مستلمة" :value="'$'.$num($earnings['received'])" icon="✅" />
            <x-kpi label="إجماليّة" :value="'$'.$num($earnings['total'])" icon="📊" />
        </section>
    @endif

    <section class="mt-5">
        <h2 class="font-bold mb-3">جدول المسحوبات</h2>

        @if ($rows->isEmpty())
            <x-empty message="لسّه مافيش مسحوبات — أوّل أرباحك على بُعد دعوة واحدة." />
        @else
            {{-- سطح المكتب: جدول ستّة أعمدة (2.15-أ-5) ومنه عمود صورة الفاتورة (19.2) --}}
            <div class="card hidden md:block overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background: var(--surface-sunken)">
                            <th class="text-start font-semibold px-4 py-3">رقم الطلب</th>
                            <th class="text-start font-semibold px-4 py-3">التاريخ</th>
                            <th class="text-start font-semibold px-4 py-3">القيمة</th>
                            <th class="text-start font-semibold px-4 py-3">الرسوم</th>
                            <th class="text-start font-semibold px-4 py-3">الحالة</th>
                            <th class="text-start font-semibold px-4 py-3">صورة الفاتورة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $row->number }}</td>
                                <td class="px-4 py-3 whitespace-nowrap" title="{{ $row->created_at?->format('Y-m-d H:i') }}">
                                    {{ $row->created_at?->diffForHumans() }}
                                </td>
                                <td class="px-4 py-3 font-semibold">${{ $num($row->amount) }}</td>
                                <td class="px-4 py-3" style="color: var(--text-muted)">${{ $num($row->fee_amount) }}</td>
                                <td class="px-4 py-3">
                                    <x-state-badge :state="$row->state()" :label="$row->statusLabel()" />
                                </td>
                                <td class="px-4 py-3">
                                    @if ($row->receipt_path)
                                        <a class="underline" target="_blank" rel="noopener"
                                           href="{{ \Illuminate\Support\Facades\Storage::url($row->receipt_path) }}">افتح الصورة</a>
                                    @else
                                        <span style="color: var(--text-muted)">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
            <div class="md:hidden space-y-3">
                @foreach ($rows as $row)
                    <div class="card p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold">{{ $row->number }}</span>
                            <x-state-badge :state="$row->state()" :label="$row->statusLabel()" />
                        </div>
                        <div class="mt-2 text-sm flex items-center justify-between gap-2">
                            <span>${{ $num($row->amount) }}</span>
                            <span style="color: var(--text-muted)">يوصلك ${{ $num($row->net_amount) }}</span>
                        </div>
                        <div class="mt-2 text-xs flex items-center justify-between gap-2" style="color: var(--text-muted)">
                            <span>{{ $row->created_at?->format('Y-m-d H:i') }}</span>
                            @if ($row->receipt_path)
                                <a class="underline" target="_blank" rel="noopener"
                                   href="{{ \Illuminate\Support\Facades\Storage::url($row->receipt_path) }}">صورة الفاتورة</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $rows->links() }}</div>
        @endif
    </section>

    @include('wallet.components.operations')
@endsection

@section('mobile_action')
    @if ($canWithdraw)
        <button type="button" data-modal-open="wallet-withdraw"
                class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">سحب الأرباح</button>
    @endif
@endsection
