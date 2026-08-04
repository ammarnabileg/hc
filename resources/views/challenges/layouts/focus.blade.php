{{-- ⛔ لا توجّل للأنيميشن ولا سمة تُطفئه — «الأنيميشن حاضر دائمًا لأنّه روح
     المنصّة» (2.3 · 2.14-ب)، ولا `prefers-reduced-motion` بديلًا عنه فهو
     مرفوضٌ بالاسم في 2.3. الحركة هنا **لا تُطفأ**. --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl" @if(auth()->check() && auth()->user()->theme === 'light') data-theme="light" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', setting('challenges.focus_layout.default_title', 'التحدّي'))</title>
    <meta name="robots" content="noindex">

    {{-- الخطوط محلّيّة داخل حزمة Vite — **بلا أيّ نداء خارجيّ** (2.10.1-2) --}}

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
