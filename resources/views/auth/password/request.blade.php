@extends('layouts.guest')
@section('title', (string) setting('auth.password_request.section_1', 'نسيت كلمة السرّ'))

@section('content')
{{-- طلب الاسترجاع (2.3) — سؤال واحد لكلّ شاشة، وفعل رئيسيّ واحد (2.15) --}}
<div class="panel w-full max-w-sm animate-fadeup">
    <h1>{{ setting('auth.password_reset.request_title', 'نسيت كلمة السرّ؟') }}</h1>
    <p class="mt-3 mb-6 muted">
        {{ setting('auth.password_reset.request_hint', 'اكتب بريدك، وهنبعتلك رابط ورمز. اختار الأسهل عليك.') }}
    </p>

    <form method="post" action="{{ route('password.email') }}" class="stack" style="gap: 14px">
        @csrf
        <x-form.input name="email" type="email" label="{{ setting('auth.password_request.label_1', 'البريد') }}" :value="old('email')" required autofocus />

        <button class="btn btn-p w-full">
            {{ setting('auth.password_reset.request_action', 'ابعتلي') }}
        </button>
    </form>

    <p class="small muted mt-4">
        {{ setting('auth.password_request.text_1', 'فاكرها؟') }} <a href="{{ route('login') }}" class="accent">{{ setting('auth.password_request.text_2', 'ارجع للدخول') }}</a>
    </p>
</div>
@endsection
