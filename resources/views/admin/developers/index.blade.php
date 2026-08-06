@extends('layouts.admin')

@section('title', setting('developers.admin.page_title', 'المطوّرين'))

@section('content')
    <x-page-header
        :title="setting('developers.admin.page_title', 'المطوّرين')"
        :subtitle="setting('developers.admin.page_subtitle', 'مفاتيح الربط بين المنصّة والمواقع والأنظمة الخارجيّة.')"
        :breadcrumbs="[['label' => setting('nav.admin.panel_title', 'لوحة الإدارة'), 'url' => url('/admin')], ['label' => setting('developers.admin.page_title', 'المطوّرين')]]" />

    {{-- تابات داخليّة: API مبنيّ كاملًا الآن، وWebhooks سقالته فقط (12.15-ب) --}}
    <div class="sticky-bar -mx-4 md:mx-0 px-4 md:px-0 py-2 mb-4" style="background: var(--surface)">
        <div class="flex gap-2 min-w-0 overflow-x-auto no-scrollbar">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.developers.index', ['tab' => $key]) }}"
                   class="shrink-0 rounded-full px-4 py-2 text-sm motion-standard"
                   style="{{ $tab === $key
                        ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                        : 'background: var(--surface-raised); color: var(--text)' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @include('admin.developers.tabs.'.$tab)
@endsection
