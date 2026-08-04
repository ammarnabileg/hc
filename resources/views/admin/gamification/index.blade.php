@extends('layouts.admin')

@section('title', setting('admin.gamification.index.altlayb_walthdyat', 'التلعيب والتحديات'))

@section('content')
    <x-page-header
        :title="setting('admin.gamification.index.altlayb_walthdyat', 'التلعيب والتحديات')"
        :subtitle="setting('admin.gamification.index.aqtsad_xp_waltdhakr_walsharat_walstryks', 'اقتصاد XP والتذاكر والشارات والستريكس والليدر بورد والحروب والاحتفالات — كلّه إعدادات.')"
        :breadcrumbs="[['label' => setting('admin.gamification.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')], ['label' => setting('admin.gamification.index.altlayb', 'التلعيب')]]" />

    {{-- تابات داخليّة تُحمَّل كسولًا: التاب المفتوح وحده يجهّز بياناته (2.15-د) --}}
    <div class="sticky-bar -mx-4 md:mx-0 px-4 md:px-0 py-2 mb-4" style="background: var(--surface)">
        <div class="flex gap-2 min-w-0 overflow-x-auto no-scrollbar">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.gamification.index', ['tab' => $key]) }}"
                   class="shrink-0 rounded-full px-4 py-2 text-sm motion-standard"
                   style="{{ $tab === $key
                        ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                        : 'background: var(--surface-raised); color: var(--text)' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @include('admin.gamification.tabs.'.$tab)
@endsection
