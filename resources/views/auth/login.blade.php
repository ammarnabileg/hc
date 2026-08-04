@extends('layouts.guest')
@section('title', (string) setting('auth.login.section_1', 'تسجيل الدخول'))

@section('content')
<div class="card p-6 w-full max-w-sm">
    <h1 class="text-xl font-extrabold mb-1">{{ setting('auth.login.text_1', 'أهلًا بعودتك') }}</h1>
    <p class="text-sm mb-5" style="color: var(--text-muted)">{{ setting('auth.login.text_2', 'ادخل بالكود أو البريد.') }}</p>

    <form method="post" action="{{ route('login') }}" class="space-y-3">
        @csrf
        <x-form.input name="identifier" label="{{ setting('auth.login.label_1', 'الكود أو البريد') }}" :value="old('identifier')" required />
        <x-form.input name="password" type="password" label="{{ setting('auth.login.label_2', 'كلمة السرّ') }}" required />

        {{--
          ⭐ 2.3: الجلسة تُحفَظ **مدى الحياة** ولا تنتهي تلقائيًّا — تفضل مفتوحة
          لحدّ ما المستخدم يعمل «تسجيل خروج» بنفسه. فـ«فكّرني» لم يعد اختيارًا
          يُنسى فيُطرَد صاحبه بعد ساعتين؛ صار **سلوك المنصّة**، ونقوله له صراحةً.
          ويبقى المفتاح ظاهرًا لو أطفأ المالك السلوك من اللوحة (2.13).
        --}}
        @unless (setting('auth.session.remember_always', true))
            <label class="flex items-center gap-2 text-sm" style="min-height: 44px">
                <input type="checkbox" name="remember" value="1"> {{ setting('auth.login.text_3', 'فكّرني') }}
            </label>
        @endunless

        <button class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                style="min-height: 44px; background: var(--color-brand-500); color:#04201c">{{ setting('auth.login.text_4', 'دخول') }}</button>
    </form>

    @if (setting('auth.session.remember_always', true))
        <p class="text-xs mt-3 flex items-start gap-2" style="color: var(--text-muted)">
            <span aria-hidden="true" style="color: var(--color-state-ok)">●</span>
            <span>{{ setting('auth.session.persistent_hint', 'هتفضل داخل على طول — لحدّ ما تعمل «تسجيل خروج» بنفسك.') }}</span>
        </p>
    @endif

    <p class="text-sm mt-4" style="color: var(--text-muted)">
        {{ setting('auth.login.text_5', 'نسيت كلمة السرّ؟') }} <a href="{{ route('password.request') }}" style="color: var(--color-brand-500)">{{ setting('auth.login.text_6', 'استرجعها') }}</a>
    </p>

    <p class="text-sm mt-2" style="color: var(--text-muted)">
        {{ setting('auth.login.text_7', 'لسّه مامعاكش حساب؟') }} <a href="{{ route('register') }}" style="color: var(--color-brand-500)">{{ setting('auth.login.text_8', 'سجّل مجّانًا') }}</a>
    </p>
</div>
@endsection
