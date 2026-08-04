@extends('layouts.admin')

@section('title', 'تعديل قالب: ' . $template->name)

@section('content')
    <x-page-header :title="'قالب: ' . $template->name"
                   subtitle="طبقات · مقاسات · حقول من القائمة المقفولة — ومعاينة ببيانات حقيقيّة."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'استوديو الصور', 'url' => route('admin.studio.index')],
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
                <h2 class="font-bold text-sm">الأساسيّات</h2>
                <x-form.input name="name" label="اسم القالب" :value="$template->name" required />

                {{-- المقاس الجاهز عمودٌ في القاعدة (`preset`) — فيُرسَل باسمه لا كمساعدٍ بصريّ --}}
                <label class="block text-sm">
                    <span class="block mb-1">مقاس جاهز</span>
                    <select name="preset" id="preset-select" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">مخصّص</option>
                        @foreach ($presets as $key => $preset)
                            <option value="{{ $key }}" data-size="{{ $preset['width'] }}x{{ $preset['height'] }}"
                                    @selected($template->preset === $key)>{{ $preset['label'] }} — {{ $preset['width'] }}×{{ $preset['height'] }}</option>
                        @endforeach
                    </select>
                    {{-- ⭐ تغيير المقاس يعيد ترتيب الطبقات نسبيًّا فلا يفسد التصميم --}}
                    <span class="block text-xs mt-1" style="color: var(--text-muted)">تغيير المقاس بيعيد توزيع الطبقات نسبيًّا تلقائيًّا.</span>
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <x-form.input name="width_px" label="العرض" type="number" :value="$template->width_px" required />
                    <x-form.input name="height_px" label="الطول" type="number" :value="$template->height_px" required />
                </div>

                <label class="block text-sm">
                    <span class="block mb-1">الجمهور</span>
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
                    <input type="checkbox" name="is_active" value="1" @checked($template->is_active)> مفعَّل
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

            {{-- لوحة الطبقات: إظهار/إخفاء · رفع/إنزال · قفل (12.14-أ) --}}
            <div class="card p-4 space-y-3" id="layers-panel">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-sm">لوحة الطبقات</h2>
                    <div class="flex gap-2 text-xs">
                        <button type="button" data-add-layer="text" class="underline">+ نصّ</button>
                        <button type="button" data-add-layer="avatar" class="underline">+ صورة المستخدم</button>
                    </div>
                </div>

                <div id="layers-list" class="space-y-3"></div>
            </div>

            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">حفظ القالب</button>
        </form>

        <div class="space-y-4">
            <div class="card p-4">
                <h2 class="font-bold text-sm mb-2">معاينة ببيانات مستخدم حقيقيّ</h2>
                <label class="block text-sm mb-2">
                    <span class="block mb-1">المستخدم</span>
                    <select id="preview-user" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($sampleUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} — #{{ $user->code }}</option>
                        @endforeach
                    </select>
                </label>
                <img id="preview-image" src="{{ route('admin.studio.preview', $template) }}"
                     alt="معاينة القالب" class="w-full rounded-xl" style="max-width:100%; background: var(--surface-sunken)">
                <p class="text-xs mt-2" style="color: var(--text-muted)">
                    كلّ صورة مستخرَجة بتحمل تاريخ اللقطة وشعار المنصّة — فمحدش ينشر ترتيبًا قديمًا كأنّه حاليّ.
                </p>
            </div>

            @can('image_templates.batch')
                <form method="post" action="{{ route('admin.studio.batch', $template) }}" class="card p-4 space-y-3">
                    @csrf
                    <h2 class="font-bold text-sm">توليد جماعيّ ⟵ ZIP</h2>
                    <label class="block text-sm">
                        <span class="block mb-1">الشريحة</span>
                        <select name="segment" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="top_xp">أوائل الليدر بورد (XP)</option>
                            <option value="volunteers">المتطوّعون النشطون</option>
                            <option value="active">الحسابات المفعَّلة</option>
                        </select>
                    </label>
                    <x-form.input name="limit" label="العدد" type="number" value="10" required />
                    <button class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">ولّد الأرشيف</button>
                </form>
            @endcan

            <div class="card p-4 text-xs" style="color: var(--text-muted)">
                <p class="font-bold mb-1" style="color: var(--text)">الحقول المسموحة فقط</p>
                <p>{{ implode(' · ', $allowedFields) }}</p>
                {{-- ⛔ الممنوع غير موجود في القائمة أصلًا — لا معطَّلًا (12.14-د) --}}
                <p class="mt-2">الموبايل والبريد وجهة الطوارئ والملاحظات الإداريّة مش موجودة في القائمة أصلًا.</p>
            </div>
        </div>
    </div>

    {{-- ⭐ نفس بوب-أب «اختَر من المكتبة / ارفع جديد» — لا منتقي وسائط ثانٍ (2.14-ب) --}}
    @include('admin.courses.partials.media-picker-modal')
@endsection

@push('scripts')
<script>
/*
 | محرّر الطبقات بجافاسكربت خام — بلا أيّ مكتبة خارجيّة.
 | الحقول تأتي من القائمة المقفولة وحدها، والخادم يرفض أيّ حقل خارجها.
 */
(function () {
    var allowed = @json($allowedFields);
    var layers = @json($template->layers ?? []);
    var list = document.getElementById('layers-list');

    function fieldOptions(selected) {
        var html = '<option value="">نصّ ثابت</option>';
        Object.keys(allowed).forEach(function (key) {
            html += '<option value="' + key + '"' + (selected === key ? ' selected' : '') + '>' + allowed[key] + '</option>';
        });
        return html;
    }

    function row(layer, index) {
        var wrap = document.createElement('div');
        wrap.className = 'rounded-xl p-3 space-y-2 text-sm';
        wrap.style.background = 'var(--surface-sunken)';

        var head = '<div class="flex items-center justify-between gap-2">'
            + '<strong>' + (layer.type === 'avatar' ? 'صورة المستخدم' : 'نصّ') + ' #' + (index + 1) + '</strong>'
            + '<span class="flex gap-2 text-xs">'
            + '<label><input type="checkbox" name="layers[' + index + '][visible]" value="1"' + (layer.visible !== false ? ' checked' : '') + '> ظاهرة</label>'
            + '<label><input type="checkbox" name="layers[' + index + '][locked]" value="1"' + (layer.locked ? ' checked' : '') + '> مقفولة</label>'
            + '<button type="button" class="underline" data-move="up" data-index="' + index + '">▲</button>'
            + '<button type="button" class="underline" data-move="down" data-index="' + index + '">▼</button>'
            + '<button type="button" class="underline" data-remove="' + index + '">✕</button>'
            + '</span></div>'
            + '<input type="hidden" name="layers[' + index + '][type]" value="' + layer.type + '">'
            + '<div class="grid grid-cols-2 gap-2">'
            + '<label class="text-xs">X<input type="number" class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][x]" value="' + (layer.x || 0) + '"></label>'
            + '<label class="text-xs">Y<input type="number" class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][y]" value="' + (layer.y || 0) + '"></label>'
            + '<label class="text-xs">دوران<input type="number" class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][rotate]" value="' + (layer.rotate || 0) + '"></label>';

        if (layer.type === 'avatar') {
            head += '<label class="text-xs">العرض<input type="number" class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][w]" value="' + (layer.w || 240) + '"></label>'
                + '<label class="text-xs">الطول<input type="number" class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][h]" value="' + (layer.h || 240) + '"></label>'
                + '<label class="text-xs">الشكل<select class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][shape]">'
                + '<option value="square"' + (layer.shape === 'square' ? ' selected' : '') + '>مربّع</option>'
                + '<option value="circle"' + (layer.shape === 'circle' ? ' selected' : '') + '>دائرة</option>'
                + '<option value="circle_border"' + (layer.shape === 'circle_border' ? ' selected' : '') + '>دائرة بحدّ</option>'
                + '</select></label>'
                + '<label class="text-xs">القصّ<select class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][fit]">'
                + '<option value="cover"' + (layer.fit === 'cover' ? ' selected' : '') + '>يملأ</option>'
                + '<option value="contain"' + (layer.fit === 'contain' ? ' selected' : '') + '>يحتوي</option>'
                + '</select></label>';
        } else {
            head += '<label class="text-xs">الحقل<select class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][field]">' + fieldOptions(layer.field) + '</select></label>'
                + '<label class="text-xs">نصّ ثابت<input type="text" class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][text]" value="' + (layer.text || '') + '"></label>'
                + '<label class="text-xs">حجم الخطّ<input type="number" class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][size]" value="' + (layer.size || 32) + '"></label>'
                + '<label class="text-xs">اللون<input type="color" class="w-full rounded-lg" name="layers[' + index + '][color]" value="' + (layer.color || '#ffffff') + '"></label>'
                + '<label class="text-xs">المحاذاة<select class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][align]">'
                + '<option value="right"' + (layer.align === 'right' ? ' selected' : '') + '>يمين</option>'
                + '<option value="center"' + (layer.align === 'center' ? ' selected' : '') + '>وسط</option>'
                + '<option value="left"' + (layer.align === 'left' ? ' selected' : '') + '>يسار</option>'
                + '</select></label>'
                + '<label class="text-xs">حدّ الأحرف<input type="number" class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][max_chars]" value="' + (layer.max_chars || 28) + '"></label>'
                + '<label class="text-xs">عند التجاوز<select class="w-full rounded-lg px-2 py-1" name="layers[' + index + '][overflow]">'
                + '<option value="shrink"' + (layer.overflow === 'shrink' ? ' selected' : '') + '>تصغير تلقائيّ</option>'
                + '<option value="truncate"' + (layer.overflow === 'truncate' ? ' selected' : '') + '>قصّ بثلاث نقاط</option>'
                + '</select></label>';
        }

        wrap.innerHTML = head + '</div>';
        return wrap;
    }

    function render() {
        list.innerHTML = '';
        layers.forEach(function (layer, index) { list.appendChild(row(layer, index)); });
    }

    function collect() {
        // نقرأ القيم الحاليّة قبل أيّ إعادة رسم حتى لا يضيع تعديل المستخدم
        layers = layers.map(function (layer, index) {
            var scope = list.children[index];
            if (!scope) { return layer; }

            scope.querySelectorAll('[name]').forEach(function (input) {
                var match = input.getAttribute('name').match(/\[(\w+)\]$/);
                if (!match) { return; }
                layer[match[1]] = input.type === 'checkbox' ? input.checked : input.value;
            });

            return layer;
        });
    }

    list.addEventListener('click', function (event) {
        var move = event.target.getAttribute('data-move');
        var remove = event.target.getAttribute('data-remove');

        if (move) {
            collect();
            var i = parseInt(event.target.getAttribute('data-index'), 10);
            var j = move === 'up' ? i + 1 : i - 1;

            if (layers[i] && layers[j] && !layers[i].locked && !layers[j].locked) {
                var tmp = layers[i];
                layers[i] = layers[j];
                layers[j] = tmp;
                render();
            }
        }

        if (remove !== null && remove !== undefined) {
            collect();
            layers.splice(parseInt(remove, 10), 1);
            render();
        }
    });

    document.querySelectorAll('[data-add-layer]').forEach(function (button) {
        button.addEventListener('click', function () {
            collect();
            var type = button.getAttribute('data-add-layer');
            layers.push(type === 'avatar'
                ? { type: 'avatar', x: 40, y: 40, w: 240, h: 240, shape: 'circle', fit: 'cover', visible: true }
                : { type: 'text', x: 40, y: 340, size: 40, color: '#ffffff', align: 'right', field: 'short_name', visible: true });
            render();
        });
    });

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

    render();
})();
</script>
@endpush
