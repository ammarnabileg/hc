@extends('layouts.app')

@section('title', setting('store.index.page_title', 'المتجر'))

@section('content')
    <x-page-header :title="setting('store.index.page_title', 'المتجر')"
                   :subtitle="setting('store.index.page_subtitle', 'اختار اللي يفيدك — والأسعار كلّها بالكوينز.')"
                   :breadcrumbs="[['label' => setting('store.home_breadcrumb_label', 'الرئيسيّة'), 'url' => \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : '/'], ['label' => setting('store.breadcrumb_label', 'المتجر')]]">
        <x-slot:action>
            @include('store.partials.balance', ['balance' => $balance])
        </x-slot:action>
    </x-page-header>

    @include('store.partials.filters', [
        'action' => route('store.index'),
        'filters' => $filters,
        'categories' => $categories,
        'typeOptions' => $typeOptions,
        'currencyOptions' => $currencyOptions,
        'rangeCurrency' => $rangeCurrency,
        'priceCeiling' => $priceCeiling,
    ])

    @if ($cards->isEmpty())
        <x-empty :message="setting('store.empty.text', 'مفيش نتائج للفلتر ده — جرّب توسّع شويّة.')"
                 :action="setting('store.index.empty_action_label', 'اعرض كلّ المتجر')"
                 :href="route('store.index')" />
    @else
        <p class="text-xs mb-3" style="color: var(--text-muted)">{{ $total }} {{ setting('store.index.results_unit_label', 'عنصر') }}</p>

        {{-- شبكة واحدة لكلّ الأنواع (17) — ومرنة على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-store-results>
            @include('store.partials.cards', ['cards' => $cards])
        </div>

        {{-- تمرير تدريجيّ بلا ترقيم صفحات (13.1 · قرار §25) --}}
        @include('partials.load-more', [
            'hasMore' => $hasMore,
            'moreUrl' => route('store.index.more', array_merge(request()->except('offset'), ['offset' => $nextOffset])),
            'nextOffset' => $nextOffset,
            'pageSize' => $pageSize,
            'targetSelector' => '[data-store-results]',
        ])
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('store.bundles') }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">{{ setting('store.bundles_link_label', 'شوف الباقات') }}</a>
@endsection
