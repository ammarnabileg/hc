@php
    /**
     * إجراءات صفّ الاجتماع (24.2-أوّلًا): فتح · إنهاء (يفتح نافذة الحضور) ·
     * إدارة الكود/الأسئلة · رفع المحضر والمرفقات · تثبيت بوست · منح حضور
     * استثنائيّ · إلغاء بسبب.
     *
     * وما لا يملكه المستخدم لا يظهر (2.15-أ-7)، وما لا معنى له في حالة
     * الاجتماع لا يظهر كذلك: لا إنهاءَ لملغيّ، ولا إلغاءَ لمنتهٍ، ولا تثبيتَ
     * لاجتماعٍ بلا بوستات.
     */
    $u = auth()->user();
    $canEnd = $u?->can('meetings.manage') || $u?->can('meetings.edit');
    $canGrant = $u?->can('meeting_attendance.manage') || $u?->can('meeting_attendance.edit');
    $posts = $posts ?? collect();
    $postOptions = collect($posts)->map(fn ($post) => [
        'id' => $post->id,
        // 📌 علامةٌ لا نصّ — المثبَّت حاليًّا يُعرَف من القائمة قبل فتحها
        'label' => ($post->is_pinned ? '📌 ' : '').str($post->body)->limit(60)->value(),
    ])->values();
@endphp

<div class="flex flex-wrap items-center gap-3 text-xs">
    <a class="underline" href="{{ route('admin.meetings.show', $meeting) }}">{{ setting('admin.meetings_admin.partials.row_actions.fth', 'فتح') }}</a>

    @if ($canEnd && ! in_array($meeting->status, ['ended', 'cancelled'], true))
        <button type="button" class="underline" data-meeting-end
                data-action="{{ route('admin.meetings.end', $meeting) }}">{{ setting('admin.meetings_admin.partials.row_actions.inha_alajtmaa', 'إنهاء الاجتماع') }}</button>
    @endif

    @if ($canEnd && $meeting->status !== 'cancelled')
        <button type="button" class="underline" data-meeting-questions
                data-action="{{ route('admin.meetings.questions', $meeting) }}">{{ setting('admin.meetings_admin.partials.row_actions.idara_alkwd_alasyla', 'إدارة الكود/الأسئلة') }}</button>

        <button type="button" class="underline" data-meeting-minutes
                data-minutes="{{ $meeting->minutes }}" data-recording="{{ $meeting->recording_url }}"
                data-action="{{ route('admin.meetings.minutes', $meeting) }}">{{ setting('admin.meetings_admin.partials.row_actions.rfa_almhdr', 'رفع المحضر') }}</button>
    @endif

    @if ($canEnd && $postOptions->isNotEmpty())
        <button type="button" class="underline" data-meeting-pin
                data-posts="{{ $postOptions->toJson() }}"
                data-action="{{ route('admin.meetings.pin', $meeting) }}">{{ setting('admin.meetings_admin.partials.row_actions.tthbyt_bwst', 'تثبيت بوست') }}</button>
    @endif

    @if ($canGrant && $meeting->status === 'ended')
        <button type="button" class="underline" data-meeting-grant
                data-action="{{ route('admin.meetings.grant', $meeting) }}">{{ setting('admin.meetings_admin.partials.row_actions.hdwr_astthnayy', 'حضور استثنائيّ') }}</button>
    @endif

    @if ($canEnd && ! in_array($meeting->status, ['ended', 'cancelled'], true))
        <button type="button" class="underline" style="color: var(--color-state-danger)" data-meeting-cancel
                data-action="{{ route('admin.meetings.cancel', $meeting) }}">{{ setting('admin.meetings_admin.partials.row_actions.ilgha_bsbb', 'إلغاء بسبب') }}</button>
    @endif
</div>
