{{--
    صفحة «بلا صلاحيّة» (403) — بديلٌ عن صفحة Laravel الافتراضيّة الخام.

    كلّ `abort(403, ...)` في المشروع (حارس الصلاحيّة · حارس لوحة الإدارة ·
    حارس القسم · إلخ) يمرّ من هنا تلقائيًّا — Laravel يحلّ
    `resources/views/errors/403.blade.php` لأيّ رفضٍ 403 بلا سباكةٍ إضافيّة.

    الصفحة مستقلّة عن كلّ ليَاوت (زيّ `maintenance/index.blade.php`): وقت
    الرفض قد يكون المستخدم غير مكتمل الجلسة أو السايد بار نفسه محلّ الرفض،
    فلا نُسقِط شاشة الاعتذار معه. والنمط **سطرٌ واحد + زرّ واحد** كحالة
    `components/empty.blade.php` (2.15-د) — تشجّع ولا تعاتب (2.17-ج).

    والرسالة عبر `setting()` (2.13) — والافتراضيّ نفسه نصّ حارس الصلاحيّة
    الحاليّ (`admin_roles.permission_guard.handle_msg`) حتى لا يتيه المستخدم
    برسالتين مختلفتين لنفس الرفض.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ setting('admin_roles.permission_guard.forbidden_page_title', 'بلا صلاحيّة') }}</title>
    {{-- الخطوط محلّيّة داخل حزمة Vite — **بلا أيّ نداء خارجيّ** (2.10.1-2) --}}
    @vite(['resources/css/app.css'])
    @include('partials.design-tokens')
</head>
<body class="min-h-screen flex items-center justify-center p-4">

@include('security.noscript')

<main class="w-full max-w-md">
    <div class="card p-8 text-center">
        <div class="mx-auto mb-4 flex items-center justify-center" aria-hidden="true"
             style="inline-size: 56px; block-size: 56px; border-radius: 9999px; background: var(--surface-sunken, var(--surface-raised))">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"
                 style="inline-size: 28px; block-size: 28px; color: var(--color-brand-500)">
                <rect x="4" y="10" width="16" height="10" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            </svg>
        </div>

        <p class="text-sm" style="color: var(--text-muted)">
            {{ setting('admin_roles.permission_guard.forbidden_page_message', 'ليس لديك صلاحيّة الوصول لهذه الصفحة.') }}
        </p>

        <a href="{{ Route::has('admin.dashboard') && request()->is('admin', 'admin/*') ? route('admin.dashboard') : (Route::has('dashboard') ? route('dashboard') : url('/')) }}"
           class="btn inline-flex items-center justify-center mt-4 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">
            {{ setting('admin_roles.permission_guard.forbidden_page_action', 'الرجوع للوحة الرئيسيّة') }}
        </a>
    </div>
</main>

</body>
</html>
