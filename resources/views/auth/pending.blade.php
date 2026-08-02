@extends('layouts.guest')
@section('title', 'حسابك تحت المراجعة')

@section('content')
<div class="card p-8 w-full max-w-md text-center">
    <div class="text-4xl mb-3">⏳</div>
    <h1 class="text-xl font-extrabold mb-2">حسابك تحت المراجعة</h1>
    <p class="text-sm mb-5" style="color: var(--text-muted)">
        سجّلت بنجاح — والتفعيل <strong>مجّانيّ</strong> وبيتمّ باعتماد من الإدارة. هنبلّغك أوّل ما يتفعّل.
    </p>
    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button class="text-sm" style="color: var(--color-brand-500)">تسجيل الخروج</button>
    </form>
</div>
@endsection
