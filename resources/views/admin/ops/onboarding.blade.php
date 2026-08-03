@extends('layouts.admin')

@section('title', 'محتوى الـOnboarding')

@section('content')
    <x-page-header title="محتوى الـOnboarding"
                   subtitle="أوّل ما يشوفه المستخدم الجديد — والمعاينة هي الحكم."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'الإعدادات والنظام', 'url' => route('admin.settings.index')],
                       ['label' => 'محتوى الـOnboarding'],
                   ]">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد بارز، والباقي في «⋯» (2.15-أ-2) --}}
            @can('onboarding.create')
                <button type="button" data-modal-open="slide-form" data-slide-new
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">+ شريحة</button>
            @endcan

            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">⋯</summary>
                <div class="absolute end-0 mt-2 w-64 card p-2 z-20 text-sm space-y-1">
                    <a class="block px-2 py-1 rounded hover:opacity-80"
                       href="{{ route('admin.ops.onboarding.preview', ['screen' => $screen]) }}"><x-icon name="eye" size="16" /> معاينة كما يراها المستخدم</a>
                    @can('onboarding.create')
                        <form method="post" action="{{ route('admin.ops.onboarding.template') }}">
                            @csrf
                            <input type="hidden" name="screen" value="{{ $screen }}">
                            <button class="w-full text-start px-2 py-1 rounded hover:opacity-80"><x-icon name="game" size="16" /> استخدم القالب الجاهز</button>
                        </form>
                    @endcan
                    <a class="block px-2 py-1 rounded hover:opacity-80"
                       href="{{ route('admin.settings.index', ['tab' => 'onboarding']) }}"><x-icon name="settings" size="16" /> إعدادات الـOnboarding</a>
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    @if ($errors->any())
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-danger); color: var(--color-state-danger)">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- ثلاثة أرقام تكفي — والحدّ أربعة (2.15-أ-3) --}}
    <div class="grid gap-3 grid-cols-2 lg:grid-cols-3 mb-4">
        @foreach ($kpis as $kpi)
            <x-kpi :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon']" />
        @endforeach
    </div>

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'slides', 'label' => 'سلسلة المراحل', 'url' => route('admin.ops.onboarding', ['tab' => 'slides', 'screen' => $screen])],
        ['key' => 'first_time', 'label' => 'شاشة أوّل مرّة', 'url' => route('admin.ops.onboarding', ['tab' => 'first_time', 'screen' => $screen])],
    ]" />

    @if ($tab === 'slides')
        @include('admin.ops.partials.onboarding-slides')
    @else
        @include('admin.ops.partials.onboarding-first-time')
    @endif

    @include('admin.ops.partials.onboarding-form')
@endsection

@section('mobile_action')
    @can('onboarding.create')
        <button type="button" data-modal-open="slide-form" data-slide-new
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">+ شريحة</button>
    @endcan
@endsection
