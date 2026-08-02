@use('App\Services\Store\Coins')

@php
    /**
     * رصيدي بالكوينز بجانب زرّ [شحن] في هيدر المتجر (24.5).
     * الشحن مملوك لمجال المحفظة — فنربطه بحماية Route::has حتى لا تنكسر الصفحة قبل بنائه.
     */
    $topupUrl = \Illuminate\Support\Facades\Route::has('wallet.topup')
        ? route('wallet.topup')
        : (\Illuminate\Support\Facades\Route::has('wallet.index') ? route('wallet.index') : null);
@endphp

<div class="flex items-center gap-2">
    <span class="card px-3 py-1.5 text-sm whitespace-nowrap">
        <span style="color: var(--text-muted)">رصيدي</span>
        {{-- عدّاد تصاعديّ — والرقم النهائيّ يظهر في كلّ الأحوال (2.17-أ) --}}
        <b data-count-to="{{ Coins::fmt($balance) }}">{{ Coins::fmt($balance) }}</b>
        <span style="color: var(--text-muted)">{{ setting('store.currency.label', 'كوين') }}</span>
    </span>

    @if ($topupUrl)
        <a href="{{ $topupUrl }}"
           class="btn inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('store.topup.button_text', 'شحن') }}</a>
    @endif
</div>
