@php
    /**
     * الإجابة **مخفيّة افتراضيًّا** — والكشف مؤقّت ومسجَّل في Audit (24.2).
     * وبلا صلاحيّة `wars_bank.view` تُخفى تمامًا بلا زرّ كشف أصلًا (2.15-أ-7).
     */
    $isRevealed = $canSeeAnswers && (int) $revealed === (int) $question->id;
@endphp

@if (! $canSeeAnswers)
    <span class="text-xs" style="color: var(--text-muted)">••••</span>
@elseif ($isRevealed)
    <span class="font-bold text-sm">{{ $question->answer ?: setting('admin.wars.bank.answer_cell.bla_ijaba', '— بلا إجابة') }}</span>
@else
    <form method="post" action="{{ route('admin.wars.bank.reveal', $question) }}" class="inline">
        @csrf
        <button type="submit" class="rounded-lg px-3 py-2 text-xs motion-standard"
                style="background: var(--surface-sunken); color: var(--text); min-height: 44px">
            {{ setting('admin.wars.bank.answer_cell.akshf', '•••• اكشف') }}
        </button>
    </form>
@endif
