@php
    /**
     * ثلاثة فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4).
     * ونطاق السعر بمنزلق **بلا بوردر** (24.5 — والقاعدة مطبَّقة في app.css).
     */
    $selectedTypes = $filters['types'] ?? [];
    $selectedCurrencies = $filters['currencies'] ?? [];
    $inputStyle = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
    $min = $filters['min'] ?? 0;
    $max = $filters['max'] ?? $priceCeiling;
    // اسم عملة المنزلق — والمنزلق يتحرّك ضمنها وحدها (17)
    $rangeLabel = \App\Services\Store\Coins::currencyLabel($rangeCurrency ?? null);
    $allTypeOptions = isset($typeOptions) ? array_merge($typeOptions['visible'], $typeOptions['folded']) : [];
    // خيارات الفرز والملكيّة من الإعدادات — القيمة مفتاحٌ ثابت والنصّ وحده يتغيّر (2.13)
    $sortOptions = (array) setting('store.filters.sort_options', ['newest' => 'الأحدث', 'price_asc' => 'الأرخص أوّلًا', 'price_desc' => 'الأغلى أوّلًا']);
    $ownedOptions = (array) setting('store.filters.owned_options', ['new' => 'اللي مش معايا', 'mine' => 'اللي معايا']);
    $allLabel = setting('store.filters.all_label', 'الكلّ');
@endphp

<x-filters :action="$action">
    <label class="block grow min-w-[12rem]">
        <span class="block text-sm mb-1">{{ setting('store.filters.search_label', 'بحث') }}</span>
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('store.filters.search_placeholder', 'اكتب اسم اللي بتدوّر عليه') }}"
               class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $inputStyle }}">
    </label>

    @isset($currencyOptions)
        {{-- ⭐ فلتر **نوع العملة** (Multi-select) — 17 --}}
        <fieldset class="min-w-[12rem]">
            <legend class="block text-sm mb-1">{{ setting('store.filters.currency_label', 'نوع العملة') }}</legend>
            <div class="flex flex-wrap gap-2">
                @foreach ($currencyOptions as $code => $label)
                    <label class="text-sm rounded-full px-3 py-1.5 cursor-pointer motion-standard"
                           style="background: var(--surface-sunken); border: 1px solid var(--border)">
                        <input type="checkbox" name="currencies[]" value="{{ $code }}"
                               @checked(in_array($code, $selectedCurrencies, true))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endisset

    <div class="block min-w-[14rem] grow" data-price-range>
        <span class="block text-sm mb-1">
            {{ setting('store.filters.price_range_label', 'نطاق السعر') }}
            <span class="text-xs" style="color: var(--text-muted)">
                (<span data-price-min>{{ (int) $min }}</span> — <span data-price-max>{{ (int) $max }}</span>
                {{ $rangeLabel }})
            </span>
        </span>
        {{-- منزلقان بلا بوردر: الأدنى والأعلى --}}
        <input type="range" name="min" min="0" max="{{ (int) $priceCeiling }}" value="{{ (int) $min }}"
               class="w-full" aria-label="{{ setting('store.filters.price_min_label', 'أقلّ سعر') }}" data-price-input="min">
        <input type="range" name="max" min="0" max="{{ (int) $priceCeiling }}" value="{{ (int) $max }}"
               class="w-full" aria-label="{{ setting('store.filters.price_max_label', 'أعلى سعر') }}" data-price-input="max">
    </div>

    <button type="submit"
            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
            style="background: var(--color-brand-500); color: #04201c">{{ setting('store.filters.apply_label', 'طبّق') }}</button>

    <x-slot:advanced>
        @isset($categories)
            <label class="block min-w-[10rem]">
                <span class="block text-sm mb-1">{{ setting('store.filters.category_label', 'التصنيف') }}</span>
                <select name="category" class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $inputStyle }}">
                    <option value="">{{ $allLabel }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name_ar }}</option>
                    @endforeach
                </select>
            </label>
        @endisset

        @isset($typeOptions)
            {{-- نوع العنصر مطويّ: الفلتران الظاهران هما **العملة ونطاق السعر** كما ينصّ 17 --}}
            <fieldset class="min-w-[14rem]">
                <legend class="block text-sm mb-1">{{ setting('store.filters.type_label', 'النوع') }}</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($allTypeOptions as $key => $label)
                        <label class="text-sm rounded-full px-3 py-1.5 cursor-pointer"
                               style="background: var(--surface-sunken); border: 1px solid var(--border)">
                            <input type="checkbox" name="types[]" value="{{ $key }}" @checked(in_array($key, $selectedTypes, true))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endisset

        <label class="block min-w-[10rem]">
            <span class="block text-sm mb-1">{{ setting('store.filters.sort_label', 'الفرز') }}</span>
            <select name="sort" class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $inputStyle }}">
                @foreach ($sortOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block min-w-[10rem]">
            <span class="block text-sm mb-1">{{ setting('store.filters.owned_label', 'الملكيّة') }}</span>
            <select name="owned" class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $inputStyle }}">
                <option value="">{{ $allLabel }}</option>
                @foreach ($ownedOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['owned'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </x-slot:advanced>
</x-filters>

@push('scripts')
    <script>
        // ردّ فوريّ لحركة المنزلق (2.17-ب): الرقم يتحدّث وأنت بتسحب، والأدنى لا يتخطّى الأعلى
        document.querySelectorAll('[data-price-range]').forEach((box) => {
            const lo = box.querySelector('[data-price-input="min"]');
            const hi = box.querySelector('[data-price-input="max"]');
            const loLabel = box.querySelector('[data-price-min]');
            const hiLabel = box.querySelector('[data-price-max]');
            if (!lo || !hi) return;

            const sync = () => {
                if (Number(lo.value) > Number(hi.value)) {
                    const swap = lo.value; lo.value = hi.value; hi.value = swap;
                }
                loLabel.textContent = lo.value;
                hiLabel.textContent = hi.value;
            };

            lo.addEventListener('input', sync);
            hi.addEventListener('input', sync);
            sync();
        });
    </script>
@endpush
