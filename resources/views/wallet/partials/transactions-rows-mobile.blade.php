{{--
  كروت الموبايل وحدها (2.15-ج · 13.1) — نفس بيانات صفوف سطح المكتب بشكلٍ
  رأسيّ بلا تمرير أفقيّ. المتغيّرات المتوقَّعة: $rows, $flow (مُعرَّفة في أعلى الملفّ المستدعي).
--}}
@foreach ($rows as $row)
    <div class="card p-4 cursor-pointer" data-tx='@json($panel($row))'>
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
        @if ($notes = \App\Http\Controllers\Trainee\WalletController::notesOf($row))
            <div class="mt-1 text-xs" style="color: var(--text-muted)">{{ $notes }}</div>
        @endif
        <div class="mt-2 text-xs flex items-center justify-between gap-2" style="color: var(--text-muted)">
            <span>{{ $row->created_at?->format('Y-m-d H:i') }}</span>
            <span>{{ str_replace(':balance', number_format((float) $row->balance_after, (int) ($row->currency?->decimals ?? 0)), (string) setting('wallet.transactions.balance_after_inline', 'الرصيد بعدها: :balance')) }}</span>
        </div>
    </div>
@endforeach
