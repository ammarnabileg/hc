{{--
    ⭐ **مبدّل السكشن الثلاثيّ** — موروث · ظاهر · مخفيّ.

    ولماذا ثلاثةٌ لا توجّلٌ ثنائيّ؟ لأنّ الثنائيّ يخلط **«أخفِه لهذا البندل»**
    بـ**«اتبع الإعداد العامّ»**: أوّل ما يُحفَظ البندل يتجمّد على قيمة اليوم،
    فيغيّر المالك التوجّل العامّ لاحقًا فلا يتحرّك شيء — إعدادٌ بلا أثر (2.13).

    المتغيّرات: $bundle · $landingService · $section · $label
--}}
@php
    $state = $landingService->sectionState($bundle, $section);
    $stateLabels = (array) setting('store.admin.bundles.state_labels', []);
    $inheritedOn = (bool) setting(\App\Services\Store\BundleLanding::SECTIONS[$section] ?? '', true);
@endphp

<div class="rounded-xl p-3 min-w-0" style="background: var(--surface-sunken)">
    <span class="block text-sm font-semibold mb-2">{{ $label }}</span>

    <div class="flex items-center gap-2 flex-wrap">
        @foreach (['inherit', 'show', 'hide'] as $option)
            <label class="inline-flex items-center gap-1 text-xs rounded-lg px-3 cursor-pointer"
                   style="min-height: 44px; border: 1px solid {{ $state === $option ? 'var(--color-brand-500)' : 'var(--border)' }}">
                <input type="radio" name="landing_sections[{{ $section }}]" value="{{ $option }}" @checked($state === $option)>
                <span>{{ $stateLabels[$option] ?? $option }}</span>
            </label>
        @endforeach
    </div>

    {{-- ما الذي سيقع لو تُرِك «موروث»؟ يُقال صراحةً بدل أن يُخمَّن --}}
    <span class="block text-xs mt-2" style="color: var(--text-muted)">
        {{ setting('store.admin.bundles.inherit_state_hint', 'الموروث دلوقتي:') }}
        {{ $inheritedOn ? ($stateLabels['show'] ?? '') : ($stateLabels['hide'] ?? '') }}
    </span>
</div>
