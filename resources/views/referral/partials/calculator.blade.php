@php
    /**
     * ⭐ الآلة الحاسبة التفاعليّة «احسب أرباحك المحتملة» — **قلب التفاعل** (7.6.2).
     *
     * لماذا هي بالذات؟ لأنّها الآليّة الوحيدة التي تحوّل «7%» من رقمٍ مجرّد إلى
     * **مبلغ يصنعه المستخدم بيده**: يسحب المنزلق فيتغيّر الرقم أمامه لحظيًّا،
     * فيصير المكسب ملكًا متخيَّلًا لا وعدًا مسموعًا (Endowment + Goal-gradient).
     * ولذلك **الاستجابة الفوريّة شرط أساسيّ** — بلا إعادة تحميل وبلا نداء خادم.
     *
     * 🛡️ وبلا Dark Patterns (2.9): النصّ يقول صراحةً إنّها **تقديرات توضيحيّة
     * لا التزام ماليّ**، والنسبة المعروضة هي نسبة العمولة الحقيقيّة من الإعدادات.
     */
    $c = $calculator;
@endphp

<section class="card p-5 mb-4" data-calc
         data-percent="{{ $c['percent'] }}"
         data-egp="{{ $c['egp_rate'] }}">
    <h2 class="font-bold mb-1">{{ setting('referral.calc.title', 'احسب أرباحك المحتملة') }}</h2>
    <p class="text-xs mb-4" style="color: var(--text-muted)">
        {{ setting('referral.calc.disclaimer', 'تقديرات توضيحيّة للواجهة — مش التزام ماليّ.') }}
    </p>

    {{-- ثلاثة كروت نتيجة تتحدّث لحظيًّا مع كلّ سحبة (7.6.2) --}}
    <div class="grid gap-3 grid-cols-1 sm:grid-cols-3 mb-5">
        <div class="rounded-2xl px-4 py-3 text-center"
             style="background: color-mix(in srgb, var(--color-brand-500) 14%, transparent)">
            <div class="text-2xl font-extrabold tabular-nums" style="color: var(--color-brand-500)" data-calc-monthly>0</div>
            <div class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('referral.calc.monthly_label', 'أرباحك الشهريّة ($)') }}</div>
        </div>

        <div class="rounded-2xl px-4 py-3 text-center" style="background: var(--surface-sunken)">
            <div class="text-2xl font-extrabold tabular-nums" data-calc-total>0</div>
            <div class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('referral.calc.total_label', 'الأرباح الكليّة ($)') }}</div>
        </div>

        <div class="rounded-2xl px-4 py-3 text-center" style="background: var(--surface-sunken)">
            <div class="text-2xl font-extrabold tabular-nums" data-calc-egp>0</div>
            <div class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('referral.calc.egp_label', 'بالجنيه المصريّ') }}</div>
        </div>
    </div>

    {{-- المنزلقات الثلاثة: كلّ واحد بقيمته الظاهرة كي يعرف ماذا يسحب (2.17-ب) --}}
    <div class="space-y-4">
        @foreach ([
            ['key' => 'invites', 'label' => setting('referral.calc.invites_label', 'عدد المدعوّين')],
            ['key' => 'topup', 'label' => setting('referral.calc.topup_label', 'متوسّط الشحن الشهريّ ($)')],
            ['key' => 'months', 'label' => setting('referral.calc.months_label', 'الفترة (شهر)')],
        ] as $slider)
            @php $conf = $c[$slider['key']]; @endphp

            <label class="block">
                <span class="flex items-center justify-between text-sm mb-1">
                    <span>{{ $slider['label'] }}</span>
                    <strong class="tabular-nums" data-calc-value="{{ $slider['key'] }}">{{ $conf['value'] }}</strong>
                </span>
                {{-- بلا `accent-color`: المنزلق المخصّص كلّه في `app.css` (2.10.1-11)، وهي
                     تُعيد الإطار النايتف الذي تمنعه القاعدة نصًّا ولا يُزال بـ`border:none` --}}
                <input type="range" class="w-full" style="min-block-size: 44px"
                       data-calc-input="{{ $slider['key'] }}"
                       min="{{ $conf['min'] }}" max="{{ $conf['max'] }}" value="{{ $conf['value'] }}"
                       aria-label="{{ $slider['label'] }}">
            </label>
        @endforeach
    </div>

    {{-- شريط «دخل سلبيّ حقيقيّ» (7.6.2) --}}
    <p class="mt-5 rounded-xl px-4 py-3 text-sm text-center"
       style="background: color-mix(in srgb, var(--color-state-honor) 12%, transparent)">
        {{ setting('referral.calc.passive_line', 'دخل سلبيّ حقيقيّ — بدون أيّ مجهود بعد الدعوة') }}
    </p>
</section>

@push('scripts')
    <script>
        /*
         | الحساب كلّه في المتصفّح لأنّ **الاستجابة الفوريّة شرط أساسيّ** (7.6.2)،
         | ولأنّه تقديرٌ توضيحيّ لا يمنح مالًا ولا يكتب شيئًا — فلا قرار هنا يخصّ
         | الخادم. والنسبة وسعر الصرف يصلان من الإعدادات لا محروقين (2.13).
         */
        (() => {
            const box = document.querySelector('[data-calc]');
            if (!box) return;

            const percent = parseFloat(box.dataset.percent || '7') / 100;
            const egp = parseFloat(box.dataset.egp || '50');
            const inputs = {};

            box.querySelectorAll('[data-calc-input]').forEach((el) => { inputs[el.dataset.calcInput] = el; });

            const money = (n) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const render = () => {
                const invites = parseInt(inputs.invites.value, 10);
                const topup = parseInt(inputs.topup.value, 10);
                const months = parseInt(inputs.months.value, 10);

                const monthly = invites * topup * percent;
                const total = monthly * months;

                box.querySelector('[data-calc-monthly]').textContent = money(monthly);
                box.querySelector('[data-calc-total]').textContent = money(total);
                box.querySelector('[data-calc-egp]').textContent = money(total * egp);

                Object.keys(inputs).forEach((key) => {
                    box.querySelector('[data-calc-value="' + key + '"]').textContent = inputs[key].value;
                });
            };

            Object.values(inputs).forEach((el) => el.addEventListener('input', render));
            render();
        })();
    </script>
@endpush
