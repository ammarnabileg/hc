{{-- جدول 5–7 أعمدة على الديسكتوب، وكروت رأسيّة على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
<div class="card overflow-hidden">
    <table class="hidden md:table w-full text-sm">
        <thead style="background: var(--surface-sunken)">
            <tr class="text-xs" style="color: var(--text-muted)">
                <th class="text-start p-3">المنتج</th>
                <th class="text-start p-3">التصنيف</th>
                <th class="text-start p-3">النوع</th>
                <th class="text-start p-3">السعر (كوينز)</th>
                <th class="text-start p-3">الحالة</th>
                <th class="text-start p-3">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $product)
                <tr style="border-top: 1px solid var(--border)">
                    <td class="p-3 font-semibold">{{ $product->name_ar }}</td>
                    <td class="p-3">{{ $product->product_category?->name_ar ?? '—' }}</td>
                    <td class="p-3">{{ $product->type }}</td>
                    <td class="p-3">{{ rtrim(rtrim(number_format((float) $product->price_coins, 2), '0'), '.') }}</td>
                    <td class="p-3"><x-state-badge :state="$product->status === 'published' ? 'ok' : ($product->status === 'draft' ? 'warn' : 'idle')" :label="$product->status === 'published' ? 'منشور' : ($product->status === 'draft' ? 'مسودّة' : 'مؤرشف')" /></td>
                    <td class="p-3">
                        @can('store_products.archive')
                            <form method="post" action="{{ route('admin.store.products.archive', $product) }}">
                                @csrf
                                <button class="text-xs underline">أرشفة</button>
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
                    <x-state-badge :state="$product->status === 'published' ? 'ok' : 'warn'" :label="$product->status === 'published' ? 'منشور' : 'مسودّة'" />
                </summary>
                <div class="px-3 pb-3 text-xs space-y-1" style="color: var(--text-muted)">
                    <div>التصنيف: {{ $product->product_category?->name_ar ?? '—' }}</div>
                    <div>النوع: {{ $product->type }}</div>
                    <div>السعر: {{ rtrim(rtrim(number_format((float) $product->price_coins, 2), '0'), '.') }} كوينز</div>
                </div>
            </details>
        @endforeach
    </div>
</div>
