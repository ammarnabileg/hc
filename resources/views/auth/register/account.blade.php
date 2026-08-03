@extends('layouts.guest')
@section('title', setting('onboarding.account.title', 'إنشاء حساب — بياناتك الأساسيّة'))

@php
    /**
     * 🚀 **2.5-ب — صفحة التسجيل الأساسية** (الشاشة الأولى من شاشتين).
     *
     * النصّ الحاكم حرفيًّا:
     *  «الحقول: **الإيميل** + **رقم الموبايل** مع **Select لأكواد الدول
     *   (بالأعلام، بشكل احترافي)** + **الباسوورد**.»
     *  «**التحقق بالـ OTP للإيميل فقط** — رقم الموبايل يُجمَع **بدون** تحقق OTP.»
     *  «**تحقق الإيميل لحظيًا:** صيغة صحيحة + **غير مستخدم من قبل**.»
     *  «عند التأكد: يظهر **تحته** **حقل كود التحقق** + زر **«إرسال»**.»
     *  «بعد الضغط على «إرسال»: يتحوّل لزر **«تأكيد»** يكون **معطّلًا** حتى يكتب
     *   المستخدم **الـ 4 أرقام** المُرسَلة على إيميله.»
     *  «**إعادة إرسال الـ OTP** لنفس الإيميل متاحة **بعد دقيقة** (**عدّاد تنازلي**).»
     *  «لو غيّر **أي حرف** في الإيميل ⇒ تتكرر خطوات التحقق من جديد.»
     *
     * ⭐ «تحته» يعني **تحته في هذه الصفحة** — لا في صفحةٍ تالية. لذلك الفورم
     *    **واحد** وأزراره ثلاثة بـ`formaction`: إرسال · تأكيد · استكمال. وبهذا
     *    يعمل الـOTP في مكانه المنصوص **بلا جافاسكربت أصلًا**، ولا تضيع مدخلات
     *    المستخدم في أيّ من الرحلات الثلاث (2.1 · 2.17-ب).
     */
    $inputClass = 'w-full rounded-xl px-3 py-2 text-sm';
    $inputStyle = 'min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
    $email = (string) (old('email') ?? '');
@endphp

@section('content')
<div class="card p-6 w-full max-w-lg">
    {{-- خطوتان معلنتان: المستخدم يعرف أين هو وكم بقي (2.9) --}}
    <p class="text-xs mb-2" style="color: var(--text-muted)">
        {{ setting('onboarding.account.step_label', 'خطوة 1 من 2 — بيانات الدخول') }}
    </p>
    <h1 class="text-xl font-extrabold mb-1">{{ setting('onboarding.account.title', 'إنشاء حساب — بياناتك الأساسيّة') }}</h1>
    {{-- التفعيل مجّانيّ باعتماد إداريّ — لا رسوم ولا اشتراك (2.5-د) --}}
    <p class="text-sm mb-5" style="color: var(--text-muted)">
        {{ setting('onboarding.account.subtitle', 'التسجيل والتفعيل مجّانيّان بالكامل. هنأكّد بريدك دلوقتي، وبيانات الشهادة في الخطوة اللي بعدها.') }}
    </p>

    @if (session('status'))
        <x-toast :message="session('status')" state="info" />
    @endif

    @if (session('referral_celebrate'))
        {{-- تفعيل هديّة الدعوة: صوت واحتفال (2.5-أ) --}}
        @include('onboarding.partials.celebration', ['celebration' => ['tier' => 2, 'sound' => false, 'sound_path' => null]])
        <p class="rounded-xl p-3 mb-4 text-sm" style="background: color-mix(in srgb, var(--color-state-ok) 14%, transparent)">
            ● {{ setting('onboarding.referral.success_text', 'تمام ✓ هديّتك اتفعّلت — كمّل تسجيلك.') }}
        </p>
    @endif

    {{-- لا حرف عربيّ داخل `<script>` — النصوص تصل إليه سماتٍ على وسم (2.13-أ) --}}
    <div data-texts hidden data-send="{{ setting('auth.otp.send_label', 'إرسال') }}"></div>

    <form method="post" action="{{ route('register') }}" class="space-y-4" data-account-form>
        @csrf
        <input type="hidden" name="step" value="{{ \App\Http\Controllers\Auth\AuthController::STEP_ACCOUNT }}">
        <input type="hidden" name="offer" value="{{ $referral }}">

        {{-- ─────────── الإيميل + تحقّقه اللحظيّ ─────────── --}}
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('onboarding.account.email_label', 'البريد الإلكترونيّ') }}</span>
            <input type="email" name="email" value="{{ $email }}" dir="ltr" autocomplete="email" required
                   class="{{ $inputClass }}" style="{{ $inputStyle }}" data-email>
            <span class="block text-xs mt-1" data-email-note aria-live="polite"
                  style="color: var(--color-state-danger)">@error('email'){{ '◉ '.$message }}@enderror</span>
        </label>

        {{--
            ⭐ **حقل كود التحقّق تحت الإيميل مباشرةً** — لا في صفحةٍ تالية (2.5-ب).
            ويُخفى ما دام البريد غير صالح أو مستعمَلًا: «**عند التأكد** يظهر تحته».
        --}}
        <div class="rounded-xl p-3" data-otp-block
             style="background: var(--surface-sunken); border: 1px dashed var(--border)"
             @if (! $otpSent && ! $otpVerified && $email === '') hidden @endif>

            @if ($otpVerified)
                <p class="text-sm font-semibold" data-otp-done style="color: var(--color-state-ok)">
                    ✓ {{ setting('auth.otp.verified_text', 'بريدك اتأكّد — كمّل باقي البيانات.') }}
                </p>
            @else
                <div class="flex items-end gap-2 flex-wrap">
                    <label class="block flex-1" style="min-width: 8rem">
                        <span class="block text-sm mb-1">{{ setting('auth.otp.code_label', 'كود التحقّق') }}</span>
                        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                               maxlength="{{ $otpLength }}" pattern="[0-9]{{ '{'.$otpLength.'}' }}" dir="ltr"
                               data-otp-input data-length="{{ $otpLength }}"
                               class="w-full rounded-xl px-3 py-2 text-center font-extrabold tabular-nums"
                               style="min-height: 44px; background: var(--surface); border: 1px solid var(--border); color: var(--text); letter-spacing: .4rem">
                    </label>

                    {{-- زرّ واحد يتحوّل: «إرسال» ⟵ «تأكيد» (2.5-ب) --}}
                    <button type="submit" formaction="{{ $otpSent ? route('register.verify.confirm') : route('register.verify.send') }}"
                            formnovalidate data-otp-action data-sent="{{ $otpSent ? '1' : '0' }}"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                        {{ $otpSent ? setting('auth.otp.confirm_label', 'تأكيد') : setting('auth.otp.send_label', 'إرسال') }}
                    </button>
                </div>

                @error('code')
                    <span class="block text-xs mt-2" style="color: var(--color-state-danger)">◉ {{ $message }}</span>
                @enderror

                @if ($otpSent)
                    {{-- إعادة الإرسال **بعد دقيقة** بعدّاد تنازليّ (2.5-ب) --}}
                    <button type="submit" formaction="{{ route('register.verify.send') }}" formnovalidate
                            data-otp-resend data-wait="{{ $otpWait }}" data-window="{{ $resendSeconds }}"
                            class="text-xs mt-2 underline motion-standard"
                            style="min-height: 44px; color: var(--text-muted)">
                        <span data-otp-resend-label>{{ setting('auth.otp.resend_label', 'إعادة إرسال الرمز') }}</span>
                    </button>
                @endif

                <p class="text-xs mt-2" style="color: var(--text-muted)">
                    {{ str_replace('{length}', (string) $otpLength, setting('auth.otp.inline_hint', 'هنبعت رمز من {length} أرقام على بريدك — اكتبه هنا عشان نتأكّد إنّه بريدك فعلًا.')) }}
                </p>
            @endif
        </div>

        {{-- ─────────── رقم الموبايل + Select أكواد الدول بالأعلام ─────────── --}}
        <div class="block">
            <span class="block text-sm mb-1">{{ setting('onboarding.account.phone_label', 'رقم الموبايل') }}</span>
            <p class="text-xs mb-1" style="color: var(--text-muted)">
                {{ setting('onboarding.account.phone_hint', 'الرقم للتواصل بس — مش هنبعتلك عليه كود تحقّق.') }}
            </p>

            <div class="flex gap-2">
                @if ($showDialCode)
                    {{--
                        «Select لأكواد الدول (بالأعلام، بشكل احترافي)» (2.5-ب)
                        + «Toggle كود الهاتف في التسجيل (**عرض 110px**)» (12.7-د).

                        ⚠️ `<option>` لا يقبل SVG في أيّ متصفّح — فالقائمة مبنيّة
                        بزرّ ولوحة، و**تحتها `<select>` حقيقيّ** يبقى وحده حين
                        تغيب الجافاسكربت (2.1): الاختيار يعمل في الحالتين.
                    --}}
                    <div class="relative shrink-0" data-dial style="width: {{ $dialWidth }}px">
                        <select name="phone_iso2" data-dial-select required aria-label="{{ setting('onboarding.account.dial_label', 'كود الدولة') }}"
                                class="w-full rounded-xl px-2 py-2 text-sm" style="{{ $inputStyle }}">
                            @foreach ($dialCodes as $row)
                                <option value="{{ $row['iso2'] }}"
                                        @selected(old('phone_iso2', $defaultIso2) === $row['iso2'])>{{ $row['dial'] }} — {{ $row['name'] }}</option>
                            @endforeach
                        </select>

                        <button type="button" data-dial-button hidden
                                class="w-full flex items-center gap-1 rounded-xl px-2 py-2 text-sm motion-standard"
                                style="{{ $inputStyle }}" aria-haspopup="listbox" aria-expanded="false">
                            <span data-dial-flag class="shrink-0"></span>
                            <span data-dial-code class="tabular-nums" dir="ltr"></span>
                            <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="currentColor"
                                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="ms-auto shrink-0"
                                 aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5"/></svg>
                        </button>

                        <div data-dial-panel hidden role="listbox" aria-label="{{ setting('onboarding.account.dial_label', 'كود الدولة') }}"
                             class="absolute z-30 mt-1 rounded-xl overflow-hidden"
                             style="inset-inline-start: 0; min-width: min(19rem, 88vw); max-height: 16rem; display: flex; flex-direction: column;
                                    background: var(--surface); border: 1px solid var(--border); box-shadow: 0 14px 34px rgba(0,0,0,.28)">
                            <input type="search" data-dial-search placeholder="{{ setting('onboarding.account.dial_search', 'دوّر بالاسم أو الكود…') }}"
                                   class="w-full px-3 py-2 text-sm" style="min-height: 44px; background: var(--surface-sunken); border: 0; color: var(--text)">
                            <div data-dial-list class="overflow-y-auto" style="max-height: 12.5rem">
                                @foreach ($dialCodes as $row)
                                    <button type="button" role="option" data-dial-option
                                            data-iso2="{{ $row['iso2'] }}" data-code="{{ $row['dial'] }}"
                                            data-search="{{ $row['name'] }} {{ $row['dial'] }} {{ $row['iso2'] }}"
                                            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-start motion-standard"
                                            style="min-height: 44px; background: transparent; color: var(--text)">
                                        {{-- علم **كلّ** دولة مرسومًا: 183KB خامًا و16KB مضغوطًا،
                                             ولا واحدة بلا علم — «بالأعلام» نصٌّ لا زينة (2.5-ب) --}}
                                        <span class="shrink-0" data-dial-option-flag><x-flag :iso2="$row['iso2']" /></span>
                                        <span class="truncate">{{ $row['name'] }}</span>
                                        <span class="ms-auto tabular-nums shrink-0" dir="ltr" style="color: var(--text-muted)">{{ $row['dial'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <input type="tel" name="phone_national" value="{{ old('phone_national') }}" dir="ltr" required
                       autocomplete="tel-national" inputmode="tel" class="{{ $inputClass }}" style="{{ $inputStyle }}">
            </div>

            @error('phone_iso2')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
            @error('phone_national')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
            @error('phone')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
        </div>

        {{-- ─────────── الباسوورد ─────────── --}}
        <div class="grid md:grid-cols-2 gap-3">
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('onboarding.account.password_label', 'كلمة السرّ') }}</span>
                <input type="password" name="password" autocomplete="new-password" required
                       class="{{ $inputClass }}" style="{{ $inputStyle }}">
                @error('password')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('onboarding.account.password_confirm_label', 'تأكيد كلمة السرّ') }}</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" required
                       class="{{ $inputClass }}" style="{{ $inputStyle }}">
            </label>
        </div>

        <button type="submit" class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                style="min-height: 44px; background: var(--color-brand-500); color:#04201c">
            {{ setting('onboarding.account.next_label', 'كمّل ⟵ بيانات الشهادة') }}
        </button>
    </form>

    <p class="text-sm mt-4" style="color: var(--text-muted)">
        {{ setting('onboarding.account.has_account', 'عندك حساب؟') }}
        <a href="{{ route('login') }}" style="color: var(--color-brand-500)">{{ setting('onboarding.account.login_label', 'ادخل من هنا') }}</a>
    </p>

    {{-- ⚖️ إسناد ODbL **حيث تُستهلَك البيانات** — أكواد الدول أعلاه (2.5-ج) --}}
    @include('auth.register.partials.attribution')
</div>

<script>
    (() => {
        const form = document.querySelector('[data-account-form]');
        if (!form) return;

        const email = form.querySelector('[data-email]');
        const note = form.querySelector('[data-email-note]');
        const block = form.querySelector('[data-otp-block]');
        const action = form.querySelector('[data-otp-action]');
        const code = form.querySelector('[data-otp-input]');
        const resend = form.querySelector('[data-otp-resend]');
        const label = form.querySelector('[data-otp-resend-label]');
        const done = form.querySelector('[data-otp-done]');

        const SEND = @json(route('register.verify.send'));
        const CONFIRM = @json(route('register.verify.confirm'));
        const PROBE = @json(route('register'));
        const LENGTH = Number(code?.dataset.length || {{ $otpLength }});

        // ── الإيميل: صيغة صحيحة + غير مستخدم من قبل — لحظيًّا (2.5-ب)
        let startedWith = email ? email.value.trim().toLowerCase() : '';
        let probe = null;

        const paint = (ok, message) => {
            if (!note) return;
            note.textContent = message ? (ok ? '✓ ' : '◉ ') + message : '';
            note.style.color = ok ? 'var(--color-state-ok)' : 'var(--color-state-danger)';
        };

        const showBlock = (show) => { if (block) block.hidden = !show; };

        const check = () => {
            if (!email) return;
            const value = email.value.trim().toLowerCase();

            // «لو غيّر أيّ حرف في الإيميل ⇒ تتكرر خطوات التحقق من جديد» (2.5-ب)
            if (value !== startedWith) {
                startedWith = value;
                if (done) done.hidden = true;
                if (action) {
                    action.dataset.sent = '0';
                    action.textContent = document.querySelector('[data-texts]')?.dataset.send || '';
                    action.setAttribute('formaction', SEND);
                }
                if (code) code.value = '';
                if (resend) resend.hidden = true;
            }

            if (!value) { paint(false, ''); showBlock(false); sync(); return; }

            clearTimeout(probe);
            probe = setTimeout(async () => {
                try {
                    const url = PROBE + '?probe=email&email=' + encodeURIComponent(value);
                    const res = await fetch(url, { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    paint(data.ok, data.message);
                    showBlock(data.ok);           // «**عند التأكد**: يظهر تحته حقل كود التحقق»
                } catch (_) {
                    showBlock(true);              // تعذّر السؤال ⟵ لا نقفل الباب في وشّه
                }
                sync();
            }, 300);
        };

        // ── زرّ «تأكيد» **معطّل** حتى تكتمل الأرقام (2.5-ب)
        const sync = () => {
            if (!action || !code) return;
            const sent = action.dataset.sent === '1';
            const ready = !sent || (/^\d+$/.test(code.value) && code.value.length === LENGTH);
            action.disabled = !ready;
            action.style.opacity = ready ? '1' : '.5';
            code.parentElement.hidden = !sent;    // الحقل يظهر بعد الإرسال
        };

        code?.addEventListener('input', () => {
            code.value = code.value.replace(/\D/g, '').slice(0, LENGTH);
            sync();
        });

        email?.addEventListener('input', check);

        // ── عدّاد إعادة الإرسال — بعد دقيقة (2.5-ب)
        if (resend && label) {
            let wait = Number(resend.dataset.wait) || 0;
            const base = label.textContent.trim();

            const tick = () => {
                if (wait <= 0) {
                    resend.disabled = false;
                    resend.style.opacity = '1';
                    label.textContent = base;
                    return;
                }
                resend.disabled = true;
                resend.style.opacity = '.55';
                label.textContent = base + ' (' + wait + ')';
                wait -= 1;
                setTimeout(tick, 1000);
            };

            tick();
        }

        // الحالة الابتدائيّة: الحقل يظهر فقط بعد أن يُبعَث الرمز فعلًا
        if (action && code && action.dataset.sent !== '1') code.parentElement.hidden = true;
        sync();
        if (email && email.value.trim()) check();
    })();

    // ── قائمة أكواد الدول بالأعلام: زرّ ولوحة بحث فوق `select` أصليّ (2.1)
    (() => {
        const wrap = document.querySelector('[data-dial]');
        if (!wrap) return;

        const select = wrap.querySelector('[data-dial-select]');
        const button = wrap.querySelector('[data-dial-button]');
        const panel = wrap.querySelector('[data-dial-panel]');
        const search = wrap.querySelector('[data-dial-search]');
        const options = Array.from(wrap.querySelectorAll('[data-dial-option]'));
        if (!select || !button || !panel) return;

        select.hidden = true;
        button.hidden = false;

        const paint = () => {
            const chosen = options.find((o) => o.dataset.iso2 === select.value) || options[0];
            if (!chosen) return;
            wrap.querySelector('[data-dial-flag]').innerHTML =
                chosen.querySelector('[data-dial-option-flag]').innerHTML;
            wrap.querySelector('[data-dial-code]').textContent = chosen.dataset.code;
        };

        const open = (show) => {
            panel.hidden = !show;
            button.setAttribute('aria-expanded', show ? 'true' : 'false');
            if (show) { search?.focus(); }
        };

        button.addEventListener('click', () => open(panel.hidden));

        options.forEach((option) => option.addEventListener('click', () => {
            select.value = option.dataset.iso2;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            paint();
            open(false);
        }));

        search?.addEventListener('input', () => {
            const q = search.value.trim().toLowerCase();
            options.forEach((o) => { o.hidden = q !== '' && !o.dataset.search.toLowerCase().includes(q); });
        });

        document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) open(false); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') open(false); });

        paint();
    })();
</script>
@endsection
