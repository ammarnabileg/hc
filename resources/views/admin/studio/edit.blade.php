@extends('layouts.admin')

@section('title', setting('admin.studio.edit.tadyl_qalb', 'تعديل قالب: ') . $template->name)

@section('content')
    <x-page-header :title="setting('admin.studio.edit.qalb', 'قالب: ') . $template->name"
                   :subtitle="setting('admin.studio.edit.tbqat_mqasat_hqwl_mn_alqayma_almqfwla', 'طبقات · مقاسات · حقول من القائمة المقفولة — ومعاينة ببيانات حقيقيّة.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.studio.edit.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.studio.edit.astwdyw_alswr', 'استوديو الصور'), 'url' => route('admin.studio.index')],
                       ['label' => $template->name],
                   ]" />

    @if ($errors->any())
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-danger); color: var(--color-state-danger)">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <form method="post" action="{{ route('admin.studio.update', $template) }}" class="space-y-4" id="template-form">
            @csrf @method('PUT')

            <div class="card p-4 space-y-3">
                <h2 class="font-bold text-sm">{{ setting('admin.studio.edit.alasasyat', 'الأساسيّات') }}</h2>
                <x-form.input name="name" :label="setting('admin.studio.edit.asm_alqalb', 'اسم القالب')" :value="$template->name" required />

                {{-- المقاس الجاهز عمودٌ في القاعدة (`preset`) — فيُرسَل باسمه لا كمساعدٍ بصريّ --}}
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.studio.edit.mqas_jahz', 'مقاس جاهز') }}</span>
                    <select name="preset" id="preset-select" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">{{ setting('admin.studio.edit.mkhss', 'مخصّص') }}</option>
                        @foreach ($presets as $key => $preset)
                            <option value="{{ $key }}" data-size="{{ $preset['width'] }}x{{ $preset['height'] }}"
                                    @selected($template->preset === $key)>{{ $preset['label'] }} — {{ $preset['width'] }}×{{ $preset['height'] }}</option>
                        @endforeach
                    </select>
                    {{-- ⭐ تغيير المقاس يعيد ترتيب الطبقات نسبيًّا فلا يفسد التصميم --}}
                    <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('admin.studio.edit.tghyyr_almqas_byayd_twzya_altbqat_nsbya', 'تغيير المقاس بيعيد توزيع الطبقات نسبيًّا تلقائيًّا.') }}</span>
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <x-form.input name="width_px" :label="setting('admin.studio.edit.alard', 'العرض')" type="number" :value="$template->width_px" required />
                    <x-form.input name="height_px" :label="setting('admin.studio.edit.altwl', 'الطول')" type="number" :value="$template->height_px" required />
                </div>

                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.studio.edit.aljmhwr', 'الجمهور') }}</span>
                    <select name="audience" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($audiences as $key => $label)
                            <option value="{{ $key }}" @selected($template->audience === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="grid grid-cols-2 gap-2">
                    {{-- العمود `purpose` كان مُصادَقًا عليه بلا حقلٍ يملؤه — فيبقى «تسويق» أبدًا --}}
                    <label class="block text-sm">
                        <span class="block mb-1">{{ setting('images.template.purpose_label') }}</span>
                        <select name="purpose" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($purposes as $key => $label)
                                <option value="{{ $key }}" @selected($template->purpose === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    {{-- نسختان (ع/إ) — 13.4-ر --}}
                    <label class="block text-sm">
                        <span class="block mb-1">{{ setting('images.template.language_label') }}</span>
                        <select name="language" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($languages as $key => $label)
                                <option value="{{ $key }}" @selected($template->language === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" @checked($template->is_active)> {{ setting('admin.studio.edit.mfal', 'مفعَّل') }}
                </label>
            </div>

            {{--
              ⭐ **رفع الفريم/الخلفيّة** (12.14-أ): «رفع الفريم/الخلفيّة كصورة،
              وتُبنى فوقها الطبقات». والمنتقي هو **بوب-أب مكتبة الوسائط نفسه**
              المستعمَل في التدريبات والمسارات — مصدرٌ واحد لا نسختان (2.14-ب).
            --}}
            <div class="card p-4 space-y-3">
                <h2 class="font-bold text-sm">{{ setting('images.template.frame_label') }}</h2>

                <x-form.input name="frame_path" label="{{ setting('images.template.frame_label') }}"
                              :value="$template->frame_path"
                              hint="{{ setting('images.template.frame_hint') }}" />

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" data-media-pick="frame_path"
                            class="rounded-xl px-3 py-1.5 text-xs"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <x-icon name="library" size="14" /> {{ setting('media.picker.cta') }}
                    </button>
                    <button type="button" data-frame-clear
                            class="rounded-xl px-3 py-1.5 text-xs"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        {{ setting('images.template.frame_clear') }}
                    </button>
                    <span data-media-preview="frame_path" class="inline-flex items-center">
                        @if ($frameUrl)
                            <img src="{{ $frameUrl }}" alt="{{ setting('images.template.frame_label') }}"
                                 class="w-20 h-20 object-cover rounded-lg">
                        @endif
                    </span>
                </div>
            </div>

            {{-- «حفظ باسم · نسخة · تفعيل/إيقاف · **مجلّدات ووسوم** · بحث» (12.14-أ) --}}
            <div class="card p-4 space-y-3">
                <h2 class="font-bold text-sm">{{ setting('images.template.organize_label') }}</h2>

                <x-form.input name="folders" label="{{ setting('images.template.folders_label') }}"
                              :value="implode(',', (array) ($template->folders ?? []))"
                              hint="{{ setting('images.template.folders_hint') }}" list="studio-folder-list" />

                <x-form.input name="tags" label="{{ setting('images.template.tags_label') }}"
                              :value="implode(',', (array) ($template->tags ?? []))"
                              hint="{{ setting('images.template.tags_hint') }}" list="studio-tag-list" />

                <datalist id="studio-folder-list">
                    @foreach ($folderList as $folder)<option value="{{ $folder }}"></option>@endforeach
                </datalist>
                <datalist id="studio-tag-list">
                    @foreach ($tagList as $tag)<option value="{{ $tag }}"></option>@endforeach
                </datalist>
            </div>

            {{--
              ⭐ محرّر السحب-إفلات (12.14 — نفس محرّك 12.5-ب بكلّ إمكاناته):
              كانفس حقيقيّ · إحداثيّات X/Y بالسحب · شبكة محاذاة (Snap) · لوحة
              طبقات (إظهار/إخفاء · رفع/إنزال · قفل) — بديل نموذج الحقول الرقميّة
              الذي كان الاستوديو محرّكًا مستقلًّا به.
            --}}
            <div class="card p-4 space-y-3" id="layers-panel">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <h2 class="font-bold text-sm">{{ setting('admin.studio.edit.lwha_altbqat', 'لوحة الطبقات') }}</h2>
                    <label class="flex items-center gap-2 text-xs">
                        <input type="checkbox" data-studio-snap @checked($snap)> {{ setting('admin.studio.edit.shbka_mhadhaa_snap', 'شبكة محاذاة (Snap)') }}
                    </label>
                </div>

                <div dir="ltr" id="studio-canvas" class="relative overflow-hidden rounded-xl select-none"
                     style="width: 100%; aspect-ratio: {{ (int) $template->width_px }} / {{ (int) $template->height_px }};
                            background: {{ $frameUrl ? 'url('.$frameUrl.') center/cover no-repeat' : 'var(--surface-sunken)' }};
                            border: 1px solid var(--border)">
                    <div class="absolute inset-0 pointer-events-none hidden" data-studio-grid
                         style="background-image:
                            linear-gradient(to right, color-mix(in srgb, var(--color-brand-500) 25%, transparent) 1px, transparent 1px),
                            linear-gradient(to bottom, color-mix(in srgb, var(--color-brand-500) 25%, transparent) 1px, transparent 1px);
                            background-size: {{ $grid }}% {{ $grid }}%"></div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" data-studio-add="text" class="btn rounded-xl px-3 py-2 text-xs"
                            style="background: var(--surface-sunken)">{{ setting('admin.studio.edit.ns', '+ نصّ') }}</button>
                    <button type="button" data-studio-add="avatar" class="btn rounded-xl px-3 py-2 text-xs"
                            style="background: var(--surface-sunken)">{{ setting('admin.studio.edit.swra_almstkhdm', '+ صورة المستخدم') }}</button>
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    {{-- لوحة الطبقات: إظهار/إخفاء · رفع/إنزال · قفل (12.14-أ) --}}
                    <div>
                        <h3 class="text-xs font-bold mb-1" style="color: var(--text-muted)">{{ setting('admin.studio.edit.lwha_altbqat', 'لوحة الطبقات') }}</h3>
                        <ul class="space-y-1 max-h-64 overflow-y-auto rounded-xl p-2" style="background: var(--surface-sunken)" data-studio-layers-panel></ul>
                    </div>

                    {{-- خصائص الطبقة المختارة --}}
                    <div class="rounded-xl p-3 space-y-2" style="background: var(--surface-sunken)" data-studio-props hidden>
                        <h3 class="text-xs font-bold" style="color: var(--text-muted)">{{ setting('admin.certificates.designer.khsays_altbqa', 'خصائص الطبقة') }}</h3>
                        <div data-studio-props-fields class="space-y-2"></div>
                        <button type="button" data-studio-delete-layer class="text-xs underline"
                                style="color: var(--color-state-danger)">{{ setting('admin.certificates.designer.hdhf_altbqa', 'حذف الطبقة') }}</button>
                    </div>
                </div>

                {{-- ⭐ الحفظ يكتب هنا حقول الطبقات المخفيّة بنفس صيغة الفورم القديمة —
                    الخادم لا يتغيّر سطرٌ واحد فيه (2.13 · لا مخاطرة على منطق قائم) --}}
                <div data-studio-hidden-inputs></div>
            </div>

            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.studio.edit.hfz_alqalb', 'حفظ القالب') }}</button>
        </form>

        <div class="space-y-4">
            <div class="card p-4">
                <h2 class="font-bold text-sm mb-2">{{ setting('admin.studio.edit.maayna_bbyanat_mstkhdm_hqyqy', 'معاينة ببيانات مستخدم حقيقيّ') }}</h2>
                <label class="block text-sm mb-2">
                    <span class="block mb-1">{{ setting('admin.studio.edit.almstkhdm', 'المستخدم') }}</span>
                    <select id="preview-user" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($sampleUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} — #{{ $user->code }}</option>
                        @endforeach
                    </select>
                </label>
                <img id="preview-image" src="{{ route('admin.studio.preview', $template) }}"
                     alt="{{ setting('admin.studio.edit.maayna_alqalb', 'معاينة القالب') }}" class="w-full rounded-xl" style="max-width:100%; background: var(--surface-sunken)">
                {{-- ⭐ معاينة بالمقاس الحقيقيّ (12.14 — نفس محرّك 12.5-ب): نفس الرسّام
                     ونفس البكسلات التي ستُنتَجها الصورة الفعليّة، لا تقريبًا بـCSS --}}
                <p class="text-xs mt-2" style="color: var(--text-muted)">
                    {{ setting('admin.studio.edit.maayna_balmqas_alhqyqy_nfs_alrsam', 'معاينة بالمقاس الحقيقيّ — نفس الرسّام الذي يُنتِج الصورة الفعليّة.') }}
                    {{ setting('admin.studio.edit.kl_swra_mstkhrja_bthml_tarykh_allqta_wshaar', 'كلّ صورة مستخرَجة بتحمل تاريخ اللقطة وشعار المنصّة — فمحدش ينشر ترتيبًا قديمًا كأنّه حاليّ.') }}
                </p>
            </div>

            @can('image_templates.batch')
                <form method="post" action="{{ route('admin.studio.batch', $template) }}" class="card p-4 space-y-3">
                    @csrf
                    <h2 class="font-bold text-sm">{{ setting('admin.studio.edit.twlyd_jmaay_zip', 'توليد جماعيّ ⟵ ZIP') }}</h2>
                    <label class="block text-sm">
                        <span class="block mb-1">{{ setting('admin.studio.edit.alshryha', 'الشريحة') }}</span>
                        <select name="segment" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="top_xp">{{ setting('admin.studio.edit.awayl_allydr_bwrd_xp', 'أوائل الليدر بورد (XP)') }}</option>
                            <option value="volunteers">{{ setting('admin.studio.edit.almttwawn_alnshtwn', 'المتطوّعون النشطون') }}</option>
                            <option value="active">{{ setting('admin.studio.edit.alhsabat_almfala', 'الحسابات المفعَّلة') }}</option>
                        </select>
                    </label>
                    <x-form.input name="limit" :label="setting('admin.studio.edit.aladd', 'العدد')" type="number" value="10" required />
                    <button class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.studio.edit.wld_alarshyf', 'ولّد الأرشيف') }}</button>
                </form>
            @endcan

            <div class="card p-4 text-xs" style="color: var(--text-muted)">
                <p class="font-bold mb-1" style="color: var(--text)">{{ setting('admin.studio.edit.alhqwl_almsmwha_fqt', 'الحقول المسموحة فقط') }}</p>
                <p>{{ implode(' · ', $allowedFields) }}</p>
                {{-- ⛔ الممنوع غير موجود في القائمة أصلًا — لا معطَّلًا (12.14-د) --}}
                <p class="mt-2">{{ setting('admin.studio.edit.almwbayl_walbryd_wjha_altwary_walmlahzat', 'الموبايل والبريد وجهة الطوارئ والملاحظات الإداريّة مش موجودة في القائمة أصلًا.') }}</p>
            </div>
        </div>
    </div>

    {{-- ⭐ نفس بوب-أب «اختَر من المكتبة / ارفع جديد» — لا منتقي وسائط ثانٍ (2.14-ب) --}}
    @include('admin.courses.partials.media-picker-modal')
@endsection

@push('scripts')
@php
    /*
     | نصوص السكربت من الإعدادات (2.13-أ): لا حرفَ عربيّ داخل `<script>`،
     | فالمحروق هناك لا يصل لوحةَ الإدارة ولا الترجمة.
     */
    $jsText = [
        'static_text' => setting('admin.studio.edit.ns_thabt', 'نصّ ثابت'),
        'user_image' => setting('admin.studio.edit.tbqa_swra_almstkhdm', 'صورة المستخدم'),
        'text' => setting('admin.studio.edit.tbqa_ns', 'نصّ'),
        'visible' => setting('admin.studio.edit.zahra', 'ظاهرة'),
        'locked' => setting('admin.studio.edit.mqfwla', 'مقفولة'),
        'rotate' => setting('admin.studio.edit.dwran', 'دوران'),
        'width' => setting('admin.studio.edit.alard', 'العرض'),
        'height' => setting('admin.studio.edit.altwl', 'الطول'),
        'shape' => setting('admin.studio.edit.alshkl', 'الشكل'),
        'square' => setting('admin.studio.edit.mrba', 'مربّع'),
        'circle' => setting('admin.studio.edit.dayra', 'دائرة'),
        'circle_border' => setting('admin.studio.edit.dayra_bhd', 'دائرة بحدّ'),
        'fit' => setting('admin.studio.edit.alqs', 'القصّ'),
        'cover' => setting('admin.studio.edit.ymla', 'يملأ'),
        'contain' => setting('admin.studio.edit.yhtwy', 'يحتوي'),
        'field' => setting('admin.studio.edit.alhql', 'الحقل'),
        'font_size' => setting('admin.studio.edit.hjm_alkht', 'حجم الخطّ'),
        'color' => setting('admin.studio.edit.allwn', 'اللون'),
        'align' => setting('admin.studio.edit.almhadha', 'المحاذاة'),
        'align_right' => setting('admin.studio.edit.ymyn', 'يمين'),
        'align_center' => setting('admin.studio.edit.wst', 'وسط'),
        'align_left' => setting('admin.studio.edit.ysar', 'يسار'),
        'max_chars' => setting('admin.studio.edit.hd_alahrf', 'حدّ الأحرف'),
        'overflow' => setting('admin.studio.edit.and_altjawz', 'عند التجاوز'),
        'overflow_shrink' => setting('admin.studio.edit.tsghyr_tlqay', 'تصغير تلقائيّ'),
        'overflow_truncate' => setting('admin.studio.edit.qs_bthlath_nqat', 'قصّ بثلاث نقاط'),
        'name_label' => setting('admin.certificates.designer.alasm', 'الاسم'),
        'toggle_visibility' => setting('admin.certificates.designer.izhar_ikhfa', 'إظهار/إخفاء'),
        'lock' => setting('admin.certificates.designer.qfl', 'قفل'),
        'move_up' => setting('admin.certificates.designer.rfa_llaala', 'رفع للأعلى'),
        'move_down' => setting('admin.certificates.designer.inzal_llasfl', 'إنزال للأسفل'),
        'border_color' => setting('admin.studio.edit.lwn_alhd', 'لون الحدّ'),
        'border_width' => setting('admin.studio.edit.smk_alhd', 'سمك الحدّ'),
        'x_label' => setting('admin.studio.edit.x_bksl', 'X (بكسل)'),
        'y_label' => setting('admin.studio.edit.y_bksl', 'Y (بكسل)'),
    ];
@endphp

<script>
    const T = @json($jsText);
/*
 | ⭐ محرّر السحب-إفلات (12.14 — نفس محرّك 12.5-ب بكلّ إمكاناته): كانفس حقيقيّ
 | بإحداثيّات X/Y بالسحب وشبكة محاذاة (Snap)، بجافاسكربت خام بلا أيّ مكتبة
 | خارجيّة — تمامًا كمصمّم قوالب الشهادات (12.5-ب). والفرق الوحيد المتعمَّد:
 | إحداثيّات هذا الاستوديو **بكسل مطلق** لا كسرًا من 0 إلى 1 — لأنّ الاستوديو
 | يدعم تغيير مقاس القالب وإعادة توزيع الطبقات نسبيًّا (`TemplateLayers::rescale`)،
 | وهذه ميزة القالب الواحد بمقاساتٍ متعدّدة (12.14-أ) تتطلّب بكسلات حقيقيّة.
 |
 | ⭐ والحفظ يكتب **نفس حقول `layers[i][key]` المخفيّة** التي كان النموذج
 | الرقميّ يرسلها — فمتحكّم الاستوديو (`ImageStudioController::validated`)
 | ومنظّف الطبقات (`TemplateLayers::sanitize`) لا يتغيّر فيهما سطرٌ واحد؛
 | الترقية كلّها في واجهة التحرير لا في الخادم (2.13 · صفر مخاطرة على منطقٍ قائم).
 */
(function () {
    var allowed = @json($allowedFields);
    var canvas = document.getElementById('studio-canvas');
    var TEMPLATE_W = {{ (int) $template->width_px }};
    var TEMPLATE_H = {{ (int) $template->height_px }};
    var GRID = {{ (int) $grid }} / 100;

    var layers = (@json($template->layers ?? [])).map(function (layer, index) {
        layer._uid = 'l' + index + '_' + Date.now();
        return layer;
    });
    var selectedUid = null;
    var snapCheckbox = document.querySelector('[data-studio-snap]');
    var snap = snapCheckbox ? snapCheckbox.checked : {{ $snap ? 'true' : 'false' }};

    var canvasPanel = document.querySelector('[data-studio-layers-panel]');
    var props = document.querySelector('[data-studio-props]');
    var propsFields = document.querySelector('[data-studio-props-fields]');
    var grid = canvas ? canvas.querySelector('[data-studio-grid]') : null;
    var hiddenBox = document.querySelector('[data-studio-hidden-inputs]');

    if (!canvas) { return; }

    function fieldOptions(selected) {
        var html = '<option value="">' + T.static_text + '</option>';
        Object.keys(allowed).forEach(function (key) {
            html += '<option value="' + key + '"' + (selected === key ? ' selected' : '') + '>' + allowed[key] + '</option>';
        });
        return html;
    }

    /* ------------------------------------------------------ الرسم على الكانفس */
    function render() {
        canvas.querySelectorAll('[data-studio-layer]').forEach(function (el) { el.remove(); });
        var scale = canvas.clientWidth / TEMPLATE_W;

        layers.forEach(function (layer) {
            var el = document.createElement('div');
            el.dataset.studioLayer = layer._uid;
            el.className = 'absolute';
            el.style.left = ((layer.x || 0) / TEMPLATE_W * 100) + '%';
            el.style.top = ((layer.y || 0) / TEMPLATE_H * 100) + '%';
            el.style.opacity = layer.visible === false ? 0.35 : 1;
            el.style.cursor = layer.locked ? 'not-allowed' : 'grab';
            el.style.outline = layer._uid === selectedUid ? '2px dashed var(--color-brand-500)' : 'none';

            if (layer.type === 'avatar') {
                var w = layer.w || 240, h = layer.h || 240;
                el.style.width = (w * scale) + 'px';
                el.style.height = (h * scale) + 'px';
                el.style.background = '#0b1512';
                el.style.color = '#fff';
                el.style.display = 'grid';
                el.style.placeItems = 'center';
                el.style.fontSize = '11px';
                el.style.borderRadius = layer.shape === 'square' ? '4px' : '50%';
                el.style.border = layer.shape === 'circle_border' ? '2px solid ' + (layer.border_color || '#00d4b8') : 'none';
                el.textContent = T.user_image;
            } else {
                var shift = layer.align === 'right' ? '-100%' : layer.align === 'left' ? '0%' : '-50%';
                el.style.whiteSpace = 'nowrap';
                el.style.transform = 'translate(' + shift + ', 0) rotate(' + (layer.rotate || 0) + 'deg)';
                el.style.fontSize = Math.max(6, (layer.size || 32) * scale) + 'px';
                el.style.color = layer.color || '#ffffff';
                el.setAttribute('dir', 'auto');
                el.textContent = valueOf(layer) || (layer.name || T.text);
            }

            canvas.appendChild(el);
        });

        renderPanel();
        syncHiddenInputs();
    }

    /* «نصّ ثابت + الحقل» — نفس منطق الخادم (App\Services\Certificates\GdEngine::layerValue) */
    function valueOf(layer) {
        if (!layer.field) { return layer.text || ''; }
        var stat = layer.text || '';
        var label = allowed[layer.field] ? '{' + allowed[layer.field] + '}' : '';
        return (stat + (stat && label ? ' ' : '') + label).trim();
    }

    /* ------------------------------------------------------- لوحة الطبقات */
    function renderPanel() {
        if (!canvasPanel) { return; }
        canvasPanel.innerHTML = '';

        layers.forEach(function (layer, index) {
            var li = document.createElement('li');
            li.className = 'flex items-center gap-1 text-xs rounded-lg px-2 py-1';
            li.style.background = layer._uid === selectedUid ? 'var(--surface-raised)' : 'transparent';

            var name = document.createElement('button');
            name.type = 'button';
            name.className = 'flex-1 text-start truncate';
            name.textContent = (layer.name || (layer.type === 'avatar' ? T.user_image : T.text)) + ' #' + (index + 1);
            name.addEventListener('click', function () { select(layer._uid); });

            li.append(name);
            li.append(iconButton(layer.visible === false ? '🚫' : '👁', T.toggle_visibility, function () {
                layer.visible = layer.visible === false;
                render();
            }));
            li.append(iconButton(layer.locked ? '🔒' : '🔓', T.lock, function () {
                layer.locked = !layer.locked;
                render();
            }));
            li.append(iconButton('▲', T.move_up, function () { moveIndex(index, index - 1); }));
            li.append(iconButton('▼', T.move_down, function () { moveIndex(index, index + 1); }));

            canvasPanel.append(li);
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

    /* الترتيب هو ترتيب المصفوفة نفسه — نفس دلالة TemplateLayers::move() بالضبط */
    function moveIndex(from, to) {
        if (to < 0 || to >= layers.length) { return; }
        if (layers[from].locked || layers[to].locked) { return; }
        var tmp = layers[from];
        layers[from] = layers[to];
        layers[to] = tmp;
        render();
    }

    /* ------------------------------------------------------------ الخصائص */
    function schemaFor(layer) {
        if (layer.type === 'avatar') {
            return [
                { key: 'name', label: T.name_label, input: 'text' },
                { key: 'w', label: T.width, input: 'number' },
                { key: 'h', label: T.height, input: 'number' },
                { key: 'rotate', label: T.rotate, input: 'number' },
                { key: 'shape', label: T.shape, input: 'select', options: [['square', T.square], ['circle', T.circle], ['circle_border', T.circle_border]] },
                { key: 'fit', label: T.fit, input: 'select', options: [['cover', T.cover], ['contain', T.contain]] },
                { key: 'border_color', label: T.border_color, input: 'color' },
                { key: 'border_width', label: T.border_width, input: 'number' },
            ];
        }

        return [
            { key: 'name', label: T.name_label, input: 'text' },
            { key: 'field', label: T.field, input: 'select', options: Object.keys(allowed).map(function (k) { return [k, allowed[k]]; }), empty: T.static_text },
            { key: 'text', label: T.static_text, input: 'text' },
            { key: 'size', label: T.font_size, input: 'number' },
            { key: 'rotate', label: T.rotate, input: 'number' },
            { key: 'color', label: T.color, input: 'color' },
            { key: 'align', label: T.align, input: 'select', options: [['right', T.align_right], ['center', T.align_center], ['left', T.align_left]] },
            { key: 'max_chars', label: T.max_chars, input: 'number' },
            { key: 'overflow', label: T.overflow, input: 'select', options: [['shrink', T.overflow_shrink], ['truncate', T.overflow_truncate]] },
        ];
    }

    function select(uid) {
        selectedUid = uid;
        var layer = layers.find(function (l) { return l._uid === uid; });
        if (!layer || !props || !propsFields) { return; }

        props.hidden = false;
        propsFields.innerHTML = '';

        schemaFor(layer).forEach(function (field) {
            var wrap = document.createElement('label');
            wrap.className = 'block text-sm';

            var value = layer[field.key];
            var input;

            if (field.input === 'select') {
                input = document.createElement('select');
                input.className = 'w-full rounded-lg px-2 py-1 text-sm mt-1';
                if (field.empty) { input.innerHTML = '<option value="">' + field.empty + '</option>'; }
                field.options.forEach(function (opt) {
                    var o = document.createElement('option');
                    o.value = opt[0];
                    o.textContent = opt[1];
                    if (value === opt[0]) { o.selected = true; }
                    input.appendChild(o);
                });
            } else {
                input = document.createElement('input');
                input.type = field.input;
                input.className = 'w-full rounded-lg px-2 py-1 text-sm mt-1';
                if (field.input === 'color') { input.value = value || '#ffffff'; }
                else { input.value = value ?? ''; }
            }

            input.dataset.studioProp = field.key;
            input.style.background = 'var(--surface-sunken)';
            input.style.border = '1px solid var(--border)';
            input.style.color = 'var(--text)';

            wrap.append(document.createTextNode(field.label));
            wrap.append(input);
            propsFields.append(wrap);

            input.addEventListener('input', function () {
                var l = layers.find(function (x) { return x._uid === selectedUid; });
                if (!l) { return; }
                var numeric = ['size', 'rotate', 'max_chars', 'w', 'h', 'border_width'].indexOf(field.key) !== -1;
                l[field.key] = numeric ? (+input.value || 0) : input.value;
                render();
            });
        });

        render();
    }

    document.querySelector('[data-studio-delete-layer]')?.addEventListener('click', function () {
        layers = layers.filter(function (l) { return l._uid !== selectedUid; });
        selectedUid = null;
        if (props) { props.hidden = true; }
        render();
    });

    /* -------------------------------------------------------- إضافة طبقة */
    document.querySelectorAll('[data-studio-add]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var kind = btn.getAttribute('data-studio-add');
            var uid = 'l' + Date.now() + Math.floor(Math.random() * 1000);
            var layer = kind === 'avatar'
                ? { _uid: uid, type: 'avatar', name: T.user_image, x: Math.round(TEMPLATE_W * 0.1), y: Math.round(TEMPLATE_H * 0.1), w: 240, h: 240, shape: 'circle', fit: 'cover', visible: true, locked: false }
                : { _uid: uid, type: 'text', name: T.text, x: Math.round(TEMPLATE_W * 0.1), y: Math.round(TEMPLATE_H * 0.5), size: 40, color: '#ffffff', align: 'right', field: 'short_name', max_chars: {{ (int) setting('images.text.default_max_chars', 28) }}, overflow: 'shrink', visible: true, locked: false };
            layers.push(layer);
            select(uid);
        });
    });

    /* -------------------------------------------- السحب الحرّ + Snap (بكسل) */
    var dragging = null;

    canvas.addEventListener('pointerdown', function (e) {
        var el = e.target.closest('[data-studio-layer]');
        if (!el) { return; }
        var layer = layers.find(function (l) { return l._uid === el.dataset.studioLayer; });
        if (!layer || layer.locked) { return; }

        dragging = layer;
        select(layer._uid);
        canvas.setPointerCapture(e.pointerId);
    });

    canvas.addEventListener('pointermove', function (e) {
        if (!dragging) { return; }
        var rect = canvas.getBoundingClientRect();
        var fx = (e.clientX - rect.left) / rect.width;
        var fy = (e.clientY - rect.top) / rect.height;

        if (snap) {
            fx = Math.round(fx / GRID) * GRID;
            fy = Math.round(fy / GRID) * GRID;
        }

        dragging.x = Math.round(Math.min(1, Math.max(0, fx)) * TEMPLATE_W);
        dragging.y = Math.round(Math.min(1, Math.max(0, fy)) * TEMPLATE_H);
        render();
    });

    canvas.addEventListener('pointerup', function () {
        if (!dragging) { return; }
        dragging = null;
        select(selectedUid);
    });

    snapCheckbox && snapCheckbox.addEventListener('change', function (e) {
        snap = e.target.checked;
        grid && grid.classList.toggle('hidden', !snap);
    });

    grid && grid.classList.toggle('hidden', !snap);

    /* --------------------------------------------------------- الحفظ */
    /* ⭐ نفس حقول `layers[i][key]` المخفيّة التي كان النموذج الرقميّ يرسلها —
       فمتحكّم الاستوديو لا يتغيّر فيه سطرٌ واحد (2.13). */
    function syncHiddenInputs() {
        if (!hiddenBox) { return; }
        hiddenBox.innerHTML = '';

        layers.forEach(function (layer, index) {
            Object.keys(layer).forEach(function (key) {
                if (key === '_uid') { return; }

                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'layers[' + index + '][' + key + ']';
                input.value = typeof layer[key] === 'boolean' ? (layer[key] ? '1' : '0') : (layer[key] ?? '');
                hiddenBox.appendChild(input);
            });
        });
    }

    var preset = document.getElementById('preset-select');
    preset && preset.addEventListener('change', function () {
        var size = preset.options[preset.selectedIndex].getAttribute('data-size');
        if (!size) { return; }
        var parts = size.split('x');
        document.getElementById('width_px').value = parts[0];
        document.getElementById('height_px').value = parts[1];
    });

    // شيل الفريم: تفريغ الحقل ومعاينته معًا — فلا تبقى صورة تقول إنّ ثمّة فريمًا
    var frameClear = document.querySelector('[data-frame-clear]');
    frameClear && frameClear.addEventListener('click', function () {
        var field = document.getElementById('frame_path');
        if (field) { field.value = ''; }
        var preview = document.querySelector('[data-media-preview="frame_path"]');
        if (preview) { preview.innerHTML = ''; }
    });

    var previewUser = document.getElementById('preview-user');
    previewUser && previewUser.addEventListener('change', function () {
        document.getElementById('preview-image').src =
            '{{ route('admin.studio.preview', $template) }}?user=' + previewUser.value + '&t=' + Date.now();
    });

    window.addEventListener('resize', render);
    render();
})();
</script>
@endpush
