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
  ⭐ التابلت (768-1199px، وضع `.compact` — 2.10.1-13): المجموعة تصير
  أيقونةً فقط + Tooltip، وفتحها (`<details open>` الأصيل بلا جافاسكربت
  للتفاعل نفسه) يعرض البنود في **Flyout** عائم بجانب الأيقونة بدل توسيعٍ
  يكسر عرض 80px — التمركز والإغلاق بالنقر خارجها في سكربت
  `sidebar-drawer.blade.php` (`data-nav-group`/`data-flyout`).
--}}
<details class="group" data-nav-group @if ($anyActive) open @endif>
    {{--
      `summary` هدف لمسٍ مثله مثل الرابط (2.15-ج) — وكان 36px كذلك، وهو
      **الأكثر عددًا** في الجرد لأنّ كلّ مجموعةٍ في كلّ سايد بار تمرّ منه.
    --}}
    <summary title="{{ $label }}"
             class="nav-compact-row flex items-center gap-2 rounded-xl px-3 py-2 text-sm cursor-pointer select-none motion-standard list-none"
             @style([
                 'background: var(--surface-raised)' => $anyActive,
                 'min-block-size: var(--touch-min, 44px)',
             ])>
        <span class="w-5 text-center shrink-0">{{ $icon }}</span>
        <span class="flex-1 nav-item-label">{{ $label }}</span>
        <span class="text-xs opacity-60 group-open:rotate-90 motion-standard inline-block nav-item-label">‹</span>
    </summary>

    <div data-flyout class="mt-1 space-y-1 pe-3" style="border-inline-end: 1px solid var(--border)">
        @foreach ($items as $item)
            {{-- بعض بنود 12.0 تابٌ داخل صفحة، فتحتاج رابطًا بمعامل (`?tab=…`) لا اسم مسار مجرّدًا --}}
            <x-nav-link :route="$item['route'] ?? null" :href="$item['href'] ?? null" :label="$item['label'] ?? ''" icon="•" />
        @endforeach
    </div>
</details>
