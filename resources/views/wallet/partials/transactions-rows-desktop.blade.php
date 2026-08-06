{{--
  صفوف جدول سطح المكتب وحدها (19.2 · 13.1) — تُستعمَل في الصفحة الكاملة أوّل
  تحميل وفي ردّ Fragment للتمرير التدريجيّ، فالماركب واحد لا نسختان.
  المتغيّرات المتوقَّعة من المستدعي: $rows, $flow, $panel (مُعرَّفتان في أعلى الملفّ المستدعي).
--}}
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
            {{ \App\Http\Controllers\Trainee\WalletController::notesOf($row) ?: '—' }}
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
