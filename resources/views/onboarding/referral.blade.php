@extends('layouts.guest')
@section('title', setting('onboarding.referral.title', 'هل دعاك شخص ما؟'))

@section('content')
    {{-- 2.5-أ: الشاشة الأولى — كود الصديق + تفعيل الهديّة أو تخطٍّ. سؤال واحد لكلّ شاشة (2.15) --}}
    <div class="card p-6 w-full max-w-md">
        <div class="mb-4 flex items-center gap-3">
            {{-- أيقونة SVG مرسومة بهويّة المنصّة — بلا أيّ مكتبة أيقونات --}}
            <span aria-hidden="true" style="color: var(--color-brand-500)">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 12v9H4v-9" />
                    <path d="M2 7h20v5H2z" />
                    <path d="M12 21V7" />
                    <path d="M12 7S9.5 3 7.5 3a2.5 2.5 0 0 0 0 5H12z" />
                    <path d="M12 7s2.5-4 4.5-4a2.5 2.5 0 0 1 0 5H12z" />
                </svg>
            </span>
            <h1 class="text-xl font-extrabold">{{ setting('onboarding.referral.title', 'هل دعاك شخص ما؟') }}</h1>
        </div>

        <p class="text-sm mb-5" style="color: var(--text-muted)">
            {{ setting('onboarding.referral.body', 'لو حد من أصحابك دعاك، اكتب كوده وخُد الهديّة. ولو لأ، كمّل عادي.') }}
        </p>

        <form method="post" action="{{ route('onboarding.referral.apply') }}" class="space-y-3">
            @csrf
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('onboarding.referral.field_label', 'كود الصديق') }}</span>
                <input type="text" name="code" value="{{ old('code') }}" autocomplete="off"
                       class="w-full rounded-xl px-3 py-2 text-sm" style="min-height: 44px;
                              background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @error('code')
                    <span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>
                @enderror
            </label>

            <button class="btn w-full rounded-xl py-2 font-semibold motion-standard" style="min-height: 44px;
                    background: var(--color-brand-500); color:#04201c">
                {{ setting('onboarding.referral.activate_label', 'فعّل الهديّة') }}
            </button>
        </form>

        {{-- الفعل الرئيسيّ واحد، والتخطّي ثانويّ بصريًّا (2.15-أ) --}}
        <form method="post" action="{{ route('onboarding.referral.skip') }}" class="mt-3 text-center">
            @csrf
            <button class="text-sm underline" style="color: var(--text-muted); min-height: 44px">
                {{ setting('onboarding.referral.skip_label', 'مافيش حد دعاني') }}
            </button>
        </form>
    </div>
@endsection
