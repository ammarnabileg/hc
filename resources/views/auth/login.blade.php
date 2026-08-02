@extends('layouts.guest')
@section('title', 'تسجيل الدخول')

@section('content')
<div class="card p-6 w-full max-w-sm">
    <h1 class="text-xl font-extrabold mb-1">أهلًا بعودتك</h1>
    <p class="text-sm mb-5" style="color: var(--text-muted)">ادخل بالكود أو البريد.</p>

    <form method="post" action="{{ route('login') }}" class="space-y-3">
        @csrf
        <x-form.input name="identifier" label="الكود أو البريد" :value="old('identifier')" required />
        <x-form.input name="password" type="password" label="كلمة السرّ" required />

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1"> فكّرني
        </label>

        <button class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                style="background: var(--color-brand-500); color:#04201c">دخول</button>
    </form>

    <p class="text-sm mt-4" style="color: var(--text-muted)">
        لسّه مامعاكش حساب؟ <a href="{{ route('register') }}" style="color: var(--color-brand-500)">سجّل مجّانًا</a>
    </p>
</div>
@endsection
