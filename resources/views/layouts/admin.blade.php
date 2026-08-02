<!DOCTYPE html>
<html lang="ar" dir="rtl" @if(auth()->check() && auth()->user()->theme === 'light') data-theme="light" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'لوحة الإدارة')</title>
    {{-- لوحة الإدارة لا تُفهرَس أبدًا --}}
    <meta name="robots" content="noindex">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen">

<div class="scroll-progress" style="transform: scaleX(0)" data-scroll-progress></div>

@include('partials.header')

<div class="flex">
    @include('partials.sidebar-admin')

    <main class="flex-1 min-w-0 px-4 md:px-6 py-6 pb-24 md:pb-6">
        @if (session('status'))
            <x-toast :message="session('status')" />
        @endif

        {{-- رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب) --}}
        @if (session('problem'))
            <x-toast :message="session('problem')" state="danger" />
        @endif

        @if ($errors->any())
            <div class="card p-4 mb-4" role="alert"
                 style="border-color: var(--color-state-danger)">
                <div class="flex items-center gap-2 mb-2">
                    <x-state-badge state="danger" label="مش هينفع نحفظ" />
                </div>
                <ul class="text-sm space-y-1" style="color: var(--text-muted)">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>

{{-- الفعل الرئيسيّ على الموبايل في متناول الإبهام (2.15-ج) --}}
@hasSection('mobile_action')
    <div class="md:hidden fixed inset-x-0 bottom-0 z-40 p-3" style="background: var(--surface); border-top: 1px solid var(--border)">
        @yield('mobile_action')
    </div>
@endif

@stack('modals')

<script>
    /* آخر تغيير على الصلاحيّة يظهر بالـHover بتأخير قصير مع رابط لبروفايل المحرّر (12.2.1-ز-4) */
    (function () {
        const delay = Number({{ (int) setting('admin.roles.audit_hover_delay_ms', 200) }}) || 200;

        document.querySelectorAll('[data-audit-hover]').forEach((host) => {
            const tip = host.querySelector('[data-audit-tip]');
            if (!tip) return;

            let timer = null;
            const show = () => { timer = setTimeout(() => tip.classList.remove('hidden'), delay); };
            const hide = () => { clearTimeout(timer); tip.classList.add('hidden'); };

            host.addEventListener('mouseenter', show);
            host.addEventListener('mouseleave', hide);
            host.addEventListener('focusin', show);
            host.addEventListener('focusout', hide);
        });
    })();

    /* الإجراء الجماعيّ يظهر عند الاختيار فقط ومخفيّ تمامًا قبله (2.15-ب) */
    (function () {
        document.querySelectorAll('[data-bulk-scope]').forEach((scope) => {
            const bar = scope.querySelector('[data-bulk-bar]');
            const counter = scope.querySelector('[data-bulk-count]');
            const master = scope.querySelector('[data-bulk-master]');
            const boxes = () => Array.from(scope.querySelectorAll('[data-bulk-item]'));
            if (!bar) return;

            const sync = () => {
                const picked = boxes().filter((b) => b.checked).length;
                bar.classList.toggle('hidden', picked === 0);
                if (counter) counter.textContent = picked;
            };

            scope.addEventListener('change', (e) => {
                if (e.target === master) boxes().forEach((b) => { b.checked = master.checked; });
                sync();
            });

            sync();
        });
    })();

    /* بحث فوريّ داخل مصفوفة الصلاحيّات — شرط قبول الشاشة (12.2.1-ط) */
    (function () {
        const field = document.querySelector('[data-perm-search]');
        if (!field) return;

        const rows = Array.from(document.querySelectorAll('[data-perm-row]'));
        const empty = document.querySelector('[data-perm-empty]');

        field.addEventListener('input', () => {
            const q = field.value.trim().toLowerCase();
            let shown = 0;

            rows.forEach((row) => {
                const hit = !q || row.dataset.permRow.toLowerCase().includes(q);
                row.classList.toggle('hidden', !hit);
                if (hit) shown += 1;
            });

            if (empty) empty.classList.toggle('hidden', shown > 0);
        });
    })();
</script>

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
