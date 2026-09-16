@props([
    'route' => null,
    'href' => null,
    'label' => '',
    'icon' => '',
    'badge' => 0,
])

@php
    // أثناء البناء التدريجيّ: المسار غير الموجود يُعرَض معطَّلًا بدل أن يكسر الصفحة
    $exists = $route ? \Illuminate\Support\Facades\Route::has($route) : (bool) $href;
    $url = $href ?: ($exists && $route ? route($route) : '#');
    $active = $exists && $route && request()->routeIs($route.'*');
@endphp

{{--
  ⭐ صفّ السايد بار حرفيًّا من ملف الهويّة (`.nav-item`): 44px · فجوة 12 · زوايا
  6px · لون هادئ، والحاليّ تعبئة `--brand-soft` مع شريط 3px على جهة البداية
  (`.nav-item.active::before` في app.css) — لا تعبئة بطاقة.

  و**هدف لمسٍ 44×44** (2.15-ج) يبقى مضمونًا بالحدّ الأدنى لا بالحشوة: الحدّ
  يرفع القصير ولا يمدّ الطويل، والقيمة من الإعدادات (2.13).
--}}
{{--
  ⭐ التابلت (768-1199px، وضع `.compact` — 2.10.1-13): سايد بار 80px أيقونات
  فقط، والاسم يظهر بـTooltip عند الهوفر. `title` هنا هو تلك الـTooltip
  الأصيلة (بلا جافاسكربت)، وصنف `nav-item-label` هو ما يُخفى في تلك
  اللقطة عبر CSS في `sidebar-drawer.blade.php` — مصدرٌ واحد للسلوك
  يشمل كلّ سايد بارات المنصّة (المتدرّب/الإدارة) لأنّه في المكوّن المشترك.
--}}
<a href="{{ $url }}"
   title="{{ $label }}{{ (int) $badge > 0 ? ' ('.$badge.')' : '' }}"
   @class([
       'nav-item nav-compact-row motion-standard',
       'active' => $active,
       'opacity-40 pointer-events-none' => ! $exists,
   ])
   style="min-block-size: var(--touch-min, 44px)"
   @if ($active) aria-current="page" @endif>
    @if ($icon)
        <span class="w-5 shrink-0 inline-flex items-center justify-center"><x-icon :name="$icon" size="20" /></span>
    @endif
    <span class="flex-1 truncate nav-label nav-item-label">{{ $label }}</span>
    @if ((int) $badge > 0)
        <span class="text-xs rounded-full px-2 py-0.5 nav-item-label"
              style="background: var(--color-brand-600); color: #fff">{{ $badge }}</span>
    @endif
</a>
