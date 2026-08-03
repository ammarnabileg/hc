{{-- ⛔ لا توجّل للأنيميشن ولا سمة تُطفئه — «الأنيميشن حاضر دائمًا لأنّه روح
     المنصّة» (2.3 · 2.14-ب)، ولا `prefers-reduced-motion` بديلًا عنه فهو
     مرفوضٌ بالاسم في 2.3. الحركة هنا **لا تُطفأ**. --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl" @if(auth()->check() && auth()->user()->theme === 'light') data-theme="light" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'لوحة التطوّع')</title>
    <meta name="robots" content="noindex">

    {{-- الخطوط محلّيّة داخل حزمة Vite — **بلا أيّ نداء خارجيّ** (2.10.1-2) --}}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.design-tokens')
    @stack('head')
</head>
<body class="min-h-screen">

{{-- شريط تقدّم التمرير (2.10.1-25) --}}
<div class="scroll-progress" style="transform: scaleX(0)" data-scroll-progress></div>

{{--
 | الهيدر المشترك كما هو: فيه مبدّل سياق العضويّة والجرس بتاباته (2.8 · 13.4-ح)
 | — ولا يُعاد بناؤه هنا.
--}}
@auth
    @include('partials.header')
@endauth

<div class="flex">
    @auth
        @include('partials.sidebar-volunteer')
    @endauth

    <main class="flex-1 min-w-0 px-4 md:px-6 py-6 pb-24 md:pb-6">
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

{{-- الفعل الرئيسيّ على الموبايل: شريط سفليّ في متناول الإبهام (2.15-ج) --}}
@hasSection('mobile_action')
    <div class="md:hidden fixed inset-x-0 bottom-0 z-40 p-3" style="background: var(--surface); border-top: 1px solid var(--border)">
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
