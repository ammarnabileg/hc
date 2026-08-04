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

{{-- Order-bump: أقصى عرضين في صفحة المراجعة — قاعدة مقفولة تُعرَض ولا تُعدَّل هنا (17) --}}
<p class="text-xs mt-3" style="color: var(--text-muted)">
    {!! strtr(setting('admin.store.partials.table_coupons.aqsa_add_arwd_order_bump_fy_sfha_almrajaa_v1', 'أقصى عدد عروض Order-bump في صفحة المراجعة: :v1 — قاعدة مقفولة.'), [':v1' => e((int) setting('order_bump.max_per_checkout', 2))]) !!}
</p>
