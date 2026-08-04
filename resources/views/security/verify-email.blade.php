@extends('layouts.guest')
@section('title', (string) setting('security.verify_email.section_1', 'تأكيد بريدك'))

@section('content')
{{--
    تحقّق البريد بـOTP (2.5-ب): زرّ «إرسال» يتحوّل لـ«تأكيد» **معطَّل** حتى تكتمل
    الأرقام · إعادة الإرسال بعد دقيقة **بعدّاد تنازليّ** · والرمز لا يتغيّر لنفس البريد.
--}}
<div class="card p-6 w-full max-w-sm animate-fadeup">
    <h1 class="text-xl font-extrabold mb-1">{{ setting('auth.otp.title', 'أكّد بريدك') }}</h1>
    <p class="text-sm mb-4" style="color: var(--text-muted)">
        {{ str_replace(['{email}', '{length}'], [$email, (string) $length], setting('auth.otp.hint', 'هنبعت رمز من {length} أرقام على {email} — نتأكّد إنّه بريدك فعلًا.')) }}
    </p>

    @if (session('status'))
        <p class="text-sm mb-3 rounded-xl px-3 py-2"
           style="background: var(--surface-sunken); color: var(--text)">{{ session('status') }}</p>
    @endif

    {{-- خطوة 1: إرسال --}}
    <form method="post" action="{{ route('register.verify.send') }}" class="mb-3">
        @csrf
        <button type="submit" data-otp-resend data-wait="{{ $wait }}"
                class="btn w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <span data-otp-resend-label>
                {{ session('otp_sent') || $sent ? setting('auth.otp.resend_label', 'إعادة إرسال الرمز') : setting('auth.otp.send_label', 'إرسال') }}
            </span>
        </button>
    </form>

    {{-- خطوة 2: تأكيد — الزرّ معطَّل حتى تكتمل الأرقام --}}
    <form method="post" action="{{ route('register.verify.confirm') }}" class="space-y-3" data-otp-form>
        @csrf
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('auth.otp.code_label', 'كود التحقّق') }}</span>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                   maxlength="{{ $length }}" pattern="{{ '[0-9]{'.$length.'}' }}"
                   data-otp-input data-length="{{ $length }}"
                   class="w-full rounded-xl px-3 py-3 text-center font-extrabold tabular-nums"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); font-size: 1.5rem; letter-spacing: .5rem"
                   dir="ltr">
            @error('code')
                <span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>
            @enderror
        </label>

        <button type="submit" data-otp-submit disabled
                class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                style="background: var(--color-brand-500); color:#04201c; opacity:.5">
            {{ setting('auth.otp.confirm_label', 'تأكيد') }}
        </button>
    </form>

    <p class="text-xs mt-4" style="color: var(--text-muted)">
        {{ setting('auth.otp.wrong_email_hint', 'البريد غلط؟') }}
        <a href="{{ route('register') }}" style="color: var(--color-brand-500)">{{ setting('auth.otp.back_label', 'ارجع عدّله') }}</a>
    </p>
</div>

<script>
    (function () {
        const input = document.querySelector('[data-otp-input]');
        const submit = document.querySelector('[data-otp-submit]');
        const resend = document.querySelector('[data-otp-resend]');
        const label = document.querySelector('[data-otp-resend-label]');
        if (!input || !submit) return;

        const length = Number(input.dataset.length) || 4;
        const baseLabel = label ? label.textContent.trim() : '';

        // زرّ «تأكيد» معطَّل حتى تكتمل الأرقام بالضبط (2.5-ب)
        function sync() {
            const ready = /^\d+$/.test(input.value) && input.value.length === length;
            submit.disabled = !ready;
            submit.style.opacity = ready ? '1' : '.5';
        }

        input.addEventListener('input', function () {
            input.value = input.value.replace(/\D/g, '').slice(0, length);
            sync();
        });

        sync();
        input.focus();

        // عدّاد تنازليّ لإعادة الإرسال — بعد دقيقة (2.5-ب)
        let wait = Number(resend?.dataset.wait) || 0;

        function tick() {
            if (!resend || !label) return;

            if (wait <= 0) {
                resend.disabled = false;
                resend.style.opacity = '1';
                label.textContent = baseLabel;
                return;
            }

            resend.disabled = true;
            resend.style.opacity = '.6';
            label.textContent = baseLabel + ' (' + wait + ')';
            wait -= 1;
            setTimeout(tick, 1000);
        }

        tick();
    })();
</script>
@endsection
