@extends('layouts.app')

@php
    /**
     * لوحة متصدّري السفراء (7.6.1 · 21.1-ج) — **ألقاب فقط بلا شارات**،
     * وأرقام حقيقيّة (دعوات مفعَّلة)، وصفحة عامّة يفتحها الزائر بلا تسجيل
     * فتصير مكانةً تُرى لا رقمًا داخليًّا.
     */
    $title = (string) setting('ambassadors.page.title', 'سفراء المنصّة');
    $subtitle = (string) setting('ambassadors.page.subtitle', 'اللقب بيتحسب بالدعوات المفعّلة بس — يعني ناس دخلت فعلًا وفعّلت حسابها.');
@endphp

@section('title', $title)
@section('meta_description', $subtitle)

@section('content')
    @guest
        @include('home.partials.nav')
    @endguest

    <x-page-header :title="$title" :subtitle="$subtitle" />

    {{-- سلّم الألقاب مكتوب صراحةً — لا ألغاز ولا شروط مخفيّة (2.9) --}}
    @if ($tiers)
        <section class="card p-4 md:p-5 mb-4" aria-labelledby="tiers-title">
            <h2 id="tiers-title" class="font-bold text-sm mb-3">{{ setting('ambassadors.tiers_title', 'سلّم الألقاب') }}</h2>

            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($tiers as $tier)
                    @php $reached = $me && (int) $myInvites >= (int) $tier['threshold']; @endphp
                    <div class="rounded-xl p-3 flex items-center gap-2"
                         style="background: var(--surface-sunken); {{ $reached ? 'border: 1px solid color-mix(in srgb, var(--color-state-honor) 40%, transparent)' : '' }}">
                        <span style="color: {{ $reached ? 'var(--color-state-honor)' : 'var(--text-muted)' }}">
                            @include('home.partials.icon', ['name' => 'crown', 'size' => 18])
                        </span>
                        <div class="min-w-0">
                            <div class="font-bold text-xs truncate">{{ $tier['label'] }}</div>
                            <div class="text-xs" style="color: var(--text-muted)">{{ $tier['threshold'] }} {{ setting('ambassadors.page.text_1', 'دعوة مفعّلة') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- سطر المستخدم نفسه: لقبه الحاليّ وكم باقي للتالي — بصدق بلا ضغط (2.9-3) --}}
    @auth
        <section class="card p-4 md:p-5 mb-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <x-avatar :user="$me" size="12" />
                    <div class="min-w-0">
                        <div class="font-bold text-sm truncate">{{ $me->shortName() }}</div>
                        @if ($myTitle)
                            <span class="inline-flex items-center gap-1 mt-1 rounded-full px-2 py-0.5 text-xs font-bold"
                                  style="background: color-mix(in srgb, var(--color-state-honor) 16%, transparent); color: var(--color-state-honor)">
                                <span aria-hidden="true">★</span>{{ $myTitle }}
                            </span>
                        @else
                            <span class="text-xs" style="color: var(--text-muted)">{{ setting('ambassadors.no_title_yet', 'لسّه مابتلقّبتش — أوّل دعوة مفعّلة هي البداية.') }}</span>
                        @endif
                    </div>
                </div>

                <div class="text-center">
                    <div class="text-2xl font-extrabold" data-count-to="{{ $myInvites }}">{{ $myInvites }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">{{ setting('ambassadors.invites_label', 'دعوة مفعّلة') }}</div>
                </div>
            </div>

            @if ($nextTier)
                <p class="mt-3 text-xs" style="color: var(--text-muted)">
                    {{ strtr((string) setting('ambassadors.page.text_2', 'باقي :a1 دعوة مفعّلة على لقب «:a2».'), [':a1' => (string) ($nextTier['remaining']), ':a2' => (string) ($nextTier['label'])]) }}
                </p>
            @endif

            @if (\Illuminate\Support\Facades\Route::has('referral.index'))
                <a href="{{ route('referral.index') }}"
                   class="btn inline-flex items-center mt-4 rounded-xl px-4 py-2 text-sm font-bold motion-standard"
                   style="background: var(--color-brand-500); color:#04201c">{{ setting('ambassadors.cta', 'خد رابط دعوتك') }}</a>
            @endif
        </section>
    @endauth

    {{-- اللوحة نفسها: بطاقات رأسيّة على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
    @if ($leaders->isEmpty())
        <x-empty :message="setting('ambassadors.empty', 'لسّه محدّش وصل لأوّل لقب — تقدر تكون إنت الأوّل.')"
                 :action="auth()->check() && \Illuminate\Support\Facades\Route::has('referral.index') ? setting('ambassadors.cta', 'خد رابط دعوتك') : setting('home.nav.register', 'أنشئ حسابك')"
                 :href="auth()->check() && \Illuminate\Support\Facades\Route::has('referral.index') ? route('referral.index') : route('register')" />
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($leaders as $leader)
                @include('home.partials.ambassador-card', ['ambassador' => $leader, 'rank' => $loop->iteration])
            @endforeach
        </div>
    @endif

    @guest
        @include('home.partials.footer')
    @endguest

    @include('home.partials.surprise')

    @if ($celebration)
        {{-- احتفال بلوغ اللقب بمستواه، ومرّة واحدة Server-side (2.14) --}}
        @include('home.partials.celebration', ['celebration' => $celebration])
    @endif
@endsection
