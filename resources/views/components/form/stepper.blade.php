@props(['id' => null, 'labels' => []])

@php
    /*
     | ⭐ الفورم الأطول من الحدّ يتقسّم **خطوات** بحفظ تلقائيّ بينها (2.15-ب).
     |
     | الحدّ إعدادٌ (`ux.forms.max_fields_before_stepper`) لا رقم محروق (2.13)،
     | وكان بلا قارئ — فكان الفورم الطويل يبقى طويلًا مهما ضُبط الإعداد.
     |
     | القاعدة: **حقل واحد لا يضيع**. كلّ الحقول تبقى في فورم واحد داخل الـDOM
     | (الخطوات إخفاء بصريّ لا حذف)، فالإرسال يحمل كلّ القيم دفعةً واحدة، ولو
     | قفل المستخدم الصفحة يرجع بمسودّته كما تركها («شغلك محفوظ» — 2.17-ب).
     |
     | والشكل من 2.10.1-24: دوائر مرقّمة 24px بينها خطوط رفيعة، بحالات
     | قادمة/نشطة/مكتملة — وعلى الموبايل تتمرّر أفقيًّا بلا ازدحام (2.15-ج).
     |
     | وبلا جافاسكربت: الفورم يظهر كاملًا ويعمل كما هو (تحسين تدريجيّ — 2.1).
     */
    $stepperId = $id ?: 'stepper-'.substr(md5((string) ($id ?? uniqid('', true))), 0, 8);
    $perStep = view_mode()->maxFieldsBeforeStepper();
@endphp

<div data-stepper="{{ $stepperId }}" data-stepper-size="{{ $perStep }}"
     data-stepper-labels="{{ json_encode(array_values($labels), JSON_UNESCAPED_UNICODE) }}">

    {{-- صفّ الخطوات — يبنيه الـJS ولا يظهر إلّا لو تجاوز الفورم الحدّ --}}
    <div data-stepper-head class="hidden items-center gap-2 min-w-0 overflow-x-auto no-scrollbar pb-3 mb-3"></div>

    <div data-stepper-body class="space-y-3">{{ $slot }}</div>

    <div data-stepper-nav class="hidden items-center justify-between gap-2 pt-3">
        <button type="button" data-stepper-prev
                class="btn rounded-xl px-4 py-2 text-sm motion-standard"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('ux.stepper.text_1', 'السابق') }}</button>

        <span class="text-xs" style="color: var(--text-muted)" data-stepper-saved></span>

        <button type="button" data-stepper-next
                class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('ux.stepper.text_2', 'التالي') }}</button>
    </div>
</div>
