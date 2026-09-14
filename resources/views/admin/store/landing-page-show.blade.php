@extends('layouts.admin')

{{--
    ⭐ معاينة الأدمن لصفحة الهبوط — `landing_pages.view` (راجع تعليق
    `LandingPageController` لشرح لماذا هذه الصلاحيّة إداريّة هنا فقط، لا على
    مسار الزائر العامّ). قراءةٌ فقط: لا فورم ولا فعل — التعديل من شاشته هو.
--}}

@section('title', $landingPage->title())

@section('content')
    <x-page-header :title="$landingPage->title()"
                   :subtitle="$landingPage->landingable->name_ar ?? ''"
                   :breadcrumbs="[
                       ['label' => setting('store.breadcrumb_label', 'المتجر'), 'url' => route('admin.store.index')],
                       ['label' => setting('admin.landing_pages.show.myana', 'معاينة صفحة هبوط')],
                   ]">
        <x-slot:action>
            @can('landing_pages.edit')
                <a href="{{ route('admin.store.landing-pages.edit', $landingPage) }}"
                   class="btn inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.landing_pages.show.tadyl', 'تعديل') }}</a>
            @endcan
        </x-slot:action>
    </x-page-header>

    <div class="card p-4 space-y-3 text-sm">
        <div class="flex items-center gap-2">
            <x-state-badge :state="$landingPage->status === 'published' ? 'ok' : ($landingPage->status === 'archived' ? 'muted' : 'idle')"
                           :label="[
                               'draft' => setting('admin.landing_pages.show.mswda', 'مسوّدة'),
                               'published' => setting('admin.landing_pages.show.mnshwra', 'منشورة'),
                               'archived' => setting('admin.landing_pages.show.mwrshfa', 'مؤرشفة'),
                           ][$landingPage->status] ?? $landingPage->status" />

            @if ($landingPage->isPublished())
                <a href="{{ route('landing-pages.show', $landingPage->slug) }}" target="_blank" rel="noopener" class="text-xs underline">{{ setting('admin.landing_pages.show.alrabt_alaam', 'الرابط العامّ ↗') }}</a>
            @endif
        </div>

        <p><strong>{{ setting('admin.landing_pages.show.mrbwta_b', 'مربوطة بـ:') }}</strong> {{ $landingPage->landingable->name_ar ?? '—' }}</p>
        <p><strong>{{ setting('admin.landing_pages.show.alwad', 'الوعد:') }}</strong> {{ $landingPage->promise() ?: '—' }}</p>

        @if (! empty($landingPage->outcomes))
            <div>
                <strong>{{ setting('admin.landing_pages.show.alntayj', 'النتائج:') }}</strong>
                <ul class="list-disc ms-5 mt-1">
                    @foreach ($landingPage->outcomes as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($landingPage->faq))
            <div>
                <strong>{{ setting('admin.landing_pages.show.alasela', 'الأسئلة:') }}</strong>
                <ul class="list-disc ms-5 mt-1">
                    @foreach ($landingPage->faq as $row)
                        <li>{{ $row['q'] ?? '' }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection
