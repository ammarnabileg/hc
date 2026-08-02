@extends('layouts.guest')
@section('title', 'كلمة سرّ جديدة')

@section('content')
{{-- إعادة التعيين (2.3) — وبعدها تُقفل كلّ الجلسات القديمة للأمان --}}
<div class="card p-6 w-full max-w-sm animate-fadeup">
    <h1 class="text-xl font-extrabold mb-1">{{ setting('auth.password_reset.reset_title', 'اختار كلمة سرّ جديدة') }}</h1>
    <p class="text-sm mb-5" style="color: var(--text-muted)">
        {{ str_replace(
            '{min}',
            (string) (int) setting('auth.password.min_length', 8),
            setting('auth.password_reset.reset_hint', '{min} خانات على الأقلّ. وهنقفل كلّ الجلسات القديمة بعد التغيير.'),
        ) }}
    </p>

    <form method="post" action="{{ route('password.update') }}" class="space-y-3">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <x-form.input name="password" type="password" label="كلمة السرّ الجديدة" required autofocus />
        <x-form.input name="password_confirmation" type="password" label="أكّدها تاني" required />

        <button class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                style="background: var(--color-brand-500); color:#04201c">
            {{ setting('auth.password_reset.reset_action', 'غيّرها') }}
        </button>
    </form>
</div>
@endsection
