{{-- ⛔ لا توجّل للأنيميشن ولا سمة تُطفئه — «الأنيميشن حاضر دائمًا لأنّه روح
     المنصّة» (2.3 · 2.14-ب)، ولا `prefers-reduced-motion` بديلًا عنه فهو
     مرفوضٌ بالاسم في 2.3. الحركة هنا **لا تُطفأ**. --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl" @if(auth()->check() && auth()->user()->theme === 'dark') data-theme="dark" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', (string) setting('ux.layout_volunteer.yield_1', 'لوحة التطوّع'))</title>
    <meta name="robots" content="noindex">

    {{-- الخطوط محلّيّة داخل حزمة Vite — **بلا أيّ نداء خارجيّ** (2.10.1-2) --}}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.design-tokens')
    @stack('head')
</head>
<body class="min-h-screen @hasSection('mobile_action') has-mobile-action @endif">

{{-- شريط تقدّم التمرير (2.10.1-25) --}}
<div class="scroll-progress" style="transform: scaleX(0)" data-scroll-progress></div>

{{--
 | الهيدر المشترك كما هو: فيه مبدّل سياق العضويّة والجرس بتاباته (2.8 · 13.4-ح)
 | — ولا يُعاد بناؤه هنا.
--}}
{{-- السايد بار عمودٌ بطول الشاشة والـTopbar داخل عمود المحتوى — كما في المرجع (`#sidebar` · `#topbar`) --}}
<div class="flex">
    @auth
        @include('partials.sidebar-volunteer')
    @endauth

    <div class="flex-1 min-w-0 flex flex-col">
        @auth
            @include('partials.header')
        @endauth

        <main id="content" class="flex-1 min-w-0">
            @if (session('status'))
                <x-toast :message="session('status')" />
            @endif

            @if (session('error'))
                <x-toast :message="session('error')" state="danger" />
            @endif

            @if ($errors->any())
                {{-- رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب) --}}
                <x-toast :message="$errors->first()" state="danger" />
            @endif

            @yield('content')
        </main>
    </div>
</div>

{{-- الفعل الرئيسيّ على الموبايل: شريط سفليّ في متناول الإبهام (2.15-ج) --}}
@hasSection('mobile_action')
    <div id="mobile-primary">
        @yield('mobile_action')
    </div>
@endif

@stack('modals')
{{-- العناصر العائمة في كلّ الصفحات: سهم العودة لأعلى + الرسائل الإيجابيّة (2.6) --}}
@include('partials.floating')

@auth
    {{-- البحث الموحّد (Ctrl+K) والتراجع خلال ثوانٍ — على كلّ الشاشات (2.15-د) --}}
    <x-command-palette />
    <x-undo-toast />
    <x-first-run />
@endauth

@stack('scripts')
</body>
</html>
