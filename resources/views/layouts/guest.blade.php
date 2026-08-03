<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    {{-- الخطوط محلّيّة داخل حزمة Vite — **بلا أيّ نداء خارجيّ** (2.10.1-2) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.design-tokens')
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    {{-- التحسين التدريجيّ: رسالة وخطوات تفعيل الجافاسكربت (2.1) --}}
    @include('security.noscript')

    @if ($errors->any())
        <div class="fixed top-4 inset-x-4 md:inset-x-auto md:w-96 md:mx-auto card p-3 text-sm"
             style="border-color: var(--color-state-danger)">
            {{ $errors->first() }}
        </div>
    @endif
    @yield('content')

    {{-- سهم العودة لأعلى في **كلّ الصفحات** — بلا استثناء (2.6-أ) --}}
    @include('partials.floating')

    {{-- الموافقة والبكسل على صفحات الدخول والتسجيل كذلك — «بدأ التسجيل» يقع هنا (21.3-أ/د) --}}
    @include('partials.consent-banner')
</body>
</html>
