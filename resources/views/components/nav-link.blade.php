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
  ⭐ **هدف لمسٍ 44×44** (2.15-ج): «الحدّ الأدنى لمساحة اللمس 44×44 بكسل».
  كان `py-2` مع `text-sm` يعطي **36px** بالضبط (20 سطرًا + 8 + 8) — فكلّ رابط
  في سايد بارات المتدرّب والمتطوّع والإدارة كان **تحت المقاس**. والحلّ هنا
  في المكوّن المشترك لا في كلّ سايد بار على حدة: مصدرٌ واحد يصلح الثلاثة.

  و`min-block-size` لا `py-3`: الحشوة تكبّر الرابط على الديسكتوب بلا سبب،
  والحدّ الأدنى يرفع القصير ولا يمدّ الطويل. والقيمة من الإعدادات (2.13).
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
       'nav-compact-row flex items-center gap-2 rounded-xl px-3 py-2 text-sm motion-standard',
       'opacity-40 pointer-events-none' => ! $exists,
   ])
   @style([
       'background: var(--surface-raised)' => $active,
       'color: var(--text)',
       'min-block-size: var(--touch-min, 44px)',
   ])
   @if ($active) aria-current="page" @endif>
    <span class="w-5 text-center shrink-0">{{ $icon }}</span>
    <span class="flex-1 truncate nav-item-label">{{ $label }}</span>
    @if ((int) $badge > 0)
        <span class="text-xs rounded-full px-2 py-0.5 nav-item-label"
              style="background: var(--color-brand-600)">{{ $badge }}</span>
    @endif
</a>
