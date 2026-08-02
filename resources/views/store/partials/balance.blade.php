@use('App\Services\Store\Coins')

@php
    /**
     * رصيدي بالكوينز بجانب زرّ [شحن] في هيدر المتجر (24.5).
     * الشحن مملوك لمجال المحفظة — فنربطه بحماية Route::has حتى لا تنكسر الصفحة قبل بنائه.
     */
    $topupUrl = \Illuminate\Support\Facades\Route::has('wallet.topup')
        ? route('wallet.topup')
        : (\Illuminate\Support\Facades\Route::has('wallet.index') ? route('wallet.index') : null);

    // السلّة تظهر فقط حين تكون فيها عناصر — والفارغ لا يشغل مساحةً ولا انتباهًا (2.15)
    $cartCount = auth()->check() && setting('store.cart.enabled', true)
        ? app(\App\Services\Store\CartService::class)->count(request())
        : 0;

    // رصيد **العملة المعروضة** — والمتجر صار يسعّر بثلاث عملات (17)
    $balanceCurrency = $balanceCurrency ?? Coins::defaultCode();
@endphp

<div class="flex items-center gap-2">
    @if ($cartCount > 0 && ! request()->routeIs('store.cart'))
        <a href="{{ route('store.cart') }}" class="card px-3 py-1.5 text-sm whitespace-nowrap hover:underline"
           aria-label="{{ setting('store.cart.page_title') }}">
            {{-- أيقونة سلّة مرسومة بهويّة المنصّة — بلا مكتبات (2.16-ج) --}}
            <span class="inline-flex items-center gap-1.5">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 5h2l2.2 9.2a1.6 1.6 0 0 0 1.6 1.3h6.9a1.6 1.6 0 0 0 1.6-1.2L20 8H7" />
                    <circle cx="10" cy="19" r="1.2" />
                    <circle cx="17" cy="19" r="1.2" />
                </svg>
                <b>{{ $cartCount }}</b>
            </span>
        </a>
    @endif

    <span class="card px-3 py-1.5 text-sm whitespace-nowrap">
        <span style="color: var(--text-muted)">رصيدي</span>
        {{-- عدّاد تصاعديّ — والرقم النهائيّ يظهر في كلّ الأحوال (2.17-أ) --}}
        <b data-count-to="{{ Coins::fmt($balance) }}">{{ Coins::fmt($balance) }}</b>
        <span style="color: var(--text-muted)">{{ Coins::currencyLabel($balanceCurrency) }}</span>
    </span>

    @if ($topupUrl)
        <a href="{{ $topupUrl }}"
           class="btn inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('store.topup.button_text', 'شحن') }}</a>
    @endif
</div>
