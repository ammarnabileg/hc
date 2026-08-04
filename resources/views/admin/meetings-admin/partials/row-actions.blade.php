@php
    /**
     * إجراءات صفّ الاجتماع في المرآة الإداريّة (24.2-أوّلًا):
     * فتح · إنهاء (يفتح نافذة الحضور) · منح حضور استثنائيّ.
     * وما لا يملكه المستخدم لا يظهر (2.15-أ-7).
     */
    $u = auth()->user();
    $canEnd = $u?->can('meetings.manage') || $u?->can('meetings.edit');
    $canGrant = $u?->can('meeting_attendance.manage') || $u?->can('meeting_attendance.edit');
@endphp

<div class="flex flex-wrap items-center gap-3 text-xs">
    <a class="underline" href="{{ route('admin.meetings.show', $meeting) }}">{{ setting('admin.meetings_admin.partials.row_actions.fth', 'فتح') }}</a>

    @if ($canEnd && $meeting->status !== 'ended')
        <button type="button" class="underline" data-meeting-end
                data-action="{{ route('admin.meetings.end', $meeting) }}">{{ setting('admin.meetings_admin.partials.row_actions.inha_alajtmaa', 'إنهاء الاجتماع') }}</button>
    @endif

    @if ($canGrant && $meeting->status === 'ended')
        <button type="button" class="underline" data-meeting-grant
                data-action="{{ route('admin.meetings.grant', $meeting) }}">{{ setting('admin.meetings_admin.partials.row_actions.hdwr_astthnayy', 'حضور استثنائيّ') }}</button>
    @endif
</div>
