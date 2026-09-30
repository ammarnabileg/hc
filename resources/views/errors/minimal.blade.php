{{--
    القالب المشترك لصفحات الأخطاء (403 · 404 · 419 · 429 · 500 · 503) — بديلٌ عن
    قالب Laravel الخام (`errors::minimal`) الذي كان يرسم 404 و419 وغيرها بالإنجليزيّة
    وبلا نظام تصميم، بينما 403 وحدها كانت مصمَّمة.

    مستقلّ عن كلّ ليَاوت: وقت الخطأ قد تكون الجلسة ناقصة أو السايد بار نفسه
    محلّ الخلل، فلا نُسقِط شاشة الاعتذار معه. والنمط **سطرٌ واحد + زرّ واحد**
    كحالة `components/empty.blade.php` (2.15-د) — تشجّع ولا تعاتب (2.17-ج).
    وكلّ النصوص عبر `setting()` (2.13).
--}}
<!DOCTYPE html>
@php
    // 404 و405 تُرسَم قبل وسطاء الجلسة، فلا مستخدمَ مصادَقًا هنا غالبًا؛ الكوكي (غير المشفّر) هو المرآة
    $errorTheme = auth()->check() ? auth()->user()->theme : request()->cookie('theme');
@endphp
<html lang="ar" dir="rtl" @if ($errorTheme === 'dark') data-theme="dark" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex">
    <title>@yield('title')</title>
    {{-- الخطوط محلّيّة داخل حزمة Vite — **بلا أيّ نداء خارجيّ** (2.10.1-2) --}}
    @vite(['resources/css/app.css'])
    @include('partials.design-tokens')
    @include('partials.head-icons')
</head>
<body class="min-h-screen flex items-center justify-center p-4">

@include('security.noscript')

<main class="w-full max-w-md">
    <div class="card p-8 text-center">
        <div class="mx-auto mb-4 flex items-center justify-center" aria-hidden="true"
             style="inline-size: 56px; block-size: 56px; border-radius: 9999px; background: var(--surface-sunken, var(--surface-raised)); color: var(--color-brand-500)">
            <x-icon :name="$icon ?? 'warning'" size="28" />
        </div>

        @hasSection('code')
            <div class="text-xs font-semibold mb-2" style="color: var(--text-muted); letter-spacing: .08em">@yield('code')</div>
        @endif

        <p class="text-sm" style="color: var(--text-muted)">@yield('message')</p>

        <a href="@yield('action_url')"
           class="btn btn-p inline-flex items-center justify-center mt-4 rounded-xl px-4 py-2 text-sm font-semibold motion-standard">
            @yield('action_label')
        </a>
    </div>
</main>

</body>
</html>
