@php
    /** المرجع قابل للضغط بأيقونة كيانه (24.4) — والاجتماع يفتح صفحته مباشرةً. */
    $isMeeting = $row->reference_type === (new \App\Models\Meeting)->getMorphClass();
    $label = match ($row->source) {
        'meeting' => setting('volunteer.transactions_reference.meeting', 'اجتماع'),
        'task' => setting('volunteer.transactions_reference.task', 'مهمّة'),
        'academy' => setting('volunteer.transactions_reference.academy', 'تسجيل'),
        'leadership' => setting('volunteer.transactions_reference.leadership', 'تقييم'),
        'behavior' => setting('volunteer.transactions_reference.behavior', 'سلوك'),
        'arbitration' => setting('volunteer.transactions_reference.arbitration', 'تحكيم'),
        default => setting('volunteer.transactions_reference.text', 'مرجع'),
    };
@endphp

@php
    $entity = $row->entity_id ? \App\Models\Entity::find($row->entity_id) : null;
@endphp

<span class="inline-flex items-center gap-1 text-xs">
    @include('volunteer.meetings.partials.icon', ['name' => $isMeeting ? 'meeting' : 'transaction'])
    @if ($isMeeting && $row->reference_id)
        <a href="{{ route('volunteer.meetings.show', $row->reference_id) }}"
           class="hover:underline" style="color: var(--color-brand-400)">{{ $label }} #{{ $row->reference_id }}</a>
    @elseif ($row->reference_id)
        <span>{{ $label }} #{{ $row->reference_id }}</span>
    @else
        <span style="color: var(--text-muted)">—</span>
    @endif

    {{-- أيقونة الكيان بجوار المرجع (24.4) --}}
    @if ($entity)
        <span class="inline-flex items-center gap-1" style="color: var(--text-muted)" title="{{ $entity->name_ar }}">
            @include('volunteer.meetings.partials.icon', ['name' => 'entity'])
            {{ $entity->name_ar }}
        </span>
    @endif
</span>
