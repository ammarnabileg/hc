@extends('layouts.admin')

@section('title', setting('cv.template.admin.decor_page_title', 'المحرّر المرئيّ: ') . $template->name)

@section('content')
    {{--
     | ⭐ المحرّر المرئيّ (Drag-drop) لقوالب الـCV — المرحلة 2/2 (12.7-ب).
     | المرحلة 1 (كوميت سابق) بنت الخلفيّة كاملةً: عمود decor_layers · CvTemplateDecor::sanitize()
     | · مسار الحفظ admin.cv-templates.decor.update · جزء الرسم المشترك
     | resources/views/cv/templates/partials/decor-layer.blade.php. هذه الشاشة
     | واجهته المرئيّة وحدها — الخادم لا يتغيّر فيه سطرٌ واحد (2.13).
     |
     | نموذج التفاعل منسوخٌ بنيويًّا من محرّر الاستوديو (resources/views/admin/studio/edit.blade.php
     | · 12.5-ب/12.14): pointer events · شبكة محاذاة اختياريّة · لوحة طبقات
     | بأزرار ظاهر/▲/▼/✕ · مزامنة حقول layers[i][key] المخفيّة قبل الإرسال.
     | والفروق الجوهريّة الخاصّة بالـCV (مُقرَّرةٌ في المرحلة 1، لا نعيد التفكير
     | فيها هنا):
     |  - الإحداثيّات **نسبةٌ مئويّة (0-100)** لا بكسل — من عرض/ارتفاع الكانفس
     |    نفسه، فتطابق مقاس `.sheet` (210mm×297mm) الحقيقيّ بلا أيّ تحويل.
     |  - خلفيّة الكانفس **iframe حيّ** يحمّل معاينة القالب الفعليّة بمحتوًى
     |    متدفّق حقيقيّ (خبرات/تعليم) — لا كانفسًا فارغًا ولا صورة سكرين-شوت.
     |  - نوعا الطبقة فقط: صورة (منتقي المكتبة الموجود) ونصٌّ ثابتٌ زخرفيّ —
     |    بلا ربط حقل بيانات إطلاقًا.
    --}}
    <x-page-header
        :title="setting('cv.template.admin.decor_page_title', 'المحرّر المرئيّ: ') . $template->name"
        :subtitle="setting('cv.template.admin.decor_page_subtitle', 'اسحب عناصر الديكور فوق معاينة حقيقيّة لمحتوى القالب — نصٌّ أو صورة، بلا ربط ببيانات السيرة.')"
        :breadcrumbs="[
            ['label' => setting('cv.template.admin.section_label', 'الإعدادات والنظام'), 'url' => route('admin.settings.index')],
            ['label' => setting('cv.template.admin.page_title', 'قوالب السيرة الذاتيّة'), 'url' => route('admin.cv-templates.index')],
            ['label' => $template->name],
        ]" />

    <form method="post" action="{{ route('admin.cv-templates.decor.update', $template) }}" id="decor-form">
        @csrf
        @method('put')

        <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
            {{-- الكانفس: iframe المعاينة الحيّة + غطاء شفّاف يلتقط السحب (12.7-ب) --}}
            <div class="card p-4 space-y-3">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <h2 class="font-bold text-sm">{{ setting('cv.template.admin.decor_canvas_label', 'الكانفس') }}</h2>
                    <label class="flex items-center gap-2 text-xs">
                        <input type="checkbox" data-decor-snap @checked($snap)>
                        {{ setting('cv.template.admin.decor_snap_label', 'شبكة محاذاة (Snap)') }}
                    </label>
                </div>

                {{-- مقاس نسبيّ A4 (210:297) — لا مقاسات حرّة كالاستوديو --}}
                <div class="mx-auto" style="max-width: 480px">
                    <div dir="ltr" id="decor-canvas" class="relative overflow-hidden rounded-xl select-none"
                         style="width: 100%; aspect-ratio: 210 / 297; background: #e9edec; border: 1px solid var(--border)">
                        {{-- المحتوى المتدفّق الحقيقيّ لهذا القالب بعينه — لا طبقة زخرفيّة هنا
                             (الكانفس فوقه هو من يرسمها، فلا ازدواج بصريّ أثناء السحب) --}}
                        <iframe src="{{ route('admin.cv-templates.decor.preview', $template) }}"
                                title="{{ setting('cv.template.admin.decor_preview_title', 'معاينة المحتوى الحقيقيّ') }}"
                                tabindex="-1" aria-hidden="true" loading="lazy"
                                style="position:absolute; inset:0; width:100%; height:100%; border:0; pointer-events:none;"></iframe>

                        <div class="absolute inset-0 pointer-events-none hidden" data-decor-grid
                             style="background-image:
                                linear-gradient(to right, color-mix(in srgb, var(--color-brand-500) 25%, transparent) 1px, transparent 1px),
                                linear-gradient(to bottom, color-mix(in srgb, var(--color-brand-500) 25%, transparent) 1px, transparent 1px);
                                background-size: {{ (int) $grid }}% {{ (int) $grid }}%"></div>

                        {{-- الغطاء الشفّاف: يلتقط pointer events فوق الـiframe، وفيه عناصر الطبقات --}}
                        <div class="absolute inset-0" data-decor-overlay style="touch-action:none"></div>
                    </div>
                </div>

                <p class="text-xs" style="color: var(--text-muted)">{{ setting('cv.template.admin.decor_hint', 'اسحب أيّ عنصر لتغيير موضعه — المحتوى خلفه معاينة حقيقيّة لقالب السيرة.') }}</p>
            </div>

            <div class="space-y-4">
                <div class="card p-4 space-y-3">
                    <h2 class="font-bold text-sm">{{ setting('cv.template.admin.decor_layers_panel_label', 'الطبقات') }}</h2>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" data-decor-add="text" class="btn rounded-xl px-3 py-2 text-xs"
                                style="background: var(--surface-sunken)">{{ setting('cv.template.admin.decor_add_text_label', '+ نصّ') }}</button>
                        <button type="button" data-decor-add="image" class="btn rounded-xl px-3 py-2 text-xs"
                                style="background: var(--surface-sunken)">{{ setting('cv.template.admin.decor_add_image_label', '+ صورة') }}</button>
                    </div>

                    <ul class="space-y-1 max-h-64 overflow-y-auto rounded-xl p-2" style="background: var(--surface-sunken)"
                        data-decor-layers-panel></ul>
                    <p class="text-xs" data-decor-empty-hint style="color: var(--text-muted)">
                        {{ setting('cv.template.admin.decor_empty_hint', 'لا عناصر زخرفيّة بعد — أضف نصًّا أو صورة.') }}
                    </p>
                </div>

                {{-- خصائص العنصر المختار --}}
                <div class="card p-4 space-y-2" data-decor-props hidden>
                    <h2 class="font-bold text-sm">{{ setting('cv.template.admin.decor_properties_label', 'خصائص العنصر') }}</h2>
                    <div data-decor-props-fields class="space-y-3"></div>
                </div>

                {{-- ⭐ الحفظ يكتب هنا حقول layers[i][key] المخفيّة بنفس صيغة
                    CvTemplateDecor::sanitize() المتوقَّعة — الخادم لا يتغيّر فيه سطرٌ واحد (2.13) --}}
                <div data-decor-hidden-inputs></div>

                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                    {{ setting('cv.template.admin.decor_save_label', 'حفظ الطبقة الزخرفيّة') }}
                </button>
            </div>
        </div>
    </form>

    {{-- قناة اختيار الصورة الوحيدة: زرّ «اختَر صورة» في لوحة الخصائص يرسل هنا
         بـdata-media-pick، وسكربت الصفحة يقرأ قيمتها/معاينتها للطبقة المختارة --}}
    <div class="hidden">
        <input type="hidden" id="cv-decor-image-target" name="cv-decor-image-target">
        <span data-media-preview="cv-decor-image-target"></span>
    </div>

    {{-- ⭐ نفس بوب-أب «اختَر من المكتبة / ارفع جديد» — لا منتقي وسائط ثانٍ (2.14-ب) --}}
    @include('admin.courses.partials.media-picker-modal')
@endsection

@push('scripts')
@php
    /*
     | نصوص السكربت من الإعدادات (2.13-أ): لا حرفَ عربيّ داخل `<script>`.
     */
    $jsText = [
        'text_layer' => setting('cv.template.admin.decor_js_text_layer', 'نصّ'),
        'image_layer' => setting('cv.template.admin.decor_js_image_layer', 'صورة'),
        'behind_badge' => setting('cv.template.admin.decor_js_behind_badge', 'خلف المحتوى'),
        'toggle_visibility' => setting('cv.template.admin.decor_js_toggle_visibility', 'إظهار/إخفاء (في هذه الشاشة فقط)'),
        'move_up' => setting('cv.template.admin.decor_js_move_up', 'لأعلى'),
        'move_down' => setting('cv.template.admin.decor_js_move_down', 'لأسفل'),
        'delete_layer' => setting('cv.template.admin.decor_js_delete_layer', 'حذف'),
        'text_label' => setting('cv.template.admin.decor_js_text_label', 'النصّ'),
        'size_label' => setting('cv.template.admin.decor_js_size_label', 'حجم الخطّ'),
        'color_label' => setting('cv.template.admin.decor_js_color_label', 'اللون'),
        'align_label' => setting('cv.template.admin.decor_js_align_label', 'المحاذاة'),
        'align_right' => setting('cv.template.admin.decor_js_align_right', 'يمين'),
        'align_center' => setting('cv.template.admin.decor_js_align_center', 'وسط'),
        'align_left' => setting('cv.template.admin.decor_js_align_left', 'يسار'),
        'rotate_label' => setting('cv.template.admin.decor_js_rotate_label', 'الدوران'),
        'behind_label' => setting('cv.template.admin.decor_js_behind_label', 'ضعها خلف المحتوى'),
        'image_label' => setting('cv.template.admin.decor_js_image_label', 'الصورة'),
        'width_label' => setting('cv.template.admin.decor_js_width_label', 'العرض %'),
        'height_label' => setting('cv.template.admin.decor_js_height_label', 'الطول %'),
        'opacity_label' => setting('cv.template.admin.decor_js_opacity_label', 'الشفافيّة %'),
        'pick_image' => setting('cv.template.admin.decor_js_pick_image', 'اختر صورة'),
        'no_image' => setting('cv.template.admin.decor_js_no_image', 'بلا صورة'),
        'empty_text_placeholder' => setting('cv.template.admin.decor_js_empty_text_placeholder', '(نصّ فارغ)'),
    ];

    /*
     | الصورة المحفوظة تصل من القاعدة كمسارٍ خامّ فقط (`path`) — ونحتاج رابطها
     | الفعليّ لعرض معاينتها في الكانفس فورًا بلا انتظار اختيارٍ جديد؛ `url`
     | هنا حقلٌ مُشتقٌّ للعرض وحده، مُستبعدٌ صراحةً من حقول الحفظ (راجع
     | FIELDS_BY_TYPE في السكربت) فلا يصل الخادم أصلًا.
     */
    $initialLayers = collect($template->decorLayers())->map(function (array $layer) {
        if (($layer['type'] ?? null) === 'image' && ! empty($layer['path'])) {
            $layer['url'] = \Illuminate\Support\Facades\Storage::url($layer['path']);
        }

        return $layer;
    })->values()->all();
@endphp

<script>
    const T = @json($jsText);
/*
 | ⭐ المحرّر المرئيّ (Drag-drop) لقوالب الـCV (12.7-ب المرحلة 2/2) — جافاسكربت
 | خامّ بلا أيّ مكتبة خارجيّة، بنفس نموذج تفاعل محرّر الاستوديو (12.5-ب/12.14):
 | pointer events + شبكة محاذاة اختياريّة + لوحة طبقات. والفروق المتعمَّدة:
 |
 |  1) الإحداثيّات **نسبةٌ مئويّة من الكانفس نفسه** لا بكسل مطلق — تطابق
 |     `.sheet` (210mm×297mm) الحقيقيّ بلا أيّ تحويل (راجع CvTemplateDecor).
 |  2) z **لا** حقلٌ رقميّ حرّ: يُشتقّ دائمًا من ترتيب القائمة + مفتاح
 |     «ضعها خلف المحتوى» — فتح/إغلاق هذا المفتاح يقرّر السالب/الموجب (خلف
 |     المحتوى المتدفّق أو فوقه، بالضبط كما يقرأه decor-layer.blade.php)،
 |     والسحب في القائمة (▲/▼) يقرّر ترتيب التكديس **داخل** كلّ مجموعة.
 |     ⚠️ وهذا يعني: فتح هذه الشاشة وحفظها فورًا **يُعيد ترقيم** قيم z إلى
 |     مخطّطٍ كثيفٍ قانونيّ (١-، ٢-، … خلف / ١، ٢، … أمام) بدل أيّ قيمةٍ سابقة
 |     كانت مكتوبةً مباشرةً عبر الـAPI — الترتيب النسبيّ (خلف/أمام كلٍّ) يبقى
 |     كما هو، لكنْ لا الأرقام المطلقة بالضرورة.
 |  3) «إخفاء» (👁/🚫) في هذه الشاشة **تأثيرٌ محليٌّ للتحرير فقط** — لا حقل
 |     ظهور/إخفاء في مخطّط CvTemplateDecor::sanitize() أصلًا (بخلاف طبقات
 |     الاستوديو)، فلا يُرسَل للخادم ولا يُحفَظ؛ يعود كلّ عنصرٍ ظاهرًا بفتح
 |     الشاشة من جديد. الهدف تسهيل ترتيب عناصر متراكبة أثناء التحرير وحده.
 |
 | والحفظ يكتب **حقول layers[i][key] المخفيّة** بنفس صيغة الفورم القديمة —
 | فمتحكّم الحفظ (`CvTemplateAdminController::updateDecor`) ومنظّف الطبقات
 | (`CvTemplateDecor::sanitize`) لا يتغيّر فيهما سطرٌ واحد (2.13).
 */
(function () {
    var canvas = document.getElementById('decor-canvas');
    var overlay = canvas ? canvas.querySelector('[data-decor-overlay]') : null;
    if (!canvas || !overlay) { return; }

    var GRID = {{ (int) $grid }};
    var SHEET_PX = 793.7; // 210mm ≈ 793.7px عند 96dpi — لتحويل حجم الخطّ (pt) لمقاس الكانفس المصغَّر فقط (عرضٌ بصريّ، والقيمة المحفوظة تبقى pt كما هي)

    var FIELDS_BY_TYPE = {
        text: ['type', 'x', 'y', 'z', 'text', 'size', 'color', 'align', 'rotate'],
        image: ['type', 'x', 'y', 'z', 'path', 'w', 'h', 'opacity'],
    };

    var layers = (@json($initialLayers)).map(function (layer, index) {
        layer._uid = 'd' + index + '_' + Date.now();
        // مُشتقٌّ من إشارة z المحفوظة مسبقًا — راجع ملاحظة (2) أعلاه
        layer.behind = (Number(layer.z) || 0) < 0;
        return layer;
    });
    var selectedUid = null;

    var panelEl = document.querySelector('[data-decor-layers-panel]');
    var emptyHint = document.querySelector('[data-decor-empty-hint]');
    var props = document.querySelector('[data-decor-props]');
    var propsFields = document.querySelector('[data-decor-props-fields]');
    var hiddenBox = document.querySelector('[data-decor-hidden-inputs]');
    var grid = canvas.querySelector('[data-decor-grid]');
    var snapCheckbox = document.querySelector('[data-decor-snap]');
    var snap = snapCheckbox ? snapCheckbox.checked : {{ $snap ? 'true' : 'false' }};
    var imageTargetField = document.getElementById('cv-decor-image-target');

    /*
     | ⭐ z تُشتقّ دائمًا من الترتيب + «خلف المحتوى» — لا قيمةٌ حرّة تُكتَب يدويًّا.
     | «فوق» في القائمة داخل كلّ مجموعة = «أقرب للمحتوى/المشاهد» في الاثنتين:
     | فأوّل عنصرٍ «أماميّ» في القائمة يأخذ أعلى z موجب (الأقرب للمشاهد)،
     | وأوّل عنصرٍ «خلفيّ» في القائمة يأخذ أقرب z سالب للصفر (الأقرب للمحتوى
     | من الخلف)، وكلّما نزل العنصر في مجموعته ابتعد أكثر.
     */
    function recomputeZ() {
        var behind = layers.filter(function (l) { return !!l.behind; });
        var front = layers.filter(function (l) { return !l.behind; });

        behind.forEach(function (l, i) { l.z = -(i + 1); });
        front.forEach(function (l, i) { l.z = front.length - i; });
    }

    recomputeZ();

    /* ------------------------------------------------------ الرسم على الكانفس */
    function render() {
        overlay.querySelectorAll('[data-decor-layer]').forEach(function (el) { el.remove(); });
        var scale = canvas.clientWidth / SHEET_PX;

        layers.forEach(function (layer) {
            var el = layer.type === 'image' ? document.createElement('img') : document.createElement('div');
            el.dataset.decorLayer = layer._uid;
            el.style.position = 'absolute';
            el.style.left = (layer.x || 0) + '%';
            el.style.top = (layer.y || 0) + '%';
            el.style.cursor = 'grab';
            el.style.outline = layer._uid === selectedUid ? '2px dashed var(--color-brand-500)' : 'none';

            if (layer._hidden) {
                el.style.opacity = '0.12';
                el.style.pointerEvents = 'none';
            } else {
                el.style.pointerEvents = 'auto';
            }

            if (layer.type === 'image') {
                // ⭐ نفس صيغة الرسم الإنتاجيّ بالحرف (decor-layer.blade.php): left/top/inline-size/block-size
                // نسبةٌ مئويّة، object-fit:cover — فما تراه هنا هو ما سيظهر فعليًّا.
                el.alt = '';
                el.src = layer.url || '';
                el.style.width = (layer.w != null ? layer.w : 100) + '%';
                el.style.height = (layer.h != null ? layer.h : 100) + '%';
                el.style.objectFit = 'cover';
                if (!layer._hidden) {
                    el.style.opacity = String((layer.opacity != null ? layer.opacity : 100) / 100);
                }
                if (!layer.url) {
                    el.style.background = 'var(--surface-sunken)';
                    el.style.border = '1px dashed var(--border)';
                }
            } else {
                el.style.fontSize = Math.max(6, (layer.size || 14) * 1.3333 * scale) + 'px';
                el.style.color = layer.color || '#000000';
                el.style.textAlign = layer.align || 'right';
                el.style.transform = 'rotate(' + (layer.rotate || 0) + 'deg)';
                el.style.whiteSpace = 'pre-wrap';
                el.style.maxWidth = '92%';
                el.style.margin = '0';
                el.setAttribute('dir', 'auto');
                el.textContent = layer.text || T.empty_text_placeholder;
            }

            overlay.appendChild(el);
        });

        renderPanel();
        syncHiddenInputs();
    }

    /* ------------------------------------------------------- لوحة الطبقات */
    function renderPanel() {
        if (!panelEl) { return; }
        panelEl.innerHTML = '';
        if (emptyHint) { emptyHint.hidden = layers.length > 0; }

        layers.forEach(function (layer, index) {
            var li = document.createElement('li');
            li.className = 'flex items-center gap-1 text-xs rounded-lg px-2 py-1';
            li.style.background = layer._uid === selectedUid ? 'var(--surface-raised)' : 'transparent';

            var name = document.createElement('button');
            name.type = 'button';
            name.className = 'flex-1 text-start truncate';
            var label = layer.type === 'image' ? T.image_layer : T.text_layer;
            name.textContent = label + ' #' + (index + 1) + (layer.behind ? ' · ' + T.behind_badge : '');
            name.addEventListener('click', function () { select(layer._uid); });

            li.append(name);
            li.append(iconButton(layer._hidden ? '🚫' : '👁', T.toggle_visibility, function () {
                layer._hidden = !layer._hidden;
                render();
            }));
            li.append(iconButton('▲', T.move_up, function () { moveIndex(index, index - 1); }));
            li.append(iconButton('▼', T.move_down, function () { moveIndex(index, index + 1); }));
            li.append(iconButton('✕', T.delete_layer, function () {
                layers = layers.filter(function (l) { return l._uid !== layer._uid; });
                if (selectedUid === layer._uid) { selectedUid = null; if (props) { props.hidden = true; } }
                recomputeZ();
                render();
            }));

            panelEl.append(li);
        });
    }

    function iconButton(symbol, title, onClick) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.title = title;
        btn.setAttribute('aria-label', title);
        btn.className = 'px-1';
        btn.textContent = symbol;
        btn.addEventListener('click', onClick);
        return btn;
    }

    function moveIndex(from, to) {
        if (to < 0 || to >= layers.length) { return; }
        var tmp = layers[from];
        layers[from] = layers[to];
        layers[to] = tmp;
        recomputeZ();
        render();
    }

    /* ------------------------------------------------------------ الخصائص */
    function schemaFor(layer) {
        if (layer.type === 'text') {
            return [
                { key: 'text', label: T.text_label, input: 'textarea' },
                { key: 'size', label: T.size_label, input: 'number', min: 6, max: 96 },
                { key: 'color', label: T.color_label, input: 'color' },
                { key: 'align', label: T.align_label, input: 'select', options: [['right', T.align_right], ['center', T.align_center], ['left', T.align_left]] },
                { key: 'rotate', label: T.rotate_label, input: 'number', min: -180, max: 180 },
                { key: 'behind', label: T.behind_label, input: 'checkbox' },
            ];
        }

        return [
            { key: 'path', label: T.image_label, input: 'image-pick' },
            { key: 'w', label: T.width_label, input: 'number', min: 1, max: 100 },
            { key: 'h', label: T.height_label, input: 'number', min: 1, max: 100 },
            { key: 'opacity', label: T.opacity_label, input: 'number', min: 0, max: 100 },
            { key: 'behind', label: T.behind_label, input: 'checkbox' },
        ];
    }

    function select(uid) {
        selectedUid = uid;
        var layer = layers.find(function (l) { return l._uid === uid; });
        if (!layer || !props || !propsFields) { render(); return; }

        props.hidden = false;
        propsFields.innerHTML = '';

        schemaFor(layer).forEach(function (field) {
            if (field.input === 'image-pick') {
                propsFields.append(imagePickField(layer, field));
                return;
            }

            var wrap = document.createElement('label');
            wrap.className = 'block text-sm';

            var value = layer[field.key];
            var input;

            if (field.input === 'select') {
                input = document.createElement('select');
                field.options.forEach(function (opt) {
                    var o = document.createElement('option');
                    o.value = opt[0];
                    o.textContent = opt[1];
                    if (value === opt[0]) { o.selected = true; }
                    input.appendChild(o);
                });
            } else if (field.input === 'textarea') {
                input = document.createElement('textarea');
                input.rows = 3;
                input.value = value || '';
            } else if (field.input === 'checkbox') {
                input = document.createElement('input');
                input.type = 'checkbox';
                input.checked = !!value;
                input.className = 'w-5 h-5 align-middle';
            } else {
                input = document.createElement('input');
                input.type = field.input;
                if (field.min !== undefined) { input.min = field.min; }
                if (field.max !== undefined) { input.max = field.max; }
                input.value = field.input === 'color' ? (value || '#000000') : (value ?? '');
            }

            if (field.input !== 'checkbox') {
                input.className = (input.className ? input.className + ' ' : '') + 'w-full rounded-lg px-2 py-1 text-sm mt-1';
                input.style.background = 'var(--surface-sunken)';
                input.style.border = '1px solid var(--border)';
                input.style.color = 'var(--text)';
            }

            wrap.append(document.createTextNode(field.label));
            wrap.append(input);
            propsFields.append(wrap);

            var eventName = field.input === 'select' || field.input === 'checkbox' ? 'change' : 'input';
            input.addEventListener(eventName, function () {
                var l = layers.find(function (x) { return x._uid === selectedUid; });
                if (!l) { return; }

                if (field.input === 'checkbox') {
                    l[field.key] = input.checked;
                    recomputeZ();
                } else {
                    var numeric = ['size', 'rotate', 'w', 'h', 'opacity'].indexOf(field.key) !== -1;
                    l[field.key] = numeric ? (+input.value || 0) : input.value;
                }

                render();
            });
        });
    }

    /* حقل اختيار الصورة: يفتح نفس بوب-أب المكتبة (admin.courses.partials.media-picker-modal) */
    function imagePickField(layer, field) {
        var wrap = document.createElement('div');
        wrap.className = 'space-y-1';

        var label = document.createElement('span');
        label.className = 'block text-sm';
        label.textContent = field.label;
        wrap.append(label);

        var row = document.createElement('div');
        row.className = 'flex items-center gap-2';

        var thumb = document.createElement('span');
        thumb.className = 'inline-flex items-center justify-center rounded-lg overflow-hidden shrink-0';
        thumb.style.cssText = 'width:48px; height:48px; background: var(--surface-sunken)';
        if (layer.url) {
            var img = document.createElement('img');
            img.src = layer.url;
            img.alt = '';
            img.className = 'w-full h-full object-cover';
            thumb.append(img);
        }

        var pickBtn = document.createElement('button');
        pickBtn.type = 'button';
        pickBtn.dataset.mediaPick = 'cv-decor-image-target';
        pickBtn.className = 'rounded-xl px-3 py-1.5 text-xs';
        pickBtn.style.cssText = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
        pickBtn.textContent = layer.url ? T.pick_image : T.no_image + ' — ' + T.pick_image;

        row.append(thumb, pickBtn);
        wrap.append(row);

        return wrap;
    }

    imageTargetField && imageTargetField.addEventListener('change', function () {
        var l = layers.find(function (x) { return x._uid === selectedUid; });
        if (!l) { return; }

        l.path = imageTargetField.value;
        var previewImg = document.querySelector('[data-media-preview="cv-decor-image-target"] img');
        l.url = previewImg ? previewImg.getAttribute('src') : '';

        render();
        select(selectedUid);
    });

    /* -------------------------------------------------------- إضافة طبقة */
    document.querySelectorAll('[data-decor-add]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var kind = btn.getAttribute('data-decor-add');
            var uid = 'd' + Date.now() + Math.floor(Math.random() * 1000);
            var layer = kind === 'image'
                ? { _uid: uid, type: 'image', x: 30, y: 30, w: 40, h: 30, opacity: 100, behind: false, path: '', url: '' }
                : { _uid: uid, type: 'text', x: 30, y: 30, text: '', size: 14, color: '#000000', align: 'right', rotate: 0, behind: false };
            // في مقدّمة القائمة = أماميّةٌ ظاهرة فورًا (بديهيّ لعنصرٍ جديد)
            layers.unshift(layer);
            recomputeZ();
            select(uid);
            render();
        });
    });

    /* -------------------------------------------- السحب الحرّ + Snap (نسبة مئويّة) */
    var dragging = null;

    overlay.addEventListener('pointerdown', function (e) {
        var el = e.target.closest('[data-decor-layer]');
        if (!el) { return; }
        var layer = layers.find(function (l) { return l._uid === el.dataset.decorLayer; });
        if (!layer || layer._hidden) { return; }

        dragging = layer;
        select(layer._uid);
        overlay.setPointerCapture(e.pointerId);
    });

    overlay.addEventListener('pointermove', function (e) {
        if (!dragging) { return; }
        var rect = overlay.getBoundingClientRect();
        var fx = ((e.clientX - rect.left) / rect.width) * 100;
        var fy = ((e.clientY - rect.top) / rect.height) * 100;

        if (snap) {
            fx = Math.round(fx / GRID) * GRID;
            fy = Math.round(fy / GRID) * GRID;
        }

        dragging.x = Math.round(Math.min(100, Math.max(0, fx)));
        dragging.y = Math.round(Math.min(100, Math.max(0, fy)));
        render();
    });

    overlay.addEventListener('pointerup', function () { dragging = null; });
    overlay.addEventListener('pointercancel', function () { dragging = null; });

    snapCheckbox && snapCheckbox.addEventListener('change', function (e) {
        snap = e.target.checked;
        grid && grid.classList.toggle('hidden', !snap);
    });

    grid && grid.classList.toggle('hidden', !snap);

    /* --------------------------------------------------------- الحفظ */
    /* ⭐ قائمة حقول بيضاء صراحةً لكلّ نوع — تطابق CvTemplateDecor::sanitize()
       بالحرف، فلا يتسرّب أيّ حقلٍ مساعد بمحرِّر الواجهة فقط (behind/url/_hidden) للخادم. */
    function syncHiddenInputs() {
        if (!hiddenBox) { return; }
        hiddenBox.innerHTML = '';

        layers.forEach(function (layer, index) {
            (FIELDS_BY_TYPE[layer.type] || []).forEach(function (key) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'layers[' + index + '][' + key + ']';
                input.value = layer[key] != null ? layer[key] : '';
                hiddenBox.appendChild(input);
            });
        });
    }

    window.addEventListener('resize', render);
    render();
})();
</script>
@endpush
