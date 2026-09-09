<div class="card overflow-hidden">
    <table class="hidden md:table w-full text-sm">
        <thead style="background: var(--surface-sunken)">
            <tr class="text-xs" style="color: var(--text-muted)">
                <th class="text-start p-3">{{ setting('admin.store.partials.table_coupons.alkwd', 'الكود') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_coupons.nwa_alkhsm', 'نوع الخصم') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_coupons.alqyma', 'القيمة') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_coupons.almstkhdm_alhd', 'المستخدَم / الحدّ') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_coupons.alhala', 'الحالة') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_coupons.ijraat', 'إجراءات') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $coupon)
                <tr style="border-top: 1px solid var(--border)">
                    <td class="p-3 font-mono">{{ $coupon->code }}</td>
                    <td class="p-3">{{ $coupon->type === 'percent' ? setting('admin.store.partials.table_coupons.nsba', 'نسبة %') : setting('admin.store.partials.table_coupons.thabt', 'ثابت') }}</td>
                    <td class="p-3">{{ rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.') }}</td>
                    <td class="p-3">{{ $coupon->used_count }} / {{ $coupon->max_uses ?? '∞' }}</td>
                    <td class="p-3"><x-state-badge :state="$coupon->is_active ? 'ok' : 'idle'" :label="$coupon->is_active ? setting('admin.store.partials.table_coupons.nsht', 'نشط') : setting('admin.store.partials.table_coupons.mwqwf', 'موقوف')" /></td>
                    <td class="p-3">
                        @can('coupons.edit')
                            <form method="post" action="{{ route('admin.store.coupons.toggle', $coupon) }}">
                                @csrf
                                <button class="text-xs underline">{{ $coupon->is_active ? setting('admin.store.partials.table_coupons.iyqaf', 'إيقاف') : setting('admin.store.partials.table_coupons.tshghyl', 'تشغيل') }}</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="md:hidden">
        @foreach ($rows as $coupon)
            <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                <div class="font-mono">{{ $coupon->code }}</div>
                <div class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ $coupon->type === 'percent' ? setting('admin.store.partials.table_coupons.nsba_2', 'نسبة') : setting('admin.store.partials.table_coupons.thabt', 'ثابت') }} · {{ $coupon->used_count }}/{{ $coupon->max_uses ?? '∞' }}
                </div>
            </div>
        @endforeach
    </div>
</div>

@php $typeLabels = (array) setting('store.bundle.item_type_labels', []); @endphp

{{-- عروض Order-bump: صفٌّ لكلّ عرض — عنصر أصل + عنصر Bump بسعرٍ خاصّ وجملة تشويق (17) --}}
<div class="card p-4 mt-4">
    <h2 class="font-bold text-sm mb-1">{{ setting('admin.store.partials.table_coupons.order_bump_title', 'عروض Order-bump') }}</h2>
    <p class="text-xs mb-3" style="color: var(--text-muted)">
        {!! strtr(setting('admin.store.partials.table_coupons.aqsa_add_arwd_order_bump_fy_sfha_almrajaa_v1', 'أقصى عدد عروض Order-bump في صفحة المراجعة: :v1 — قاعدة مقفولة.'), [':v1' => e((int) setting('order_bump.max_per_checkout', 2))]) !!}
    </p>

    @if (($orderBumps ?? collect())->isNotEmpty())
        <div class="space-y-2 mb-4">
            @foreach ($orderBumps as $offer)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl p-3 text-sm" style="background: var(--surface-sunken)">
                    <div>
                        <div>
                            <span style="color: var(--text-muted)">{{ $typeLabels[$offer->parent_type] ?? $offer->parent_type }}:</span>
                            <strong>{{ $offer->parent_slug }}</strong>
                            <span class="mx-1">⟵</span>
                            <span style="color: var(--text-muted)">{{ $typeLabels[$offer->bump_type] ?? $offer->bump_type }}:</span>
                            <strong>{{ $offer->bump_slug }}</strong>
                            @if ($offer->price_coins !== null)
                                <span style="color: var(--color-brand-500)">— {{ rtrim(rtrim(number_format((float) $offer->price_coins, 2), '0'), '.') }}</span>
                            @endif
                        </div>
                        @if ($offer->teaser)
                            <div class="text-xs mt-1" style="color: var(--text-muted)">{{ $offer->teaser }}</div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <x-state-badge :state="$offer->is_active ? 'ok' : 'idle'" :label="$offer->is_active ? setting('admin.store.partials.table_coupons.nsht', 'نشط') : setting('admin.store.partials.table_coupons.mwqwf', 'موقوف')" />
                        @can('order_bump.edit')
                            <form method="post" action="{{ route('admin.store.order-bumps.toggle', $offer) }}">
                                @csrf
                                <button class="text-xs underline">{{ $offer->is_active ? setting('admin.store.partials.table_coupons.iyqaf', 'إيقاف') : setting('admin.store.partials.table_coupons.tshghyl', 'تشغيل') }}</button>
                            </form>
                        @endcan
                        @can('order_bump.delete')
                            <form method="post" action="{{ route('admin.store.order-bumps.destroy', $offer) }}">
                                @csrf
                                @method('delete')
                                <button class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('admin.store.partials.table_coupons.shyl', 'شيل') }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @can('order_bump.create')
        <form method="post" action="{{ route('admin.store.order-bumps.store') }}" class="grid gap-3 md:grid-cols-2" data-order-bump-form>
            @csrf

            <label class="block text-sm">
                <span class="block mb-1">{{ setting('admin.store.partials.table_coupons.alasl_alash_ally_yzhr_maah_alard', 'الأصل (الصفحة اللي يظهر معاها العرض)') }}</span>
                <select name="parent_slug" data-order-bump-select="parent" required
                        class="w-full rounded-xl px-3 py-2 text-sm"
                        style="min-height: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">…</option>
                    @foreach ($orderBumpOptions ?? [] as $type => $optionRows)
                        <optgroup label="{{ $typeLabels[$type] ?? $type }}">
                            @foreach ($optionRows as $row)
                                <option value="{{ $row['slug'] }}" data-type="{{ $type }}">{{ $row['title'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>
            <input type="hidden" name="parent_type" data-order-bump-type="parent" value="">

            <label class="block text-sm">
                <span class="block mb-1">{{ setting('admin.store.partials.table_coupons.aansr_albump_ally_ynadi_bh', 'عنصر الـBump اللي يُقترَح') }}</span>
                <select name="bump_slug" data-order-bump-select="bump" required
                        class="w-full rounded-xl px-3 py-2 text-sm"
                        style="min-height: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">…</option>
                    @foreach ($orderBumpOptions ?? [] as $type => $optionRows)
                        <optgroup label="{{ $typeLabels[$type] ?? $type }}">
                            @foreach ($optionRows as $row)
                                <option value="{{ $row['slug'] }}" data-type="{{ $type }}" data-price="{{ $row['price'] }}">{{ $row['title'] }} — {{ rtrim(rtrim(number_format((float) $row['price'], 2), '0'), '.') }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>
            <input type="hidden" name="bump_type" data-order-bump-type="bump" value="">

            <x-form.input name="price_coins" data-order-bump-price step="0.01" min="0"
                          :label="setting('admin.store.partials.table_coupons.sar_khas_akhtyary', 'سعر خاصّ (اختياريّ — الأصليّ لو فاضي)')" type="number" />

            <x-form.input name="teaser" :label="setting('admin.store.partials.table_coupons.jmla_tshwyq', 'جملة تشويق')" maxlength="255" />

            <div class="md:col-span-2">
                <button class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--color-brand-500); color: #04201c; min-height: 44px">
                    {{ setting('admin.store.partials.table_coupons.dyf_ard', 'ضيف عرض') }}
                </button>
            </div>
        </form>
    @endcan
</div>

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-order-bump-form]').forEach(function (form) {
        ['parent', 'bump'].forEach(function (role) {
            var select = form.querySelector('[data-order-bump-select="' + role + '"]');
            var typeInput = form.querySelector('[data-order-bump-type="' + role + '"]');
            if (!select || !typeInput) { return; }
            select.addEventListener('change', function () {
                var option = select.options[select.selectedIndex];
                typeInput.value = option ? (option.dataset.type || '') : '';
            });
        });
    });
})();
</script>
@endpush
