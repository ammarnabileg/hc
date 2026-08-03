@extends('layouts.volunteer')

@section('title', $path->name_ar)

@php
    /**
     * داخل المسار الأكاديميّ (13.4-ل · 24.4-9).
     * - Roadmap رأسيّ بالمحطّات، و**نفس علامة الإكمال بالظبط بلا تمييز** عن المسار الطبيعيّ.
     * - **[احصل على الشهادة] بعد 100% فقط** لمسار مربوط — وقبلها **بار صامت بلا CTA**.
     * - المسار التعليميّ الصِرف: **بلا أيّ إيحاء بشهادة ناقصة**.
     */
    $done = $progress['done'];
@endphp

@section('content')
    <x-page-header
        :title="$path->name_ar"
        :subtitle="$progress['percent'].'% من المسار'"
        :breadcrumbs="[
            ['label' => 'لوحة التطوّع', 'url' => url('/volunteer')],
            ['label' => 'الأكاديمية', 'url' => route('volunteer.academy')],
            ['label' => $path->name_ar],
        ]" />

    <div class="card p-4 mb-4">
        <div class="h-2 rounded-full" style="background: var(--surface-sunken)">
            <div class="h-2 rounded-full motion-standard"
                 style="width: {{ $progress['percent'] }}%; background: var(--color-brand-500)"></div>
        </div>
        <p class="mt-2 text-xs" style="color: var(--text-muted)">
            {{ count($done) }} من {{ $progress['courses']->count() }} تدريبات
            @if ($progress['coverage'])
                · يغطّي {{ $progress['coverage']['covered'] }} من {{ $progress['coverage']['total'] }} تدريبات مسار الشهادة
            @endif
        </p>

        @if ($progress['complete'])
            <p class="mt-3 text-sm">{{ $completionMessage }}</p>

            @if ($showCta)
                {{-- الزرّ لا يظهر إلّا هنا: 100% + مسار مربوط (13.4-ل) --}}
                <a href="{{ url('/paths/'.$progress['target']->slug) }}"
                   class="btn inline-flex mt-3 rounded-xl px-4 py-2 text-sm font-semibold"
                   style="background: var(--color-brand-500); color: #04201c">احصل على الشهادة</a>
            @endif
        @endif
    </div>

    {{-- Roadmap رأسيّ بمحطّات مرقّمة --}}
    <ol class="space-y-3">
        @foreach ($progress['courses'] as $index => $course)
            @php $isDone = in_array((int) $course->id, $done, true); @endphp

            <li class="card p-4 flex items-start gap-3 animate-fadeup">
                <span class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                      style="background: var(--surface-sunken)">{{ $index + 1 }}</span>

                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold">{{ $course->name_ar }}</h2>
                    @if ($course->description_ar)
                        <p class="text-xs mt-1 line-clamp-2" style="color: var(--text-muted)">{{ $course->description_ar }}</p>
                    @endif
                </div>

                {{-- ⭐ نفس علامة الإكمال بالظبط في المسار الطبيعيّ — بلا أيّ تمييز --}}
                @if ($isDone)
                    <x-state-badge state="ok" label="مكتمل" />
                @else
                    <x-state-badge state="idle" label="لم يبدأ" />
                @endif
            </li>
        @endforeach
    </ol>

    @if ($progress['courses']->isEmpty())
        <x-empty message="المسار ده لسّه بيتجهّز — أوّل تدريب هيظهر هنا" />
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('volunteer.academy') }}"
       class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">كلّ التدريبات</a>
@endsection
