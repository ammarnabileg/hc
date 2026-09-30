<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- المخطّط اللونيّ حرفيًّا من المرجع، ولون إطار المتصفّح على الموبايل يتبع مظهر المستخدم --}}
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="{{ auth()->check() && auth()->user()->theme === 'dark' ? '#191917' : '#fcfbf8' }}" data-theme-color-light="#fcfbf8" data-theme-color-dark="#191917">
    <title>@yield('title', config('app.name'))</title>
    {{-- الخطوط محلّيّة داخل حزمة Vite — **بلا أيّ نداء خارجيّ** (2.10.1-2) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.design-tokens')
    @include('partials.script-texts')
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    {{-- التحسين التدريجيّ: رسالة وخطوات تفعيل الجافاسكربت (2.1) --}}
    @include('security.noscript')

    {{-- شريط تقدّم التمرير — ثابتٌ على كلّ الصفحات بلا استثناء (2.10.1-25) --}}
    <div class="scroll-progress" style="transform: scaleX(0)" data-scroll-progress aria-hidden="true"></div>

    @php
        /*
         | اللافتة العلويّة لخطأٍ لا حقلَ له في الصفحة وحده: حقلٌ رسمه x-form.input يحمل
         | خطأه تحته (بمعرّف <name>-error)، فكانت الرسالة نفسها تظهر مرّتين — لافتةً في
         | الأعلى وسطرًا تحت الحقل (2.15-أ-8: سطر واحد). المحتوى مرسومٌ قبل اللياوت،
         | فنقرأه ونستثني ما ظهر فيه.
         */
        $renderedContent = $__env->yieldContent('content');
        $bannerError = collect($errors->keys())
            ->first(fn ($key) => ! str_contains($renderedContent, 'id="'.preg_replace('/[^a-zA-Z0-9_-]/', '-', $key).'-error"'));
    @endphp
    @if ($bannerError !== null)
        <div class="fixed top-4 inset-x-4 md:inset-x-auto md:w-96 md:mx-auto card p-3 text-sm" role="alert"
             style="border-color: var(--color-state-danger)">
            {{ $errors->first($bannerError) }}
        </div>
    @endif
    {!! $renderedContent !!}

    {{-- سهم العودة لأعلى في **كلّ الصفحات** — بلا استثناء (2.6-أ) --}}
    @include('partials.floating')

    {{-- الموافقة والبكسل على صفحات الدخول والتسجيل كذلك — «بدأ التسجيل» يقع هنا (21.3-أ/د) --}}
    @include('partials.consent-banner')
</body>
</html>
