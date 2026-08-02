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

/* ---------------------------------------------------------------
 | مساحة عمل المستخدم (2.15-د): Ctrl+K · التثبيت · التراجع · أوّل مرّة
 --------------------------------------------------------------- */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const post = (url, body) =>
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: JSON.stringify(body || {}),
    }).then((r) => r.json().then((data) => ({ ok: r.ok, data })));

// ---- البحث الموحّد (Ctrl+K) — بديل التنقّل في السايد بار
(() => {
    const palette = document.querySelector('[data-palette]');
    if (!palette) return;

    const input = palette.querySelector('[data-palette-input]');
    const results = palette.querySelector('[data-palette-results]');
    const isMobile = () => window.matchMedia('(max-width: 767px)').matches;

    const open = () => {
        palette.classList.remove('hidden');
        palette.classList.add('flex');
        input?.focus();
    };
    const close = () => {
        palette.classList.add('hidden');
        palette.classList.remove('flex');
    };

    // على الموبايل الأيقونة تفتح شاشة البحث الكاملة — فنترك الرابط يعمل
    document.querySelectorAll('[data-palette-open]').forEach((el) => {
        el.addEventListener('click', (e) => {
            if (isMobile()) return;
            e.preventDefault();
            open();
        });
    });

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            palette.classList.contains('hidden') ? open() : close();
        }
        if (e.key === 'Escape') close();
    });

    palette.querySelector('[data-palette-close]')?.addEventListener('click', close);
    palette.addEventListener('click', (e) => { if (e.target === palette) close(); });

    const groups = { pages: 'صفحات', people: 'أشخاص', tasks: 'مهامّ' };
    let timer = null;

    const render = (data) => {
        const parts = [];
        Object.keys(groups).forEach((key) => {
            const rows = data[key] || [];
            if (!rows.length) return;
            parts.push(`<div class="px-3 pt-3 pb-1 text-[11px]" style="color: var(--text-muted)">${groups[key]}</div>`);
            rows.forEach((row) => {
                parts.push(
                    `<a href="${row.url}" class="flex items-center justify-between gap-2 rounded-xl px-3 py-2 motion-standard"
                        style="min-height:44px; color: var(--text)">
                        <span class="truncate">${row.label}</span>
                        <span class="text-[11px] shrink-0" style="color: var(--text-muted)">${row.hint || ''}</span>
                     </a>`,
                );
            });
        });
        results.innerHTML = parts.length
            ? parts.join('')
            : '<p class="p-3 text-xs" style="color: var(--text-muted)">مفيش نتيجة — جرّب كلمة تانية.</p>';
    };

    input?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            fetch(`/ui/palette?q=${encodeURIComponent(input.value)}`, { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : { pages: [], people: [], tasks: [] }))
                .then(render)
                .catch(() => { results.innerHTML = '<p class="p-3 text-xs">البحث وقف لحظيًّا — جرّب تاني بعد ثانية.</p>'; });
        }, 180);
    });
})();

// ---- التثبيت (Pin): زرّ الهيدر + فكّ التثبيت + الترتيب بالسحب
(() => {
    const toggle = (route, label, button) =>
        post('/ui/pins', { route, label }).then(({ ok, data }) => {
            if (!ok) return toast(data.message || 'مقدرناش نثبّتها دلوقتي — جرّب تاني.');
            toast(data.message);
            if (button) {
                const pinned = data.pinned ? '1' : '0';
                button.dataset.pinned = pinned;
                button.setAttribute('aria-pressed', data.pinned ? 'true' : 'false');
                button.style.color = data.pinned ? 'var(--color-brand-500)' : 'var(--text-muted)';
                button.querySelector('svg')?.setAttribute('fill', data.pinned ? 'currentColor' : 'none');
            }
        });

    document.querySelectorAll('[data-pin-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => toggle(btn.dataset.pinToggle, btn.dataset.pinLabel || '', btn));
    });

    document.querySelectorAll('[data-unpin]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const row = btn.closest('[data-pin-item]');
            toggle(btn.dataset.unpin, btn.dataset.label || '', null).then(() => row?.remove());
        });
    });

    const list = document.querySelector('[data-pins]');
    if (!list) return;

    let dragged = null;
    list.addEventListener('dragstart', (e) => { dragged = e.target.closest('[data-pin-item]'); });
    list.addEventListener('dragover', (e) => {
        e.preventDefault();
        const over = e.target.closest('[data-pin-item]');
        if (!over || !dragged || over === dragged) return;
        const after = over.getBoundingClientRect().top < dragged.getBoundingClientRect().top;
        over.parentNode.insertBefore(dragged, after ? over : over.nextSibling);
    });
    list.addEventListener('drop', () => {
        const routes = [...list.querySelectorAll('[data-pin-item]')].map((el) => el.dataset.pinItem);
        post('/ui/pins/reorder', { routes }).then(({ data }) => toast(data.message || 'اتحفظ ✓'));
        dragged = null;
    });
})();

// ---- التراجع خلال ثوانٍ (2.15-د) — أيّ شاشة تُطلق ui:undoable
(() => {
    const host = document.querySelector('[data-undo-host]');
    if (!host) return;

    const message = host.querySelector('[data-undo-message]');
    const countdown = host.querySelector('[data-undo-countdown]');
    const action = host.querySelector('[data-undo-action]');
    const seconds = parseInt(host.dataset.undoSeconds || '5', 10);
    let timer = null;
    let token = null;

    const hide = () => {
        clearInterval(timer);
        host.classList.add('hidden');
        host.classList.remove('flex');
        token = null;
    };

    window.addEventListener('ui:undoable', (e) => {
        token = e.detail?.token;
        if (!token) return;
        message.textContent = e.detail?.message || 'اتنفّذ ✓';
        host.classList.remove('hidden');
        host.classList.add('flex');

        let left = seconds;
        countdown.textContent = left;
        clearInterval(timer);
        timer = setInterval(() => {
            left -= 1;
            countdown.textContent = Math.max(left, 0);
            if (left <= 0) hide();
        }, 1000);
    });

    action?.addEventListener('click', () => {
        if (!token) return;
        const current = token;
        hide();
        post(`/ui/undo/${current}`).then(({ data }) => toast(data.message));
    });
})();

// ---- «شاشة أوّل مرّة» (2.15-د): مراحل، وزرّ «؟» يعيدها
(() => {
    const box = document.querySelector('[data-first-run]');
    if (!box) return;

    const screen = box.dataset.firstRun;
    const steps = JSON.parse(box.querySelector('[data-first-run-steps]')?.textContent || '[]');
    const title = box.querySelector('[data-first-run-title]');
    const body = box.querySelector('[data-first-run-body]');
    const index = box.querySelector('[data-first-run-index]');
    const next = box.querySelector('[data-first-run-next]');
    let at = 0;

    const paint = () => {
        title.textContent = steps[at]?.title || '';
        body.textContent = steps[at]?.body || '';
        index.textContent = at + 1;
        next.textContent = at === steps.length - 1 ? 'يلا نبدأ' : 'التالي';
    };

    const finish = () => {
        box.classList.add('hidden');
        box.classList.remove('flex');
        post('/ui/first-run', { screen });
    };

    next?.addEventListener('click', () => {
        if (at >= steps.length - 1) return finish();
        at += 1;
        paint();
    });

    box.querySelector('[data-first-run-skip]')?.addEventListener('click', finish);

    document.querySelector('[data-first-run-replay]')?.addEventListener('click', () => {
        at = 0;
        paint();
        box.classList.remove('hidden');
        box.classList.add('flex');
    });

    if (steps.length) paint();
})();

// ردّ فوريّ لكلّ فعل: Toast خفيف بلا إعادة تحميل (2.17-ب)
function toast(text) {
    if (!text) return;
    const el = document.createElement('div');
    el.className = 'fixed z-[80] rounded-xl px-4 py-3 text-sm animate-fadeup';
    el.style.cssText = 'inset-inline-end:1rem; inset-block-start:calc(var(--header-h,64px) + 12px);'
        + 'background: var(--surface-raised); border:1px solid var(--border); color: var(--text)';
    el.setAttribute('role', 'status');
    el.textContent = text;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}
window.platformToast = toast;
