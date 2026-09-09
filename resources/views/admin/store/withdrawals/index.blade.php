@extends('layouts.admin')

@section('title', setting('admin.store.withdrawals.index.tlbat_alshb', 'طلبات السحب'))

@section('content')
    <x-page-header :title="setting('admin.store.withdrawals.index.tlbat_alshb', 'طلبات السحب')"
                   :subtitle="setting('admin.store.withdrawals.index.subtitle', 'المبلغ مخصومٌ بالفعل — الاعتماد يقفل الطلب بعد صرفه، والرفض يردّه لصاحبه.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.store.withdrawals.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.store.withdrawals.index.almtjr_walmalyat', 'المتجر والماليّات'), 'url' => route('admin.store.index')],
                       ['label' => setting('admin.store.withdrawals.index.tlbat_alshb', 'طلبات السحب')],
                   ]" />

    {{-- ⭐ تاب بعدّاد N لأيّ جديد — والحالات الأربع لا خامس لها --}}
    <x-tabs :current="$status" :tabs="collect($statuses)->map(fn ($label, $key) => [
        'key' => $key,
        'label' => $label,
        'count' => $key === 'pending' ? $pendingCount : null,
        'url' => route('admin.withdrawals.index', ['status' => $key]),
    ])->values()->push([
        'key' => 'all',
        'label' => setting('admin.store.withdrawals.index.alkl', 'الكلّ'),
        'url' => route('admin.withdrawals.index', ['status' => 'all']),
    ])->all()" />

    @if ($rows->isEmpty())
        <x-empty :message="setting('admin.store.withdrawals.index.mafysh_tlbat', 'مافيش طلبات في النطاق ده — كلّ حاجة هادية.')" />
    @else
        <div class="card overflow-hidden">
            <table class="hidden md:table w-full text-sm">
                <thead style="background: var(--surface-sunken)">
                    <tr class="text-xs" style="color: var(--text-muted)">
                        <th class="text-start p-3">{{ setting('admin.store.withdrawals.index.altlb', 'الطلب') }}</th>
                        <th class="text-start p-3">{{ setting('admin.store.withdrawals.index.almstkhdm', 'المستخدم') }}</th>
                        <th class="text-start p-3">{{ setting('admin.store.withdrawals.index.alqyma_alsafya', 'القيمة الصافية') }}</th>
                        <th class="text-start p-3">{{ setting('admin.store.withdrawals.index.tryqa_althwyl', 'طريقة التحويل') }}</th>
                        <th class="text-start p-3">{{ setting('admin.store.withdrawals.index.alhala', 'الحالة') }}</th>
                        <th class="text-start p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-3 font-mono text-xs">{{ $row->number }}</td>
                            <td class="p-3">{{ $row->user?->name }} <span style="color: var(--text-muted)">#{{ $row->user?->code }}</span></td>
                            <td class="p-3">${{ rtrim(rtrim(number_format((float) $row->net_amount, 2), '0'), '.') }}</td>
                            <td class="p-3">{{ \App\Services\Wallet\WithdrawService::methods()[$row->method] ?? $row->method }}</td>
                            <td class="p-3"><x-state-badge :state="$row->state()" :label="$statuses[$row->status] ?? $row->statusLabel()" /></td>
                            <td class="p-3">
                                <a href="{{ route('admin.withdrawals.show', $row) }}" class="text-xs underline">{{ setting('admin.store.withdrawals.index.afth_almrajaa', 'افتح المراجعة') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="md:hidden">
                @foreach ($rows as $row)
                    <a href="{{ route('admin.withdrawals.show', $row) }}" class="block p-3 text-sm" style="border-top: 1px solid var(--border)">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs">{{ $row->number }}</span>
                            <x-state-badge :state="$row->state()" :label="$statuses[$row->status] ?? $row->statusLabel()" />
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $row->user?->name }} · ${{ rtrim(rtrim(number_format((float) $row->net_amount, 2), '0'), '.') }}
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="mt-5">{{ $rows->links() }}</div>
    @endif
@endsection
