<div class="card overflow-hidden">
    <table class="hidden md:table w-full text-sm">
        <thead style="background: var(--surface-sunken)">
            <tr class="text-xs" style="color: var(--text-muted)">
                <th class="text-start p-3">#</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_orders.almstkhdm', 'المستخدم') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_orders.alijmaly', 'الإجماليّ') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_orders.alkhsm', 'الخصم') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_orders.alhala', 'الحالة') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_orders.altarykh', 'التاريخ') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $order)
                <tr style="border-top: 1px solid var(--border)" data-order-row
                    data-url="{{ route('admin.store.orders.show', $order) }}" class="cursor-pointer">
                    <td class="p-3 font-mono">{{ $order->number }}</td>
                    <td class="p-3">{{ $order->user?->name }} <span style="color: var(--text-muted)">#{{ $order->user?->code }}</span></td>
                    <td class="p-3">{{ rtrim(rtrim(number_format((float) $order->total, 2), '0'), '.') }}</td>
                    <td class="p-3">{{ rtrim(rtrim(number_format((float) $order->discount, 2), '0'), '.') }}</td>
                    <td class="p-3">
                        <x-state-badge :state="match ($order->status) { 'paid' => 'ok', 'pending' => 'warn', 'failed' => 'danger', default => 'idle' }"
                                       :label="match ($order->status) { 'paid' => setting('admin.store.partials.table_orders.mktml', 'مكتمل'), 'pending' => setting('admin.store.partials.table_orders.malq', 'معلّق'), 'failed' => setting('admin.store.partials.table_orders.fashl', 'فاشل'), default => setting('admin.store.partials.table_orders.mlgha', 'ملغى') }" />
                    </td>
                    <td class="p-3 text-xs" title="{{ $order->created_at }}">{{ $order->created_at?->diffForHumans() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="md:hidden">
        @foreach ($rows as $order)
            <div class="p-3 text-sm cursor-pointer" style="border-top: 1px solid var(--border)"
                 data-order-row data-url="{{ route('admin.store.orders.show', $order) }}">
                <div class="font-mono">{{ $order->number }}</div>
                <div class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ $order->user?->name }} · {{ rtrim(rtrim(number_format((float) $order->total, 2), '0'), '.') }}
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- التفاصيل في بانل جانبيّ لا صفحة جديدة (2.15-أ-6) — والبانل يحمل سكربته معه --}}
<div data-order-panel class="hidden fixed inset-0 z-40" role="dialog" aria-modal="true" aria-label="{{ setting('admin.store.partials.table_orders.tfasyl_altlb', 'تفاصيل الطلب') }}">
    <div class="absolute inset-0" style="background: rgba(0,0,0,.5)" data-order-close></div>
    <aside class="absolute inset-y-0 end-0 w-full max-w-md overflow-y-auto"
           style="background: var(--surface-raised); border-inline-start: 1px solid var(--border)">
        <button type="button" data-order-close class="m-3 rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken)">{{ setting('admin.store.partials.table_orders.ighlaq', 'إغلاق') }}</button>
        <div data-order-body class="text-sm">
            <p class="p-4" style="color: var(--text-muted)">{{ setting('admin.store.partials.table_orders.bnjyb_altfasyl', 'بنجيب التفاصيل…') }}</p>
        </div>
    </aside>
</div>

@php
    /*
     | نصوص السكربت من الإعدادات (2.13-أ): لا حرفَ عربيّ داخل `<script>`،
     | فالمحروق هناك لا يصل لوحةَ الإدارة ولا الترجمة.
     */
    $jsText = [
        'loading' => setting('admin.store.partials.table_orders.bnjyb_altfasyl', 'بنجيب التفاصيل…'),
        'open_failed' => setting('admin.store.partials.table_orders.tadhr_fth_altlb_jrb_tany_aw_hdth_alsfha', 'تعذّر فتح الطلب. جرّب تاني أو حدّث الصفحة.'),
        'network_failed' => setting('admin.store.partials.table_orders.tadhr_alatsal_raja_alshbka_wjrb_tany', 'تعذّر الاتصال. راجع الشبكة وجرّب تاني.'),
    ];
@endphp

<script>
    const HC_ORDERS_TEXT = @json($jsText);
    (() => {
        const panel = document.querySelector('[data-order-panel]');
        const body = panel?.querySelector('[data-order-body]');
        if (!panel || !body) return;

        const close = () => panel.classList.add('hidden');

        document.querySelectorAll('[data-order-row]').forEach((row) => {
            row.addEventListener('click', async () => {
                panel.classList.remove('hidden');
                body.innerHTML = '<p class="p-4">' + HC_ORDERS_TEXT.loading + '</p>';

                try {
                    const res = await fetch(row.dataset.url, { headers: { Accept: 'text/html' } });
                    body.innerHTML = res.ok
                        ? await res.text()
                        : '<p class="p-4">' + HC_ORDERS_TEXT.open_failed + '</p>';
                } catch (e) {
                    body.innerHTML = '<p class="p-4">' + HC_ORDERS_TEXT.network_failed + '</p>';
                }
            });
        });

        panel.querySelectorAll('[data-order-close]').forEach((el) => el.addEventListener('click', close));
        document.addEventListener('keydown', (e) => e.key === 'Escape' && close());
    })();
</script>

