@extends('layouts.app')

@section('title', setting('wallet.transactions.title', 'المعاملات والفواتير'))

@php
    use App\Http\Controllers\Trainee\WalletController;

    /*
     | أعمدة الجدول **بنصّ 19.2 حرفًا**: # / العملة / الكمية / من ← إلى / السبب /
     | ملاحظات / التاريخ — سبعة، وهو أقصى ما يسمح به 2.15-أ-5.
     | وبيانات بانل التفاصيل تُبنى مرّةً وتُستعمَل في الجدول وفي كروت الموبايل.
     */
    $flow = fn ($row) => WalletController::flowOf($row);

    $panel = fn ($row) => [
        'date' => $row->created_at?->format('Y-m-d H:i'),
        'type' => WalletController::sourceLabels()[$row->source] ?? $row->source,
        'currency' => $row->currency?->name_ar,
        'amount' => (float) $row->amount,
        'applied' => (float) ($row->applied_amount ?? $row->amount),
        'balance_after' => (float) $row->balance_after,
        'reason' => $row->reason,
        'flow' => $flow($row)['from'].' ← '.$flow($row)['to'],
        'notes' => WalletController::notesOf($row),
        'reference' => $row->reference_type ? class_basename($row->reference_type).'#'.$row->reference_id : null,
        'capped' => (bool) $row->exceeded_daily_cap,
        'correction' => (bool) $row->is_correction,
        'invoice' => 'INV-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT),
    ];

    /*
     | ملاحظتا بانل التفاصيل تُبنيان هنا لا في ذيل الملفّ: في القالب توجيه
     | PHP سطريّ بأقواس، وبليد يزاوج أوّل فاتحة كتلةٍ بأوّل خاتمة — فكتلةٌ
     | ثانية أسفل الملفّ تبتلع نصفه وتكسر التصريف.
     */
    $txNotes = [
        'capped' => (string) setting('wallet.transactions.note_capped', 'الحركة دي تعدّت الحدّ اليوميّ، فاتسجّلت كاملة واتطبّق منها الجزء المسموح.'),
        'correction' => (string) setting('wallet.transactions.note_correction', 'دي حركة تصحيح موثّقة تعكس حركةً سابقة.'),
    ];
@endphp

@section('content')
    <x-page-header
        :title="setting('wallet.transactions.title', 'المعاملات والفواتير')"
        :subtitle="setting('wallet.transactions.subtitle', 'كلّ حركة على محفظتك بمرجعها ورصيدك بعدها.')"
        :breadcrumbs="[['label' => setting('wallet.index.breadcrumb_root', 'المحفظة'), 'url' => route('wallet.index')], ['label' => setting('wallet.transactions.title', 'المعاملات والفواتير')]]">
        <x-slot:action>
            @can('wallet.export')
                <a href="{{ route('wallet.transactions.export', request()->query()) }}"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.transactions.export_action', 'تصدير كشف CSV') }}</a>
            @endcan
        </x-slot:action>
    </x-page-header>

    {{-- ثلاثة تابات (19.2): رصيدي / المعاملات / المسحوبات --}}
    @include('wallet.components.tabs', ['current' => 'transactions'])

    {{-- ثلاثة فلاتر ظاهرة + بحث، والمدى الافتراضيّ آخر 30 يومًا (2.15-أ-4 · 2.15-د) --}}
    <x-filters :action="route('wallet.transactions')">
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('wallet.transactions.filter_currency', 'العملة') }}</span>
            <select name="currency" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('wallet.transactions.filter_all', 'الكلّ') }}</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->code }}" @selected($filters['currency'] === $currency->code)>{{ $currency->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('wallet.transactions.filter_source', 'النوع') }}</span>
            <select name="source" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('wallet.transactions.filter_all', 'الكلّ') }}</option>
                @foreach ($sources as $key => $label)
                    <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('wallet.transactions.filter_search', 'بحث بالسبب') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('wallet.transactions.filter_search_placeholder', 'اكتب كلمة…') }}"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.transactions.filter_apply', 'طبّق') }}</button>

        <x-slot:advanced>
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('wallet.transactions.filter_from', 'من تاريخ') }}</span>
                <input type="date" name="from" value="{{ $filters['from']?->format('Y-m-d') }}"
                       class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('wallet.transactions.filter_to', 'إلى تاريخ') }}</span>
                <input type="date" name="to" value="{{ $filters['to']?->format('Y-m-d') }}"
                       class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="all_time" value="1" @checked($filters['all_time'])>
                <span>{{ setting('wallet.transactions.filter_all_time', 'وسّع المدى لكلّ الفترات') }}</span>
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty :message="setting('wallet.transactions.empty_message', 'مافيش حركات في المدى ده — وسّع المدى أو ابدأ بشحن رصيدك.')"
                 :action="setting('wallet.index.topup_action', 'اشحن رصيدك')" :href="route('wallet.topup')" />
    @else
        {{-- سطح المكتب: الأعمدة السبعة المنصوصة في 19.2 --}}
        <div class="card hidden md:block min-w-0 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start font-semibold px-4 py-3">#</th>
                        <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.transactions.col_currency', 'العملة') }}</th>
                        <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.transactions.col_amount', 'الكمية') }}</th>
                        <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.transactions.col_flow', 'من ← إلى') }}</th>
                        <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.transactions.col_reason', 'السبب') }}</th>
                        <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.transactions.col_notes', 'ملاحظات') }}</th>
                        <th class="text-start font-semibold px-4 py-3">{{ setting('wallet.transactions.col_date', 'التاريخ') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php($line = $flow($row))
                        <tr class="cursor-pointer motion-standard hover:opacity-90"
                            style="border-top: 1px solid var(--border)"
                            data-tx='@json($panel($row))'>
                            <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $row->id }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row->currency?->name_ar }}</td>
                            <td class="px-4 py-3">
                                @include('wallet.components.amount', [
                                    'value' => $row->applied_amount ?? $row->amount,
                                    'decimals' => (int) ($row->currency?->decimals ?? 0),
                                ])
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $line['from'] }} ← {{ $line['to'] }}</td>
                            <td class="px-4 py-3" style="color: var(--text-muted)">{{ $row->reason ?: '—' }}</td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-muted)">
                                {{ WalletController::notesOf($row) ?: '—' }}
                                @if ($row->exceeded_daily_cap)
                                    <x-state-badge state="warn" :label="setting('wallet.transactions.badge_capped', 'تجاوز الحدّ اليوميّ')" />
                                @endif
                                @if ($row->is_correction)
                                    <x-state-badge state="idle" :label="setting('wallet.transactions.badge_correction', 'تصحيح')" />
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap" title="{{ $row->created_at?->format('Y-m-d H:i') }}">
                                {{ $row->created_at?->diffForHumans() }}
                            </td>
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
                        <span class="text-sm font-semibold">{{ $row->currency?->name_ar }}</span>
                        @include('wallet.components.amount', [
                            'value' => $row->applied_amount ?? $row->amount,
                            'decimals' => (int) ($row->currency?->decimals ?? 0),
                        ])
                    </div>
                    <div class="mt-1 text-xs" style="color: var(--text-muted)">
                        {{ $flow($row)['from'] }} ← {{ $flow($row)['to'] }} · {{ $row->reason ?: '—' }}
                    </div>
                    @if ($notes = WalletController::notesOf($row))
                        <div class="mt-1 text-xs" style="color: var(--text-muted)">{{ $notes }}</div>
                    @endif
                    <div class="mt-2 text-xs flex items-center justify-between gap-2" style="color: var(--text-muted)">
                        <span>{{ $row->created_at?->format('Y-m-d H:i') }}</span>
                        <span>{{ str_replace(':balance', number_format((float) $row->balance_after, (int) ($row->currency?->decimals ?? 0)), (string) setting('wallet.transactions.balance_after_inline', 'الرصيد بعدها: :balance')) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $rows->links() }}</div>
    @endif
@endsection

@push('modals')
    {{-- التفاصيل والفاتورة في بانل/Bottom Sheet — فلا يفقد المستخدم مكانه (2.15-أ-6) --}}
    <x-modal id="tx-details" :title="setting('wallet.transactions.details_title', 'تفاصيل الحركة')">
        <dl class="space-y-3 text-sm" data-tx-body>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">{{ setting('wallet.transactions.field_invoice', 'رقم الفاتورة') }}</dt><dd data-tx-field="invoice"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">{{ setting('wallet.transactions.col_date', 'التاريخ') }}</dt><dd data-tx-field="date"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">{{ setting('wallet.transactions.filter_source', 'النوع') }}</dt><dd data-tx-field="type"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">{{ setting('wallet.transactions.col_currency', 'العملة') }}</dt><dd data-tx-field="currency"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">{{ setting('wallet.transactions.field_amount', 'القيمة المسجَّلة') }}</dt><dd data-tx-field="amount"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">{{ setting('wallet.transactions.field_applied', 'المطبَّق فعلًا') }}</dt><dd data-tx-field="applied"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">{{ setting('wallet.transactions.col_flow', 'من ← إلى') }}</dt><dd data-tx-field="flow"></dd></div>
            {{-- «المرجع» = مرجع الحركة فعلًا (الطلب/الحوالة)، لا السبب — والعنوانان كانا متبادلَين --}}
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">{{ setting('wallet.transactions.field_reference', 'المرجع') }}</dt><dd data-tx-field="reference"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">{{ setting('wallet.transactions.col_reason', 'السبب') }}</dt><dd data-tx-field="reason"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">{{ setting('wallet.transactions.col_notes', 'ملاحظات') }}</dt><dd data-tx-field="notes"></dd></div>
            <div class="flex justify-between gap-3"><dt style="color: var(--text-muted)">{{ setting('wallet.transactions.field_balance_after', 'الرصيد بعدها') }}</dt><dd data-tx-field="balance_after"></dd></div>
            <p class="pt-2 text-xs" style="color: var(--text-muted)" data-tx-note></p>
        </dl>
    </x-modal>
@endpush

@push('scripts')
    <script>
        const txNotes = @json($txNotes);
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

                ['invoice', 'date', 'type', 'currency', 'flow', 'reference', 'reason', 'notes'].forEach((k) => set(k, tx[k]));
                set('amount', tx.amount);
                set('applied', tx.applied);
                set('balance_after', tx.balance_after);

                const note = modal.querySelector('[data-tx-note]');
                if (note) {
                    note.textContent = tx.capped
                        ? txNotes.capped
                        : (tx.correction ? txNotes.correction : '');
                }

                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        })();
    </script>
@endpush
