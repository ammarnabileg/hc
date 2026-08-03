{{-- عمود **آخر تبديل** (مَن/متى) — والغياب يُقال صراحةً لا بشرطةٍ صامتة (2.17-ب) --}}
@if ($row['last_toggled_at'])
    {{ $people[$row['last_toggled_by']] ?? '—' }}
    · <span title="{{ $row['last_toggled_at'] }}">{{ \Illuminate\Support\Carbon::parse($row['last_toggled_at'])->diffForHumans() }}</span>
@else
    {{ setting('features.ui.never', 'لسه ما اتبدّلتش') }}
@endif
