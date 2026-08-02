@extends('layouts.app')

@section('title', 'المتجر')

@section('content')
    <x-page-header title="المتجر"
                   subtitle="اختار اللي يفيدك — والأسعار كلّها بالكوينز."
                   :breadcrumbs="[['label' => 'الرئيسيّة', 'url' => \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : '/'], ['label' => 'المتجر']]">
        <x-slot:action>
            @include('store.partials.balance', ['balance' => $balance])
        </x-slot:action>
    </x-page-header>

    @include('store.partials.filters', [
        'action' => route('store.index'),
        'filters' => $filters,
        'categories' => $categories,
        'typeOptions' => $typeOptions,
        'priceCeiling' => $priceCeiling,
    ])

    @if ($cards->isEmpty())
        <x-empty :message="setting('store.empty.text', 'مفيش نتائج للفلتر ده — جرّب توسّع شويّة.')"
                 action="اعرض كلّ المتجر"
                 :href="route('store.index')" />
    @else
        <p class="text-xs mb-3" style="color: var(--text-muted)">{{ $total }} عنصر</p>

        {{-- شبكة واحدة لكلّ الأنواع (17) — ومرنة على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($cards as $card)
                @include('store.partials.card', ['card' => $card])
            @endforeach
        </div>

        <div class="mt-6">{{ $cards->links() }}</div>
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('store.bundles') }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">شوف الباقات</a>
@endsection
