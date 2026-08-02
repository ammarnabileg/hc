@extends('layouts.app')

@section('title', 'التلعيب والتحديات')

@section('content')
    <x-page-header
        title="التلعيب والتحديات"
        subtitle="اقتصاد XP والتذاكر والشارات والستريكس والليدر بورد والحروب والاحتفالات — كلّه إعدادات."
        :breadcrumbs="[['label' => 'لوحة الإدارة', 'url' => url('/admin')], ['label' => 'التلعيب']]" />

    {{-- تابات داخليّة تُحمَّل كسولًا: التاب المفتوح وحده يجهّز بياناته (2.15-د) --}}
    <div class="sticky-bar -mx-4 md:mx-0 px-4 md:px-0 py-2 mb-4" style="background: var(--surface)">
        <div class="flex gap-2 overflow-x-auto no-scrollbar">
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
