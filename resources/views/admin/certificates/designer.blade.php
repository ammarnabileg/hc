@extends('layouts.app')

@section('title', 'مصمّم قالب — '.$type->name_ar)

@section('content')
    {{-- ⭐ مصمّم القوالب المرئيّ (12.5-ب · 24.1) — JS خام بلا أيّ مكتبة سحب أو رسم --}}
    <x-page-header
        :title="'مصمّم القالب: '.$type->name_ar"
        subtitle="ارفع خلفيّة الشهادة وحُطّ النصوص فوقها زيّ ما تحبّ — الموضع والحجم والخطّ واللون والمحاذاة والدوران."
        :breadcrumbs="[
            ['label' => 'الشهادات', 'url' => route('admin.certificates.index')],
            ['label' => 'الأنواع والقوالب', 'url' => route('admin.certificates.index', ['tab' => 'types'])],
            ['label' => $type->name_ar],
        ]" />

    {{-- الموبايل: المصمّم للشاشات الكبيرة — تنبيه صريح بدل تجربة مكسورة (2.15-ج) --}}
    <div class="lg:hidden card p-4 text-center">
        <p class="text-sm">
            {{ setting('certificates.designer.mobile_notice', 'مصمّم القالب محتاج شاشة كبيرة — افتحه من اللابتوب عشان السحب يبقى مريح.') }}
        </p>
        <a href="{{ route('admin.certificates.designer.preview', $template) }}"
           class="btn inline-block mt-3 rounded-xl px-4 py-2 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c">شوف المعاينة بس</a>
    </div>

    <div class="hidden lg:block">
        {{-- شريط لاصق: اللغة + الحفظ + المعاينة بالمقاس الحقيقيّ (24.1) --}}
        <div class="sticky-bar card p-3 mb-4 flex items-center gap-3 flex-wrap" style="background: var(--surface-raised)">
            <div class="flex gap-2">
                @foreach (['ar' => 'النسخة العربيّة', 'en' => 'النسخة الإنجليزيّة'] as $code => $label)
                    <a href="{{ route('admin.certificates.designer', [$type, 'lang' => $code]) }}"
                       class="rounded-full px-3 py-1 text-sm motion-standard"
                       style="{{ $language === $code
                            ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                            : 'background: var(--surface-sunken); color: var(--text)' }}">{{ $label }}</a>
                @endforeach
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" data-snap @checked($snap)> شبكة محاذاة (Snap)
            </label>

            <span class="flex-1 text-xs" style="color: var(--text-muted)" data-save-note>
                نسخة القالب رقم {{ $template->version }}
            </span>

            <a href="{{ route('admin.certificates.designer.preview', $template) }}" target="_blank" rel="noopener"
               class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-sunken)">معاينة بالمقاس الحقيقيّ</a>

            @can('certificate_templates.edit')
                <button type="button" data-save
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">حفظ التصميم</button>
            @endcan
        </div>

        <div class="grid grid-cols-[1fr_20rem] gap-4 items-start">
            {{-- ------------------------------------------------ اللوحة --}}
            <div>
                <div dir="ltr" id="designer-canvas" class="relative overflow-hidden rounded-xl select-none"
                     style="width: 100%; aspect-ratio: {{ $template->width_px }} / {{ $template->height_px }};
                            background: {{ $template->background_path
                                ? 'url('.\Illuminate\Support\Facades\Storage::disk('public')->url($template->background_path).') center/cover no-repeat'
                                : 'var(--surface-sunken)' }};
                            border: 1px solid var(--border)">
                    <div class="absolute inset-0 pointer-events-none hidden" data-grid
                         style="background-image:
                            linear-gradient(to right, color-mix(in srgb, var(--color-brand-500) 25%, transparent) 1px, transparent 1px),
                            linear-gradient(to bottom, color-mix(in srgb, var(--color-brand-500) 25%, transparent) 1px, transparent 1px);
                            background-size: {{ $grid }}% {{ $grid }}%"></div>
                </div>

                {{-- خلفيّة الشهادة: من المكتبة أو مسح (12.5-ب) --}}
                @can('certificate_templates.edit')
                    <form method="post" action="{{ route('admin.certificates.designer.background', $template) }}"
                          class="card p-3 mt-3 flex items-end gap-3 flex-wrap">
                        @csrf
                        <div class="flex-1 min-w-[14rem]">
                            <x-form.input name="background_path" label="خلفيّة الشهادة (مسار من مكتبة الوسائط)"
                                          :value="$template->background_path" />
                        </div>
                        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">اضبط الخلفيّة</button>
                        <button name="clear" value="1" class="btn rounded-xl px-4 py-2 text-sm"
                                style="background: var(--surface-sunken)">امسحها</button>
                        <a href="{{ route('admin.media.index') }}" class="text-xs underline">افتح المكتبة</a>
                    </form>
                @endcan
            </div>

            {{-- ------------------------------------------------ الجانب --}}
            <div class="space-y-4">
                @can('certificate_templates.edit')
                    <div class="card p-3">
                        <h2 class="font-bold text-sm mb-2">إضافة طبقة</h2>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-add="text" class="btn rounded-xl px-3 py-2 text-xs"
                                    style="background: var(--surface-sunken)">نصّ ثابت</button>
                            <button type="button" data-add="field" class="btn rounded-xl px-3 py-2 text-xs"
                                    style="background: var(--surface-sunken)">حقل من البيانات</button>
                            <button type="button" data-add="qr" class="btn rounded-xl px-3 py-2 text-xs"
                                    style="background: var(--surface-sunken)">QR</button>
                        </div>
                    </div>
                @endcan

                {{-- لوحة الطبقات: إظهار/إخفاء · رفع/إنزال · قفل (12.5-ب) --}}
                <div class="card p-3">
                    <h2 class="font-bold text-sm mb-2">الطبقات</h2>
                    <ul class="space-y-1 max-h-64 overflow-y-auto" data-layers-panel></ul>
                </div>

                {{-- خصائص الطبقة المختارة --}}
                <div class="card p-3 space-y-3" data-props hidden>
                    <h2 class="font-bold text-sm">خصائص الطبقة</h2>

                    <label class="block text-sm">
                        الاسم
                        <input type="text" data-prop="label" class="w-full rounded-lg px-2 py-1 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>

                    <label class="block text-sm">
                        نصّ ثابت
                        <input type="text" data-prop="text" class="w-full rounded-lg px-2 py-1 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>

                    <label class="block text-sm">
                        الحقل
                        <select data-prop="field" class="w-full rounded-lg px-2 py-1 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">— بلا حقل —</option>
                            @foreach ($fields as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                            {{-- ربط بأعمدة قاعدة البيانات بلا حدود — من قائمة آمنة (12.5-ب) --}}
                            @foreach ($bindableTables as $table => $meta)
                                @foreach ($meta['columns'] as $column => $label)
                                    <option value="bind:{{ $table }}.{{ $column }}">{{ $meta['label'] }} ← {{ $label }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </label>

                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <label class="block">X %
                            <input type="number" step="0.1" data-prop="x" class="w-full rounded-lg px-2 py-1 mt-1"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>
                        <label class="block">Y %
                            <input type="number" step="0.1" data-prop="y" class="w-full rounded-lg px-2 py-1 mt-1"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>
                        <label class="block">الحجم
                            <input type="number" step="1" data-prop="size" class="w-full rounded-lg px-2 py-1 mt-1"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>
                        <label class="block">الدوران °
                            <input type="number" step="1" data-prop="rotate" class="w-full rounded-lg px-2 py-1 mt-1"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>
                    </div>

                    <label class="block text-sm">
                        المحاذاة
                        <select data-prop="align" class="w-full rounded-lg px-2 py-1 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="start">لليمين</option>
                            <option value="center">توسيط</option>
                            <option value="end">لليسار</option>
                        </select>
                    </label>

                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <label class="block">اللون
                            <input type="color" data-prop="color" class="w-full h-9 rounded-lg mt-1"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border)">
                        </label>
                        <label class="block">الخطّ
                            <input type="text" data-prop="font" class="w-full rounded-lg px-2 py-1 mt-1"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" data-prop="bold"> عريض
                    </label>

                    {{-- حقل شرطيّ: الفاضي لا يظهر (12.5-ب) --}}
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" data-prop="conditional"> يختفي لو قيمته فاضية
                    </label>

                    <button type="button" data-delete-layer class="text-xs underline"
                            style="color: var(--color-state-danger)">حذف الطبقة</button>
                </div>

                @can('certificate_templates.edit')
                    <form method="post" action="{{ route('admin.certificates.designer.reset', $template) }}"
                          onsubmit="return confirm('نرجّع التصميم الافتراضيّ؟ اللي عملته هيتشال.')">
                        @csrf
                        <button class="text-xs underline">↺ إعادة للتصميم الافتراضيّ</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    @include('admin.courses.partials.toast')

    @push('scripts')
        <script>
            /* =================================================================
             | مصمّم القوالب — JS خام (12.5-ب).
             |
             | الوحدات: X/Y كسور 0…1 · حجم الخطّ بكسل على عرض 1754 —
             | نفس وحدات الراسم على الخادم، فالمعاينة = الشهادة الصادرة.
             ================================================================= */
            (function () {
                const canvas = document.getElementById('designer-canvas');
                if (!canvas) return;

                const REFERENCE = {{ \App\Services\Admin\Content\TemplateDesigner::REFERENCE_WIDTH }};
                const GRID = {{ $grid }} / 100;
                const SAVE_URL = @json(route('admin.certificates.designer.save', $type));
                const LANGUAGE = @json($language);
                const SAMPLE = @json($sample);
                const FIELD_LABELS = @json($fields);

                let layers = @json($layers);
                let selectedId = null;
                let snap = document.querySelector('[data-snap]')?.checked ?? true;

                const panel = document.querySelector('[data-layers-panel]');
                const props = document.querySelector('[data-props]');
                const note = document.querySelector('[data-save-note]');
                const grid = canvas.querySelector('[data-grid]');

                /* ------------------------------------------------------ الرسم */
                function render() {
                    canvas.querySelectorAll('[data-layer]').forEach((el) => el.remove());
                    const scale = canvas.clientWidth / REFERENCE;

                    layers
                        .slice()
                        .sort((a, b) => a.z - b.z)
                        .forEach((layer) => {
                            const el = document.createElement('div');
                            el.dataset.layer = layer.id;
                            el.className = 'absolute';
                            el.style.left = `${layer.x * 100}%`;
                            el.style.top = `${layer.y * 100}%`;
                            el.style.opacity = layer.visible ? 1 : 0.35;
                            el.style.cursor = layer.locked ? 'not-allowed' : 'grab';
                            el.style.outline = layer.id === selectedId
                                ? '2px dashed var(--color-brand-500)'
                                : 'none';

                            if (layer.type === 'qr') {
                                el.style.width = `${layer.size * 100}%`;
                                el.style.aspectRatio = '1';
                                el.style.transform = 'translate(-50%, -50%)';
                                el.style.background = '#fff';
                                el.style.color = '#000';
                                el.style.display = 'grid';
                                el.style.placeItems = 'center';
                                el.style.fontWeight = '700';
                                el.textContent = 'QR';
                            } else {
                                const shift = layer.align === 'start' ? '-100%' : layer.align === 'end' ? '0%' : '-50%';
                                el.style.whiteSpace = 'nowrap';
                                el.style.transform = `translate(${shift}, -100%) rotate(${-layer.rotate}deg)`;
                                el.style.fontSize = `${Math.max(6, layer.size * scale)}px`;
                                el.style.fontWeight = layer.bold ? 800 : 400;
                                el.style.color = layer.color;
                                el.style.fontFamily = layer.font || 'inherit';
                                el.setAttribute('dir', 'auto');
                                el.textContent = valueOf(layer) || layer.label;
                            }

                            canvas.appendChild(el);
                        });

                    renderPanel();
                }

                /* «نصّ ثابت + الحقل» — نفس منطق الخادم بالضبط */
                function valueOf(layer) {
                    const dynamic = layer.field ? (SAMPLE[layer.field] ?? '') : '';
                    if (layer.field && dynamic === '') return '';
                    const stat = layer.text || '';
                    return `${stat}${stat && dynamic ? ' ' : ''}${dynamic}`.trim();
                }

                /* ------------------------------------------- لوحة الطبقات */
                function renderPanel() {
                    if (!panel) return;
                    panel.innerHTML = '';

                    layers
                        .slice()
                        .sort((a, b) => b.z - a.z)
                        .forEach((layer) => {
                            const li = document.createElement('li');
                            li.className = 'flex items-center gap-1 text-xs rounded-lg px-2 py-1';
                            li.style.background = layer.id === selectedId ? 'var(--surface-sunken)' : 'transparent';

                            const name = document.createElement('button');
                            name.type = 'button';
                            name.className = 'flex-1 text-start truncate';
                            name.textContent = layer.label + (layer.field ? ` · ${FIELD_LABELS[layer.field] ?? layer.field}` : '');
                            name.addEventListener('click', () => select(layer.id));

                            li.append(name);
                            li.append(iconButton(layer.visible ? '👁' : '🚫', 'إظهار/إخفاء', () => {
                                layer.visible = !layer.visible;
                                render();
                            }));
                            li.append(iconButton(layer.locked ? '🔒' : '🔓', 'قفل', () => {
                                layer.locked = !layer.locked;
                                render();
                            }));
                            li.append(iconButton('▲', 'رفع للأعلى', () => { moveZ(layer, 1); }));
                            li.append(iconButton('▼', 'إنزال للأسفل', () => { moveZ(layer, -1); }));

                            panel.append(li);
                        });
                }

                function iconButton(symbol, title, onClick) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.title = title;
                    btn.setAttribute('aria-label', title);
                    btn.className = 'px-1';
                    btn.textContent = symbol;
                    btn.addEventListener('click', onClick);
                    return btn;
                }

                function moveZ(layer, direction) {
                    const sorted = layers.slice().sort((a, b) => a.z - b.z);
                    const index = sorted.findIndex((l) => l.id === layer.id);
                    const swap = sorted[index + direction];
                    if (!swap) return;
                    const tmp = layer.z;
                    layer.z = swap.z;
                    swap.z = tmp;
                    render();
                }

                /* ------------------------------------------------ الخصائص */
                function select(id) {
                    selectedId = id;
                    const layer = layers.find((l) => l.id === id);
                    if (!layer || !props) return;

                    props.hidden = false;
                    props.querySelectorAll('[data-prop]').forEach((input) => {
                        const key = input.dataset.prop;
                        let value = layer[key];
                        if (key === 'x' || key === 'y') value = +(value * 100).toFixed(1);
                        if (input.type === 'checkbox') input.checked = !!value;
                        else input.value = value ?? '';
                    });

                    render();
                }

                props?.querySelectorAll('[data-prop]').forEach((input) => {
                    input.addEventListener('input', () => {
                        const layer = layers.find((l) => l.id === selectedId);
                        if (!layer) return;
                        const key = input.dataset.prop;

                        if (input.type === 'checkbox') layer[key] = input.checked;
                        else if (key === 'x' || key === 'y') layer[key] = Math.min(1, Math.max(0, (+input.value || 0) / 100));
                        else if (key === 'size' || key === 'rotate') layer[key] = +input.value || 0;
                        else layer[key] = input.value;

                        render();
                    });
                });

                document.querySelector('[data-delete-layer]')?.addEventListener('click', () => {
                    layers = layers.filter((l) => l.id !== selectedId);
                    selectedId = null;
                    if (props) props.hidden = true;
                    render();
                });

                /* -------------------------------------------- إضافة طبقة */
                document.querySelectorAll('[data-add]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const kind = btn.dataset.add;
                        const id = `l${Date.now()}${Math.floor(Math.random() * 1000)}`;
                        layers.push({
                            id,
                            type: kind === 'qr' ? 'qr' : 'text',
                            label: kind === 'qr' ? 'QR التحقّق' : kind === 'field' ? 'حقل جديد' : 'نصّ جديد',
                            text: kind === 'text' ? 'نصّ جديد' : '',
                            field: kind === 'field' ? 'holder_name' : null,
                            binding: null,
                            x: 0.5,
                            y: 0.5,
                            size: kind === 'qr' ? 0.12 : {{ (int) setting('certificates.designer.default_font_size', 32) }},
                            font: @json(setting('certificates.designer.default_font', 'Cairo')),
                            color: @json(setting('certificates.designer.default_color', '#e8f5f2')),
                            align: 'center',
                            rotate: 0,
                            bold: false,
                            max_chars: {{ (int) setting('certificates.render.max_chars_per_line', 48) }},
                            visible: true,
                            locked: false,
                            z: layers.length + 1,
                            conditional: false,
                        });
                        select(id);
                    });
                });

                /* ------------------------------------- السحب الحرّ + Snap */
                let dragging = null;

                canvas.addEventListener('pointerdown', (e) => {
                    const el = e.target.closest('[data-layer]');
                    if (!el) return;
                    const layer = layers.find((l) => l.id === el.dataset.layer);
                    if (!layer || layer.locked) return;

                    dragging = layer;
                    select(layer.id);
                    canvas.setPointerCapture(e.pointerId);
                });

                canvas.addEventListener('pointermove', (e) => {
                    if (!dragging) return;
                    const rect = canvas.getBoundingClientRect();
                    let x = (e.clientX - rect.left) / rect.width;
                    let y = (e.clientY - rect.top) / rect.height;

                    if (snap) {
                        x = Math.round(x / GRID) * GRID;
                        y = Math.round(y / GRID) * GRID;
                    }

                    dragging.x = Math.min(1, Math.max(0, x));
                    dragging.y = Math.min(1, Math.max(0, y));
                    render();
                });

                canvas.addEventListener('pointerup', () => {
                    if (!dragging) return;
                    dragging = null;
                    select(selectedId);
                });

                document.querySelector('[data-snap]')?.addEventListener('change', (e) => {
                    snap = e.target.checked;
                    grid?.classList.toggle('hidden', !snap);
                });

                grid?.classList.toggle('hidden', !snap);

                /* ----------------------------------------------- الحفظ */
                document.querySelector('[data-save]')?.addEventListener('click', () => {
                    fetch(SAVE_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            language: LANGUAGE,
                            layers,
                            width_px: {{ (int) $template->width_px }},
                            height_px: {{ (int) $template->height_px }},
                        }),
                    })
                        .then((r) => (r.ok ? r.json() : Promise.reject()))
                        .then((data) => {
                            if (note) note.textContent = `${data.message} — نسخة رقم ${data.version}`;
                            window.hcToast?.(data.message);
                        })
                        .catch(() => window.hcToast?.('التصميم ما اتحفظش — جرّب تاني.', 'danger'));
                });

                window.addEventListener('resize', render);
                render();
            })();
        </script>
    @endpush
@endsection
