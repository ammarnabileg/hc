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
                <tr style="border-top: 1px solid var(--border)">
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
            <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                <div class="font-mono">{{ $order->number }}</div>
                <div class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ $order->user?->name }} · {{ rtrim(rtrim(number_format((float) $order->total, 2), '0'), '.') }}
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- ⛔ لا مسار استرجاع نقديّ (19.4) — والبديل الوحيد تصحيح خطأ تقنيّ موثّق --}}
<p class="text-xs mt-3" style="color: var(--text-muted)">
    مافيش استرجاع نقديّ — الرصيد يفضل في محفظة صاحبه، والخطأ التقنيّ يتصحَّح بمعاملة موثّقة بمرجعها.
</p>
