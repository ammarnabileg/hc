<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
</body>
</html>
