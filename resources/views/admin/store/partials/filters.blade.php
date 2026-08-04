{{-- ثلاثة فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4) --}}
<x-filters :action="route('admin.store.index')">
    <input type="hidden" name="tab" value="{{ $tab }}">

    <label class="text-xs">
        <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.store.partials.filters.bhth', 'بحث') }}</span>
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('admin.store.partials.filters.alasm_aw_alrqm', 'الاسم أو الرقم…') }}"
               class="rounded-xl px-3 py-2 text-sm w-48"
               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
    </label>

    @if ($tab === 'products')
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.store.partials.filters.altsnyf', 'التصنيف') }}</span>
            <select name="category" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.store.partials.filters.alkl', 'الكلّ') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category'] == $category->id)>{{ $category->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.store.partials.filters.alhala', 'الحالة') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.store.partials.filters.alkl', 'الكلّ') }}</option>
                <option value="published" @selected($filters['status'] === 'published')>{{ setting('admin.store.partials.filters.mnshwr', 'منشور') }}</option>
                <option value="draft" @selected($filters['status'] === 'draft')>{{ setting('admin.store.partials.filters.mswda', 'مسودّة') }}</option>
                <option value="archived" @selected($filters['status'] === 'archived')>{{ setting('admin.store.partials.filters.mwrshf', 'مؤرشف') }}</option>
            </select>
        </label>
    @endif

    {{-- فلاتر البندلز بنصّ 24: بحث بالاسم · الحالة · نطاق السعر · الفرز --}}
    @if ($tab === 'bundles')
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('store.admin.bundles.col_status') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="min-height: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('store.admin.filter_all_label', 'الكلّ') }}</option>
                @foreach ((array) setting('store.admin.status_labels', []) as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('store.admin.bundles.sort_label', 'الفرز') }}</span>
            <select name="sort" class="rounded-xl px-3 py-2 text-sm"
                    style="min-height: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                @foreach ($bundleSortOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    @endif

    @if ($tab === 'library')
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.store.partials.filters.wda_alhmaya', 'وضع الحماية') }}</span>
            <select name="protection" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.store.partials.filters.alkl', 'الكلّ') }}</option>
                @foreach ($protectionModes as $key => $label)
                    <option value="{{ $key }}" @selected($filters['protection'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    @endif

    @if ($tab === 'orders')
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.store.partials.filters.alhala', 'الحالة') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.store.partials.filters.alkl', 'الكلّ') }}</option>
                <option value="paid" @selected($filters['status'] === 'paid')>{{ setting('admin.store.partials.filters.mktml', 'مكتمل') }}</option>
                <option value="pending" @selected($filters['status'] === 'pending')>{{ setting('admin.store.partials.filters.malq', 'معلّق') }}</option>
                <option value="failed" @selected($filters['status'] === 'failed')>{{ setting('admin.store.partials.filters.fashl', 'فاشل') }}</option>
                <option value="cancelled" @selected($filters['status'] === 'cancelled')>{{ setting('admin.store.partials.filters.mlgha', 'ملغى') }}</option>
            </select>
        </label>
    @endif

    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.store.partials.filters.fltra', 'فلترة') }}</button>

    <x-slot:advanced>
        @if ($tab === 'bundles')
            {{-- نطاق السعر (24) — على سعر البندل نفسه لا على قيمة عناصره --}}
            <label class="text-xs">
                <span class="block mb-1" style="color: var(--text-muted)">{{ setting('store.admin.bundles.price_min_label', 'أقلّ سعر') }}</span>
                <input type="number" name="price_min" min="0" value="{{ request('price_min') }}"
                       class="rounded-xl px-3 py-2 text-sm" style="min-height: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="text-xs">
                <span class="block mb-1" style="color: var(--text-muted)">{{ setting('store.admin.bundles.price_max_label', 'أعلى سعر') }}</span>
                <input type="number" name="price_max" min="0" value="{{ request('price_max') }}"
                       class="rounded-xl px-3 py-2 text-sm" style="min-height: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            </label>
        @endif

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.store.partials.filters.mn', 'من') }}</span>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.store.partials.filters.ila', 'إلى') }}</span>
            <input type="date" name="to" value="{{ $filters['to'] }}" class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-slot:advanced>
</x-filters>
