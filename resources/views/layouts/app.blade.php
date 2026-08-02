<!DOCTYPE html>
<html lang="ar" dir="rtl" @if(auth()->check() && auth()->user()->theme === 'light') data-theme="light" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', '')">

    {{-- صورة OG تُولَّد من استوديو الصور (21.1) --}}
    <meta property="og:title" content="@yield('title', config('app.name'))">
    <meta property="og:description" content="@yield('meta_description', '')">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif
    @hasSection('noindex')
        <meta name="robots" content="noindex">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen">

{{-- شريط تقدّم التمرير (2.10.1-25) --}}
<div class="scroll-progress" style="transform: scaleX(0)" data-scroll-progress></div>

@auth
    @include('partials.header')
@endauth

<div class="flex">
    @auth
        @include('partials.sidebar')
    @endauth

    <main class="flex-1 min-w-0 px-4 md:px-6 py-6 pb-24 md:pb-6">
        @if (session('status'))
            <x-toast :message="session('status')" />
        @endif

        @yield('content')
    </main>
</div>

{{-- الفعل الرئيسيّ على الموبايل: شريط سفليّ في متناول الإبهام (2.15-ج) --}}
@hasSection('mobile_action')
    <div class="md:hidden fixed inset-x-0 bottom-0 z-40 p-3" style="background: var(--surface); border-top: 1px solid var(--border)">
        @yield('mobile_action')
    </div>
@endif

@stack('modals')
@stack('scripts')
</body>
</html>
