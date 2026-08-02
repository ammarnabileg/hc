@extends('layouts.app')

@php
    /**
     * ⭐ الواجهة العامّة للمنصّة (21.1 · 21.2-ب).
     *
     * صفحة **مفهرسة** بلا `noindex`: عنوان ووصف ميتا من `setting()`، ومعها
     * Schema.org من نوع `Organization` لتظهر نتيجةً غنيّة في محرّكات البحث.
     * وكلّ نصوصها من مجموعة `home.*` — فالأدمن يغيّرها بلا سطر كود (2.13).
     * تعمل للزائر بلا تسجيل، والمسجَّل يُحوَّل للوحته من الكنترولر.
     */
    $ogImage = (string) setting('home.og.image', '');
@endphp

@section('title', setting('home.meta_title', config('app.name').' — اتعلّم واطلع بشهادة'))
@section('meta_description', setting('home.meta_description', 'منصّة عربيّة للتعلّم والتطوّع: تدريبات ومسارات وشهادات معتمدة — والتسجيل والتفعيل مجّانيّان.'))

@if ($ogImage)
    @section('og_image', str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage))
@endif

@push('head')
    {{-- Schema.org — نتيجة غنيّة بلا مكتبة خارجيّة (21.2-ب) --}}
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <link rel="canonical" href="{{ url('/') }}">
@endpush

@section('content')
    @include('home.partials.nav')
    @include('home.partials.hero')
    @include('home.partials.value')
    @include('home.partials.free')
    @include('home.partials.learning')
    @include('home.partials.articles')
    @include('home.partials.events')
    @include('home.partials.ambassadors')
    @include('home.partials.cta')
    @include('home.partials.footer')

    @include('home.partials.surprise')
@endsection
