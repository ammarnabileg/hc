<div class="card overflow-hidden">
    <table class="hidden md:table w-full text-sm">
        <thead style="background: var(--surface-sunken)">
            <tr class="text-xs" style="color: var(--text-muted)">
                <th class="text-start p-3">البندل</th>
                <th class="text-start p-3">عدد العناصر</th>
                <th class="text-start p-3">القيمة الإجماليّة</th>
                <th class="text-start p-3">سعر البندل</th>
                <th class="text-start p-3">التوفير</th>
                <th class="text-start p-3">الحالة</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $bundle)
                @php
                    // نسبة التوفير محسوبة في العرض — والقاعدة أنّ الأرقام لا تُحرَق
                    $saving = (float) $bundle->original_value > 0
                        ? round((1 - (float) $bundle->price_coins / (float) $bundle->original_value) * 100)
                        : 0;
                @endphp
                <tr style="border-top: 1px solid var(--border)">
                    <td class="p-3 font-semibold">
                        {{-- إدارة عناصر الباقة وتسعير كلٍّ منها (18) --}}
                        <a href="{{ route('admin.store.bundles.show', $bundle) }}" class="hover:underline">{{ $bundle->name_ar }}</a>
                    </td>
                    <td class="p-3">{{ $bundle->items_count ?? 0 }}</td>
                    <td class="p-3">{{ rtrim(rtrim(number_format((float) $bundle->original_value, 2), '0'), '.') }}</td>
                    <td class="p-3">{{ rtrim(rtrim(number_format((float) $bundle->price_coins, 2), '0'), '.') }}</td>
                    <td class="p-3">{{ $saving }}%</td>
                    <td class="p-3"><x-state-badge :state="$bundle->status === 'published' ? 'ok' : 'warn'" :label="$bundle->status === 'published' ? 'نشط' : 'مسودّة'" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="md:hidden">
        @foreach ($rows as $bundle)
            <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                <a href="{{ route('admin.store.bundles.show', $bundle) }}" class="font-semibold hover:underline">{{ $bundle->name_ar }}</a>
                <div class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ $bundle->items_count ?? 0 }} عنصر · {{ rtrim(rtrim(number_format((float) $bundle->price_coins, 2), '0'), '.') }} كوينز
                </div>
            </div>
        @endforeach
    </div>
</div>
