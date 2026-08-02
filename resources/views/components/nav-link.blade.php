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

<a href="{{ $url }}"
   @class([
       'flex items-center gap-2 rounded-xl px-3 py-2 text-sm motion-standard',
       'opacity-40 pointer-events-none' => ! $exists,
   ])
   @style([
       'background: var(--surface-raised)' => $active,
       'color: var(--text)',
   ])
   @if ($active) aria-current="page" @endif>
    <span class="w-5 text-center">{{ $icon }}</span>
    <span class="flex-1 truncate">{{ $label }}</span>
    @if ((int) $badge > 0)
        <span class="text-xs rounded-full px-2 py-0.5"
              style="background: var(--color-brand-600); color: #04201c">{{ $badge }}</span>
    @endif
</a>
