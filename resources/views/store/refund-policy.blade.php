@extends('layouts.app')

@section('title', $title)
@section('meta_description', 'سياسة الاسترجاع المعتمَدة في المنصّة.')

@section('content')
    <x-page-header :title="$title"
                   subtitle="واضحة قبل الدفع — عشان محدّش يتفاجئ بعده."
                   :breadcrumbs="[['label' => 'المتجر', 'url' => route('store.index')], ['label' => $title]]" />

    {{-- النصّ يُدار بالكامل من لوحة الإدارة ويقبل HTML أو نصًّا عاديًّا (19.4 · 2.13-د) --}}
    <article class="card p-6 leading-8 text-sm max-w-3xl">
        {!! $body !!}
    </article>

    <p class="text-xs mt-4" style="color: var(--text-muted)">
        {{ setting('store.refund.alternative_text', 'البديل المعتمَد: رصيدك يفضل في محفظتك وتشتري بيه اللي تحبّه من الموقع.') }}
    </p>
@endsection
