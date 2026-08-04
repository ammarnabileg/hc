@extends('layouts.guest')
@section('title', (string) setting('auth.pending.section_1', 'حسابك تحت المراجعة'))

@section('content')
<div class="card p-8 w-full max-w-md text-center">
    <div class="mx-auto mb-3" style="color: var(--color-state-warn)" aria-hidden="true">
        {{-- ساعة رمليّة مرسومة SVG — بلا مكتبة أيقونات --}}
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-inline: auto">
            <path d="M6 3h12M6 21h12" />
            <path d="M8 3v4a4 4 0 0 0 4 4 4 4 0 0 0 4-4V3" />
            <path d="M8 21v-4a4 4 0 0 1 4-4 4 4 0 0 1 4 4v4" />
        </svg>
    </div>

    <h1 class="text-xl font-extrabold mb-2">{{ setting('auth.pending.text_1', 'حسابك تحت المراجعة') }}</h1>

    {{-- 2.5-د-3: الأدمن يقدر يضيف فيها **كود HTML** من لوحة الإدارة (2.13) --}}
    <div class="text-sm mb-5" style="color: var(--text-muted)">
        {!! setting('onboarding.review.html', '') !!}
    </div>

    @if (session('status'))
        <p class="text-sm mb-4" style="color: var(--color-state-ok)">● {{ session('status') }}</p>
    @endif

    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button class="text-sm" style="color: var(--color-brand-500); min-height: 44px">{{ setting('auth.pending.text_2', 'تسجيل الخروج') }}</button>
    </form>
</div>
@endsection
