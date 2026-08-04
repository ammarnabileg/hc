@extends('layouts.guest')
@section('title', (string) setting('auth.password_reset.section_1', 'كلمة سرّ جديدة'))

@section('content')
{{--
    إعادة التعيين (2.3 · 12.1-الأمان) — نصّ الدستور حرفيًّا:
    صفحة تعرض **إيميله** (للتذكير)، وتحتها **حقلان** (كلمة مرور جديدة + تأكيدها)
    **بدون شروط غير أن تكون أكثر من 6 خانات** — وزرّ «تأكيد» **معطّل حتى تتطابق
    الكلمتان**، مع **أيقونة العين** لإظهار/إخفاء كلمة المرور.

    والزرّ يبدأ معطّلًا ويُفعَّل لحظة التطابق — وهو **راحةٌ للمستخدم لا حارسٌ
    أمنيّ**: الخادم يتحقّق من التطابق والطول على أيّ حال، ورسالة `noscript`
    العامّة (2.1) بتشرح للمستخدم إزاي يفعّل الجافاسكربت لو مقفول.
--}}
@php
    $min = max(1, (int) setting('auth.password.min_length', 7));
@endphp

<div class="card p-6 w-full max-w-sm animate-fadeup">
    <h1 class="text-xl font-extrabold mb-1">{{ setting('auth.password_reset.reset_title', 'اختار كلمة سرّ جديدة') }}</h1>

    {{-- ⭐ الإيميل ظاهر للتذكير — فمَن وصله الرابط من الدعم يعرف الحساب المقصود --}}
    <p class="text-sm mb-1">
        <span style="color: var(--text-muted)">{{ setting('auth.password_reset.email_label', 'الحساب') }}:</span>
        <bdi dir="ltr" class="font-semibold">{{ $email }}</bdi>
    </p>

    <p class="text-sm mb-5" style="color: var(--text-muted)">
        {{ str_replace(
            '{min}',
            (string) $min,
            setting('auth.password_reset.reset_hint', '{min} خانات على الأقلّ ومفيش أيّ شرط تاني. وهنقفل كلّ الجلسات القديمة بعد التغيير.'),
        ) }}
    </p>

    <form method="post" action="{{ route('password.update') }}" class="space-y-3" data-password-form>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        @foreach (['password' => (string) setting('auth.password_reset.foreach_1', 'كلمة السرّ الجديدة'), 'password_confirmation' => (string) setting('auth.password_reset.foreach_2', 'أكّدها تاني')] as $field => $label)
            <label class="block">
                <span class="block text-sm mb-1">{{ $label }}</span>
                <span class="relative flex items-center">
                    <input type="password" name="{{ $field }}" id="{{ $field }}" required
                           minlength="{{ $min }}" autocomplete="new-password"
                           data-password-field
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="padding-inline-end: 3rem; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                    {{-- 👁 أيقونة العين — SVG مرسوم بهويّة المنصّة، ولا مكتبة أيقونات --}}
                    <button type="button" data-password-eye
                            aria-label="{{ setting('auth.password_reset.show_label', 'إظهار كلمة السرّ') }}"
                            aria-pressed="false"
                            data-show-label="{{ setting('auth.password_reset.show_label', 'إظهار كلمة السرّ') }}"
                            data-hide-label="{{ setting('auth.password_reset.hide_label', 'إخفاء كلمة السرّ') }}"
                            class="absolute flex items-center justify-center rounded-xl motion-standard"
                            style="inset-inline-end: .25rem; inline-size: 44px; block-size: 44px; color: var(--text-muted)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round" style="inline-size: 20px; block-size: 20px">
                            <path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z" />
                            <circle cx="12" cy="12" r="2.8" />
                            <path d="M4 20 20 4" data-eye-slash class="hidden" />
                        </svg>
                    </button>
                </span>
            </label>
        @endforeach

        {{-- سطر واحد يقول الحالة بنصّ لا بلون وحده (2.16) --}}
        <p class="text-xs" data-password-hint
           data-mismatch="{{ setting('auth.password_reset.mismatch_text', 'الكلمتان لسّه مش متطابقتين.') }}"
           data-match="{{ setting('auth.password_reset.match_text', 'متطابقتين ✓') }}"
           style="color: var(--text-muted)"></p>

        <button data-password-submit disabled
                class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                style="min-block-size: 44px; background: var(--color-brand-500); color:#04201c">
            {{ setting('auth.password_reset.reset_action', 'غيّرها') }}
        </button>

    </form>
</div>

<script>
    /* أيقونة العين + تعطيل الزرّ حتى تتطابق الكلمتان (12.1-الأمان · 2.17-أ) */
    (function () {
        const form = document.querySelector('[data-password-form]');
        if (!form) return;

        const fields = Array.from(form.querySelectorAll('[data-password-field]'));
        const submit = form.querySelector('[data-password-submit]');
        const hint = form.querySelector('[data-password-hint]');
        const min = {{ $min }};

        form.querySelectorAll('[data-password-eye]').forEach(function (eye) {
            eye.addEventListener('click', function () {
                const field = eye.closest('span').querySelector('[data-password-field]');
                const shown = field.type === 'text';

                field.type = shown ? 'password' : 'text';
                eye.setAttribute('aria-pressed', shown ? 'false' : 'true');
                eye.setAttribute('aria-label', shown ? eye.dataset.showLabel : eye.dataset.hideLabel);
                eye.querySelector('[data-eye-slash]').classList.toggle('hidden', shown);
            });
        });

        function sync() {
            const [first, second] = fields.map((f) => f.value);
            const longEnough = first.length >= min;
            const match = first !== '' && first === second;

            submit.disabled = !(longEnough && match);
            submit.style.opacity = submit.disabled ? '.5' : '1';

            hint.textContent = second === '' ? '' : (match ? hint.dataset.match : hint.dataset.mismatch);
            hint.style.color = match ? 'var(--color-state-ok)' : 'var(--color-state-danger)';
        }

        fields.forEach((field) => field.addEventListener('input', sync));
        sync();
    })();
</script>
@endsection
