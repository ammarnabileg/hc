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

<details class="group" @if ($anyActive) open @endif>
    {{--
      `summary` هدف لمسٍ مثله مثل الرابط (2.15-ج) — وكان 36px كذلك، وهو
      **الأكثر عددًا** في الجرد لأنّ كلّ مجموعةٍ في كلّ سايد بار تمرّ منه.
    --}}
    <summary class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm cursor-pointer select-none motion-standard list-none"
             @style([
                 'background: var(--surface-raised)' => $anyActive,
                 'min-block-size: var(--touch-min, 44px)',
             ])>
        <span class="w-5 text-center">{{ $icon }}</span>
        <span class="flex-1">{{ $label }}</span>
        <span class="text-xs opacity-60 group-open:rotate-90 motion-standard inline-block">‹</span>
    </summary>

    <div class="mt-1 space-y-1 pe-3" style="border-inline-end: 1px solid var(--border)">
        @foreach ($items as $item)
            {{-- بعض بنود 12.0 تابٌ داخل صفحة، فتحتاج رابطًا بمعامل (`?tab=…`) لا اسم مسار مجرّدًا --}}
            <x-nav-link :route="$item['route'] ?? null" :href="$item['href'] ?? null" :label="$item['label'] ?? ''" icon="•" />
        @endforeach
    </div>
</details>
