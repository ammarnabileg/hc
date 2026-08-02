@extends('layouts.app')

@section('title', 'الرئيسيّة')

@php
    // روابط «⋯» تُبنى بأسماء مسارات المجالات الأخرى إن كانت منشورة (بناء تدريجيّ)
    $storeUrl = \Illuminate\Support\Facades\Route::has('store.index') ? route('store.index') : null;
    $certificatesUrl = \Illuminate\Support\Facades\Route::has('learning.certificates') ? route('learning.certificates') : null;
@endphp

@section('content')
    <x-page-header :title="$greeting" subtitle="أين إنت في تدريباتك دلوقتي">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد بارز، والباقي في «⋯» (2.15-أ-2) --}}
            @if ($nextLesson)
                <a href="{{ $nextLesson['url'] }}"
                   class="btn hidden md:inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ $primaryLabel }}</a>
            @endif

            @if ($storeUrl || $certificatesUrl)
                <details class="relative">
                    <summary class="list-none cursor-pointer rounded-xl px-3 py-2 text-sm select-none"
                             style="background: var(--surface-raised)" aria-label="أفعال أخرى">⋯</summary>
                    <div class="card absolute end-0 mt-2 w-48 p-1 z-40">
                        @if ($storeUrl)
                            <a href="{{ $storeUrl }}" class="block rounded-lg px-3 py-2 text-sm motion-standard">تصفّح المتجر</a>
                        @endif
                        @if ($certificatesUrl)
                            <a href="{{ $certificatesUrl }}" class="block rounded-lg px-3 py-2 text-sm motion-standard">شهاداتي</a>
                        @endif
                    </div>
                </details>
            @endif
        </x-slot:action>
    </x-page-header>

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
    <script>
        /* عدّادات «أقرب المواعيد» تنبض حيّةً — والنصّ الخادميّ يبقى ظاهرًا لو الجافاسكربت وقف (2.17-أ) */
        (function () {
            const badges = document.querySelectorAll('[data-countdown]');
            if (!badges.length) return;

            const arabic = (n, one, two, few, many) =>
                n === 1 ? one : n === 2 ? two : n <= 10 ? `${n} ${few}` : `${n} ${many}`;

            const render = () => badges.forEach((badge) => {
                const at = Date.parse(badge.dataset.countdown);
                const text = badge.querySelector('span:last-child');
                if (!Number.isFinite(at) || !text) return;

                let seconds = Math.round((at - Date.now()) / 1000);
                const late = seconds <= 0;
                seconds = Math.abs(seconds);

                const label = seconds < 3600
                    ? arabic(Math.floor(seconds / 60) || 1, 'دقيقة', 'دقيقتين', 'دقائق', 'دقيقة')
                    : seconds < 86400
                        ? arabic(Math.floor(seconds / 3600), 'ساعة', 'ساعتين', 'ساعات', 'ساعة')
                        : arabic(Math.floor(seconds / 86400), 'يوم', 'يومين', 'أيّام', 'يومًا');

                text.textContent = (late ? 'فات الموعد من ' : 'باقي ') + label;
            });

            render();
            setInterval(render, 30000);
        })();
    </script>
@endpush
