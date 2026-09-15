@extends('layouts.app')

@section('title', setting('dashboard.page.title', 'الرئيسيّة'))

@php
    // روابط «⋯» تُبنى بأسماء مسارات المجالات الأخرى إن كانت منشورة (بناء تدريجيّ)
    $storeUrl = \Illuminate\Support\Facades\Route::has('store.index') ? route('store.index') : null;
    $certificatesUrl = \Illuminate\Support\Facades\Route::has('learning.certificates') ? route('learning.certificates') : null;
@endphp

@php
    /*
     | ⭐ رأس الصفحة تحوّل إلى Hero حرفيًّا من ملف الهويّة المرجعيّ: صورة محفورة
     | خافتة + عنوان علويّ + H1 بالتحيّة + سطر فرعيّ + فعل رئيسيّ واحد. والزيادة
     | التي لا يملكها المرجع (تثبيت الصفحة · وضع متقدّم · قائمة «⋯») بقيت بنفس
     | وظيفتها لكن بأسلوبه: شريط أدوات رفيع من `.icon-button`/`.hc-switch` فوق
     | الـHero لا هيدر مستقلّ يكسر تركيب الصفحة (تعليمات المالك المباشرة).
     */
    $pinRoute = request()->route()?->getName();
    $pinnedRoutes = collect(auth()->user()?->pinned_pages ?? [])->pluck('route')->all();
    $isPinned = $pinRoute && in_array($pinRoute, $pinnedRoutes, true);
@endphp

@section('content')
    <div class="spread mb-2" style="gap: 8px">
        <div class="cluster" style="gap: 4px">
            @if ($pinRoute && \Illuminate\Support\Facades\Route::has($pinRoute))
                <button type="button" data-pin-toggle="{{ $pinRoute }}" data-pin-label="{{ $greeting }}"
                        data-pinned="{{ $isPinned ? '1' : '0' }}" class="icon-button"
                        aria-pressed="{{ $isPinned ? 'true' : 'false' }}"
                        style="color: {{ $isPinned ? 'var(--color-brand-500)' : 'var(--text-muted)' }}"
                        aria-label="{{ $isPinned ? setting('ux.page_header.aria_label_expr_1', 'فكّ تثبيت الصفحة') : setting('ux.page_header.aria_label_expr_2', 'ثبّت الصفحة أعلى السايد بار') }}">
                    <x-icon name="pin" size="18" />
                </button>
            @endif
            @if ($storeUrl || $certificatesUrl)
                <details class="relative">
                    <summary class="icon-button list-none cursor-pointer select-none" aria-label="{{ setting('dashboard.page.more_actions_aria', 'أفعال أخرى') }}"><x-icon name="more" size="18" /></summary>
                    <div class="dropdown">
                        @if ($storeUrl)
                            <a href="{{ $storeUrl }}">{{ setting('dashboard.page.more_store', 'تصفّح المتجر') }}</a>
                        @endif
                        @if ($certificatesUrl)
                            <a href="{{ $certificatesUrl }}">{{ setting('dashboard.page.more_certificates', 'شهاداتي') }}</a>
                        @endif
                    </div>
                </details>
            @endif
        </div>
        <x-advanced-toggle />
    </div>

    <section class="hero">
        <img class="hero-art" src="{{ asset('images/identity/editorial-engraving.webp') }}" alt="">
        <div class="hero-copy">
            <span class="eyebrow">{{ setting('dashboard.hero.eyebrow', 'المعرفة تبدأ بخطوة') }}</span>
            <h1>{{ $greeting }}</h1>
            <p>{{ setting('dashboard.page.subtitle', 'أين إنت في تدريباتك دلوقتي') }}</p>
            @if ($nextLesson)
                <a href="{{ $nextLesson['url'] }}" class="btn btn-p inline-flex items-center gap-2">
                    {{ $primaryLabel }} <x-icon name="left" size="16" />
                </a>
            @endif
        </div>
    </section>

    @if (! $hasEnrollments)
        {{-- الحالة الفارغة: سطر واحد + زرّ واحد، تشجّع ولا تعاتب (2.15-د · 2.17-ج) --}}
        <x-empty :message="setting('dashboard.empty.message', 'لسّه مابدأتش تدريب')"
                 :action="setting('dashboard.empty.action', 'تصفّح المتجر')"
                 :href="$storeUrl ?? url('/')" />
    @else
        <x-tabs :tabs="$tabs" :current="$tab" />

        @if ($tab === 'details')
            @include('dashboard.partials.details')
        @elseif ($tab === 'stats')
            @include('dashboard.partials.stats')
        @else
            @include('dashboard.partials.overview')
        @endif
    @endif
    {{-- ⭐ زرّ عائم «أكمل من حيث توقفت» في الرئيسيّة (3.4-15) --}}
    @include('learning.partials.resume-fab')

@endsection

@if ($nextLesson)
    @section('mobile_action')
        {{-- الفعل الرئيسيّ في متناول الإبهام على الموبايل (2.15-ج) --}}
        <a href="{{ $nextLesson['url'] }}"
           class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ $primaryLabel }}</a>
    @endsection
@endif

@push('scripts')
    @php
        // نصوص العدّاد الحيّ — من الإعدادات لا من السكربت (2.13)، وصيغُ الجمع
        // العربيّة أربع: مفرد · مثنّى · جمع قلّة (3–10) · جمع كثرة، و`:n` مكانُ الرقم.
        $countdownWords = [
            'minutes' => [
                'one' => (string) setting('dashboard.countdown.minutes_one', 'دقيقة'),
                'two' => (string) setting('dashboard.countdown.minutes_two', 'دقيقتين'),
                'few' => (string) setting('dashboard.countdown.minutes_few', ':n دقائق'),
                'many' => (string) setting('dashboard.countdown.minutes_many', ':n دقيقة'),
            ],
            'hours' => [
                'one' => (string) setting('dashboard.countdown.hours_one', 'ساعة'),
                'two' => (string) setting('dashboard.countdown.hours_two', 'ساعتين'),
                'few' => (string) setting('dashboard.countdown.hours_few', ':n ساعات'),
                'many' => (string) setting('dashboard.countdown.hours_many', ':n ساعة'),
            ],
            'days' => [
                'one' => (string) setting('dashboard.countdown.days_one', 'يوم'),
                'two' => (string) setting('dashboard.countdown.days_two', 'يومين'),
                'few' => (string) setting('dashboard.countdown.days_few', ':n أيّام'),
                'many' => (string) setting('dashboard.countdown.days_many', ':n يومًا'),
            ],
            'late' => (string) setting('dashboard.countdown.late', 'فات الموعد من :duration'),
            'remaining' => (string) setting('dashboard.countdown.remaining', 'باقي :duration'),
        ];
    @endphp
    <script>
        /* عدّادات «أقرب المواعيد» تنبض حيّةً — والنصّ الخادميّ يبقى ظاهرًا لو الجافاسكربت وقف (2.17-أ) */
        (function () {
            const badges = document.querySelectorAll('[data-countdown]');
            if (!badges.length) return;

            const words = @json($countdownWords);
            const arabic = (n, forms) =>
                (n === 1 ? forms.one : n === 2 ? forms.two : n <= 10 ? forms.few : forms.many)
                    .split(':n').join(n);

            const render = () => badges.forEach((badge) => {
                const at = Date.parse(badge.dataset.countdown);
                const text = badge.querySelector('span:last-child');
                if (!Number.isFinite(at) || !text) return;

                let seconds = Math.round((at - Date.now()) / 1000);
                const late = seconds <= 0;
                seconds = Math.abs(seconds);

                const label = seconds < 3600
                    ? arabic(Math.floor(seconds / 60) || 1, words.minutes)
                    : seconds < 86400
                        ? arabic(Math.floor(seconds / 3600), words.hours)
                        : arabic(Math.floor(seconds / 86400), words.days);

                text.textContent = (late ? words.late : words.remaining).split(':duration').join(label);
            });

            render();
            setInterval(render, 30000);
        })();
    </script>
@endpush
