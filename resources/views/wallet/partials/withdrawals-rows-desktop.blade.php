{{--
  صفوف جدول المسحوبات على سطح المكتب وحدها (19.2 · 13.1). المتغيّرات
  المتوقَّعة: $rows, $num (مُعرَّفة في أعلى الملفّ المستدعي).
--}}
@foreach ($rows as $row)
    <tr style="border-top: 1px solid var(--border)">
        <td class="px-4 py-3 whitespace-nowrap">{{ $row->number }}</td>
        <td class="px-4 py-3 whitespace-nowrap" title="{{ $row->created_at?->format('Y-m-d H:i') }}">
            {{ $row->created_at?->diffForHumans() }}
        </td>
        <td class="px-4 py-3 font-semibold">${{ $num($row->amount) }}</td>
        <td class="px-4 py-3" style="color: var(--text-muted)">${{ $num($row->fee_amount) }}</td>
        <td class="px-4 py-3">
            <x-state-badge :state="$row->state()" :label="$row->statusLabel()" />
        </td>
        <td class="px-4 py-3">
            @if ($row->receipt_path)
                <a class="underline" target="_blank" rel="noopener"
                   href="{{ \Illuminate\Support\Facades\Storage::url($row->receipt_path) }}">{{ setting('wallet.withdrawals.open_receipt', 'افتح الصورة') }}</a>
            @else
                <span style="color: var(--text-muted)">—</span>
            @endif
        </td>
    </tr>
@endforeach
