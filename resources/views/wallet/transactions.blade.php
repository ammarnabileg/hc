@extends('layouts.app')

@section('title', 'المعاملات والفواتير')

@php
    // بيانات بانل التفاصيل لكلّ صفّ — تُبنى مرّةً وتُستعمَل في الجدول وفي كروت الموبايل
    $panel = fn ($row) => [
        'date' => $row->created_at?->format('Y-m-d H:i'),
        'type' => \App\Http\Controllers\Trainee\WalletController::SOURCE_LABELS[$row->source] ?? $row->source,
        'currency' => $row->currency?->name_ar,
        'amount' => (float) $row->amount,
        'applied' => (float) ($row->applied_amount ?? $row->amount),
        'balance_after' => (float) $row->balance_after,
        'reason' => $row->reason,
        'reference' => $row->reference_type ? class_basename($row->reference_type).'#'.$row->reference_id : null,
        'capped' => (bool) $row->exceeded_daily_cap,
        'correction' => (bool) $row->is_correction,
        'invoice' => 'INV-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT),
    ];
@endphp

@section('content')
    <x-page-header
        title="المعاملات والفواتير"
        subtitle="كلّ حركة على محفظتك بمرجعها ورصيدك بعدها."
        :breadcrumbs="[['label' => 'المحفظة', 'url' => route('wallet.index')], ['label' => 'المعاملات والفواتير']]">
        <x-slot:action>
            @can('wallet.export')
                <a href="{{ route('wallet.transactions.export', request()->query()) }}"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">تصدير كشف CSV</a>
            @endcan
        </x-slot:action>
    </x-page-header>

    {{-- ثلاثة فلاتر ظاهرة + بحث، والمدى الافتراضيّ آخر 30 يومًا (2.15-أ-4 · 2.15-د) --}}
    <x-filters :action="route('wallet.transactions')">
        <label class="block">
            <span class="block text-sm mb-1">العملة</span>
            <select name="currency" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->code }}" @selected($filters['currency'] === $currency->code)>{{ $currency->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">النوع</span>
            <select name="source" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($sources as $key => $label)
                    <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">بحث بالسبب</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="اكتب كلمة…"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">طبّق</button>

        <x-slot:advanced>
            <label class="block">
                <span class="block text-sm mb-1">من تاريخ</span>
                <input type="date" name="from" value="{{ $filters['from']?->format('Y-m-d') }}"
                       class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="block">
                <span class="block text-sm mb-1">إلى تاريخ</span>
                <input type="date" name="to" value="{{ $filters['to']?->format('Y-m-d') }}"
                       class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="all_time" value="1" @checked($filters['all_time'])>
                <span>وسّع المدى لكلّ الفترات</span>
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty message="مافيش حركات في المدى ده — وسّع المدى أو ابدأ بشحن رصيدك."
                 action="اشحن رصيدك" :href="route('wallet.topup')" />
    @else
        {{-- سطح المكتب: جدول بخمسة أعمدة (2.15-أ-5) --}}
        <div class="card hidden md:block overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start font-semibold px-4 py-3">التاريخ</th>
                        <th class="text-start font-semibold px-4 py-3">النوع</th>
                        <th class="text-start font-semibold px-4 py-3">القيمة</th>
                        <th class="text-start font-semibold px-4 py-3">المرجع</th>
                        <th class="text-start font-semibold px-4 py-3">الرصيد بعدها</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="cursor-pointer motion-standard hover:opacity-90"
                            style="border-top: 1px solid var(--border)"
                            data-tx='@json($panel($row))'>
                            <td class="px-4 py-3 whitespace-nowrap" title="{{ $row->created_at?->format('Y-m-d H:i') }}">
                                {{ $row->created_at?->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3">
                                {{ \App\Http\Controllers\Trainee\WalletController::SOURCE_LABELS[$row->source] ?? $row->source }}
                                @if ($row->exceeded_daily_cap)
                                    <x-state-badge state="warn" label="تجاوز الحدّ اليوميّ" />
                                @endif
                                @if ($row->is_correction)
                                    <x-state-badge state="idle" label="تصحيح" />
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @include('wallet.components.amount', [
                                    'value' => $row->applied_amount ?? $row->amount,
                                    'decimals' => (int) ($row->currency?->decimals ?? 0),
                                ])
                            </td>
                            <td class="px-4 py-3" style="color: var(--text-muted)">{{ $row->reason ?: '—' }}</td>
                            <td class="px-4 py-3 font-semibold">{{ number_format((float) $row->balance_after, (int) ($row->currency?->decimals ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="md:hidden space-y-3">
            @foreach ($rows as $row)
                <div class="card p-4 cursor-pointer"
                     data-tx='@json($panel($row))'>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold">{{ \App\Http\Controllers\Trainee\WalletController::SOURCE_LABELS[$row->source] ?? $row->source }}</span>
                        @include('wallet.components.amount', [
                            'value' => $row->applied_amount ?? $row->amount,
                            'decimals' => (int) ($row->currency?->decimals ?? 0),
                        ])
                    </div>
                    <div class="mt-2 text-xs flex items-center justify-between gap-2" style="color: var(--text-muted)">
                        <span>{{ $row->created_at?->format('Y-m-d H:i') }}</span>
                        <span>الرصيد بعدها: {{ number_format((float) $row->balance_after, (int) ($row->currency?->decimals ?? 0)) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $rows->links() }}</div>
    @endif
@endsection

@push('modals')
    {{-- التفاصيل والفاتورة في بانل/Bottom Sheet — فلا يفقد المستخدم مكانه (2.15-أ-6) --}}
    <x-modal id="tx-details" title="تفاصيل الحركة">
        <dl class="space-y-3 text-sm" data-tx-body>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">رقم الفاتورة</dt><dd data-tx-field="invoice"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">التاريخ</dt><dd data-tx-field="date"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">النوع</dt><dd data-tx-field="type"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">العملة</dt><dd data-tx-field="currency"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">القيمة المسجَّلة</dt><dd data-tx-field="amount"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">المطبَّق فعلًا</dt><dd data-tx-field="applied"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">المرجع</dt><dd data-tx-field="reference"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">السبب</dt><dd data-tx-field="reason"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">الرصيد بعدها</dt><dd data-tx-field="balance_after"></dd></div>
            <p class="pt-2 text-xs" style="color: var(--text-muted)" data-tx-note></p>
        </dl>
    </x-modal>
@endpush

@push('scripts')
    <script>
        // بانل التفاصيل: قائمة + بانل بنمطٍ واحد، وعلى الموبايل يظهر كـBottom Sheet
        (function () {
            const modal = document.getElementById('tx-details');
            if (!modal) return;

            const set = (key, text) => {
                const el = modal.querySelector(`[data-tx-field="${key}"]`);
                if (el) el.textContent = text ?? '—';
            };

            document.addEventListener('click', (e) => {
                const holder = e.target.closest('[data-tx]');
                if (!holder) return;

                let tx;
                try { tx = JSON.parse(holder.dataset.tx); } catch { return; }

                ['invoice', 'date', 'type', 'currency', 'reference', 'reason'].forEach((k) => set(k, tx[k]));
                set('amount', tx.amount);
                set('applied', tx.applied);
                set('balance_after', tx.balance_after);

                const note = modal.querySelector('[data-tx-note]');
                if (note) {
                    note.textContent = tx.capped
                        ? 'الحركة دي تعدّت الحدّ اليوميّ، فاتسجّلت كاملة واتطبّق منها الجزء المسموح.'
                        : (tx.correction ? 'دي حركة تصحيح موثّقة تعكس حركةً سابقة.' : '');
                }

                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        })();
    </script>
@endpush
