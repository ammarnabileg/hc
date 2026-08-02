@use('App\Services\Store\Coins')

@php
    /**
     * بوب-أب الشراء (24.5): الملخّص · الرصيد قبل/بعد · كوبون · Order-bump ·
     * إقرار سياسة عدم الاسترجاع (19.4) · وشحن المحفظة من داخله بلا مغادرة الصفحة.
     *
     * ⭐ كلّ الأرقام هنا معروضة كما حسبها الخادم — والفورم لا يرسل سعرًا ولا خصمًا.
     * وعلى الموبايل يفتح كـBottom Sheet (2.15-ج).
     */
    // Order-bump: واحد أو اثنان كحدٍّ أقصى — والحدّ نفسه إعداد (17)
    $bumps = array_slice($quote['bump_offers'] ?? [], 0, max((int) setting('store.order_bump.max', 2), 1));
    $topupUrl = \Illuminate\Support\Facades\Route::has('wallet.topup')
        ? route('wallet.topup')
        : (\Illuminate\Support\Facades\Route::has('wallet.index') ? route('wallet.index') : null);
    $reopen = session('checkout_reason') !== null;

    // ⭐ أقرب عرض يكفّيك (19.5-ب-2): يُحسَب في الخادم ويُعرَض عند نقص الرصيد فقط
    $currency = $quote['currency'] ?? Coins::defaultCode();
    $suggestion = app(\App\Services\Store\NearestTopupOffer::class)->forDeficit($quote['total'] - $quote['balance_before'], $currency);
    $suggestionText = app(\App\Services\Store\NearestTopupOffer::class)->sentence($suggestion);
@endphp

<div id="purchase-sheet"
     class="fixed inset-0 z-50 {{ $reopen ? 'flex' : 'hidden' }} items-end md:items-center justify-center md:p-4"
     style="background: rgb(0 0 0 / .55)" data-modal role="dialog" aria-modal="true" aria-label="إتمام الشراء">

    {{-- Bottom Sheet على الموبايل، وبوب-أب في المنتصف على الشاشات الأكبر --}}
    <div class="modal-shell card w-full md:max-w-lg rounded-b-none md:rounded-2xl">
        <div class="modal-head flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid var(--border)">
            <h2 class="font-bold">إتمام الشراء</h2>
            <button type="button" class="text-sm opacity-70 hover:opacity-100" data-modal-close aria-label="إغلاق">✕</button>
        </div>

        <form method="post" action="{{ route('store.checkout') }}" data-purchase-form
              data-quote-url="{{ route('store.quote') }}">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="slug" value="{{ $item->slug }}">

            <div class="modal-body px-5 py-4 space-y-4">
                {{-- الملخّص --}}
                <div class="space-y-2" data-quote-lines>
                    @foreach ($quote['lines'] as $line)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span>{{ $line['title'] }}</span>
                            <span class="font-semibold">{{ Coins::label($line['price'], $currency) }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Order-bump: تشيك بوكس لكلّ عرض (واحد أو اثنان) يُضاف فورًا ويتحدّث الإجماليّ (17) --}}
                @foreach ($bumps as $bump)
                    <label class="card p-3 flex items-start gap-3 cursor-pointer" style="background: var(--surface-sunken)">
                        {{-- ⭐ القيمة slug لا سعر — والخادم يطابقها بعروضه هو --}}
                        <input type="checkbox" name="bumps[]" value="{{ $bump['slug'] }}" class="mt-1" data-quote-trigger>
                        <span class="text-sm">
                            <span class="font-semibold">{{ $bump['title'] }}</span>
                            <span> — {{ Coins::label($bump['price'], $currency) }}</span>
                            @if ($bump['list_price'] > $bump['price'])
                                <span class="text-xs line-through" style="color: var(--text-muted)">{{ Coins::fmt($bump['list_price']) }}</span>
                            @endif
                            @if ($bump['teaser'])
                                <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ $bump['teaser'] }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach

                {{-- الكوبون: يُتحقَّق منه في الخادم --}}
                @if (setting('store.coupons.enabled', true))
                    <label class="block">
                        <span class="block text-sm mb-1">كود خصم <span class="text-xs" style="color: var(--text-muted)">(اختياريّ)</span></span>
                        <input type="text" name="coupon_code" value="{{ old('coupon_code') }}" autocomplete="off"
                               class="w-full rounded-xl px-3 py-2 text-sm" data-quote-trigger
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <span class="block text-xs mt-1" data-coupon-message style="color: var(--text-muted)"></span>
                    </label>
                @endif

                {{-- الرصيد قبل/بعد والإجماليّ --}}
                <div class="card p-3 space-y-2 text-sm" style="background: var(--surface-sunken)">
                    <div class="flex items-center justify-between">
                        <span style="color: var(--text-muted)">المجموع</span>
                        <span data-quote="subtotal">{{ Coins::label($quote['subtotal'], $currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span style="color: var(--text-muted)">الخصم</span>
                        <span data-quote="discount">{{ Coins::label($quote['discount'], $currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between font-bold">
                        <span>الإجماليّ</span>
                        <span data-quote="total">{{ Coins::label($quote['total'], $currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between" style="color: var(--text-muted)">
                        <span>رصيدك قبل</span>
                        <span data-quote="balance_before">{{ Coins::label($quote['balance_before'], $currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between" style="color: var(--text-muted)">
                        <span>رصيدك بعد</span>
                        <span data-quote="balance_after">{{ Coins::label($quote['balance_after'], $currency) }}</span>
                    </div>
                </div>

                {{-- ⭐ رصيد غير كافٍ: [اشحن المحفظة] هنا نفسه بلا مغادرة الصفحة (24.5) --}}
                <div class="card p-3 space-y-2 {{ $quote['sufficient'] ? 'hidden' : '' }}" data-topup-block
                     style="border-color: var(--color-state-warn)">
                    <p class="text-sm">{{ setting('store.insufficient_text', 'رصيدك أقلّ من قيمة الطلب — اشحن محفظتك وكمّل من نفس المكان.') }}</p>

                    {{-- ⭐ أقرب عرض يكفّيك (19.5-ب-2): بقيمته الحقيقيّة صراحةً وبلا Dark Patterns (2.9) --}}
                    <p class="text-sm {{ $suggestionText ? '' : 'hidden' }}" data-topup-suggestion
                       style="color: var(--text-muted)">{{ $suggestionText }}</p>

                    <a href="{{ $suggestion['offer']['url'] ?? $topupUrl }}" data-topup-link
                       class="btn inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold {{ ($suggestion['offer']['url'] ?? $topupUrl) ? '' : 'hidden' }}"
                       style="background: var(--color-brand-500); color: #04201c">{{ setting('store.topup.sheet_button_text', 'اشحن المحفظة') }}</a>
                </div>

                {{-- إقرار سياسة عدم الاسترجاع — إلزاميّ قبل الدفع (19.4) --}}
                <label class="flex items-start gap-3 text-sm cursor-pointer">
                    <input type="checkbox" name="refund_ack" value="1" required class="mt-1" data-refund-ack>
                    <span>
                        {{ setting('store.refund.ack_text', 'قرأت سياسة عدم الاسترجاع وموافق عليها.') }}
                        <a href="{{ route('store.refund-policy') }}" target="_blank" rel="noopener"
                           class="hover:underline" style="color: var(--color-brand-400)">اقرأ السياسة</a>
                    </span>
                </label>

                {{-- النصّ الكامل حاضر هنا كي لا يُفاجَأ أحد بعد الدفع (19.4) — ويقبل HTML --}}
                <details class="text-xs" style="color: var(--text-muted)">
                    <summary class="cursor-pointer">نصّ السياسة</summary>
                    <div class="mt-2 leading-6">
                        {!! setting('store.refund.policy_text', 'لا يوجد استرجاع نقديّ للمدفوعات، ويبقى رصيدك في محفظتك تشتري به ما تشاء من الموقع.') !!}
                    </div>
                </details>

                @error('checkout')
                    <p class="text-sm" style="color: var(--color-state-danger)">◉ {{ $message }}</p>
                @enderror
            </div>

            <div class="modal-head px-5 py-4 flex items-center gap-2" style="border-top: 1px solid var(--border)">
                <button type="submit" data-purchase-submit @disabled(! $quote['sufficient'])
                        class="btn flex-1 inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c; opacity: {{ $quote['sufficient'] ? '1' : '.5' }}">
                    تأكيد الشراء
                </button>
                <button type="button" data-modal-close
                        class="rounded-xl px-4 py-3 text-sm motion-standard"
                        style="background: var(--surface-sunken); color: var(--text)">إلغاء</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        /**
         * ⭐ لا حساب في المتصفّح: نرسل هويّة العنصر والكوبون واختيار الـBump،
         * والخادم يرجّع الأرقام مصاغةً — فلا يمكن التلاعب بسعر أو خصم.
         */
        (function () {
            const form = document.querySelector('[data-purchase-form]');
            if (!form) return;

            const submit = form.querySelector('[data-purchase-submit]');
            const topupBlock = form.querySelector('[data-topup-block]');
            const suggestion = form.querySelector('[data-topup-suggestion]');
            const suggestionLink = form.querySelector('[data-topup-link]');
            const couponMessage = form.querySelector('[data-coupon-message]');
            const linesBox = form.querySelector('[data-quote-lines]');
            let timer = null;

            const paint = (data) => {
                form.querySelectorAll('[data-quote]').forEach((el) => {
                    const key = el.dataset.quote;
                    if (data[key] !== undefined) el.textContent = data[key];
                });

                if (linesBox && Array.isArray(data.lines)) {
                    linesBox.replaceChildren(...data.lines.map((line) => {
                        const row = document.createElement('div');
                        row.className = 'flex items-center justify-between gap-3 text-sm';
                        const name = document.createElement('span');
                        name.textContent = line.title;
                        const price = document.createElement('span');
                        price.className = 'font-semibold';
                        price.textContent = line.price;
                        row.append(name, price);
                        return row;
                    }));
                }

                if (couponMessage) couponMessage.textContent = data.coupon_message || '';
                if (topupBlock) topupBlock.classList.toggle('hidden', !!data.sufficient);

                /* أقرب عرض يكفّيك يتغيّر مع الإجماليّ — والنصّ كلّه من الخادم (19.5-ب-2) */
                if (suggestion) {
                    suggestion.textContent = data.suggestion || '';
                    suggestion.classList.toggle('hidden', !data.suggestion);
                }
                if (suggestionLink && data.suggestion_url) suggestionLink.href = data.suggestion_url;

                if (submit) submit.disabled = !data.sufficient;
                if (submit) submit.style.opacity = data.sufficient ? '1' : '.5';
            };

            const refresh = () => {
                const body = new FormData(form);
                body.delete('_token');
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
                    // فشل الشبكة لا يغيّر الأرقام المعروضة من الخادم — والزرّ يظلّ يعمل
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
