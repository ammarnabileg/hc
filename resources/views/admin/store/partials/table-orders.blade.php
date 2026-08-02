<div class="card overflow-hidden">
    <table class="hidden md:table w-full text-sm">
        <thead style="background: var(--surface-sunken)">
            <tr class="text-xs" style="color: var(--text-muted)">
                <th class="text-start p-3">#</th>
                <th class="text-start p-3">المستخدم</th>
                <th class="text-start p-3">الإجماليّ</th>
                <th class="text-start p-3">الخصم</th>
                <th class="text-start p-3">الحالة</th>
                <th class="text-start p-3">التاريخ</th>
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
                                       :label="match ($order->status) { 'paid' => 'مكتمل', 'pending' => 'معلّق', 'failed' => 'فاشل', default => 'ملغى' }" />
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
<div data-order-panel class="hidden fixed inset-0 z-40" role="dialog" aria-modal="true" aria-label="تفاصيل الطلب">
    <div class="absolute inset-0" style="background: rgba(0,0,0,.5)" data-order-close></div>
    <aside class="absolute inset-y-0 end-0 w-full max-w-md overflow-y-auto"
           style="background: var(--surface-raised); border-inline-start: 1px solid var(--border)">
        <button type="button" data-order-close class="m-3 rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken)">إغلاق</button>
        <div data-order-body class="text-sm">
            <p class="p-4" style="color: var(--text-muted)">بنجيب التفاصيل…</p>
        </div>
    </aside>
</div>

<script>
    (() => {
        const panel = document.querySelector('[data-order-panel]');
        const body = panel?.querySelector('[data-order-body]');
        if (!panel || !body) return;

        const close = () => panel.classList.add('hidden');

        document.querySelectorAll('[data-order-row]').forEach((row) => {
            row.addEventListener('click', async () => {
                panel.classList.remove('hidden');
                body.innerHTML = '<p class="p-4">بنجيب التفاصيل…</p>';

                try {
                    const res = await fetch(row.dataset.url, { headers: { Accept: 'text/html' } });
                    body.innerHTML = res.ok
                        ? await res.text()
                        : '<p class="p-4">تعذّر فتح الطلب. جرّب تاني أو حدّث الصفحة.</p>';
                } catch (e) {
                    body.innerHTML = '<p class="p-4">تعذّر الاتصال. راجع الشبكة وجرّب تاني.</p>';
                }
            });
        });

        panel.querySelectorAll('[data-order-close]').forEach((el) => el.addEventListener('click', close));
        document.addEventListener('keydown', (e) => e.key === 'Escape' && close());
    })();
</script>

