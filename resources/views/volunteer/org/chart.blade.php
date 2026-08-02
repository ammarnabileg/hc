@extends('layouts.app')

@section('title', 'الهيكل التنظيميّ')

@push('head')
    @include('volunteer.org.partials.chart-styles')
@endpush

@section('content')
    <x-page-header
        title="الهيكل التنظيميّ"
        :subtitle="$root ? $root->name_ar.' — شجرة الكيان وسلاسل الأبلاين' : null"
        :breadcrumbs="[['label' => 'الرئيسيّة', 'url' => route('dashboard')], ['label' => 'قسمي'], ['label' => 'الهيكل التنظيميّ']]">
        <x-slot:action>
            @include('volunteer.org.partials.entity-switcher', ['action' => route('volunteer.org')])
            {{-- فعل رئيسيّ واحد بارز (2.15-أ-2) --}}
            <button type="button" data-org="snapshot"
                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">لقطة (Snapshot)</button>
        </x-slot:action>
    </x-page-header>

    @if (! $root || empty($chart['nodes']))
        <x-empty message="مفيش فريق تحتك — دي شجرة كيانك لسّه في أوّلها" action="الأعضاء والبوزشنز" :href="route('volunteer.department')" />
    @else
        {{-- عدّاد شبكتك الكاملة بكلّ المستويات + أعلى رقم وصلته بلون هادئ بلا سهم نازل (13.4-م) --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="card p-4">
                <div class="text-sm" style="color: var(--text-muted)">شبكتك الكاملة</div>
                {{-- الرقم النهائيّ يظهر في كلّ الأحوال (2.17-أ) --}}
                <div class="mt-2 text-2xl font-extrabold" data-count-to="{{ $chart['network_total'] }}">{{ $chart['network_total'] }}</div>
                <div class="mt-1 text-xs" style="color: var(--text-muted)">
                    أعلى رقم وصلته: <span data-org="best">{{ $chart['network_total'] }}</span>
                </div>
            </div>
            <x-kpi label="أعضاء الكيان" :value="$chart['entity_members']" icon="👥" />
            <div class="card p-4 col-span-2 flex flex-col justify-center gap-2">
                <input type="search" data-org="search" placeholder="ابحث بالاسم أو الكود — نقفز للعقدة ونضيئها"
                       aria-label="بحث في الهيكل"
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <button type="button" data-org="center" class="rounded-full px-3 py-1" style="background: var(--surface-sunken)">توسيط عليّ</button>
                    <button type="button" data-org="relayout" class="rounded-full px-3 py-1" style="background: var(--surface-sunken)">إعادة ترتيب تلقائيّ</button>
                    <button type="button" data-org="zoom-in" class="rounded-full px-3 py-1" style="background: var(--surface-sunken)" aria-label="تكبير">＋</button>
                    <button type="button" data-org="zoom-out" class="rounded-full px-3 py-1" style="background: var(--surface-sunken)" aria-label="تصغير">－</button>
                    <label class="flex items-center gap-1">
                        <input type="checkbox" data-org="occupancy" checked style="accent-color: var(--color-brand-500)">
                        إظهار مؤشّر الإشغال
                    </label>
                </div>
            </div>
        </div>

        {{-- الكانفاس: سحب وتكبير وخطوط منحنية تتحرّك مع السحب — SVG بأيدينا بلا أيّ مكتبة (2.16-ج) --}}
        <div class="hidden md:block card org-stage" id="org-stage">
            <div class="org-world" id="org-world">
                <svg class="org-links" id="org-links" width="1" height="1" aria-hidden="true"></svg>
            </div>
        </div>

        {{-- الموبايل: قائمة شجريّة قابلة للطيّ بدل السحب (2.15-ج) --}}
        <div class="md:hidden card p-3">
            <ul>
                @foreach ($tree as $node)
                    @include('volunteer.org.partials.tree-node', ['node' => $node])
                @endforeach
            </ul>
        </div>

        <x-modal id="org-node-modal" title="تفاصيل سريعة">
            <div data-org-node-body class="text-sm">
                <p style="color: var(--text-muted)">جارٍ التحميل…</p>
            </div>
        </x-modal>

        <x-modal id="org-snapshot-modal" title="لقطة الهيكل">
            <div data-org-snapshot class="text-sm text-center">
                <p style="color: var(--text-muted)">جارٍ تجهيز اللقطة…</p>
            </div>
        </x-modal>
    @endif
@endsection

@push('scripts')
@if ($root && ! empty($chart['nodes']))
<script>
/* -----------------------------------------------------------------
 | كانفاس الهيكل التنظيميّ (13.4-م-3) — JS خام بلا أيّ مكتبة رسم.
 | الخطوط منحنية ناعمة وتتحرّك مع السحب، والمواضع تُحفَظ لكلّ مستخدم.
 ----------------------------------------------------------------- */
(() => {
    const DATA = @json($chart);
    const NODE_URL = @json(url('/volunteer/org/node'));
    const STORE = 'hc.org.' + @json($root->id . '-' . auth()->id());
    const stage = document.getElementById('org-stage');
    const world = document.getElementById('org-world');
    const svg = document.getElementById('org-links');
    if (!stage || !world || !svg) return;

    const NODE_W = 200, NODE_H = 104, HGAP = 26, VGAP = 78;
    const SVGNS = 'http://www.w3.org/2000/svg';
    const nodes = DATA.nodes;
    const byId = new Map(nodes.map((n) => [n.id, n]));
    const kids = new Map();
    nodes.forEach((n) => {
        const key = n.parent === null ? '_root' : n.parent;
        if (!kids.has(key)) kids.set(key, []);
        kids.get(key).push(n.id);
    });
    // ترتيب من اليمين لليسار ليقرأ الهيكل بالعربيّة
    kids.forEach((list) => list.reverse());

    const childrenOf = (id) => kids.get(id) || [];
    const roots = kids.get('_root') || [];

    let saved = {};
    try { saved = JSON.parse(localStorage.getItem(STORE) || '{}') || {}; } catch (e) { saved = {}; }

    // ----- العمق والطيّ التلقائيّ للمستويات الأعمق (13.4-م)
    const depth = new Map();
    (function measure(list, d) {
        list.forEach((id) => { depth.set(id, d); measure(childrenOf(id), d + 1); });
    })(roots, 0);

    const collapsed = new Set();
    const myDepth = DATA.me !== null && depth.has(DATA.me) ? depth.get(DATA.me) : 0;
    if (DATA.network_total > DATA.collapse_threshold) {
        nodes.forEach((n) => {
            if (childrenOf(n.id).length && depth.get(n.id) >= myDepth + DATA.default_depth) collapsed.add(n.id);
        });
    }

    // ----- بناء العقد
    const els = new Map();
    const chip = (state, label) =>
        `<span class="org-node__chip" style="background: color-mix(in srgb, var(--color-state-${state}) 15%, transparent);
         color: var(--color-state-${state})">${label}</span>`;

    nodes.forEach((n) => {
        const el = document.createElement('div');
        el.className = 'org-node'
            + (n.is_me ? ' is-me' : '')
            + (n.is_club ? ' is-club' : '')
            + (n.honorary ? ' is-honorary' : '');
        el.dataset.id = n.id;

        const avatar = n.avatar
            ? `<span class="org-node__avatar"><img src="${n.avatar}" alt=""></span>`
            : `<span class="org-node__avatar">${n.initials}</span>`;

        if (n.honorary) {
            // ⭐ بلا Rep ولا VXP ولا حِمل ولا إشغال — شرفيّ بحت (13.4-ص)
            el.innerHTML = `
                <div class="flex items-center gap-2">
                    ${avatar}
                    <div class="min-w-0">
                        <div class="org-node__name truncate">${n.name}</div>
                        <div class="org-node__meta" style="color: var(--color-state-honor)">★ ${n.position}</div>
                    </div>
                </div>
                <div class="org-node__meta" style="margin-block-start:10px">عنصر شرفيّ — خارج كلّ العدّادات</div>`;
        } else {
            const marks = [];
            if (n.absent_until) marks.push(chip('idle', 'غائب حتى ' + n.absent_until + (n.delegate ? ' — البديل: ' + n.delegate : '')));
            if (n.acting) marks.push(chip('warn', 'قائم بأعمال'));

            const occupancy = n.occupancy === null ? '' : `
                <div data-occupancy style="margin-block-start:6px">
                    <div class="org-node__meta">إشغال ${n.occupancy}%</div>
                    <div class="org-bar"><span style="inline-size:${Math.min(100, n.occupancy)}%;
                        background: var(--color-state-${n.occupancy_state})"></span></div>
                </div>`;

            el.innerHTML = `
                <div class="flex items-center gap-2">
                    ${avatar}
                    <div class="min-w-0 flex-1">
                        <div class="org-node__name truncate">${n.name}</div>
                        <div class="org-node__meta truncate">${n.position}</div>
                    </div>
                </div>
                <div class="flex items-center gap-1 flex-wrap" style="margin-block-start:6px">
                    ${chip(n.rep_state, n.rep_label)}
                    <span class="org-node__chip" style="background: var(--surface-sunken); color: var(--text-muted)">حِمل ${n.load}</span>
                </div>
                ${occupancy}
                <div class="flex items-center gap-1 flex-wrap" style="margin-block-start:4px">${marks.join('')}</div>`;
        }

        const count = childrenOf(n.id).length;
        if (count) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'org-expand';
            btn.dataset.toggle = n.id;
            el.appendChild(btn);
        }

        world.appendChild(el);
        els.set(n.id, el);
    });

    // ----- التخطيط: شجرة مرتّبة، والمواضع المحفوظة تعلوها
    let pos = {};
    let visible = new Set();

    function layout() {
        pos = {};
        visible = new Set();
        let cursor = 0;

        const walk = (id, d) => {
            visible.add(id);
            const children = collapsed.has(id) ? [] : childrenOf(id);
            let x;
            if (!children.length) {
                x = cursor;
                cursor += NODE_W + HGAP;
            } else {
                const xs = children.map((c) => walk(c, d + 1));
                x = (xs[0] + xs[xs.length - 1]) / 2;
            }
            pos[id] = { x: x, y: d * (NODE_H + VGAP) };
            return x;
        };

        roots.forEach((r) => walk(r, 0));
        Object.keys(saved).forEach((id) => { if (pos[id]) pos[id] = { x: saved[id].x, y: saved[id].y }; });
        paint();
    }

    function paint() {
        let maxX = 0, maxY = 0;
        els.forEach((el, id) => {
            if (!visible.has(id)) { el.style.display = 'none'; return; }
            el.style.display = '';
            el.style.left = pos[id].x + 'px';
            el.style.top = pos[id].y + 'px';
            maxX = Math.max(maxX, pos[id].x + NODE_W);
            maxY = Math.max(maxY, pos[id].y + NODE_H);

            const toggle = el.querySelector('.org-expand');
            if (toggle) {
                const n = childrenOf(id).length;
                const isCollapsed = collapsed.has(id);
                toggle.textContent = isCollapsed ? 'وسّع (' + n + ')' : 'اطوِ';
                toggle.style.display = n ? '' : 'none';
            }
        });

        svg.setAttribute('width', maxX + NODE_W);
        svg.setAttribute('height', maxY + NODE_H);
        drawLinks();
    }

    function drawLinks() {
        while (svg.firstChild) svg.removeChild(svg.firstChild);
        visible.forEach((id) => {
            const n = byId.get(id);
            if (!n.parent || !visible.has(n.parent)) return;
            const p = pos[n.parent], c = pos[id];
            const x1 = p.x + NODE_W / 2, y1 = p.y + NODE_H;
            const x2 = c.x + NODE_W / 2, y2 = c.y;
            const mid = (y1 + y2) / 2;
            const path = document.createElementNS(SVGNS, 'path');
            // خطوط ربط منحنية ناعمة تتحرّك مع السحب (13.4-م)
            path.setAttribute('d', `M ${x1} ${y1} C ${x1} ${mid}, ${x2} ${mid}, ${x2} ${y2}`);
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke-width', '1.5');
            path.setAttribute('stroke', 'color-mix(in srgb, var(--color-brand-500) 45%, transparent)');
            svg.appendChild(path);
        });
    }

    // ----- التحريك: سحب اللوحة وتكبيرها
    let tx = 0, ty = 0, k = 1;
    const applyView = () => { world.style.transform = `translate(${tx}px, ${ty}px) scale(${k})`; };

    function centerOn(id) {
        if (!pos[id]) return;
        k = Math.min(k, 1);
        tx = stage.clientWidth / 2 - (pos[id].x + NODE_W / 2) * k;
        ty = stage.clientHeight / 3 - (pos[id].y + NODE_H / 2) * k;
        applyView();
    }

    let panning = false, panStart = null;
    stage.addEventListener('pointerdown', (e) => {
        if (e.target.closest('.org-node')) return;
        panning = true;
        panStart = { x: e.clientX - tx, y: e.clientY - ty };
        stage.classList.add('is-panning');
    });
    window.addEventListener('pointermove', (e) => {
        if (!panning) return;
        tx = e.clientX - panStart.x;
        ty = e.clientY - panStart.y;
        applyView();
    });
    window.addEventListener('pointerup', () => { panning = false; stage.classList.remove('is-panning'); });

    stage.addEventListener('wheel', (e) => {
        e.preventDefault();
        k = Math.max(0.4, Math.min(1.8, k + (e.deltaY < 0 ? 0.08 : -0.08)));
        applyView();
    }, { passive: false });

    // ----- سحب الكارت: الموضع يُحفَظ لكلّ مستخدم محليًّا (13.4-م)
    let drag = null;
    world.addEventListener('pointerdown', (e) => {
        const el = e.target.closest('.org-node');
        if (!el || e.target.closest('.org-expand')) return;
        e.stopPropagation();
        drag = { id: el.dataset.id, x: e.clientX, y: e.clientY, moved: false };
    });
    window.addEventListener('pointermove', (e) => {
        if (!drag) return;
        const dx = (e.clientX - drag.x) / k, dy = (e.clientY - drag.y) / k;
        if (Math.abs(dx) + Math.abs(dy) > 3) drag.moved = true;
        pos[drag.id] = { x: pos[drag.id].x + dx, y: pos[drag.id].y + dy };
        drag.x = e.clientX; drag.y = e.clientY;
        const el = els.get(drag.id);
        el.style.left = pos[drag.id].x + 'px';
        el.style.top = pos[drag.id].y + 'px';
        drawLinks();
    });
    window.addEventListener('pointerup', () => {
        if (!drag) return;
        if (drag.moved) {
            saved[drag.id] = pos[drag.id];
            try { localStorage.setItem(STORE, JSON.stringify(saved)); } catch (e) { /* التخزين المحليّ اختياريّ */ }
        } else {
            openNode(drag.id);
        }
        drag = null;
    });

    // ----- طيّ/توسيع
    world.addEventListener('click', (e) => {
        const btn = e.target.closest('.org-expand');
        if (!btn) return;
        const id = btn.dataset.toggle;
        collapsed.has(id) ? collapsed.delete(id) : collapsed.add(id);
        layout();
    });

    // ----- بوب-أب العقدة
    const nodeModal = document.getElementById('org-node-modal');
    const nodeBody = nodeModal?.querySelector('[data-org-node-body]');
    function openNode(id) {
        const n = byId.get(id);
        if (!n || n.honorary || !nodeModal) return; // العنصر الشرفيّ بلا بروفايل ولا مؤشّرات
        nodeModal.classList.remove('hidden'); nodeModal.classList.add('flex');
        nodeBody.innerHTML = '<p style="color: var(--text-muted)">جارٍ التحميل…</p>';
        fetch(NODE_URL + '/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => r.json())
            .then((d) => {
                nodeBody.innerHTML = `
                    <div class="font-bold mb-1">${d.name}</div>
                    <div class="text-xs mb-3" style="color: var(--text-muted)">#${d.code} · ${d.position} · ${d.entity}</div>
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="card p-2 text-center"><div class="text-xs" style="color: var(--text-muted)">مدّة الخدمة</div><div class="font-bold text-sm mt-1">${d.service_duration}</div></div>
                        <div class="card p-2 text-center"><div class="text-xs" style="color: var(--text-muted)">Kudos</div><div class="font-bold text-sm mt-1">${d.kudos}</div></div>
                        <div class="card p-2 text-center"><div class="text-xs" style="color: var(--text-muted)">الشهادات</div><div class="font-bold text-sm mt-1">${d.certificates}</div></div>
                    </div>
                    <a href="${d.profile_url}" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                       style="background: var(--color-brand-500); color:#04201c">فتح البروفايل</a>`;
            })
            .catch(() => { nodeBody.innerHTML = '<p>تعذّر تحميل التفاصيل — جرّب تاني.</p>'; });
    }

    // ----- أدوات الشريط
    document.querySelector('[data-org="center"]')?.addEventListener('click', () => {
        if (DATA.me) centerOn(DATA.me); else if (roots.length) centerOn(roots[0]);
    });
    document.querySelector('[data-org="relayout"]')?.addEventListener('click', () => {
        saved = {};
        try { localStorage.removeItem(STORE); } catch (e) { /* لا يضرّ */ }
        layout();
        if (DATA.me) centerOn(DATA.me);
    });
    document.querySelector('[data-org="zoom-in"]')?.addEventListener('click', () => { k = Math.min(1.8, k + 0.15); applyView(); });
    document.querySelector('[data-org="zoom-out"]')?.addEventListener('click', () => { k = Math.max(0.4, k - 0.15); applyView(); });
    document.querySelector('[data-org="occupancy"]')?.addEventListener('change', (e) => {
        world.querySelectorAll('[data-occupancy]').forEach((b) => { b.style.display = e.target.checked ? '' : 'none'; });
    });

    // بحث حيّ يقفز للعقدة ويضيئها
    let hit = null;
    document.querySelector('[data-org="search"]')?.addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase();
        if (hit) { els.get(hit)?.classList.remove('is-hit'); hit = null; }
        if (!q) return;
        const found = nodes.find((n) => n.name.toLowerCase().includes(q) || (n.code || '').toLowerCase().includes(q));
        if (!found) return;
        // افتح كلّ الآباء المطويّين حتى تظهر العقدة
        let cur = found.parent;
        while (cur) { collapsed.delete(cur); cur = byId.get(cur)?.parent; }
        layout();
        centerOn(found.id);
        hit = found.id;
        els.get(hit)?.classList.add('is-hit');
    });

    // ----- أعلى رقم وصلته (Best): لون هادئ بلا سهم أحمر نازل (13.4-م)
    const bestEl = document.querySelector('[data-org="best"]');
    if (bestEl) {
        let best = DATA.network_total;
        try {
            best = Math.max(parseInt(localStorage.getItem(STORE + '.best') || '0', 10) || 0, DATA.network_total);
            localStorage.setItem(STORE + '.best', String(best));
        } catch (e) { /* التخزين المحليّ اختياريّ */ }
        bestEl.textContent = best.toLocaleString('ar-EG');
    }

    // ----- لقطة (Snapshot): إطار فيه اسم الكيان وعدد الشبكة وتاريخ اللقطة
    document.querySelector('[data-org="snapshot"]')?.addEventListener('click', () => {
        const modal = document.getElementById('org-snapshot-modal');
        const box = modal?.querySelector('[data-org-snapshot]');
        if (!modal || !box) return;
        modal.classList.remove('hidden'); modal.classList.add('flex');
        box.innerHTML = '<p style="color: var(--text-muted)">جارٍ تجهيز اللقطة…</p>';

        const css = getComputedStyle(document.documentElement);
        const val = (name, fallback) => (css.getPropertyValue(name) || fallback).trim();
        const ids = Array.from(visible);
        if (!ids.length) return;

        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        ids.forEach((id) => {
            minX = Math.min(minX, pos[id].x); minY = Math.min(minY, pos[id].y);
            maxX = Math.max(maxX, pos[id].x + NODE_W); maxY = Math.max(maxY, pos[id].y + NODE_H);
        });

        const pad = 36, head = 74, ratio = 2;
        const w = (maxX - minX) + pad * 2;
        const h = (maxY - minY) + pad * 2 + head;
        const cv = document.createElement('canvas');
        cv.width = w * ratio; cv.height = h * ratio;
        const ctx = cv.getContext('2d');
        ctx.scale(ratio, ratio);

        ctx.fillStyle = val('--surface', '#0b1512');
        ctx.fillRect(0, 0, w, h);
        ctx.strokeStyle = val('--color-brand-600', '#00b39c');
        ctx.lineWidth = 2;
        ctx.strokeRect(8, 8, w - 16, h - 16);

        ctx.fillStyle = val('--text', '#e8f5f2');
        ctx.direction = 'rtl';
        ctx.textAlign = 'right';
        ctx.font = '800 20px Cairo, sans-serif';
        ctx.fillText(@json($chart['entity_name']), w - 26, 40);
        ctx.font = '500 13px Cairo, sans-serif';
        ctx.fillStyle = val('--text-muted', '#9bb3ad');
        ctx.fillText('الشبكة: ' + DATA.network_total + ' · لقطة ' + @json($snapshotDate), w - 26, 62);

        const ox = pad - minX, oy = head + pad - minY;

        ctx.strokeStyle = val('--color-brand-700', '#00806c');
        ctx.lineWidth = 1.4;
        ids.forEach((id) => {
            const n = byId.get(id);
            if (!n.parent || !visible.has(n.parent)) return;
            const p = pos[n.parent], c = pos[id];
            const x1 = p.x + NODE_W / 2 + ox, y1 = p.y + NODE_H + oy;
            const x2 = c.x + NODE_W / 2 + ox, y2 = c.y + oy;
            const mid = (y1 + y2) / 2;
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.bezierCurveTo(x1, mid, x2, mid, x2, y2);
            ctx.stroke();
        });

        ids.forEach((id) => {
            const n = byId.get(id);
            const x = pos[id].x + ox, y = pos[id].y + oy;
            ctx.fillStyle = val('--surface-raised', '#10201c');
            ctx.strokeStyle = n.honorary || n.is_club ? val('--color-state-honor', '#d4af37') : val('--border', '#274');
            ctx.lineWidth = 1.2;
            ctx.beginPath();
            ctx.roundRect(x, y, NODE_W, NODE_H, 14);
            ctx.fill(); ctx.stroke();

            ctx.textAlign = 'right';
            ctx.fillStyle = val('--text', '#e8f5f2');
            ctx.font = '700 13px Cairo, sans-serif';
            ctx.fillText(n.name, x + NODE_W - 12, y + 26, NODE_W - 24);
            ctx.fillStyle = val('--text-muted', '#9bb3ad');
            ctx.font = '500 11px Cairo, sans-serif';
            ctx.fillText(n.position, x + NODE_W - 12, y + 46, NODE_W - 24);
            if (!n.honorary) ctx.fillText('حِمل ' + n.load, x + NODE_W - 12, y + 66, NODE_W - 24);
        });

        try {
            const url = cv.toDataURL('image/png');
            box.innerHTML = `<img src="${url}" alt="لقطة الهيكل" style="max-inline-size:100%; border-radius:12px">
                <a href="${url}" download="org-snapshot.png" class="btn inline-block mt-3 rounded-xl px-4 py-2 text-sm font-semibold"
                   style="background: var(--color-brand-500); color:#04201c">حفظ الصورة</a>`;
        } catch (e) {
            box.innerHTML = '<p>تعذّر تجهيز اللقطة — جرّب تاني.</p>';
        }
    });

    layout();
    if (DATA.me) centerOn(DATA.me); else if (roots.length) centerOn(roots[0]);
})();
</script>
@endif
@endpush
