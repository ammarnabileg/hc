{{--
  كروت المسحوبات على الموبايل وحدها (2.15-ج · 13.1). المتغيّرات المتوقَّعة:
  $rows, $num (مُعرَّفة في أعلى الملفّ المستدعي).
--}}
@foreach ($rows as $row)
    <div class="card p-4">
        <div class="flex items-center justify-between gap-3">
            <span class="text-sm font-semibold">{{ $row->number }}</span>
            <x-state-badge :state="$row->state()" :label="$row->statusLabel()" />
        </div>
        <div class="mt-2 text-sm flex items-center justify-between gap-2">
            <span>${{ $num($row->amount) }}</span>
            <span style="color: var(--text-muted)">{{ str_replace(':net', $num($row->net_amount), (string) setting('wallet.withdrawals.net_inline', 'يوصلك $:net')) }}</span>
        </div>
        <div class="mt-2 text-xs flex items-center justify-between gap-2" style="color: var(--text-muted)">
            <span>{{ $row->created_at?->format('Y-m-d H:i') }}</span>
            @if ($row->receipt_path)
                <a class="underline" target="_blank" rel="noopener"
                   href="{{ \Illuminate\Support\Facades\Storage::url($row->receipt_path) }}">{{ setting('wallet.withdrawals.col_receipt', 'صورة الفاتورة') }}</a>
            @endif
        </div>
    </div>
@endforeach
