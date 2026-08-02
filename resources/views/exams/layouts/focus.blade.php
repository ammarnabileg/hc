<!DOCTYPE html>
<html lang="ar" dir="rtl" @if(auth()->check() && auth()->user()->theme === 'light') data-theme="light" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', config('app.name'))</title>

    {{-- شاشة تركيز: بلا سايد بار وبلا هيدر وبلا أيّ خطّ أو مكتبة خارجيّة (24.5) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen">

<header class="sticky top-0 z-40 px-4 md:px-6 py-3"
        style="background: var(--surface); border-bottom: 1px solid var(--border)">
    <div class="max-w-3xl mx-auto flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="font-extrabold truncate">@yield('exam_title')</h1>
            <p class="text-xs mt-0.5" style="color: var(--text-muted)">@yield('exam_meta')</p>
        </div>
        @yield('exam_timer')
    </div>
</header>

<main class="max-w-3xl mx-auto px-4 md:px-6 py-6 pb-28">
    @if (session('status'))
        <x-toast :message="session('status')" />
    @endif

    @yield('content')
</main>

{{-- «شغلك محفوظ» عند انقطاع الشبكة (2.17-ب) — يظهر بالجافاسكربت وقت الحاجة فقط --}}
<div id="network-banner" class="fixed inset-x-0 bottom-0 z-50 hidden p-3">
    <div class="card p-3 max-w-3xl mx-auto flex items-center gap-2 text-sm">
        <x-state-badge state="warn" label="" />
        <span>{{ setting('exams.messages.offline', 'الشبكة اتقطعت — إجاباتك محفوظة، وهنكمّل من مكانك أوّل ما ترجع.') }}</span>
    </div>
</div>

@stack('modals')
@stack('scripts')
</body>
</html>
