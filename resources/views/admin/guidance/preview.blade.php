@extends('layouts.admin')

@section('title', setting('admin.guidance.preview.maayna_almnshwr', 'معاينة المنشور'))

@php
    /**
     * معاينة على الأجهزة قبل النشر (12.6-أ · 24.3): موبايل ⇄ ديسكتوب.
     *
     * المعاينة تمرّ بنفس بطاقة الفيد الحقيقيّة — بالتخصيص الديناميكيّ وبالاستطلاع
     * كما يراه القارئ — فلا يعاين الأدمن شيئًا غير الذي سيُنشَر.
     */
    $isMobile = $device === 'mobile';
@endphp

@section('content')
    <x-page-header
        :title="setting('admin.guidance.preview.maayna', 'معاينة: ').$raw->title"
        :subtitle="setting('admin.guidance.preview.shwf_almnshwr_bayn_alqary_qbl_ma_tnshrh', 'شوف المنشور بعين القارئ قبل ما تنشره.')"
        :breadcrumbs="[
            ['label' => setting('admin.guidance.preview.altalymat', 'التعليمات'), 'url' => route('admin.guidance.index')],
            ['label' => setting('admin.guidance.preview.maayna_2', 'معاينة')],
        ]">
        <x-slot:action>
            <a href="{{ route('admin.guidance.index') }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.preview.rjwa_llthryr', 'رجوع للتحرير') }}</a>
        </x-slot:action>
    </x-page-header>

    {{-- تبديل الجهاز كرقائق — واللون لا يحمل المعنى وحده (2.16) --}}
    <div class="flex items-center gap-2 mb-4" role="group" aria-label="{{ setting('admin.guidance.preview.jhaz_almaayna', 'جهاز المعاينة') }}">
        @foreach ($devices as $key => $label)
            <a href="{{ route('admin.guidance.preview', ['announcement' => $raw, 'device' => $key]) }}"
               aria-current="{{ $device === $key ? 'true' : 'false' }}"
               class="rounded-xl px-4 py-2 text-sm motion-standard"
               style="{{ $device === $key
                    ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                    : 'background: var(--surface-raised); color: var(--text)' }}">
                {{ $device === $key ? '● ' : '○ ' }}{{ $label }}
            </a>
        @endforeach
    </div>

    <div class="flex justify-center">
        <div class="w-full"
             @style([
                 'max-width: '.$width.'px' => $isMobile,
                 'border: 1px solid var(--border)',
                 'border-radius: '.($isMobile ? '1.5rem' : '1rem'),
                 'padding: '.($isMobile ? '0.75rem' : '1.25rem'),
                 'background: var(--surface-sunken)',
             ])>
            @if ($isMobile)
                <div class="mx-auto mb-3 rounded-full" style="width: 4rem; height: 0.3rem; background: var(--border)"></div>
            @endif

            @include('announcements.partials.card', [
                'announcement' => $announcement,
                'read' => null,
                'poll' => $poll,
                'counts' => [],
                'reactions' => (array) setting('announcements.reactions.allowed', ['👍']),
                'ackLabel' => setting('announcements.acknowledge.label', 'قرأتُ وفهمت'),
                'preview' => true,
            ])
        </div>
    </div>

    {{-- الوسوم المتاحة للتخصيص الديناميكيّ — تفسّر ما تراه في المعاينة (12.6-أ) --}}
    <div class="card p-4 mt-4">
        <div class="text-sm font-semibold">{{ setting('admin.guidance.preview.wswm_altkhsys_aldynamyky', 'وسوم التخصيص الديناميكيّ') }}</div>
        <p class="text-xs mt-1" style="color: var(--text-muted)">
            {{ setting('admin.guidance.preview.aktbha_fy_alns_wkl_qary_hyshwf_byanath_hw', 'اكتبها في النصّ، وكلّ قارئ هيشوف بياناته هو. والوسم اللي مالوش قيمة بيتبدّل ببديل مهذّب — محدّش هيقرا وسمًا خامًا.') }}
        </p>
        <ul class="mt-2 grid gap-1 text-xs md:grid-cols-2">
            @foreach ($tokens as $token => $meaning)
                <li><code>{{ $token }}</code> — {{ $meaning }}</li>
            @endforeach
        </ul>
    </div>
@endsection

@section('mobile_action')
    <a href="{{ route('admin.guidance.index') }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.preview.rjwa_llthryr', 'رجوع للتحرير') }}</a>
@endsection
