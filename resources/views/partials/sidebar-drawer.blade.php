{{--
  لوحة السايد بار المنزلقة على الموبايل (الدستور 13 · 2.15-ج).

  ⛔ العطل الذي يسدّه هذا الملفّ: السايد بار كان `hidden md:block` و`#mobile-drawer`
  فارغًا في القوالب الثلاثة — فمستخدم الموبايل بلا تنقّل أصلًا، والموبايل **معيار
  قبول** (2.15-ج). والدستور 13 ينصّ على «لوحة جانبيّة منزلقة» لا على قائمة مخفيّة.

  لماذا الأنماط هنا لا في `resources/css/app.css`؟ لأنّ ملفّ التنسيق العامّ يعمل
  عليه إيجنت آخر بالتوازي، ولأنّ هذه الأنماط تخصّ هذا المكوّن وحده. والسلوك
  الافتراضيّ **بلا جافاسكربت** هو الظهور الكامل على الديسكتوب، والانزلاق تحسينٌ
  تدريجيّ (2.1) — فلا شاشة تُفقَد لو تعطّل السكربت.
--}}

<style>
    /* الموبايل: لوحة منزلقة خارج التدفّق — الاتّجاه منطقيّ فيعمل RTL وLTR معًا */
    [data-sidebar] {
        position: fixed;
        inset-block: 0;
        inset-inline-start: 0;
        width: min(20rem, 86vw);
        max-width: 86vw;
        z-index: 70;
        background: var(--surface);
        transform: translateX(-100%);
        transition: transform var(--motion-standard, 200ms) ease;
        will-change: transform;
    }

    [dir="rtl"] [data-sidebar] { transform: translateX(100%); }

    [data-sidebar][data-open="true"],
    [dir="rtl"] [data-sidebar][data-open="true"] { transform: translateX(0); }

    /* لا مُطفئ للحركة — «الأنيميشن حاضر دائمًا» (2.3 · 2.14-ب) */

    [data-sidebar-backdrop] {
        position: fixed;
        inset: 0;
        z-index: 60;
        background: rgba(0, 0, 0, .5);
        opacity: 0;
        visibility: hidden;
        transition: opacity var(--motion-standard, 200ms) ease;
    }

    [data-sidebar-backdrop][data-open="true"] { opacity: 1; visibility: visible; }

    /* لمسة 44×44 كحدّ أدنى لكلّ زرّ داخل اللوحة (2.15-ج) */
    [data-sidebar-close] { min-width: 44px; min-height: 44px; }

    @media (min-width: 768px) {
        [data-sidebar],
        [dir="rtl"] [data-sidebar],
        [data-sidebar][data-open="true"] {
            position: static;
            width: 16rem;
            max-width: none;
            transform: none;
            z-index: auto;
            background: transparent;
        }

        [data-sidebar-backdrop],
        [data-sidebar-close] { display: none; }
    }
</style>

<div id="mobile-drawer" class="md:hidden">
    <div data-sidebar-backdrop data-open="false" aria-hidden="true"></div>

    <button type="button" data-sidebar-close aria-label="{{ setting('nav.drawer.close_aria', 'اقفل القائمة') }}"
            class="fixed top-3 rounded-xl text-lg items-center justify-center hidden"
            style="inset-inline-end: .75rem; z-index: 80; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        ✕
    </button>
</div>

<script>
    /* اللوحة المنزلقة: زرّ الهيدر يفتح · الخلفيّة و«اقفل» وESC يقفلون (2.15-ج · 2.17) */
    (function () {
        var panel = document.querySelector('[data-sidebar]');
        var backdrop = document.querySelector('[data-sidebar-backdrop]');
        var closeBtn = document.querySelector('[data-sidebar-close]');
        var toggles = document.querySelectorAll('[data-drawer-toggle]');

        if (!panel || !backdrop) return;

        function isMobile() { return window.matchMedia('(max-width: 767px)').matches; }

        function setOpen(open) {
            panel.setAttribute('data-open', open ? 'true' : 'false');
            backdrop.setAttribute('data-open', open ? 'true' : 'false');
            if (closeBtn) closeBtn.classList.toggle('hidden', !open);
            if (closeBtn) closeBtn.classList.toggle('flex', open);
            document.documentElement.style.overflow = open ? 'hidden' : '';
            if (open) panel.focus({ preventScroll: true });
        }

        toggles.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                /* السكربت المحزوم القديم كان يقلب `!block` على نفس الزرّ — فيتعارك
                   معنا على نفس العنصر؛ ونحن مسجَّلون قبله فنوقفه لهذا الحدث. */
                e.stopImmediatePropagation();
                e.preventDefault();
                setOpen(panel.getAttribute('data-open') !== 'true');
            });
        });

        backdrop.addEventListener('click', function () { setOpen(false); });
        if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') setOpen(false);
        });

        /* التنقّل يقفل اللوحة — فلا تبقى فوق الصفحة الجديدة */
        panel.addEventListener('click', function (e) {
            if (isMobile() && e.target.closest('a')) setOpen(false);
        });

        window.addEventListener('resize', function () {
            if (!isMobile()) setOpen(false);
        });
    })();
</script>
