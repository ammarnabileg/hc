@extends('layouts.admin')

@section('title', setting('admin.store.withdrawals.show.mrajaa_tlb_shb', 'مراجعة طلب سحب ') . $withdrawal->number)

@section('content')
    <x-page-header :title="setting('admin.store.withdrawals.show.mrajaa_altlb', 'مراجعة الطلب ') . $withdrawal->number"
                   :subtitle="setting('admin.store.withdrawals.show.subtitle', 'المبلغ محجوزٌ بالفعل — تأكيد الصرف يقفل الطلب، والرفض يردّه فورًا.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.store.withdrawals.show.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.store.withdrawals.show.tlbat_alshb', 'طلبات السحب'), 'url' => route('admin.withdrawals.index')],
                       ['label' => $withdrawal->number],
                   ]" />

    @if ($errors->any())
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-danger); color: var(--color-state-danger)">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-4">
            <div class="card p-4 space-y-2 text-sm">
                <h2 class="font-bold">{{ setting('admin.store.withdrawals.show.byanat_altlb', 'بيانات الطلب') }}</h2>
                <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.almblgh_almtlwb', 'المبلغ المطلوب') }}</span><strong>${{ rtrim(rtrim(number_format((float) $withdrawal->amount, 2), '0'), '.') }}</strong></div>
                <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.alrswm', 'الرسوم') }}</span><span>${{ rtrim(rtrim(number_format((float) $withdrawal->fee_amount, 2), '0'), '.') }} ({{ rtrim(rtrim(number_format((float) $withdrawal->fee_percent, 2), '0'), '.') }}%)</span></div>
                <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.alqyma_alsafya', 'القيمة الصافية') }}</span><strong style="color: var(--color-brand-500)">${{ rtrim(rtrim(number_format((float) $withdrawal->net_amount, 2), '0'), '.') }}</strong></div>
                <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.tryqa_althwyl', 'طريقة التحويل') }}</span><span>{{ \App\Services\Wallet\WithdrawService::methods()[$withdrawal->method] ?? $withdrawal->method }}</span></div>
                <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.rqm_alhsab', 'رقم الحساب') }}</span><span dir="ltr">{{ $withdrawal->account_number }}</span></div>
                @if ($withdrawal->account_name)
                    <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.asm_alhsab', 'اسم صاحب الحساب') }}</span><span>{{ $withdrawal->account_name }}</span></div>
                @endif
                <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.wqt_altlb', 'وقت الطلب') }}</span><span>{{ $withdrawal->created_at?->format('Y/m/d H:i') }}</span></div>
                <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.alhala', 'الحالة') }}</span><x-state-badge :state="$withdrawal->state()" :label="$statuses[$withdrawal->status] ?? $withdrawal->statusLabel()" /></div>

                @if ($withdrawal->status === \App\Models\WalletWithdrawal::PAID)
                    <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.mlahzt_alsrf', 'ملاحظة الصرف') }}</span><span>{{ $withdrawal->admin_note }}</span></div>
                @elseif ($withdrawal->status === \App\Models\WalletWithdrawal::REJECTED)
                    <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.sbb_alrfd', 'سبب الرفض') }}</span><span>{{ $withdrawal->reject_reason }}</span></div>
                @endif
            </div>

            <div class="card p-4 space-y-2 text-sm">
                <h2 class="font-bold">{{ setting('admin.store.withdrawals.show.almstkhdm', 'المستخدم') }}</h2>
                <div class="flex items-center gap-3">
                    <x-avatar :user="$withdrawal->user" size="10" />
                    <div>
                        <div class="font-semibold">{{ $withdrawal->user->name }}</div>
                        <div class="text-xs" style="color: var(--text-muted)">#{{ $withdrawal->user->code }}</div>
                    </div>
                </div>
                @if ($history->isNotEmpty())
                    <div class="text-xs mt-2" style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.tlbath_alsabqa', 'طلباته السابقة:') }} {{ $history->count() }}</div>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            @if ($withdrawal->status === \App\Models\WalletWithdrawal::PENDING)
                @if (auth()->user()->isPlatformOwner())
                    <form method="post" action="{{ route('admin.withdrawals.approve', $withdrawal) }}" class="card p-4 space-y-3">
                        @csrf
                        <h2 class="font-bold text-sm">{{ setting('admin.store.withdrawals.show.tsjyl_alsrf', 'تسجيل الصرف') }}</h2>
                        <p class="text-xs" style="color: var(--text-muted)">{{ setting('admin.store.withdrawals.show.hint_approve', 'حوّل المبلغ فعليًّا للمستخدم أوّلًا — وبعدين سجّل التأكيد هنا.') }}</p>
                        <label class="block text-sm">
                            <span class="block mb-1">{{ setting('admin.store.withdrawals.show.mlahzt_alsrf_ilzamy', 'ملاحظة الصرف (إلزاميّة)') }}</span>
                            <input type="text" name="note" required minlength="3"
                                   class="w-full rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                                   placeholder="{{ setting('admin.store.withdrawals.show.mthal_thwyl_bnky_rqm_alaamlya', 'مثال: تحويل بنكيّ — رقم العمليّة 12345') }}">
                        </label>
                        <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.store.withdrawals.show.tokid_alsrf', 'تأكيد الصرف ✓') }}</button>
                    </form>

                    <form method="post" action="{{ route('admin.withdrawals.reject', $withdrawal) }}" class="card p-4 space-y-3">
                        @csrf
                        <h2 class="font-bold text-sm">{{ setting('admin.store.withdrawals.show.rfd_altlb', 'رفض الطلب') }}</h2>
                        <label class="block text-sm">
                            <span class="block mb-1">{{ setting('admin.store.withdrawals.show.sbb_alrfd_ilzamy', 'سبب الرفض (إلزاميّ — بيوصل صاحب الطلب والمبلغ بيرجعله)') }}</span>
                            <textarea name="reason" rows="2" required minlength="3"
                                      class="w-full rounded-xl px-3 py-2 text-sm"
                                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                                      placeholder="{{ setting('admin.store.withdrawals.show.mthal_rqm_alhsab_ghyr_sh_raja_akdh', 'مثال: رقم الحساب غير صحّ — راجع وابعت طلبًا جديدًا') }}"></textarea>
                        </label>
                        <button class="rounded-xl px-4 py-2 text-sm font-semibold"
                                style="background: color-mix(in srgb, var(--color-state-danger) 22%, transparent); color: var(--color-state-danger)">
                            {{ setting('admin.store.withdrawals.show.rfd_altlb', 'رفض الطلب') }}
                        </button>
                    </form>
                @else
                    <div class="card p-4 text-sm" style="color: var(--text-muted)">
                        {{ setting('admin.store.withdrawals.show.alaatmad_walrfd_lmalk_almnsa_whdh', 'الاعتماد والرفض لمالك المنصّة وحده — إنت تقدر تستعرض بس.') }}
                    </div>
                @endif
            @endif
        </div>
    </div>
@endsection
