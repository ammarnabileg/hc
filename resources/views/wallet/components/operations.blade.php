@php
    /**
     * العمليّات المالِيّة الثلاث (19.3) — كلٌّ في بوب-أب بتفاصيله.
     *
     * ⭐ حسم تعارض 19.2 مع 24.5: التابات ثلاثة والأفعال أربعة كما يوجب **19.2**،
     * لكن احترامًا لـ2.15 «البساطة أوّلًا» يظهر **فعل رئيسيّ واحد ([شحن])**
     * والثلاثة الباقية في **قائمة إجراءات ثانويّة** — لا أربعة أزرار متساوية.
     *
     * ⭐ ولا يُحسَب رقمٌ ماليّ في هذا الملفّ: كلّ ملخّصٍ لحظيّ يُطلَب من الخادم،
     * فالرقم المعروض قبل التأكيد هو نفسه المنفَّذ بعده.
     */
    $methods = \App\Services\Wallet\WithdrawService::METHODS;
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp

{{-- رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب) --}}
@if ($errors->has('wallet'))
    <x-toast :message="$errors->first('wallet')" state="danger" />
@endif

@push('modals')
    @if ($canTransfer)
        {{-- ------------------------------------------------------- إرسال حوالة --}}
        <x-modal id="wallet-transfer" :title="setting('wallet.transfer.title', 'إرسال حوالة')">
            <form method="POST" action="{{ route('wallet.transfer') }}" class="space-y-4"
                  data-quote-form data-quote-url="{{ route('wallet.transfer.quote') }}" data-quote-box="transfer">
                @csrf

                <x-form.input name="code" :label="setting('wallet.transfer.code_label', 'كود المستلِم')" value="{{ old('code') }}"
                              :hint="setting('wallet.transfer.code_hint', 'اكتب كود صاحبك زيّ ما هو، وهيظهر لك اسمه قبل التأكيد.')" data-quote-field required />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('wallet.transfer.currency_label', 'العملة') }}</span>
                    <select name="currency" data-quote-field required
                            class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($transferCurrencies as $currency)
                            <option value="{{ $currency['code'] }}" @selected(old('currency') === $currency['code'])>
                                {{ str_replace([':name', ':fee'], [$currency['name'], $num($currency['fee'])], (string) setting('wallet.transfer.currency_option', ':name — رسوم :fee%')) }}
                            </option>
                        @endforeach
                    </select>
                    <span class="block text-xs mt-1" style="color: var(--text-muted)">
                        {{ setting('wallet.transfer.xp_fee_note', 'رسوم الـXP مرتفعة عن قصد عشان تفضل لوحة المتصدّرين نضيفة.') }}
                    </span>
                </label>

                <x-form.input name="amount" :label="setting('wallet.transfer.amount_label', 'الكمّيّة')" type="number" step="0.01" min="0.01"
                              value="{{ old('amount') }}" data-quote-field
                              :hint="str_replace(':min', $num($transferMin), (string) setting('wallet.transfer.amount_hint', 'أقلّ حوالة :min.'))" required />

                {{-- الملخّص اللحظيّ: المُرسَل / الضريبة / يستلم (19.3) --}}
                <div class="card p-3 text-sm space-y-2" data-quote-summary>
                    <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">{{ setting('wallet.quote.sent', 'المُرسَل') }}</span><span data-q="amount">—</span></div>
                    <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">{{ setting('wallet.quote.fee', 'الرسوم') }}</span><span data-q="fee">—</span></div>
                    <div class="flex justify-between gap-3 font-bold"><span>{{ setting('wallet.quote.received', 'يستلم') }}</span><span data-q="net">—</span></div>
                    <p class="text-xs pt-1" style="color: var(--text-muted)" data-q="note">{{ setting('wallet.transfer.quote_hint', 'اكتب الكمّيّة وهنحسب لك كلّ حاجة.') }}</p>
                </div>

                <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.transfer.submit', 'أكّد الحوالة') }}</button>
            </form>
        </x-modal>

        {{-- ------------------------------------------------------ تحويل العملة --}}
        <x-modal id="wallet-exchange" :title="setting('wallet.exchange.title', 'تحويل العملة')">
            <form method="POST" action="{{ route('wallet.exchange') }}" class="space-y-4"
                  data-quote-form data-quote-url="{{ route('wallet.exchange.quote') }}" data-quote-box="exchange">
                @csrf

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('wallet.exchange.path_label', 'من ← إلى') }}</span>
                    <select name="path" data-quote-field data-quote-path
                            class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($exchangePaths as $path)
                            <option value="{{ $path['from'] }}|{{ $path['to'] }}">
                                {{ $path['from_name'] }} ← {{ $path['to_name'] }}
                            </option>
                        @endforeach
                    </select>
                    <span class="block text-xs mt-1" style="color: var(--text-muted)">
                        {{ str_replace(':fee', $num($exchangeFee), (string) setting('wallet.exchange.fee_note', 'رسوم ثابتة :fee% على كلّ المسارات.')) }}
                    </span>
                </label>

                <input type="hidden" name="from" data-quote-from>
                <input type="hidden" name="to" data-quote-to>

                <x-form.input name="amount" :label="setting('wallet.exchange.amount_label', 'الكمّيّة')" type="number" step="0.01" min="0.01"
                              value="{{ old('amount') }}" data-quote-field required />

                <div class="card p-3 text-sm space-y-2" data-quote-summary>
                    <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">{{ setting('wallet.quote.sent', 'المُرسَل') }}</span><span data-q="amount">—</span></div>
                    <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">{{ setting('wallet.quote.fee', 'الرسوم') }}</span><span data-q="fee">—</span></div>
                    <div class="flex justify-between gap-3 font-bold"><span>{{ setting('wallet.quote.received', 'يستلم') }}</span><span data-q="net">—</span></div>
                    <p class="text-xs pt-1" style="color: var(--text-muted)" data-q="note">{{ setting('wallet.exchange.quote_hint', 'اكتب الكمّيّة وهنحسب لك الناتج.') }}</p>
                </div>

                <div class="text-xs space-y-1" style="color: var(--text-muted)">
                    @foreach ($rateTable as $rate)
                        <div>{{ $rate['label'] }}: {{ $rate['value'] }}</div>
                    @endforeach
                </div>

                <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.exchange.submit', 'أكّد التحويل') }}</button>
            </form>
        </x-modal>
    @endif

    @if ($canWithdraw)
        {{-- ------------------------------------------------------- سحب الأرباح --}}
        <x-modal id="wallet-withdraw" :title="setting('wallet.withdraw.title', 'سحب الأرباح')">
            @if ($pendingWithdrawal)
                <p class="text-sm">
                    {!! str_replace(':number', '<strong>'.e($pendingWithdrawal->number).'</strong>', e(setting('wallet.withdraw.pending_note', 'عندك طلب سحب رقم :number لسّه تحت المراجعة — استنّى نتيجته وبعدين ابعت طلبًا جديدًا.'))) !!}
                </p>
            @else
                <form method="POST" action="{{ route('wallet.withdraw') }}" class="space-y-4"
                      data-quote-form data-quote-url="{{ route('wallet.withdraw.quote') }}" data-quote-box="withdraw">
                    @csrf

                    <div class="card p-3 text-sm flex justify-between gap-3">
                        <span style="color: var(--text-muted)">{{ setting('wallet.withdraw.available', 'متاح للسحب') }}</span>
                        <span class="font-bold">${{ $num($earnings['ready'] ?? 0) }}</span>
                    </div>

                    <x-form.input name="amount" :label="setting('wallet.withdraw.amount_label', 'قيمة السحب بالدولار')" type="number" step="0.01" min="0.01"
                                  value="{{ old('amount') }}" data-quote-field
                                  :hint="str_replace(
                                      [':min', ':fee', ':minfee'],
                                      [$num($withdrawLimits['min_amount']), $num($withdrawLimits['fee_percent']), $num($withdrawLimits['min_fee'])],
                                      (string) setting('wallet.withdraw.amount_hint', 'أقلّ سحب $:min · رسوم :fee% بحدّ أدنى $:minfee.'),
                                  )"
                                  required />

                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('wallet.withdraw.method_label', 'طريقة التحويل') }}</span>
                        <select name="method" required class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($methods as $key => $label)
                                <option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <x-form.input name="account_number" :label="setting('wallet.withdraw.account_number_label', 'رقم الحساب أو المحفظة')"
                                  value="{{ old('account_number') }}" required />
                    <x-form.input name="account_name" :label="setting('wallet.withdraw.account_name_label', 'اسم صاحب الحساب (اختياريّ)')"
                                  value="{{ old('account_name') }}" />

                    <div class="card p-3 text-sm space-y-2" data-quote-summary>
                        <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">{{ setting('wallet.withdraw.requested', 'المطلوب') }}</span><span data-q="amount">—</span></div>
                        <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">{{ setting('wallet.quote.fee', 'الرسوم') }}</span><span data-q="fee">—</span></div>
                        <div class="flex justify-between gap-3 font-bold"><span>{{ setting('wallet.withdraw.you_get', 'يوصلك') }}</span><span data-q="net">—</span></div>
                        <p class="text-xs pt-1" style="color: var(--text-muted)" data-q="note">{{ setting('wallet.withdraw.quote_hint', 'اكتب القيمة وهنحسب لك الصافي.') }}</p>
                    </div>

                    <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.withdraw.submit', 'ابعت الطلب') }}</button>
                </form>
            @endif
        </x-modal>
    @endif
@endpush

@push('scripts')
@php
    // نصوص الملخّص اللحظيّ من الإعدادات لا من السكربت (2.13)
    $quoteWords = [
        'error' => (string) setting('wallet.quote.error', 'مش قادرين نحسب دلوقتي — جرّب تاني بعد شويّة.'),
        'recipient' => (string) setting('wallet.quote.recipient', 'المستلِم: :name (:code)'),
        'rate' => (string) setting('wallet.quote.rate_used', 'السعر المستعمَل: 1 :from = :rate :to.'),
        'server' => (string) setting('wallet.quote.server_note', 'الأرقام دي محسوبة في الخادم، ومش هتتغيّر بعد التأكيد.'),
        'offline' => (string) setting('wallet.quote.offline', 'الاتّصال اتقطع فمقدرناش نحسب — راجع النت وجرّب تاني.'),
    ];
@endphp
<script>
const quoteWords = @json($quoteWords);
/* ⭐ الملخّص اللحظيّ من الخادم لا من المتصفّح (19.3):
   لا نسبة ولا رسم يُحسَب هنا — نرسل المدخلات ونعرض ما يردّه الخادم كما هو. */
(function () {
    const forms = document.querySelectorAll('[data-quote-form]');
    if (!forms.length) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    forms.forEach((form) => {
        const box = form.querySelector('[data-quote-summary]');
        const kind = form.dataset.quoteBox;
        const pathField = form.querySelector('[data-quote-path]');
        const fromField = form.querySelector('[data-quote-from]');
        const toField = form.querySelector('[data-quote-to]');
        let timer = null;

        const syncPath = () => {
            if (!pathField || !fromField || !toField) return;
            const [from, to] = String(pathField.value).split('|');
            fromField.value = from || '';
            toField.value = to || '';
        };

        const set = (key, text) => {
            const el = box?.querySelector(`[data-q="${key}"]`);
            if (el) el.textContent = text;
        };

        const refresh = async () => {
            syncPath();

            const amount = parseFloat(form.querySelector('[name="amount"]')?.value || '0');
            if (!Number.isFinite(amount) || amount <= 0) {
                ['amount', 'fee', 'net'].forEach((k) => set(k, '—'));
                return;
            }

            const body = { amount };
            if (kind === 'transfer') {
                body.currency = form.querySelector('[name="currency"]')?.value;
                body.code = form.querySelector('[name="code"]')?.value || '';
            }
            if (kind === 'exchange') {
                body.from = fromField?.value;
                body.to = toField?.value;
            }

            try {
                const res = await fetch(form.dataset.quoteUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify(body),
                });
                const data = await res.json();

                if (!res.ok) {
                    set('note', data.message || quoteWords.error);
                    return;
                }

                const unit = kind === 'withdraw' ? '$' : '';
                set('amount', unit + data.amount);
                set('fee', unit + data.fee + ' (' + data.fee_percent + '%)');
                set('net', unit + (data.net ?? data.credited));

                if (kind === 'transfer' && data.recipient) {
                    set('note', data.recipient.error || quoteWords.recipient
                        .split(':name').join(data.recipient.name)
                        .split(':code').join(data.recipient.code));
                } else if (kind === 'exchange') {
                    set('note', quoteWords.rate
                        .split(':from').join(data.from_name)
                        .split(':rate').join(data.rate)
                        .split(':to').join(data.to_name));
                } else {
                    set('note', quoteWords.server);
                }
            } catch {
                /* رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب) */
                set('note', quoteWords.offline);
            }
        };

        form.querySelectorAll('[data-quote-field]').forEach((field) => {
            const handler = () => { clearTimeout(timer); timer = setTimeout(refresh, 300); };
            field.addEventListener('input', handler);
            field.addEventListener('change', handler);
        });

        syncPath();
    });
})();
</script>
@endpush
