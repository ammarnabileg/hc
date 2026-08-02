@php
    /** المرجع قابل للضغط بأيقونة كيانه (24.4) — والاجتماع يفتح صفحته مباشرةً. */
    $isMeeting = $row->reference_type === (new \App\Models\Meeting)->getMorphClass();
    $label = match ($row->source) {
        'meeting' => 'اجتماع',
        'task' => 'مهمّة',
        'academy' => 'تسجيل',
        'leadership' => 'تقييم',
        'behavior' => 'سلوك',
        'arbitration' => 'تحكيم',
        default => 'مرجع',
    };
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
</span>
