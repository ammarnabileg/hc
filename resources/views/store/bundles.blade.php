@extends('layouts.app')

@section('title', 'الباقات')

@section('content')
    <x-page-header title="الباقات"
                   subtitle="عناصر مجمّعة بسعر واحد — والتوفير مكتوب بقيمته الحقيقيّة."
                   :breadcrumbs="[['label' => 'المتجر', 'url' => route('store.index')], ['label' => 'الباقات']]">
        <x-slot:action>
            @include('store.partials.balance', ['balance' => $balance])
        </x-slot:action>
    </x-page-header>

    @include('store.partials.filters', [
        'action' => route('store.bundles'),
        'filters' => $filters,
        'priceCeiling' => $priceCeiling,
    ])

    @if ($cards->isEmpty())
        <x-empty :message="setting('store.bundles.empty_text', 'مفيش باقات متاحة دلوقتي.')"
                 action="اتفرّج على المتجر"
                 :href="route('store.index')" />
    @else
        <p class="text-xs mb-3" style="color: var(--text-muted)">{{ $total }} باقة</p>

        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" data-store-bundles-results>
            @include('store.partials.cards', ['cards' => $cards])
        </div>

        {{-- تمرير تدريجيّ بلا ترقيم صفحات (13.1 · قرار §25) --}}
        @include('partials.load-more', [
            'hasMore' => $hasMore,
            'moreUrl' => route('store.bundles.more', array_merge(request()->except('offset'), ['offset' => $nextOffset])),
            'nextOffset' => $nextOffset,
            'pageSize' => $pageSize,
            'targetSelector' => '[data-store-bundles-results]',
        ])
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('store.index') }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">ارجع للمتجر</a>
@endsection
