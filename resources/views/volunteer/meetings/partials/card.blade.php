@php
    /** كارت الاجتماع (24.4): العنوان · الموعد وعدّاد · صاحبه · الجمهور · الرابط · شارة حالتي */
    $status = $mine[$meeting->id]->status ?? null;
    $myValue = $mine[$meeting->id]->rep_value ?? null;

    $badge = match ($status) {
        'registered' => ['ok', setting('volunteer.meetings_card.text', 'سجّلت حضوري')],
        'excused', 'excused_settled' => ['idle', setting('volunteer.meetings_card.text_2', 'اعتذرت مسبقًا')],
        'absent' => ['danger', setting('volunteer.meetings_card.text_3', 'غياب بلا اعتذار')],
        default => ['warn', setting('volunteer.meetings_card.text_4', 'لسّه ما سجّلتش')],
    };

    $audienceLabel = match ($meeting->audience) {
        'all' => setting('volunteer.common.all', 'الكلّ'),
        'sub_entity' => setting('volunteer.meetings_card.sub_entity', 'قسم فرعيّ'),
        default => setting('volunteer.meetings_card.text_5', 'قسم'),
    };

    $windowOpen = $attendance->windowOpen($meeting);
    $canManage = $scope->canManage(auth()->user(), $meeting);
@endphp

<article class="card p-4 animate-fadeup" style="animation-delay: {{ ($loop->index % 8) * 40 }}ms">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('volunteer.meetings.show', $meeting) }}" class="font-bold hover:underline flex items-center gap-2">
                @include('volunteer.meetings.partials.icon', ['name' => 'meeting'])
                <span class="truncate">{{ $meeting->title }}</span>
            </a>
            <p class="text-xs mt-1 flex flex-wrap items-center gap-x-3 gap-y-1" style="color: var(--text-muted)">
                <span title="{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}">
                    @include('volunteer.meetings.partials.icon', ['name' => 'clock'])
                    {{ $meeting->scheduled_at?->format('Y-m-d · H:i') }}
                </span>
                <span>{{ setting('volunteer.meetings_card.text_6', 'صاحبه:') }} {{ $meeting->owner?->name }}</span>
                <span>
                    @include('volunteer.meetings.partials.icon', ['name' => 'entity'])
                    {{ $meeting->entity?->name_ar ?? setting('volunteer.common.all_entities', 'كلّ الكيانات') }} ({{ $audienceLabel }})
                </span>
            </p>
        </div>
        <x-state-badge :state="$badge[0]" :label="$badge[1]" />
    </div>

    {{-- عدّاد الموعد للاجتماع القادم --}}
    @if ($meeting->status !== 'ended' && $meeting->scheduled_at)
        <p class="mt-3 text-xs" style="color: var(--text-muted)">
            <span data-countdown="{{ $meeting->scheduled_at->toIso8601String() }}"
                  data-prefix="{{ setting('volunteer.meetings_card.prefix', 'باقي على الموعد') }}">{{ $meeting->scheduled_at->diffForHumans() }}</span>
        </p>
    @endif

    {{-- بعد «إنهاء الاجتماع»: عدّاد نافذة التسجيل الملوّن + قيم Rep من جدولها --}}
    @if ($meeting->status === 'ended' && $meeting->attendance_closes_at)
        @php $state = $windowOpen ? $attendance->tierState($meeting) : 'idle'; @endphp
        <div class="mt-3 rounded-xl px-3 py-2 text-xs flex flex-wrap items-center gap-2"
             style="background: color-mix(in srgb, var(--color-state-{{ $state }}) 12%, transparent)">
            <x-state-badge :state="$state" :label="$windowOpen ? setting('volunteer.meetings_card.label', 'نافذة التسجيل مفتوحة') : setting('volunteer.meetings_card.label_2', 'النافذة اتقفلت')" />
            @if ($windowOpen)
                <span data-countdown="{{ $meeting->attendance_closes_at->toIso8601String() }}" data-prefix="{{ setting('volunteer.meetings_card.prefix_2', 'باقي') }}">
                    {{ $meeting->attendance_closes_at->diffForHumans() }}
                </span>
            @endif
            {{-- قواعد النظام في «؟» لا في كارت شرح ثابت (2.15-ب) — والقيم من جدول Rep --}}
            <button type="button" class="rounded-full w-6 h-6 text-xs" style="border: 1px solid var(--border)"
                    title="{{ setting('volunteer.meetings_card.tooltip', 'خلال') }} {{ $attendance->tier1Hours() }} {{ setting('volunteer.meetings_card.tooltip_2', 'ساعات') }} {{ $attendance->valueLabel(rep_rule('meeting.within_3h')) }} · {{ setting('volunteer.meetings_card.tooltip_3', 'حتى') }} {{ $attendance->tier2Hours() }} {{ setting('volunteer.common.hour', 'ساعة') }} {{ $attendance->valueLabel(rep_rule('meeting.within_12h')) }} · {{ setting('volunteer.meetings_card.tooltip_4', 'غياب باعتذار') }} {{ $attendance->valueLabel(rep_rule('meeting.excused_absence')) }} · {{ setting('volunteer.meetings_card.tooltip_5', 'بلا اعتذار') }} {{ $attendance->valueLabel(rep_rule('meeting.unexcused_absence')) }}">؟</button>
            @if ($myValue !== null && $status !== null && $status !== 'pending')
                <span>{{ setting('volunteer.meetings_card.text_7', 'قيمتي:') }} <strong>{{ $attendance->valueLabel((float) $myValue) }}</strong></span>
            @endif
        </div>
    @endif

    <div class="mt-3 flex flex-wrap items-center gap-2">
        @if ($meeting->external_link)
            <a href="{{ $meeting->external_link }}" target="_blank" rel="noopener"
               class="btn inline-flex items-center gap-1 rounded-xl px-3 py-1.5 text-xs"
               style="border: 1px solid var(--border)">
                @include('volunteer.meetings.partials.icon', ['name' => 'link']) {{ setting('volunteer.meetings_card.link', 'رابط الاجتماع') }}
            </a>
        @endif

        @if ($windowOpen && $status !== 'registered')
            <button type="button" data-modal-open="register-{{ $meeting->id }}"
                    class="btn inline-flex items-center rounded-xl px-3 py-1.5 text-xs font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.meetings_card.action', 'سجّل حضورك') }}</button>
        @endif

        @if ($meeting->status !== 'ended' && ! in_array($status, ['excused', 'excused_settled'], true))
            <button type="button" data-modal-open="excuse-{{ $meeting->id }}"
                    class="btn inline-flex items-center rounded-xl px-3 py-1.5 text-xs"
                    style="border: 1px solid var(--border)">{{ setting('volunteer.meetings_card.action_2', 'اعتذار مسبق') }}</button>
        @endif

        {{-- بلا صلاحيّة = مخفيّ فعلًا لا معطَّل (2.15-أ-7) --}}
        @if ($canManage && $meeting->status !== 'ended')
            <button type="button" data-modal-open="end-{{ $meeting->id }}"
                    class="btn inline-flex items-center rounded-xl px-3 py-1.5 text-xs"
                    style="border: 1px solid var(--border)">{{ setting('volunteer.meetings_card.action_3', 'إنهاء الاجتماع') }}</button>
        @endif

        <a href="{{ route('volunteer.meetings.show', $meeting) }}"
           class="btn inline-flex items-center rounded-xl px-3 py-1.5 text-xs ms-auto"
           style="border: 1px solid var(--border)">{{ setting('volunteer.meetings_card.link_2', 'فتح') }}</a>
    </div>
</article>
