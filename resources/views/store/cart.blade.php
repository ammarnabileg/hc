@extends('layouts.app')
@use('App\Services\Store\Coins')

@section('title', setting('store.cart.page_title'))

@section('content')
    {{--
        صفحة **مراجعة الطلب** (17): السلّة ← مراجعة ← دفع بأقلّ خطوات، مع «وفّرت X».
        سؤال واحد للشاشة: «أراجع وأدفع؟» — وفعل رئيسيّ واحد (2.15).
        ⭐ ولا رقم في هذا الفورم: الأسطر والإجماليّ كلّها كما حسبها الخادم.
    --}}
    <x-page-header :title="setting('store.cart.page_title')"
                   :subtitle="setting('store.cart.page_subtitle')"
                   :breadcrumbs="[
                       ['label' => 'المتجر', 'url' => $storeUrl],
                       ['label' => setting('store.cart.page_title')],
                   ]">
        <x-slot:action>
            @include('store.partials.balance', ['balance' => $quote['balance_before']])
        </x-slot:action>
    </x-page-header>

    @if (session('status'))
        <div class="card p-3 mb-4 text-sm" role="status">{{ session('status') }}</div>
    @endif

    @if ($quote['lines'] === [])
        {{-- حالة فارغة: سطر واحد وزرّ واحد يشجّع ولا يعاتب (2.15-أ-8 · 2.17) --}}
        <x-empty :message="setting('store.cart.empty_text')"
                 action="اتفرّج على المتجر"
                 :href="$storeUrl" />
    @else
        <form method="post" action="{{ route('store.cart.checkout') }}" data-cart-form
              data-quote-url="{{ route('store.cart.quote') }}" class="grid gap-4 lg:grid-cols-3">
            @csrf

            <div class="lg:col-span-2 space-y-4">
                {{-- أسطر الطلب --}}
                <div class="card overflow-hidden">
                    @foreach ($quote['lines'] as $line)
                        <div class="flex items-center justify-between gap-3 p-4" style="border-bottom: 1px solid var(--border)">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate">{{ $line['title'] }}</p>
                                @if ($line['is_order_bump'])
                                    <p class="text-xs mt-0.5" style="color: var(--text-muted)">{{ setting('store.cart.bump_line_label') }}</p>
                                @endif
                            </div>

                            <div class="flex items-center gap-3 shrink-0">
                                <span class="text-sm font-semibold tabular-nums">{{ Coins::label($line['price']) }}</span>

                                @unless ($line['is_order_bump'])
                                    <button type="submit" form="cart-remove-{{ $loop->index }}"
                                            class="text-xs hover:underline" style="color: var(--text-muted)">
                                        {{ setting('store.cart.remove_label') }}
                                    </button>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Order-bump في صفحة المراجعة: واحد أو اثنان، ويتحدّث الإجماليّ فورًا (17) --}}
                @foreach ($quote['bump_offers'] as $bump)
                    <label class="card p-3 flex items-start gap-3 cursor-pointer" style="background: var(--surface-sunken)">
                        <input type="checkbox" name="bumps[]" value="{{ $bump['slug'] }}" class="mt-1" data-quote-trigger>
                        <span class="text-sm">
                            <span class="font-semibold">{{ $bump['title'] }}</span>
                            <span> — {{ Coins::label($bump['price']) }}</span>
                            @if ($bump['list_price'] > $bump['price'])
                                <span class="text-xs line-through" style="color: var(--text-muted)">{{ Coins::fmt($bump['list_price']) }}</span>
                            @endif
                            @if ($bump['teaser'])
                                <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ $bump['teaser'] }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach

                @if (setting('store.coupons.enabled', true))
                    <label class="card p-4 block">
                        <span class="block text-sm mb-1">{{ setting('store.cart.coupon_label') }}</span>
                        <input type="text" name="coupon_code" autocomplete="off" data-quote-trigger
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <span class="block text-xs mt-1" data-coupon-message style="color: var(--text-muted)"></span>
                    </label>
                @endif
            </div>

            {{-- الملخّص: يلتصق على الشاشات الكبيرة، ويأتي أسفل المحتوى على الموبايل --}}
            <aside class="space-y-3">
                <div class="card p-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span style="color: var(--text-muted)">{{ setting('store.cart.subtotal_label') }}</span>
                        <span data-quote="subtotal">{{ Coins::label($quote['subtotal']) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span style="color: var(--text-muted)">{{ setting('store.cart.discount_label') }}</span>
                        <span data-quote="discount">{{ Coins::label($quote['discount']) }}</span>
                    </div>
                    <div class="flex items-center justify-between font-bold">
                        <span>{{ setting('store.cart.total_label') }}</span>
                        <span data-quote="total">{{ Coins::label($quote['total']) }}</span>
                    </div>

                    {{-- «وفّرت X» من قيمةٍ حقيقيّة مسجَّلة لا من رقمٍ مخترَع (2.9 · 17) --}}
                    <p class="text-xs {{ $quote['savings'] > 0 ? '' : 'hidden' }}" data-savings-row
                       data-savings-template="{{ setting('store.savings.text', 'وفّرت {amount}') }}"
                       style="color: var(--color-brand-400)">
                        {{ str_replace('{amount}', Coins::label($quote['savings']), setting('store.savings.text', 'وفّرت {amount}')) }}
                    </p>

                    <div class="flex items-center justify-between" style="color: var(--text-muted)">
                        <span>{{ setting('store.cart.balance_before_label') }}</span>
                        <span data-quote="balance_before">{{ Coins::label($quote['balance_before']) }}</span>
                    </div>
                    <div class="flex items-center justify-between" style="color: var(--text-muted)">
                        <span>{{ setting('store.cart.balance_after_label') }}</span>
                        <span data-quote="balance_after">{{ Coins::label($quote['balance_after']) }}</span>
                    </div>
                </div>

                {{-- رصيد غير كافٍ + ⭐ أقرب عرض يكفّيك (19.5-ب-2) --}}
                <div class="card p-3 space-y-2 {{ $quote['sufficient'] ? 'hidden' : '' }}" data-topup-block
                     style="border-color: var(--color-state-warn)">
                    <p class="text-sm">{{ setting('store.insufficient_text') }}</p>
                    <p class="text-sm {{ $suggestionText ? '' : 'hidden' }}" data-topup-suggestion
                       style="color: var(--text-muted)">{{ $suggestionText }}</p>
                    @if ($quote['suggestion']['offer']['url'] ?? null)
                        <a href="{{ $quote['suggestion']['offer']['url'] }}" data-topup-link
                           class="btn inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold"
                           style="background: var(--color-brand-500); color: #04201c">{{ setting('store.topup.sheet_button_text') }}</a>
                    @endif
                </div>

                {{-- إقرار سياسة عدم الاسترجاع — إلزاميّ قبل الدفع (19.4) --}}
                <label class="card p-3 flex items-start gap-3 text-sm cursor-pointer">
                    <input type="checkbox" name="refund_ack" value="1" required class="mt-1">
                    <span>
                        {{ setting('store.refund.ack_text') }}
                        <a href="{{ route('store.refund-policy') }}" target="_blank" rel="noopener"
                           class="hover:underline" style="color: var(--color-brand-400)">{{ setting('store.refund.link_text') }}</a>
                    </span>
                </label>

                @error('checkout')
                    <p class="text-sm" style="color: var(--color-state-danger)">◉ {{ $message }}</p>
                @enderror

                {{-- الفعل الرئيسيّ الوحيد على الشاشة (2.15-أ-2) --}}
                <button type="submit" data-cart-submit @disabled(! $quote['sufficient'])
                        class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c; opacity: {{ $quote['sufficient'] ? '1' : '.5' }}">
                    {{ setting('store.cart.submit_label') }}
                </button>
            </aside>
        </form>

        {{-- فورمات الحذف خارج فورم الدفع حتى لا تتداخل الحقول --}}
        @foreach ($quote['lines'] as $index => $line)
            @unless ($line['is_order_bump'])
                <form id="cart-remove-{{ $index }}" method="post" action="{{ route('store.cart.remove') }}" class="hidden">
                    @csrf
                    <input type="hidden" name="type" value="{{ $line['type'] }}">
                    <input type="hidden" name="slug" value="{{ $line['slug'] }}">
                </form>
            @endunless
        @endforeach
    @endif
@endsection

@push('scripts')
<script>
    /* ⭐ لا حساب في المتصفّح: نرسل الكوبون واختيار الـBump، والخادم يرجّع الأرقام مصاغةً. */
    (function () {
        const form = document.querySelector('[data-cart-form]');
        if (!form) return;

        const submit = form.querySelector('[data-cart-submit]');
        const topupBlock = form.querySelector('[data-topup-block]');
        const suggestion = form.querySelector('[data-topup-suggestion]');
        const suggestionLink = form.querySelector('[data-topup-link]');
        const couponMessage = form.querySelector('[data-coupon-message]');
        const savings = form.querySelector('[data-savings-row]');
        let timer = null;

        const paint = (data) => {
            form.querySelectorAll('[data-quote]').forEach((el) => {
                const key = el.dataset.quote;
                if (data[key] !== undefined) el.textContent = data[key];
            });

            if (couponMessage) couponMessage.textContent = data.coupon_message || '';
            if (topupBlock) topupBlock.classList.toggle('hidden', !!data.sufficient);
            if (submit) {
                submit.disabled = !data.sufficient;
                submit.style.opacity = data.sufficient ? '1' : '.5';
            }
            if (suggestion) {
                suggestion.textContent = data.suggestion || '';
                suggestion.classList.toggle('hidden', !data.suggestion);
            }
            if (suggestionLink && data.suggestion_url) suggestionLink.href = data.suggestion_url;

            if (savings) {
                savings.textContent = (savings.dataset.savingsTemplate || '').replace('{amount}', data.savings || '');
                savings.classList.toggle('hidden', !data.savings);
            }
        };

        const refresh = () => {
            const body = new FormData(form);
            body.delete('_token');
            body.delete('refund_ack');
            fetch(form.dataset.quoteUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                body: body,
            })
                .then((r) => (r.ok ? r.json() : null))
                .then((data) => { if (data) paint(data); })
                /* فشل الشبكة لا يغيّر أرقام الخادم المعروضة */
                .catch(() => {});
        };

        form.querySelectorAll('[data-quote-trigger]').forEach((el) => {
            el.addEventListener('change', refresh);
            el.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(refresh, 400);
            });
        });
    })();
</script>
@endpush
