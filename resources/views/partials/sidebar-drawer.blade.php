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
    /* 320px (2.10.1-13، تصحيح الهويّة 2.2) — مع سقفٍ نسبيّ للشاشات الأضيق */
    [data-sidebar] {
        position: fixed;
        inset-block: 0;
        inset-inline-start: 0;
        width: min(320px, 88vw);
        max-width: 88vw;
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
            width: 248px; /* 2.10.1-13، الهويّة 2.0 */
            max-width: none;
            transform: none;
            z-index: auto;
            background: transparent;
        }

        [data-sidebar-backdrop],
        [data-sidebar-close] { display: none; }
    }

    /*
     | التابلت (768-1199px، وضع `.compact` — 2.10.1-13): 80px أيقونات فقط،
     | والاسم يظهر بـTooltip عند الهوفر (`title` على كلّ صفّ + إخفاء
     | `.nav-item-label`). و`[data-compact-hide]` يُخفي الودجات التي لا
     | تستوي في 80px (البحث · ويدجت الدعوة · المثبَّتة · بطاقة عنوان لوحة
     | الإدارة) — البحث الموحّد Ctrl+K بديلٌ متاحٌ من كلّ شاشة (2.15-د).
     |
     | والمجموعات (`x-nav-group`) تفتح بـFlyout عائمٍ لا بتوسيعٍ في المكان
     | يكسر عرض 80px — السكربت أسفله يحسب موضعه (`position:fixed` لا
     | `absolute` تحديدًا لأنّ حاوية السايد بار `overflow-y:auto`، فأيّ
     | Flyout بموضعٍ نسبيّ سينقصّ ضمنها بدل أن يعوم فوق المحتوى).
     */
    @media (min-width: 768px) and (max-width: 1199px) {
        [data-sidebar] { width: 80px; }

        [data-sidebar] [data-compact-hide],
        [data-sidebar] .nav-item-label { display: none; }

        [data-sidebar] .nav-compact-row { justify-content: center; }

        /* استثناء: صفوف الـFlyout نفسها تبقى عاديّة (أيقونة + اسمٌ ظاهر) */
        [data-sidebar] [data-flyout] .nav-compact-row { justify-content: flex-start; }
        [data-sidebar] [data-flyout] .nav-item-label { display: inline; }

        [data-sidebar] details[data-nav-group] { position: relative; }

        [data-sidebar] details[data-nav-group][open] > [data-flyout] {
            position: fixed;
            margin: 0;
            min-width: 220px;
            max-width: 280px;
            padding: 8px;
            border-radius: 12px;
            background: var(--surface);
            border: 1px solid var(--border);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
            z-index: 55;
        }
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

        /*
         | تفويضٌ على المستند لا ربطٌ بالزرّ نفسه: زرّ الفتح في الـTopbar، وهو
         | يُرسَم **بعد** السايد بار في المستند (كالمرجع: الـTopbar داخل عمود
         | المحتوى)، فلحظة تنفيذ هذا السكربت لا زرّ بعد. وطور الالتقاط (capture)
         | يسبق معالج السكربت المحزوم القديم على الزرّ نفسه فنوقفه لهذا الحدث.
         */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-drawer-toggle]');
            if (!btn) return;
            e.stopImmediatePropagation();
            e.preventDefault();
            setOpen(panel.getAttribute('data-open') !== 'true');
        }, true);

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

    /*
     | Flyout مجموعات التابلت المضغوط (768-1199px — 2.10.1-13): `<details>`
     | يفتح/يقفل أصيلًا بلا جافاسكربت، والسكربت هنا يتكفّل بأمرين فقط
     | CSS وحدها لا تقدر عليهما: (1) حساب موضع الـFlyout الثابت بجانب
     | الأيقونة، (2) إقفاله بالنقر خارجه — فلا يبقى عائمًا فوق صفحةٍ
     | انتقل عنها المستخدم بالفأرة لا بالنقر.
     */
    (function () {
        var groups = document.querySelectorAll('[data-sidebar] details[data-nav-group]');

        if (!groups.length) return;

        function isCompact() {
            return window.matchMedia('(min-width: 768px) and (max-width: 1199px)').matches;
        }

        function isRtl() {
            return getComputedStyle(document.documentElement).direction === 'rtl';
        }

        function position(det) {
            var summary = det.querySelector(':scope > summary');
            var flyout = det.querySelector(':scope > [data-flyout]');
            if (!summary || !flyout) return;

            if (!isCompact() || !det.open) {
                flyout.style.top = '';
                flyout.style.insetInlineStart = '';
                return;
            }

            var rect = summary.getBoundingClientRect();
            var edge = isRtl() ? (window.innerWidth - rect.left) : rect.right;
            flyout.style.top = Math.max(8, rect.top) + 'px';
            flyout.style.insetInlineStart = edge + 'px';
        }

        groups.forEach(function (det) {
            det.addEventListener('toggle', function () { position(det); });
            /* مفتوحةٌ افتراضيًّا لو صفحتها الحاليّة داخل المجموعة (anyActive) */
            if (det.open) position(det);
        });

        window.addEventListener('resize', function () {
            groups.forEach(position);
        });

        /* النقر خارج الـFlyout المفتوح يقفله — سلوكٌ متوقَّعٌ لعنصرٍ عائم */
        document.addEventListener('click', function (e) {
            if (!isCompact()) return;

            groups.forEach(function (det) {
                if (det.open && !det.contains(e.target)) det.open = false;
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !isCompact()) return;

            groups.forEach(function (det) { det.open = false; });
        });
    })();
</script>
