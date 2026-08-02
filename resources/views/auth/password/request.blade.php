@extends('layouts.guest')
@section('title', 'نسيت كلمة السرّ')

@section('content')
{{-- طلب الاسترجاع (2.3) — سؤال واحد لكلّ شاشة، وفعل رئيسيّ واحد (2.15) --}}
<div class="card p-6 w-full max-w-sm animate-fadeup">
    <h1 class="text-xl font-extrabold mb-1">{{ setting('auth.password_reset.request_title', 'نسيت كلمة السرّ؟') }}</h1>
    <p class="text-sm mb-5" style="color: var(--text-muted)">
        {{ setting('auth.password_reset.request_hint', 'اكتب بريدك وهنبعتلك رابط ورمز — أيّهما أسهل عليك.') }}
    </p>

    <form method="post" action="{{ route('password.email') }}" class="space-y-3">
        @csrf
        <x-form.input name="email" type="email" label="البريد" :value="old('email')" required autofocus />

        <button class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                style="background: var(--color-brand-500); color:#04201c">
            {{ setting('auth.password_reset.request_action', 'ابعتلي') }}
        </button>
    </form>

    <p class="text-sm mt-4" style="color: var(--text-muted)">
        فاكرها؟ <a href="{{ route('login') }}" style="color: var(--color-brand-500)">ارجع للدخول</a>
    </p>
</div>
@endsection
