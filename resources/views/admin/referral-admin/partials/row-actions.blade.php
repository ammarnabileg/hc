@php
    /**
     * إجراءات صفّ الدعوة (24.2): صرف · تعليق · تدقيق شبكة الداعي.
     * والمصروفة لا تُصرَف مرّتين — الزرّ يختفي بعد الصرف.
     */
    $u = auth()->user();
    $canManage = $u?->can('referrals.manage');
@endphp

<div class="flex flex-wrap items-center gap-3 text-xs">
    @if ($canManage && $referral->payout_status !== 'paid')
        <button type="button" class="underline" data-referral-payout
                data-action="{{ route('admin.referrals.payout', $referral) }}">صرف</button>
    @endif

    @if ($canManage && $referral->payout_status !== 'held')
        <button type="button" class="underline" data-referral-hold
                data-action="{{ route('admin.referrals.hold', $referral) }}">تعليق</button>
    @endif

    @if ($referral->referrer)
        <a class="underline" href="{{ route('admin.referrals.audit', $referral->referrer) }}">تدقيق</a>
    @endif

    @if ($referral->is_flagged)
        <x-state-badge state="warn" label="محتاج تدقيق" />
    @endif
</div>
