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
        <x-modal id="wallet-transfer" title="إرسال حوالة">
            <form method="POST" action="{{ route('wallet.transfer') }}" class="space-y-4"
                  data-quote-form data-quote-url="{{ route('wallet.transfer.quote') }}" data-quote-box="transfer">
                @csrf

                <x-form.input name="code" label="كود المستلِم" value="{{ old('code') }}"
                              hint="اكتب كود صاحبك زيّ ما هو، وهيظهر لك اسمه قبل التأكيد." data-quote-field required />

                <label class="block">
                    <span class="block text-sm mb-1">العملة</span>
                    <select name="currency" data-quote-field required
                            class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($transferCurrencies as $currency)
                            <option value="{{ $currency['code'] }}" @selected(old('currency') === $currency['code'])>
                                {{ $currency['name'] }} — رسوم {{ $num($currency['fee']) }}%
                            </option>
                        @endforeach
                    </select>
                    <span class="block text-xs mt-1" style="color: var(--text-muted)">
                        رسوم الـXP مرتفعة عن قصد عشان تفضل لوحة المتصدّرين نضيفة.
                    </span>
                </label>

                <x-form.input name="amount" label="الكمّيّة" type="number" step="0.01" min="0.01"
                              value="{{ old('amount') }}" data-quote-field
                              hint="أقلّ حوالة {{ $num($transferMin) }}." required />

                {{-- الملخّص اللحظيّ: المُرسَل / الضريبة / يستلم (19.3) --}}
                <div class="card p-3 text-sm space-y-2" data-quote-summary>
                    <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">المُرسَل</span><span data-q="amount">—</span></div>
                    <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">الرسوم</span><span data-q="fee">—</span></div>
                    <div class="flex justify-between gap-3 font-bold"><span>يستلم</span><span data-q="net">—</span></div>
                    <p class="text-xs pt-1" style="color: var(--text-muted)" data-q="note">اكتب الكمّيّة وهنحسب لك كلّ حاجة.</p>
                </div>

                <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">أكّد الحوالة</button>
            </form>
        </x-modal>

        {{-- ------------------------------------------------------ تحويل العملة --}}
        <x-modal id="wallet-exchange" title="تحويل العملة">
            <form method="POST" action="{{ route('wallet.exchange') }}" class="space-y-4"
                  data-quote-form data-quote-url="{{ route('wallet.exchange.quote') }}" data-quote-box="exchange">
                @csrf

                <label class="block">
                    <span class="block text-sm mb-1">من ← إلى</span>
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
                        رسوم ثابتة {{ $num($exchangeFee) }}% على كلّ المسارات.
                    </span>
                </label>

                <input type="hidden" name="from" data-quote-from>
                <input type="hidden" name="to" data-quote-to>

                <x-form.input name="amount" label="الكمّيّة" type="number" step="0.01" min="0.01"
                              value="{{ old('amount') }}" data-quote-field required />

                <div class="card p-3 text-sm space-y-2" data-quote-summary>
                    <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">المُرسَل</span><span data-q="amount">—</span></div>
                    <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">الرسوم</span><span data-q="fee">—</span></div>
                    <div class="flex justify-between gap-3 font-bold"><span>يستلم</span><span data-q="net">—</span></div>
                    <p class="text-xs pt-1" style="color: var(--text-muted)" data-q="note">اكتب الكمّيّة وهنحسب لك الناتج.</p>
                </div>

                <div class="text-xs space-y-1" style="color: var(--text-muted)">
                    @foreach ($rateTable as $rate)
                        <div>{{ $rate['label'] }}: {{ $rate['value'] }}</div>
                    @endforeach
                </div>

                <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">أكّد التحويل</button>
            </form>
        </x-modal>
    @endif

    @if ($canWithdraw)
        {{-- ------------------------------------------------------- سحب الأرباح --}}
        <x-modal id="wallet-withdraw" title="سحب الأرباح">
            @if ($pendingWithdrawal)
                <p class="text-sm">
                    عندك طلب سحب رقم <strong>{{ $pendingWithdrawal->number }}</strong> لسّه تحت المراجعة —
                    استنّى نتيجته وبعدين ابعت طلبًا جديدًا.
                </p>
            @else
                <form method="POST" action="{{ route('wallet.withdraw') }}" class="space-y-4"
                      data-quote-form data-quote-url="{{ route('wallet.withdraw.quote') }}" data-quote-box="withdraw">
                    @csrf

                    <div class="card p-3 text-sm flex justify-between gap-3">
                        <span style="color: var(--text-muted)">متاح للسحب</span>
                        <span class="font-bold">${{ $num($earnings['ready'] ?? 0) }}</span>
                    </div>

                    <x-form.input name="amount" label="قيمة السحب بالدولار" type="number" step="0.01" min="0.01"
                                  value="{{ old('amount') }}" data-quote-field
                                  hint="أقلّ سحب ${{ $num($withdrawLimits['min_amount']) }} · رسوم {{ $num($withdrawLimits['fee_percent']) }}% بحدّ أدنى ${{ $num($withdrawLimits['min_fee']) }}."
                                  required />

                    <label class="block">
                        <span class="block text-sm mb-1">طريقة التحويل</span>
                        <select name="method" required class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($methods as $key => $label)
                                <option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <x-form.input name="account_number" label="رقم الحساب أو المحفظة"
                                  value="{{ old('account_number') }}" required />
                    <x-form.input name="account_name" label="اسم صاحب الحساب (اختياريّ)"
                                  value="{{ old('account_name') }}" />

                    <div class="card p-3 text-sm space-y-2" data-quote-summary>
                        <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">المطلوب</span><span data-q="amount">—</span></div>
                        <div class="flex justify-between gap-3"><span style="color: var(--text-muted)">الرسوم</span><span data-q="fee">—</span></div>
                        <div class="flex justify-between gap-3 font-bold"><span>يوصلك</span><span data-q="net">—</span></div>
                        <p class="text-xs pt-1" style="color: var(--text-muted)" data-q="note">اكتب القيمة وهنحسب لك الصافي.</p>
                    </div>

                    <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">ابعت الطلب</button>
                </form>
            @endif
        </x-modal>
    @endif
@endpush

@push('scripts')
<script>
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
                    set('note', data.message || 'مش قادرين نحسب دلوقتي — جرّب تاني بعد شويّة.');
                    return;
                }

                const unit = kind === 'withdraw' ? '$' : '';
                set('amount', unit + data.amount);
                set('fee', unit + data.fee + ' (' + data.fee_percent + '%)');
                set('net', unit + (data.net ?? data.credited));

                if (kind === 'transfer' && data.recipient) {
                    set('note', data.recipient.error || ('المستلِم: ' + data.recipient.name + ' (' + data.recipient.code + ')'));
                } else if (kind === 'exchange') {
                    set('note', 'السعر المستعمَل: 1 ' + data.from_name + ' = ' + data.rate + ' ' + data.to_name + '.');
                } else {
                    set('note', 'الأرقام دي محسوبة في الخادم، ومش هتتغيّر بعد التأكيد.');
                }
            } catch {
                /* رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب) */
                set('note', 'الاتّصال اتقطع فمقدرناش نحسب — راجع النت وجرّب تاني.');
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
