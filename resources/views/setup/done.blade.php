@extends('layouts.guest')
@section('title', 'تمّ التنصيب بنجاح')

@section('content')
<div class="w-full max-w-lg">
    <div class="card p-8 text-center">
        {{-- لحظة فخر بسيطة بلا مبالغة (2.17-أ) --}}
        <div class="flex items-center justify-center mb-4">
            <x-state-badge state="ok" label="التنصيب تمّ" />
        </div>

        <h1 class="text-xl font-extrabold mb-2">{{ $appName }} جاهزة</h1>

        <p class="text-sm mb-6" style="color: var(--text-muted)">
            كلّ حاجة اتظبطت، وصفحة التنصيب اتقفلت. ادخل بحسابك وابدأ.
        </p>

        <ul class="space-y-2 mb-6 text-sm">
            <li class="flex items-center justify-between gap-3 rounded-xl p-3"
                style="background: var(--surface-sunken); border: 1px solid var(--border)">
                <span style="color: var(--text-muted)">بريد الدخول</span>
                <span class="font-semibold" dir="ltr">{{ $ownerEmail }}</span>
            </li>
            @if ($ownerCode)
                <li class="flex items-center justify-between gap-3 rounded-xl p-3"
                    style="background: var(--surface-sunken); border: 1px solid var(--border)">
                    <span style="color: var(--text-muted)">كودك في المنصّة</span>
                    <span class="font-semibold" dir="ltr">{{ $ownerCode }}</span>
                </li>
            @endif
        </ul>

        <a href="{{ route('login') }}"
           class="btn inline-flex items-center justify-center w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="background: var(--color-brand-500); color:#04201c">ادخل للمنصّة</a>
    </div>
</div>
@endsection
