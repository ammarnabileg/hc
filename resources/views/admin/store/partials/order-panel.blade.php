{{--
    بانل الطلب: الفاتورة وسطورها في بانل جانبيّ لا صفحة جديدة (2.15-أ-6).
    ⛔ ولا استرجاع نقديّ (19.4) — البديل الوحيد تصحيح خطأ تقنيّ بمرجع المعاملة.
--}}

@php
    $money = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $items = \App\Models\OrderItem::query()->where('order_id', $order->id)->get();
@endphp

<div class="p-4 space-y-4">
    <header class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-bold text-base font-mono">{{ $order->number }}</h2>
            <p class="text-xs mt-1" style="color: var(--text-muted)">
                {{ $order->created_at?->translatedFormat('j F Y — H:i') }}
            </p>
        </div>

        <x-state-badge :state="match ($order->status) { 'paid' => 'ok', 'pending' => 'warn', 'failed' => 'danger', default => 'idle' }"
                       :label="match ($order->status) { 'paid' => 'مكتمل', 'pending' => 'معلّق', 'failed' => 'فاشل', default => 'ملغى' }" />
    </header>

    <section class="card p-3 text-sm">
        <div class="flex items-center gap-3">
            <x-avatar :user="$order->user" size="9" />
            <div class="min-w-0">
                <div class="truncate">{{ $order->user?->name ?? 'مستخدم محذوف' }}</div>
                <div class="text-xs font-mono" style="color: var(--text-muted)">#{{ $order->user?->code }}</div>
            </div>
        </div>
    </section>

    <section>
        <h3 class="font-semibold text-sm mb-2">سطور الفاتورة</h3>

        @if ($items->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">مفيش سطور على الطلب ده.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs" style="color: var(--text-muted)">
                            <th class="text-start p-2">البند</th>
                            <th class="text-start p-2">الكمّيّة</th>
                            <th class="text-start p-2">السعر</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="p-2">
                                    {{ $item->title }}
                                    @if ($item->is_order_bump)
                                        <span class="text-xs" style="color: var(--text-muted)">(عرض إضافيّ)</span>
                                    @endif
                                </td>
                                <td class="p-2">{{ (int) $item->quantity }}</td>
                                <td class="p-2">{{ $money($item->price) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card p-3 text-sm space-y-1">
        <div class="flex justify-between"><span style="color: var(--text-muted)">الإجماليّ قبل الخصم</span><span>{{ $money($order->subtotal) }}</span></div>
        <div class="flex justify-between">
            <span style="color: var(--text-muted)">الخصم @if ($order->coupon)<span class="font-mono">({{ $order->coupon->code }})</span>@endif</span>
            <span>{{ $money($order->discount) }}</span>
        </div>
        <div class="flex justify-between font-semibold" style="border-top: 1px solid var(--border); padding-top: .5rem">
            <span>المستحقّ</span>
            <span>{{ $money($order->total) }} {{ $order->currency?->code }}</span>
        </div>
    </section>

    <p class="text-xs" style="color: var(--text-muted)">
        مافيش استرجاع نقديّ — الرصيد يفضل في محفظة صاحبه، والخطأ التقنيّ يتصحَّح بمعاملة موثّقة بمرجعها.
    </p>

    @if (auth()->user()?->isPlatformOwner())
        {{-- 🔒 التصحيح الماليّ لمالك المنصّة وحده، وبمرجع المعاملة الأصليّة إلزاميًّا (19.4) --}}
        <form method="post" action="{{ route('admin.store.orders.correction', $order) }}" class="card p-3 space-y-2">
            @csrf
            <h3 class="font-semibold text-sm">تصحيح خطأ تقنيّ</h3>
            <input type="number" name="original_transaction_id" required placeholder="رقم المعاملة الأصليّة"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <textarea name="reason" required rows="2" placeholder="السبب — يتسجّل في سجلّ التدقيق"
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">تسجيل التصحيح</button>
        </form>
    @endif
</div>
