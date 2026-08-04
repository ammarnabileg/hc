{{--
    ⭐ **جدول البندلز بأعمدته التسعة** (24 حرفيًّا):
    الغلاف · الاسم (ع/إ) · عدد العناصر · **القيمة الإجماليّة للعناصر** · **سعر
    البندل** · نسبة التوفير المحسوبة · المشتريات · الحالة · إجراءات
    (تعديل · معاينة اللاندنج · نسخ الرابط · تكرار · أرشفة).

    وثلاثة أرقامٍ هنا **محسوبة لا مقروءة من عمودٍ مكتوب**: القيمة الإجماليّة من
    أسعار العناصر · النسبة منها ومن السعر · والمشتريات من الطلبات المدفوعة —
    فلا رقم بلا سند (18 · 2.9).
--}}
@php
    $canEdit = auth()->user()->allows('bundles.edit');
    $canDuplicate = auth()->user()->allows('bundles.create');
    $canArchive = auth()->user()->allows('bundles.archive');
    $statusLabels = (array) setting('store.admin.status_labels', []);
@endphp

<div class="card overflow-hidden">
    <div class="hidden md:block" style="overflow-x: auto">
        <table class="w-full text-sm">
            <thead style="background: var(--surface-sunken)">
                <tr class="text-xs" style="color: var(--text-muted)">
                    <th class="text-start p-3">{{ setting('store.admin.bundles.col_cover', 'الغلاف') }}</th>
                    <th class="text-start p-3">{{ setting('store.admin.bundles.col_name', 'الاسم (ع/إ)') }}</th>
                    <th class="text-start p-3">{{ setting('store.admin.bundles.col_items', 'عدد العناصر') }}</th>
                    <th class="text-start p-3">{{ setting('store.admin.bundles.col_value', 'القيمة الإجماليّة') }}</th>
                    <th class="text-start p-3">{{ setting('store.admin.bundles.col_price', 'سعر البندل') }}</th>
                    <th class="text-start p-3">{{ setting('store.admin.bundles.col_savings', 'نسبة التوفير') }}</th>
                    <th class="text-start p-3">{{ setting('store.admin.bundles.col_purchases', 'المشتريات') }}</th>
                    <th class="text-start p-3">{{ setting('store.admin.bundles.col_status', 'الحالة') }}</th>
                    <th class="text-start p-3">{{ setting('store.admin.bundles.col_actions', 'إجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $bundle)
                    <tr style="border-top: 1px solid var(--border)">
                        <td class="p-3">
                            @if ($bundle->cover_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($bundle->cover_path) }}" alt=""
                                     class="rounded-lg object-cover" style="width: 44px; height: 44px" loading="lazy">
                            @else
                                <span class="inline-flex items-center justify-center rounded-lg"
                                      style="width: 44px; height: 44px; background: var(--surface-sunken); color: var(--color-brand-500)">
                                    <x-icon name="bundle" size="20" />
                                </span>
                            @endif
                        </td>
                        <td class="p-3 font-semibold min-w-0">
                            <a href="{{ route('admin.store.bundles.show', $bundle) }}" class="hover:underline">{{ $bundle->name_ar }}</a>
                            @if ($bundle->name_en)
                                <span class="block text-xs font-normal" dir="ltr" style="color: var(--text-muted)">{{ $bundle->name_en }}</span>
                            @endif
                        </td>
                        <td class="p-3">{{ $bundle->items_count ?? 0 }}</td>
                        <td class="p-3">{{ \App\Services\Store\Coins::fmt($bundle->items_value) }}</td>
                        <td class="p-3">{{ \App\Services\Store\Coins::fmt((float) $bundle->price_coins) }}</td>
                        <td class="p-3">{{ $bundle->savings_percent }}%</td>
                        <td class="p-3">{{ $bundle->purchases ?? 0 }}</td>
                        <td class="p-3">
                            <x-state-badge :state="$bundle->status === 'published' ? 'ok' : ($bundle->status === 'archived' ? 'muted' : 'warn')"
                                           :label="$statusLabels[$bundle->status] ?? $bundle->status" />
                        </td>
                        <td class="p-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if ($canEdit)
                                    <a href="{{ route('admin.store.bundles.show', $bundle) }}"
                                       class="inline-flex items-center justify-center rounded-lg px-2 text-xs hover:underline"
                                       style="min-height: 44px; min-width: 44px">{{ setting('store.admin.bundles.edit_label', 'تعديل') }}</a>
                                @endif

                                <a href="{{ route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]) }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center justify-center rounded-lg px-2 text-xs hover:underline"
                                   style="min-height: 44px; min-width: 44px; color: var(--text-muted)">{{ setting('store.admin.bundles.preview_label') }}</a>

                                <button type="button" data-copy-link="{{ route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]) }}"
                                        class="inline-flex items-center justify-center rounded-lg px-2 text-xs hover:underline"
                                        style="min-height: 44px; min-width: 44px; color: var(--text-muted)">{{ setting('store.admin.bundles.copy_link_label') }}</button>

                                {{-- 2.15-أ-7: ما لا يملكه المستخدم **يُخفى** لا يُعطَّل --}}
                                @if ($canDuplicate)
                                    <form method="post" action="{{ route('admin.store.bundles.duplicate', $bundle) }}">
                                        @csrf
                                        <button class="inline-flex items-center justify-center rounded-lg px-2 text-xs hover:underline"
                                                style="min-height: 44px; min-width: 44px; color: var(--text-muted)">{{ setting('store.admin.bundles.duplicate_label') }}</button>
                                    </form>
                                @endif

                                @if ($canArchive && $bundle->status !== 'archived')
                                    <form method="post" action="{{ route('admin.store.bundles.archive', $bundle) }}">
                                        @csrf
                                        <button class="inline-flex items-center justify-center rounded-lg px-2 text-xs hover:underline"
                                                style="min-height: 44px; min-width: 44px; color: var(--text-muted)">{{ setting('store.admin.bundles.archive_label') }}</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
    <div class="md:hidden">
        @foreach ($rows as $bundle)
            <div class="p-3 text-sm min-w-0" style="border-top: 1px solid var(--border)">
                <div class="flex items-start gap-3 min-w-0">
                    @if ($bundle->cover_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($bundle->cover_path) }}" alt=""
                             class="rounded-lg object-cover" style="width: 44px; height: 44px" loading="lazy">
                    @else
                        <span class="inline-flex items-center justify-center rounded-lg shrink-0"
                              style="width: 44px; height: 44px; background: var(--surface-sunken); color: var(--color-brand-500)">
                            <x-icon name="bundle" size="20" />
                        </span>
                    @endif

                    <div class="min-w-0">
                        <a href="{{ route('admin.store.bundles.show', $bundle) }}" class="font-semibold hover:underline">{{ $bundle->name_ar }}</a>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $bundle->items_count ?? 0 }} · {{ \App\Services\Store\Coins::fmt((float) $bundle->price_coins) }} ·
                            {{ $bundle->savings_percent }}% · {{ $bundle->purchases ?? 0 }}
                        </div>
                        <div class="mt-2">
                            <x-state-badge :state="$bundle->status === 'published' ? 'ok' : ($bundle->status === 'archived' ? 'muted' : 'warn')"
                                           :label="$statusLabels[$bundle->status] ?? $bundle->status" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-2 flex-wrap">
                    <a href="{{ route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]) }}" target="_blank" rel="noopener"
                       class="inline-flex items-center text-xs hover:underline"
                       style="min-height: 44px; color: var(--text-muted)">{{ setting('store.admin.bundles.preview_label') }}</a>

                    @if ($canDuplicate)
                        <form method="post" action="{{ route('admin.store.bundles.duplicate', $bundle) }}">
                            @csrf
                            <button class="inline-flex items-center text-xs hover:underline"
                                    style="min-height: 44px; color: var(--text-muted)">{{ setting('store.admin.bundles.duplicate_label') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
    <script>
        // «نسخ الرابط» من إجراءات 24 — ردٌّ فوريّ بلا مغادرة الصفحة (2.17-ب)
        document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.dataset.copyLink;
                if (navigator.clipboard) { navigator.clipboard.writeText(url); }
                btn.setAttribute('data-copied', '1');
            });
        });
    </script>
@endpush
