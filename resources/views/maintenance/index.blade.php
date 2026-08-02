{{--
    صفحة الصيانة للمستخدم (12.7-و-1).

    الرسالة من الإعدادات · عدّاد تنازليّ · أنيميشن متّسق مع نظام التصميم (2.10.1) ·
    تحديث تلقائيّ كلّ دقيقتين · و⭐ عند بلوغ الصفر **لا عدّاد سالب**: تتبدّل الرسالة
    تلقائيًّا إلى «قرّبنا ننتهي — دقايق» **بنفس المساحة بالظبط** فلا تقفز الصفحة.

    الصفحة مستقلّة عن الليَاوت عن قصد: وقت الصيانة قد تكون الهيدر والسايد بار نفسها
    محلّ التعديل، فلا نُسقِط شاشة الاعتذار معها.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ setting('system.maintenance.page_title', 'المنصّة تحت الصيانة') }}</title>
    {{-- الخطوط محلّيّة داخل حزمة Vite — **بلا أيّ نداء خارجيّ** (2.10.1-2) --}}
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center p-4">

@include('security.noscript')

<main class="w-full max-w-md text-center animate-fadeup">

    {{-- أنيميشن هادئ: نبضة واحدة بمنحنى النظام — لا صفحة ميّتة ولا لهو --}}
    <div class="mx-auto mb-5 flex items-center justify-center" aria-hidden="true"
         style="inline-size: 96px; block-size: 96px;">
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round"
             style="inline-size: 100%; block-size: 100%; color: var(--color-brand-500)"
             class="maint-spin">
            <circle cx="24" cy="24" r="19" style="opacity: .25" />
            <path d="M24 5a19 19 0 0 1 19 19" />
            <path d="M24 14v10l6 4" />
        </svg>
    </div>

    <h1 class="text-2xl font-extrabold mb-2">{{ setting('system.maintenance.page_title', 'المنصّة تحت الصيانة') }}</h1>

    {{-- الرسالة يكتبها الأدمن ونقرأها من الإعدادات — بلا نصّ محروق (2.13) --}}
    <p class="text-sm mb-5" style="color: var(--text-muted)">
        {{ $state['message'] !== '' ? $state['message'] : setting('system.maintenance.message', 'بنطوّر حاجة حلوة — هنرجع قريب.') }}
    </p>

    {{--
        ⭐ صندوق واحد بمساحة ثابتة يحمل العدّاد أو رسالة ما بعد الصفر —
        فالتبديل يحصل **في نفس المكان بالظبط** والصفحة ما تقفزش (12.7-و-1).
    --}}
    <div class="card p-5 mb-4 flex items-center justify-center"
         style="min-block-size: 104px"
         data-countdown-box
         data-seconds-left="{{ (int) $state['seconds_left'] }}"
         data-overrun="{{ $state['overrun'] ? '1' : '0' }}"
         data-overrun-text="{{ $overrunMessage }}">

        <div data-countdown-timer class="{{ $state['overrun'] ? 'hidden' : '' }}">
            <div class="text-xs mb-2" style="color: var(--text-muted)">
                {{ setting('system.maintenance.countdown_label', 'باقي على الرجوع') }}
            </div>
            <div class="flex items-center justify-center gap-2 font-extrabold tabular-nums"
                 style="font-size: clamp(1.5rem, 8vw, 2.25rem)" dir="ltr">
                <span data-part="h">00</span><span style="opacity:.4">:</span>
                <span data-part="m">00</span><span style="opacity:.4">:</span>
                <span data-part="s">00</span>
            </div>
        </div>

        <p data-countdown-overrun class="text-lg font-extrabold {{ $state['overrun'] ? '' : 'hidden' }}">
            {{ $overrunMessage }}
        </p>
    </div>

    <p class="text-xs" style="color: var(--text-muted)">
        {{ str_replace(
            '{minutes}',
            (string) max(1, (int) round($state['refresh_seconds'] / 60)),
            setting('system.maintenance.refresh_hint', 'الصفحة بتحدّث نفسها كلّ {minutes} دقيقة — مش محتاج تعمل حاجة.'),
        ) }}
    </p>

    <a href="{{ route('login') }}" class="btn inline-flex items-center justify-center rounded-xl px-4 py-2 mt-5 text-sm font-semibold motion-standard"
       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        {{ setting('system.maintenance.staff_link_label', 'دخول فريق العمل') }}
    </a>
</main>

<style>
    /* حركة واحدة بمنحنى النظام (2.10.1) — والتحكّم فيها من إعداد المستخدم
       داخل المنصّة لا من تفضيل نظام التشغيل (2.3 · 2.14-ب). */
    @keyframes maint-spin { to { transform: rotate(360deg); } }
    .maint-spin { animation: maint-spin 6s linear infinite; transform-origin: 50% 50%; }
</style>

<script>
    (function () {
        const box = document.querySelector('[data-countdown-box]');
        if (!box) return;

        const timer = box.querySelector('[data-countdown-timer]');
        const overrun = box.querySelector('[data-countdown-overrun]');
        const parts = {
            h: box.querySelector('[data-part="h"]'),
            m: box.querySelector('[data-part="m"]'),
            s: box.querySelector('[data-part="s"]'),
        };

        let left = Number(box.dataset.secondsLeft) || 0;

        const pad = (n) => String(n).padStart(2, '0');

        function paint() {
            // ⭐ لا عدّاد سالب أبدًا: عند الصفر تتبدّل الرسالة في نفس الصندوق
            if (left <= 0) {
                timer.classList.add('hidden');
                overrun.classList.remove('hidden');
                return;
            }

            parts.h.textContent = pad(Math.floor(left / 3600));
            parts.m.textContent = pad(Math.floor((left % 3600) / 60));
            parts.s.textContent = pad(left % 60);
        }

        paint();

        setInterval(function () {
            if (left > 0) { left -= 1; paint(); }
        }, 1000);

        // تحديث تلقائيّ — فلا يفضل المستخدم يجرّب بنفسه (12.7-و-1)
        setTimeout(function () { window.location.reload(); }, {{ (int) $state['refresh_seconds'] * 1000 }});
    })();
</script>

</body>
</html>
