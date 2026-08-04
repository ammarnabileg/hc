@use('App\Services\Store\Coins')
@php
    // السعر يُعرَض **بعملته المعلَنة** (17) — ولا عمود صامت يوهم بمجّانيّة
    $pricing = app(\App\Services\Store\PricingService::class);
@endphp

{{-- جدول 5–7 أعمدة على الديسكتوب، وكروت رأسيّة على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
<div class="card overflow-hidden">
    <table class="hidden md:table w-full text-sm">
        <thead style="background: var(--surface-sunken)">
            <tr class="text-xs" style="color: var(--text-muted)">
                <th class="text-start p-3">{{ setting('admin.store.partials.table_products.almntj', 'المنتج') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_products.altsnyf', 'التصنيف') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_products.alnwa', 'النوع') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_products.alsar', 'السعر') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_products.alhala', 'الحالة') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_products.ijraat', 'إجراءات') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $product)
                <tr style="border-top: 1px solid var(--border)">
                    <td class="p-3 font-semibold">{{ $product->name_ar }}</td>
                    <td class="p-3">{{ $product->product_category?->name_ar ?? '—' }}</td>
                    <td class="p-3">{{ $product->type }}</td>
                    <td class="p-3">{{ Coins::label($pricing->priceOf('product', $product), $pricing->currencyOf('product', $product)) }}</td>
                    <td class="p-3"><x-state-badge :state="$product->status === 'published' ? 'ok' : ($product->status === 'draft' ? 'warn' : 'idle')" :label="$product->status === 'published' ? setting('admin.store.partials.table_products.mnshwr', 'منشور') : ($product->status === 'draft' ? setting('admin.store.partials.table_products.mswda', 'مسودّة') : setting('admin.store.partials.table_products.mwrshf', 'مؤرشف'))" /></td>
                    <td class="p-3">
                        @can('store_products.archive')
                            <form method="post" action="{{ route('admin.store.products.archive', $product) }}">
                                @csrf
                                <button class="text-xs underline">{{ setting('admin.store.partials.table_products.arshfa', 'أرشفة') }}</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="md:hidden">
        @foreach ($rows as $product)
            <details style="border-top: 1px solid var(--border)">
                <summary class="p-3 flex items-center justify-between gap-2 cursor-pointer">
                    <span class="font-semibold text-sm">{{ $product->name_ar }}</span>
                    <x-state-badge :state="$product->status === 'published' ? 'ok' : 'warn'" :label="$product->status === 'published' ? setting('admin.store.partials.table_products.mnshwr', 'منشور') : setting('admin.store.partials.table_products.mswda', 'مسودّة')" />
                </summary>
                <div class="px-3 pb-3 text-xs space-y-1" style="color: var(--text-muted)">
                    <div>{{ setting('admin.store.partials.table_products.altsnyf_2', 'التصنيف:') }} {{ $product->product_category?->name_ar ?? '—' }}</div>
                    <div>{{ setting('admin.store.partials.table_products.alnwa_2', 'النوع:') }} {{ $product->type }}</div>
                    <div>{{ setting('admin.store.partials.table_products.alsar_2', 'السعر:') }} {{ Coins::label($pricing->priceOf('product', $product), $pricing->currencyOf('product', $product)) }}</div>
                </div>
            </details>
        @endforeach
    </div>
</div>
