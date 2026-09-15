@extends('layouts.guest')
@section('title', (string) setting('auth.pending.section_1', 'حسابك تحت المراجعة'))

@section('content')
<div class="panel w-full max-w-md text-center">
    <div class="mx-auto mb-3" style="color: var(--color-state-warn)" aria-hidden="true">
        {{-- ساعة رمليّة مرسومة SVG — بلا مكتبة أيقونات --}}
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="margin-inline: auto">
            <path d="M6 3h12M6 21h12" />
            <path d="M8 3v4a4 4 0 0 0 4 4 4 4 0 0 0 4-4V3" />
            <path d="M8 21v-4a4 4 0 0 1 4-4 4 4 0 0 1 4 4v4" />
        </svg>
    </div>

    <h1>{{ setting('auth.pending.text_1', 'حسابك تحت المراجعة') }}</h1>

    {{-- 2.5-د-3: الأدمن يقدر يضيف فيها **كود HTML** من لوحة الإدارة (2.13) --}}
    <div class="small muted mt-3 mb-6">
        {!! setting('onboarding.review.html', '') !!}
    </div>

    @if (session('status'))
        <p class="small mb-4" style="color: var(--success)">● {{ session('status') }}</p>
    @endif

    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button class="btn text">{{ setting('auth.pending.text_2', 'تسجيل الخروج') }}</button>
    </form>
</div>
@endsection
