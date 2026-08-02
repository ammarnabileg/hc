<div class="card overflow-hidden">
    <table class="hidden md:table w-full text-sm">
        <thead style="background: var(--surface-sunken)">
            <tr class="text-xs" style="color: var(--text-muted)">
                <th class="text-start p-3">الملفّ</th>
                <th class="text-start p-3">النوع</th>
                <th class="text-start p-3">وضع الحماية</th>
                <th class="text-start p-3">صفحات العيّنة</th>
                <th class="text-start p-3">تعديل الحماية</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $item)
                <tr style="border-top: 1px solid var(--border)">
                    <td class="p-3 font-semibold">{{ $item->name_ar }}</td>
                    <td class="p-3">{{ $item->type }}</td>
                    <td class="p-3">
                        <x-state-badge :state="$item->is_downloadable ? 'idle' : 'ok'"
                                       :label="$item->is_downloadable ? 'قابل للتحميل' : 'Flip-only محميّ'" />
                    </td>
                    <td class="p-3">{{ $item->teaser_pages }}</td>
                    <td class="p-3">
                        @can('product_protection.manage')
                            <form method="post" action="{{ route('admin.store.protection.update', $item) }}" class="flex items-center gap-2">
                                @csrf
                                <select name="protection" class="rounded-lg px-2 py-1 text-xs"
                                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                                    @foreach ($protectionModes as $key => $label)
                                        <option value="{{ $key }}" @selected(($key === 'download') === (bool) $item->is_downloadable)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="teaser_pages" value="{{ $item->teaser_pages }}" min="0" max="200"
                                       class="w-16 rounded-lg px-2 py-1 text-xs"
                                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                                <button class="text-xs underline">حفظ</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="md:hidden">
        @foreach ($rows as $item)
            <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                <div class="font-semibold">{{ $item->name_ar }}</div>
                <div class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ $item->is_downloadable ? 'قابل للتحميل' : 'Flip-only محميّ' }} · عيّنة {{ $item->teaser_pages }} صفحة
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- التحليلات مجمّعة فقط — ولا سجلّ فتح فرديّ لأيّ ملفّ (مرفوض صراحةً في 20.5) --}}
<p class="text-xs mt-3" style="color: var(--text-muted)">
    التحليلات مجمّعة فقط (الأكثر قراءةً · متوسّط الإكمال) — مافيش سجلّ فتح فرديّ لأيّ مستخدم.
</p>
