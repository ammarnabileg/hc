{{--
    صفحة الاحتواء (12.1-متقدّم-1): **«تم حظر الحساب»** — ثابتة، بلا ليَاوت ولا سايد بار.

    نصّ الدستور حرفيًّا: صفحة ثابتة مكتوب فيها «تم حظر الحساب»، وتحتها «إذا كنت
    تعتقد أنه بالخطأ رجاء التواصل مع دعم المنصة»، وتحتها **كارت «رسالة إدارية»**
    يعرض الرسالة لو الأدمن أضافها.

    ولماذا صفحة مستقلّة لا بانل داخل الليَاوت؟ لأنّ الليَاوت نفسه بيحمّل سايد بار
    وعدّادات وإشعارات — وكلّها تصفُّحٌ للمنصّة، والمحظور لا يتصفّح.

    والتعليق المؤقّت نفس الصفحة بنبرة أهدأ ووقت عودةٍ معلَن — لأنّه **ينتهي وحده**.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center p-4">

@include('security.noscript')

<main class="w-full max-w-md text-center animate-fadeup">

    {{-- أيقونة مرسومة بهويّة المنصّة — ولا مكتبة أيقونات (2.15) --}}
    <div class="mx-auto mb-5 flex items-center justify-center" aria-hidden="true"
         style="inline-size: 88px; block-size: 88px">
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round"
             style="inline-size: 100%; block-size: 100%; color: {{ $banned ? 'var(--color-state-danger)' : 'var(--color-state-warn)' }}">
            <rect x="10" y="21" width="28" height="19" rx="4" />
            <path d="M16 21v-6a8 8 0 0 1 16 0v6" />
            @if ($banned)
                <path d="M20 27l8 8M28 27l-8 8" />
            @else
                <path d="M24 28v5" />
            @endif
        </svg>
    </div>

    {{-- ⭐ الرمز مع اللون دائمًا — اللون وحده لا يحمل المعنى (2.16) --}}
    <p class="text-xs font-bold mb-2" style="color: {{ $banned ? 'var(--color-state-danger)' : 'var(--color-state-warn)' }}">
        <span aria-hidden="true">{{ $banned ? setting('ux.state.danger.icon', '◉') : setting('ux.state.warn.icon', '▲') }}</span>
        {{ $banned ? setting('ux.state.danger.label', 'خطر') : setting('ux.state.warn.label', 'انتبه') }}
    </p>

    <h1 class="text-2xl font-extrabold mb-2">{{ $title }}</h1>

    <p class="text-sm mb-5" style="color: var(--text-muted)">{{ $support }}</p>

    {{-- متى يرجع الحساب وحده — التعليق بمدّة تنتهي بلا أيّ تدخّل (12.1-متقدّم-3) --}}
    @if ($until)
        <p class="text-sm mb-5">
            {{ str_replace(
                '{date}',
                $until->translatedFormat('Y-m-d H:i'),
                setting('account.containment.returns_at', 'الحساب بيرجع لوحده يوم {date} — مش محتاج تعمل حاجة.'),
            ) }}
        </p>
    @endif

    {{-- كارت «رسالة إداريّة»: لا يظهر إلّا لو الأدمن كتب رسالة فعلًا (12.1-متقدّم-1) --}}
    @if (trim($message) !== '')
        <section class="card p-4 text-start mb-5" style="border-color: var(--color-state-warn)">
            <h2 class="text-xs font-bold mb-2" style="color: var(--text-muted)">{{ $noticeTitle }}</h2>
            <p class="text-sm">{{ $message }}</p>
        </section>
    @endif

    {{-- الفعل الرئيسيّ الوحيد: الخروج — وحقّه مفتوح مهما كانت حالته (2.15-أ-2) --}}
    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button class="btn w-full rounded-xl py-3 text-sm font-semibold motion-standard"
                style="min-block-size: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            {{ setting('account.containment.logout_label', 'تسجيل الخروج') }}
        </button>
    </form>
</main>

</body>
</html>
