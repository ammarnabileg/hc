@props([
    'name' => null,
    'checked' => false,
    'label' => '',
    'hint' => '',
    'disabled' => false,
    'value' => '1',
])

@php
    /*
     | ⭐ السويتش (2.10.1-10) — **مكوّن نظام التصميم**، لا نسخة في كلّ شاشة.
     |
     | نصّ البند حرفيًّا: «المسار `40×22px`/`999px`/`rgba(255,255,255,.15)`؛ عند
     | التفعيل → `--t`. · الإبهام دائرة `16px` بيضاء تنزلق `3px→21px` (RTL عبر
     | `inset-inline-start`)». والمقاسات كلّها في `.hc-switch` من الإعدادات.
     |
     | **لماذا `checkbox` لا `select`؟** لأنّ السويتش حالةٌ من اثنتين تُقلَب
     | بضغطةٍ واحدة، والقائمة المنسدلة تجعلها **ثلاث ضغطاتٍ وقائمةً تفتح** —
     | وتكسر شكل البند نفسه. وكانت شاشات الإعدادات تعرضه `<select>` بخيارَي
     | «مفعَّل/موقوف»، فلا سويتش في المنصّة أصلًا خارج «الوضع المتقدّم».
     |
     | و`accent-color` **مسموحة على الشيك-بوكس** (المنع في 2.10.1-11 على
     | المنزلقات وحدها) — لكنّها هنا بلا معنًى: الصندوق نفسه `opacity:0` تحت
     | المسار المرسوم، فالمرئيّ هو `.hc-switch` لا النايتف.
     |
     | ويعمل بلا جافاسكربت: `<input type=checkbox>` حقيقيّ داخل `<label>` —
     | يُرسَل مع الفورم ويُقلَب بلوحة المفاتيح (تحسينٌ تدريجيّ 2.1).
     */
    $id = 'sw-'.substr(md5(($name ?? '').$label.uniqid('', true)), 0, 8);
@endphp

<label for="{{ $id }}"
       {{ $attributes->class(['relative inline-flex items-center gap-2 cursor-pointer select-none text-sm'])
           ->merge(['style' => 'min-block-size: var(--touch-min, 44px)']) }}>

    {{--
      الصندوق الحقيقيّ: مخفيٌّ بصريًّا وحاضرٌ للفورم ولقارئ الشاشة.
      ⛔ ولا `opacity:0` — القاعدة الإلزاميّة الرابعة في 2.10.1 تحذّر منها،
      والقصّ (`clip-path`) يخفي بلا تعتيمٍ ويبقي التركيز والإرسال يعملان.
    --}}
    <input type="checkbox" id="{{ $id }}"
           @if ($name) name="{{ $name }}" @endif
           value="{{ $value }}"
           @checked($checked) @disabled($disabled)
           class="absolute"
           style="inline-size: 1px; block-size: 1px; padding: 0; margin: -1px;
                  overflow: hidden; clip-path: inset(50%); white-space: nowrap; border: 0">

    <span class="hc-switch" aria-hidden="true"></span>

    @if ($label !== '' || $hint !== '')
        <span class="min-w-0">
            @if ($label !== '')<span class="block">{{ $label }}</span>@endif
            @if ($hint !== '')
                <span class="block text-xs" style="color: var(--text-muted)">{{ $hint }}</span>
            @endif
        </span>
    @endif

    {{ $slot }}
</label>
