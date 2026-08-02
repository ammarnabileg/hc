/* ---------------------------------------------------------------
 | تفاعلات مشتركة — تعمل بلا أطر ثقيلة، وبتدرّج آمن لو الـJS مقفول (2.1)
 --------------------------------------------------------------- */

// شريط تقدّم التمرير (2.10.1-25)
const progress = document.querySelector('[data-scroll-progress]');
if (progress) {
    const update = () => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        progress.style.transform = `scaleX(${max > 0 ? window.scrollY / max : 0})`;
    };
    window.addEventListener('scroll', update, { passive: true });
    update();
}

// عدّاد تصاعديّ (2.17-أ) — ⭐ الرقم النهائيّ يظهر في كلّ الأحوال ولا يعلق أبدًا
document.querySelectorAll('[data-count-to]').forEach((el) => {
    const target = parseFloat(String(el.dataset.countTo).replace(/[^\d.-]/g, ''));
    if (!Number.isFinite(target)) return;

    const settle = () => { el.textContent = el.dataset.countTo; };
    let frame = 0;
    const total = 30;
    const tick = () => {
        frame += 1;
        if (frame >= total) return settle();
        el.textContent = Math.round((target * frame) / total).toLocaleString('ar-EG');
        requestAnimationFrame(tick);
    };
    try { requestAnimationFrame(tick); } catch { settle(); }
    setTimeout(settle, 1500); // شبكة أمان: مهما حصل، الرقم الصحيح يظهر
});

// بوب-أب: فتح/إغلاق + ESC
document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-modal-open]');
    if (opener) {
        const modal = document.getElementById(opener.dataset.modalOpen);
        if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    }
    if (e.target.closest('[data-modal-close]')) {
        const modal = e.target.closest('[data-modal]');
        if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('[data-modal]:not(.hidden)').forEach((m) => {
            m.classList.add('hidden'); m.classList.remove('flex');
        });
    }
});

// جرس الإشعارات وتاباته (2.8)
const bell = document.querySelector('[data-bell]');
const panel = document.querySelector('[data-bell-panel]');
if (bell && panel) {
    bell.addEventListener('click', () => panel.classList.toggle('hidden'));
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-bell]') && !e.target.closest('[data-bell-panel]')) {
            panel.classList.add('hidden');
        }
    });
    panel.querySelectorAll('[data-bell-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            const layer = tab.dataset.bellTab;
            panel.querySelectorAll('[data-bell-tab]').forEach((t) => {
                const on = t === tab;
                t.style.background = on ? 'var(--color-brand-500)' : 'var(--surface-sunken)';
                t.style.color = on ? '#04201c' : 'var(--text)';
            });
            panel.querySelectorAll('[data-layer]').forEach((row) => {
                row.style.display = layer === 'all' || row.dataset.layer === layer ? '' : 'none';
            });
        });
    });
}

// Drawer الموبايل: السايد بار يُفتَح من الهيدر ويُغلَق بالسحب (2.15-ج)
const drawerToggle = document.querySelector('[data-drawer-toggle]');
const aside = document.querySelector('aside');
if (drawerToggle && aside) {
    drawerToggle.addEventListener('click', () => {
        const open = aside.classList.toggle('!block');
        aside.classList.toggle('fixed', open);
        aside.classList.toggle('inset-0', open);
        aside.classList.toggle('z-40', open);
        aside.style.background = open ? 'var(--surface)' : '';
    });
}
