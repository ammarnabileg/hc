@extends('layouts.guest')
@section('title', 'إنشاء حساب')

@section('content')
<div class="card p-6 w-full max-w-md">
    <h1 class="text-xl font-extrabold mb-1">إنشاء حساب</h1>
    {{-- التفعيل مجّانيّ باعتماد إداريّ — لا رسوم ولا اشتراك (2.5-د) --}}
    <p class="text-sm mb-5" style="color: var(--text-muted)">التسجيل والتفعيل <strong>مجّانيّان بالكامل</strong>.</p>

    <form method="post" action="{{ route('register') }}" class="space-y-3">
        @csrf
        <input type="hidden" name="offer" value="{{ $referral }}">
        <x-form.input name="name" label="الاسم" :value="old('name')" required />
        <x-form.input name="email" type="email" label="البريد" :value="old('email')" required />
        <x-form.input name="phone" label="رقم الموبايل (اختياريّ)" :value="old('phone')" />
        <x-form.input name="password" type="password" label="كلمة السرّ" required />
        <x-form.input name="password_confirmation" type="password" label="تأكيد كلمة السرّ" required />

        <button class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                style="background: var(--color-brand-500); color:#04201c">إنشاء الحساب</button>
    </form>

    <p class="text-sm mt-4" style="color: var(--text-muted)">
        عندك حساب؟ <a href="{{ route('login') }}" style="color: var(--color-brand-500)">ادخل من هنا</a>
    </p>
</div>
@endsection
