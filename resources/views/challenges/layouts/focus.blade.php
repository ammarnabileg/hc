<!DOCTYPE html>
<html lang="ar" dir="rtl" @if(auth()->check() && auth()->user()->theme === 'light') data-theme="light" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'التحدّي')</title>
    <meta name="robots" content="noindex">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
  شاشة تركيز: بلا سايد بار وبلا أزرار تشتيت (24.5) — وتملأ الشاشة على الموبايل.
--}}
<body class="min-h-screen flex flex-col" style="background: var(--surface)">
    @yield('content')
    @stack('scripts')
</body>
</html>
