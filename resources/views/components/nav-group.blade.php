@props([
    'label' => '',
    'icon' => '',
    'items' => [],
])

@php
    $anyActive = collect($items)->contains(
        fn ($i) => isset($i['route'])
            && \Illuminate\Support\Facades\Route::has($i['route'])
            && request()->routeIs($i['route'].'*')
    );
@endphp

{{--
  ⭐ المجموعة حرفيًّا من ملف الهويّة: صفّ `.nav-item` وسهم `.chev` مدفوعٌ إلى
  جهة النهاية، والبنود في `.nav-sub` بخطٍّ رفيع على جهة البداية.

  والتابلت (768-1199px، وضع `.compact` — 2.10.1-13): المجموعة تصير
  أيقونةً فقط + Tooltip، وفتحها (`<details open>` الأصيل بلا جافاسكربت
  للتفاعل نفسه) يعرض البنود في **Flyout** عائم بجانب الأيقونة بدل توسيعٍ
  يكسر عرض 80px — التمركز والإغلاق بالنقر خارجها في سكربت
  `sidebar-drawer.blade.php` (`data-nav-group`/`data-flyout`).
--}}
<details class="group" data-nav-group @if ($anyActive) open @endif>
    {{-- `summary` هدف لمسٍ مثله مثل الرابط (2.15-ج) — والحدّ الأدنى من الإعدادات (2.13) --}}
    <summary title="{{ $label }}"
             @class(['nav-item nav-compact-row cursor-pointer select-none motion-standard list-none', 'active' => $anyActive])
             style="min-block-size: var(--touch-min, 44px)">
        @if ($icon)
            <span class="w-5 shrink-0 inline-flex items-center justify-center"><x-icon :name="$icon" size="20" /></span>
        @endif
        <span class="flex-1 truncate nav-label nav-item-label">{{ $label }}</span>
        <x-icon name="down" size="14" class="chev nav-item-label group-open:rotate-180 motion-standard" />
    </summary>

    <div data-flyout class="nav-sub">
        @foreach ($items as $item)
            {{-- بعض بنود 12.0 تابٌ داخل صفحة، فتحتاج رابطًا بمعامل (`?tab=…`) لا اسم مسار مجرّدًا --}}
            <x-nav-link :route="$item['route'] ?? null" :href="$item['href'] ?? null" :label="$item['label'] ?? ''" />
        @endforeach
    </div>
</details>
