@extends('layouts.guest')
@section('title', (string) setting('auth.login.section_1', 'تسجيل الدخول'))

@section('content')
{{-- ⭐ شاشة دخول حرفيًّا من `authTemplate()` في ملف الهويّة: بطاقة .panel وسط الصفحة، عنوان علويّ (eyebrow) + H1 + سطر فرعيّ (24.5) --}}
<div class="panel w-full max-w-sm">
    <span class="eyebrow">{{ setting('auth.login.eyebrow', 'المنصّة') }}</span>
    <h1>{{ setting('auth.login.text_1', 'أهلًا بعودتك') }}</h1>
    <p class="mt-3 mb-6 muted">{{ setting('auth.login.text_2', 'ادخل بالكود أو البريد.') }}</p>

    <form method="post" action="{{ route('login') }}" class="stack" style="gap: 14px">
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
            <label class="cluster small" style="min-height: 44px">
                <input type="checkbox" name="remember" value="1"> {{ setting('auth.login.text_3', 'فكّرني') }}
            </label>
        @endunless

        <button class="btn btn-p w-full">{{ setting('auth.login.text_4', 'دخول') }}</button>
    </form>

    @if (setting('auth.session.remember_always', true))
        <p class="small muted mt-4 cluster" style="align-items: flex-start; gap: 8px">
            <span aria-hidden="true" style="color: var(--success)">●</span>
            <span>{{ setting('auth.session.persistent_hint', 'هتفضل داخل على طول — لحدّ ما تعمل «تسجيل خروج» بنفسك.') }}</span>
        </p>
    @endif

    <p class="small muted mt-4">
        {{ setting('auth.login.text_5', 'نسيت كلمة السرّ؟') }} <a href="{{ route('password.request') }}" class="accent">{{ setting('auth.login.text_6', 'استرجعها') }}</a>
    </p>

    <p class="small muted mt-2">
        {{ setting('auth.login.text_7', 'لسّه مامعاكش حساب؟') }} <a href="{{ route('register') }}" class="accent">{{ setting('auth.login.text_8', 'سجّل مجّانًا') }}</a>
    </p>
</div>
@endsection
