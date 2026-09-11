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

/*
 | المؤشّر المخصّص (2.10.1-18) — **الفأرة فقط**.
 |
 | «نقطة 8px تركوازيّة تكبر إلى 42px (شفّافة بحدّ) فوق العناصر التفاعليّة
 | بانتقال cubic-bezier(.22,1,.36,1)؛ **تُخفى على اللمس**».
 |
 | ⭐ الشرط `(pointer: fine)` **قبل الإنشاء** لا بعده: على اللمس لا يُخلق العنصر
 | أصلًا، فلا نقطة عالقة في مكان آخر لمسةٍ ولا مستمع حدثٍ بلا عمل. والمقاسان
 | من متغيّرات `DesignTokens` في الورقة — فالحركة هنا **موضعٌ فقط**.
 |
 | ولا نضيف `cursor:none` على الصفحة: مؤشّر النظام يبقى (فقدُه يجعل النقر
 | تخمينًا لو تعطّل السكربت) — والنقطة **طبقةُ إحساسٍ فوقه** (2.17).
 */
(() => {
    if (!window.matchMedia?.('(pointer: fine)').matches) return;

    const dot = document.createElement('div');
    dot.className = 'hc-cursor';
    dot.setAttribute('aria-hidden', 'true');
    document.body.appendChild(dot);

    const HOT = 'a[href], button, [role="button"], [role="switch"], [role="tab"], summary,'
        + ' input:not([type="hidden"]), select, textarea, label[for], [data-modal-open]';

    let x = 0;
    let y = 0;
    let queued = false;

    const paint = () => {
        queued = false;
        dot.style.transform = `translate(${x}px, ${y}px)`;
    };

    document.addEventListener('pointermove', (e) => {
        // قلمٌ أو إصبعٌ على جهازٍ هجين: النقطة تختفي حتى تعود الفأرة
        if (e.pointerType !== 'mouse') { dot.dataset.visible = '0'; return; }

        x = e.clientX;
        y = e.clientY;
        dot.dataset.visible = '1';
        dot.dataset.hot = e.target.closest?.(HOT) ? '1' : '0';

        if (!queued) { queued = true; requestAnimationFrame(paint); }
    }, { passive: true });

    document.addEventListener('pointerleave', () => { dot.dataset.visible = '0'; });
    window.addEventListener('blur', () => { dot.dataset.visible = '0'; });
})();

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

/*
 | المنزلقات (2.10.1-11): التعبئة تدرّجٌ على خلفيّة المنزلق **يتحدّث بالـJS**
 | حسب القيمة — لا `accent-color` (يترك إطارًا نايتف لا يُزال بـ`border:none`).
 | و«الاستجابة اللحظيّة» مطلوبة نصًّا: الرقم المرتبط يتغيّر فورًا مع السحب.
 */
(() => {
    const paint = (input) => {
        const min = parseFloat(input.min || '0');
        const max = parseFloat(input.max || '100');
        const value = parseFloat(input.value || '0');
        const span = max - min;
        const percent = span > 0 ? ((value - min) / span) * 100 : 0;
        input.style.setProperty('--range-fill', `${Math.min(100, Math.max(0, percent))}%`);

        // الرقم المرتبط (اختياريّ): <output data-range-for="ID">
        const echo = input.id && document.querySelector(`[data-range-for="${input.id}"]`);
        if (echo) echo.textContent = input.value;
    };

    const bind = (input) => {
        paint(input);
        input.addEventListener('input', () => paint(input));
        input.addEventListener('change', () => paint(input));
    };

    document.querySelectorAll('input[type="range"]').forEach(bind);

    // منزلقات تظهر لاحقًا (بوب-أب/تاب كسول) تتلوّن هي الأخرى
    document.addEventListener('input', (e) => {
        if (e.target instanceof HTMLInputElement && e.target.type === 'range') paint(e.target);
    });
})();

/*
 | الفورم الطويل يتقسّم خطوات بحفظ تلقائيّ بينها (2.15-ب).
 | الحقول كلّها تبقى في الـDOM — الخطوة إخفاءٌ بصريّ لا حذف — فلا تضيع قيمة،
 | والمسودّة تُحفَظ محلّيًّا فيرجع المستخدم لشغله كما تركه (2.17-ب).
 */
document.querySelectorAll('[data-stepper]').forEach((host) => {
    const body = host.querySelector('[data-stepper-body]');
    const head = host.querySelector('[data-stepper-head]');
    const nav = host.querySelector('[data-stepper-nav]');
    if (!body || !head || !nav) return;

    const size = Math.max(1, parseInt(host.dataset.stepperSize || '7', 10));
    const blocks = [...body.children];

    // العدّ بالحقول الحقيقيّة لا بالبلوكات: «فورم أطول من N حقلًا» (2.15-ب)
    const fieldsIn = (el) => {
        const inner = el.querySelectorAll('input:not([type="hidden"]), select, textarea').length;
        return inner || (el.matches('input:not([type="hidden"]), select, textarea') ? 1 : 0);
    };
    const totalFields = blocks.reduce((sum, el) => sum + fieldsIn(el), 0);
    if (totalFields <= size) return; // تحت الحدّ: الفورم يبقى كما هو

    const labels = JSON.parse(host.dataset.stepperLabels || '[]');
    const steps = [];
    let current = [];
    let count = 0;
    for (const el of blocks) {
        const c = fieldsIn(el);
        if (current.length && count + c > size) { steps.push(current); current = []; count = 0; }
        current.push(el);
        count += c;
    }
    if (current.length) steps.push(current);
    if (steps.length < 2) return;

    const key = `hc.draft.${host.dataset.stepper}`;
    const form = host.closest('form');
    const saved = host.querySelector('[data-stepper-saved]');
    let at = 0;

    // ---- المسودّة: استرجاعٌ عند الفتح وحفظٌ مع كلّ تغيير
    const inputs = () => [...(form?.querySelectorAll('input, select, textarea') || [])]
        .filter((el) => el.name && el.type !== 'password' && el.type !== 'file' && el.type !== 'hidden');

    try {
        const draft = JSON.parse(localStorage.getItem(key) || '{}');
        inputs().forEach((el) => { if (draft[el.name] !== undefined && !el.value) el.value = draft[el.name]; });
    } catch { /* مسودّة تالفة تُتجاهَل بصمت ولا تعطّل الفورم */ }

    const saveDraft = () => {
        try {
            const draft = {};
            inputs().forEach((el) => { draft[el.name] = el.value; });
            localStorage.setItem(key, JSON.stringify(draft));
            if (saved) saved.textContent = 'اتحفظ ✓';
        } catch { /* مساحة ممتلئة: الفورم يكمل عادي */ }
    };

    form?.addEventListener('input', saveDraft);
    form?.addEventListener('submit', () => { try { localStorage.removeItem(key); } catch { /* تجاهل */ } });

    // ---- بناء صفّ الخطوات (2.10.1-24)
    head.innerHTML = steps
        .map((_, i) => `
            <span class="flex items-center gap-2 shrink-0">
                <span data-step-dot="${i}" class="inline-flex items-center justify-center rounded-full text-[11px] font-bold"
                      style="width:24px;height:24px"></span>
                <span data-step-label="${i}" class="text-xs whitespace-nowrap"></span>
                ${i < steps.length - 1 ? '<span class="inline-block" style="width:28px;height:1px;background:rgb(0 212 184 / .12)"></span>' : ''}
            </span>`)
        .join('');
    head.classList.remove('hidden');
    head.classList.add('flex');
    nav.classList.remove('hidden');
    nav.classList.add('flex');

    const prev = nav.querySelector('[data-stepper-prev]');
    const next = nav.querySelector('[data-stepper-next]');

    const paint = () => {
        steps.forEach((group, i) => {
            group.forEach((el) => el.classList.toggle('hidden', i !== at));

            const dot = head.querySelector(`[data-step-dot="${i}"]`);
            const label = head.querySelector(`[data-step-label="${i}"]`);
            const done = i < at;
            const active = i === at;

            dot.textContent = done ? '' : String(i + 1);
            dot.style.background = done ? 'var(--color-brand-500)' : active ? 'rgb(0 212 184 / .12)' : 'rgb(0 212 184 / .08)';
            dot.style.border = `1px solid ${active ? 'var(--color-brand-500)' : 'rgb(0 212 184 / .2)'}`;
            dot.style.color = done ? '#020e18' : active ? 'var(--color-brand-500)' : 'var(--text-muted)';
            if (done) dot.innerHTML = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor"'
                + ' stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>';

            label.textContent = labels[i] || `خطوة ${i + 1}`;
            label.style.color = active ? 'var(--color-brand-500)' : 'var(--text-muted)';
            label.style.fontWeight = active ? '700' : '400';
        });

        prev.disabled = at === 0;
        prev.style.opacity = at === 0 ? '.5' : '1';
        next.classList.toggle('hidden', at === steps.length - 1);
    };

    prev.addEventListener('click', () => { if (at > 0) { at -= 1; saveDraft(); paint(); } });
    next.addEventListener('click', () => { if (at < steps.length - 1) { at += 1; saveDraft(); paint(); } });

    paint();
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

/*
 | ⭐ استطلاعٌ لحظيّ (Toast — 2.8): «إشعار لحظيّ للأحداث المهمّة + سجلّ دائم في
 | المركز». السجلّ الدائم موجودٌ من الأوّل — والفجوة كانت هنا: حدثٌ يقع
 | والمستخدم على الصفحة لا يُنتج أيّ Toast إطلاقًا. لا Websocket ولا SSE هنا —
 | استطلاعٌ خفيف (polling) بفارق `after_id` وحده، فلا يُعاد جلب ما رآه المستخدم.
 */
(() => {
    const box = document.querySelector('[data-notifications-poll]');
    if (!box) return;

    const url = box.dataset.pollUrl;
    const seconds = parseInt(box.dataset.pollSeconds, 10) || 20;
    const badge = box.querySelector('[data-unread-badge]');
    let lastId = parseInt(box.dataset.lastId, 10) || 0;

    const tick = () => {
        fetch(`${url}?after_id=${lastId}`, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((data) => {
                if (!data) return;

                lastId = data.last_id || lastId;
                (data.items || []).forEach((item) => toast(item.title));

                if (badge) {
                    const n = data.unread || 0;
                    badge.textContent = n;
                    badge.classList.toggle('hidden', n <= 0);
                }
            })
            .catch(() => {});
    };

    setInterval(tick, seconds * 1000);
})();

/*
 | Drawer الموبايل انتقل إلى `resources/views/partials/sidebar-drawer.blade.php`
 | (الدستور 13: «لوحة جانبيّة منزلقة»). كان هنا قلبُ `!block` على أوّل `<aside>`
 | فيغطّي الشاشة كلّها بلا خلفيّة ولا زرّ إغلاق ولا انزلاق — والسايد بار نفسه كان
 | `hidden md:block` فلا يظهر أصلًا. والمكوّن الجديد يحمل نمطه وسكربته معه، فيعمل
 | بلا إعادة بناء للحزمة ولا تصادم مع ملفّ التنسيق المشترك.
 */

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
    // الشرائح ونصوص الأزرار كلّها ممّا كتبه الأدمن — ولا نصّ محروق هنا (2.13)
    const steps = JSON.parse(box.querySelector('[data-first-run-steps]')?.textContent || '[]');
    const labels = JSON.parse(box.querySelector('[data-first-run-labels]')?.textContent || '{}');
    const title = box.querySelector('[data-first-run-title]');
    const body = box.querySelector('[data-first-run-body]');
    const index = box.querySelector('[data-first-run-index]');
    const next = box.querySelector('[data-first-run-next]');
    const image = box.querySelector('[data-first-run-image]');
    const action = box.querySelector('[data-first-run-action]');
    let at = 0;

    const paint = () => {
        const step = steps[at] || {};
        title.textContent = step.title || '';
        body.textContent = step.body || '';
        index.textContent = at + 1;
        next.textContent = at === steps.length - 1 ? (labels.done || '') : (labels.next || '');

        if (image) {
            image.classList.toggle('hidden', !step.image_url);
            if (step.image_url) image.src = step.image_url;
        }

        if (action) {
            const linked = Boolean(step.action_url && step.action_label);
            action.classList.toggle('hidden', !linked);
            if (linked) {
                action.href = step.action_url;
                action.textContent = step.action_label;
            }
        }
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

/* ---------------------------------------------------------------
 | أدوات السيرة الذاتيّة (9): استيراد وتحليل · الرابط العامّ
 --------------------------------------------------------------- */
(() => {
    const box = document.querySelector('[data-cv-tools]');
    if (!box) return;

    const token = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const preview = box.querySelector('[data-cv-import-preview]');
    const summary = box.querySelector('[data-cv-import-summary]');
    const fields = box.querySelector('[data-cv-import-fields]');

    box.querySelector('[data-cv-import]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = new FormData(e.currentTarget);
        const res = await fetch('/cv/import', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
            body,
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok || !data.ok) {
            // ماذا حدث + ماذا تفعل (2.17-ب)
            window.platformToast?.(data.message || 'مقدرناش نقرا الملفّ — جرّب صيغة تانية.');
            return;
        }

        // ⭐ معاينةٌ تحريريّة حقيقيّة (9) — حقول بنّاء السيرة نفسها (لا عدّادات
        // فقط)، والخادم يرسلها جاهزةً بنفس أجزاء `row-*.blade.php` فلا تتكرّر
        // واجهة الإدخال هنا من جديد.
        summary.textContent = `${data.question} — لقينا ${data.counts.experience} خبرة و${data.counts.education} مؤهّل و${data.counts.languages} لغة.`;
        fields.innerHTML = data.html || '';
        preview.classList.remove('hidden');
    });

    // حذف صفٍّ فرديّ من المعاينة قبل الحفظ — لا يصل للبيانات النهائيّة (9)
    fields?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-repeat-remove]');
        if (!btn) return;
        btn.closest('[data-repeat-row]')?.remove();
    });

    // آخر مقطع من اسم الحقل (`data[experience][0][title]` ⟵ `title`) —
    // بلا اعتمادٍ على رقم الفهرس أصلًا، فحذف صفّ في المنتصف لا يكسر الباقي.
    function fieldKey(name) {
        const m = /\[([a-zA-Z_]+)\]$/.exec(name || '');
        return m ? m[1] : null;
    }

    /** صفوف قسمٍ متكرّر **بعد** تعديل/حذف المستخدم — لا المستخرَج الخام (9) */
    function collectRows(section) {
        const list = fields?.querySelector(`[data-import-list="${section}"]`);
        if (!list) return [];

        return [...list.querySelectorAll('[data-repeat-row]')].map((row) => {
            const obj = {};
            row.querySelectorAll('[name]').forEach((el) => {
                const key = fieldKey(el.name);
                if (!key) return;
                obj[key] = el.type === 'checkbox' ? el.checked : el.value.trim();
            });
            return obj;
        }).filter((obj) => Object.values(obj).some((v) => v !== '' && v !== false));
    }

    function collectProfile() {
        const obj = {};
        fields?.querySelectorAll('[data-import-profile] [data-field]').forEach((el) => {
            const v = el.value.trim();
            if (v !== '') obj[el.dataset.field] = v;
        });
        return obj;
    }

    box.querySelectorAll('[data-cv-import-mode]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            // ⭐ الصفوف بعد تعديل المستخدم لها (9) — لا الاستخراج الخام الأصليّ
            const editedPreview = {
                profile: collectProfile(),
                experience: collectRows('experience'),
                education: collectRows('education'),
                languages: collectRows('languages'),
                skills: fields?.querySelector('[data-import-skills]')?.value.trim() || '',
            };

            const res = await fetch('/cv/import/apply', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
                body: JSON.stringify({ mode: btn.dataset.cvImportMode, preview: editedPreview }),
            });
            const data = await res.json().catch(() => ({}));
            window.platformToast?.(data.message || 'مقدرناش نحفظ — جرّب تاني.');
            if (res.ok && data.ok) window.location.reload();
        });
    });

    // الرابط العامّ للسيرة — يُفتَح ويُقفَل بضغطة (9)
    const toggle = box.querySelector('[data-cv-public]');
    const urlField = box.querySelector('[data-cv-public-url]');
    toggle?.addEventListener('change', async () => {
        const res = await fetch('/cv/public', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
            body: JSON.stringify({ enabled: toggle.checked }),
        });
        const data = await res.json().catch(() => ({}));
        if (data.url) {
            urlField.value = data.url;
            urlField.classList.toggle('hidden', !data.enabled);
        }
        window.platformToast?.(data.message || 'اتحفظ ✓');
    });
})();
