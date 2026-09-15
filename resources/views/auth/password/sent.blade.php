@extends('layouts.guest')
@section('title', (string) setting('auth.password_sent.section_1', 'بعتنالك'))

@section('content')
{{--
    الرابط والرمز وجهان لنفس الطلب (2.3): الرابط لمن يفتح بريده على نفس الجهاز،
    والرمز الرباعيّ لمن يفتحه على تليفون تاني — وكلاهما يوصل لنفس الشاشة.
--}}
<div class="panel w-full max-w-sm animate-fadeup">
    <h1>{{ setting('auth.password_reset.sent_title', 'بصّ في بريدك') }}</h1>

    <p class="small muted mt-3 mb-5">
        {{ str_replace(
            ['{email}', '{minutes}'],
            [$email, (string) $ttlMinutes],
            setting('auth.password_reset.sent_hint', 'لو {email} مسجّل عندنا، هتلاقي رابط ورمز. صالحين {minutes} دقيقة.'),
        ) }}
    </p>

    @if (session('status'))
        <p class="small wash mb-5" style="padding: 10px 14px; border-radius: 12px">{{ session('status') }}</p>
    @endif

    <form method="post" action="{{ route('password.code') }}" class="stack" style="gap: 14px" data-otp-form>
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('auth.password_reset.code_label', 'أو اكتب الرمز اللي وصلك') }}</span>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                   maxlength="{{ $length }}" pattern="{{ '[0-9]{'.$length.'}' }}"
                   data-otp-input data-length="{{ $length }}"
                   class="w-full rounded-xl px-3 py-3 text-center font-extrabold tabular-nums"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); font-size: 1.5rem; letter-spacing: .5rem"
                   dir="ltr">
            @error('code')
                <span class="field-error block mt-1">{{ $message }}</span>
            @enderror
        </label>

        <button type="submit" data-otp-submit disabled class="btn btn-p w-full" style="opacity: .5">
            {{ setting('auth.password_reset.code_action', 'كمّل بالرمز') }}
        </button>
    </form>

    <form method="post" action="{{ route('password.email') }}" class="mt-3">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <button type="submit" data-otp-resend data-wait="{{ $wait }}" class="btn btn-g w-full">
            <span data-otp-resend-label>{{ setting('auth.password_reset.resend_label', 'ابعت تاني') }}</span>
        </button>
    </form>
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
