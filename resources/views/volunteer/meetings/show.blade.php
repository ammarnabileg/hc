@extends('layouts.volunteer')

@section('title', $meeting->title)

@php
    /** صفحة الاجتماع (24.4): تابات التفاصيل · الحضور · المحضر · النقاش — بتحميل كسول. */
    $mine = collect([$meeting->id => $myAttendance])->filter();
    $windowOpen = $attendance->windowOpen($meeting);
    $statusState = match ($meeting->status) {
        'ended' => 'idle',
        'running' => 'ok',
        default => 'warn',
    };
    $statusLabel = match ($meeting->status) {
        'ended' => setting('volunteer.meetings_show.ended', 'منتهٍ'),
        'running' => setting('volunteer.meetings_show.running', 'جارٍ'),
        default => setting('volunteer.meetings_show.text', 'قادم'),
    };
    $tabUrl = fn (string $key) => route('volunteer.meetings.show', ['meeting' => $meeting, 'tab' => $key]);
@endphp

@section('content')
    <x-page-header
        :title="$meeting->title"
        :subtitle="$meeting->scheduled_at?->format('Y-m-d · H:i').setting('volunteer.meetings_show.subtitle', ' — صاحبه ').$meeting->owner?->name"
        :breadcrumbs="[
            ['label' => setting('volunteer.meetings_show.label', 'الاجتماعات'), 'url' => route('volunteer.meetings')],
            ['label' => $meeting->title],
        ]">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد بارز، والباقي في «⋯» (2.15-أ-2) --}}
            @if ($windowOpen && ($myAttendance->status ?? null) !== 'registered')
                <button type="button" data-modal-open="register-{{ $meeting->id }}"
                        class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.meetings_show.action', 'تسجيل حضور') }}</button>
            @endif

            <details class="relative">
                <summary class="btn list-none cursor-pointer rounded-xl px-3 py-2 text-sm"
                         style="border: 1px solid var(--border)">⋯</summary>
                <div class="card absolute end-0 mt-2 w-56 p-2 z-40 space-y-1">
                    @if ($meeting->status !== 'ended' && ! in_array($myAttendance->status ?? null, ['excused', 'excused_settled'], true))
                        <button type="button" data-modal-open="excuse-{{ $meeting->id }}"
                                class="w-full text-start rounded-xl px-3 py-2 text-sm">{{ setting('volunteer.meetings_show.action_2', 'اعتذار مسبق') }}</button>
                    @endif
                    @if ($canManage && $meeting->status !== 'ended')
                        <button type="button" data-modal-open="end-{{ $meeting->id }}"
                                class="w-full text-start rounded-xl px-3 py-2 text-sm">{{ setting('volunteer.meetings_show.action_3', 'إنهاء الاجتماع') }}</button>
                    @endif
                    @if ($canManage)
                        <button type="button" data-modal-open="questions-{{ $meeting->id }}"
                                class="w-full text-start rounded-xl px-3 py-2 text-sm">{{ setting('volunteer.meetings_show.action_4', 'إضافة أسئلة / OTP') }}</button>
                    @endif
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <x-state-badge :state="$statusState" :label="$statusLabel" />
        @if ($windowOpen)
            <x-state-badge :state="$attendance->tierState($meeting)" :label="setting('volunteer.meetings_show.label_2', 'نافذة التسجيل مفتوحة')" />
            <span class="text-xs" style="color: var(--text-muted)"
                  data-countdown="{{ $meeting->attendance_closes_at->toIso8601String() }}"
                  data-prefix="{{ setting('volunteer.meetings_show.prefix', 'باقي') }}">{{ $meeting->attendance_closes_at->diffForHumans() }}</span>
        @endif
    </div>

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'details', 'label' => setting('volunteer.common.details', 'التفاصيل'), 'url' => $tabUrl('details')],
        ['key' => 'attendance', 'label' => setting('volunteer.meetings_show.label_3', 'الحضور'), 'url' => $tabUrl('attendance')],
        ['key' => 'minutes', 'label' => setting('volunteer.meetings_show.label_4', 'المحضر'), 'url' => $tabUrl('minutes')],
        ['key' => 'discussion', 'label' => setting('volunteer.meetings_show.label_5', 'النقاش'), 'url' => $tabUrl('discussion')],
    ]" />

    @if ($tab === 'details')
        <div class="card p-4 space-y-3">
            <p class="text-sm whitespace-pre-line">{{ $meeting->description ?: setting('volunteer.meetings_show.text_2', 'مفيش وصف مكتوب للاجتماع ده.') }}</p>

            @if ($meeting->external_link)
                <a href="{{ $meeting->external_link }}" target="_blank" rel="noopener"
                   class="btn inline-flex items-center gap-1 rounded-xl px-3 py-1.5 text-xs"
                   style="border: 1px solid var(--border)">
                    @include('volunteer.meetings.partials.icon', ['name' => 'link']) {{ setting('volunteer.meetings_show.link', 'رابط الاجتماع') }}
                </a>
            @endif

            <div class="text-xs grid gap-2 sm:grid-cols-2" style="color: var(--text-muted)">
                <span>{{ setting('volunteer.meetings_show.text_3', 'الكيان:') }} {{ $meeting->entity?->name_ar ?? setting('volunteer.common.all_entities', 'كلّ الكيانات') }}</span>
                <span>{{ setting('volunteer.meetings_show.text_4', 'الجمهور:') }} {{ ['all' => setting('volunteer.common.all', 'الكلّ'), 'sub_entity' => setting('volunteer.meetings_show.sub_entity', 'قسم فرعيّ')][$meeting->audience] ?? setting('volunteer.meetings_show.text_5', 'قسم') }}</span>
                <span>{{ setting('volunteer.meetings_show.text_6', 'عدد أسئلة الحضور:') }} {{ $meeting->questions->count() }}</span>
                <span>{{ setting('volunteer.meetings_show.text_7', 'كود حضور:') }} {{ $meeting->attendance_code ? setting('volunteer.meetings_show.text_8', 'مفعَّل') : setting('volunteer.meetings_show.text_9', 'بلا كود') }}</span>
            </div>

            @include('volunteer.meetings.partials.attachments', [
                'items' => $attachments, 'meeting' => $meeting, 'canManage' => $canManage,
            ])
        </div>
    @endif

    @if ($tab === 'attendance')
        @if (! $canSeeFull)
            <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('volunteer.meetings_show.text_10', 'القائمة الكاملة للمخوَّل — وإنت شايف حالتك إنت.') }}</p>
        @endif

        @if ($rows->isEmpty())
            <x-empty :message="setting('volunteer.meetings_show.empty', 'مفيش تسجيلات حضور لسّه')" />
        @else
            {{-- الجدول على الديسكتوب، وكروت رأسيّة على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
            <div class="card min-w-0 overflow-x-auto hidden md:block">
                <table class="w-full text-sm">
                    <thead style="color: var(--text-muted)">
                        <tr class="text-start">
                            <th class="p-3 text-start">{{ setting('volunteer.meetings_show.col', 'العضو') }}</th>
                            <th class="p-3 text-start">{{ setting('volunteer.common.status', 'الحالة') }}</th>
                            <th class="p-3 text-start">{{ setting('volunteer.meetings_show.col_2', 'وقت التسجيل') }}</th>
                            <th class="p-3 text-start">{{ setting('volunteer.meetings_show.col_3', 'بعد الانتهاء') }}</th>
                            <th class="p-3 text-start">{{ setting('volunteer.meetings_show.col_4', 'القيمة على Rep') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="p-3">{{ $row->user?->name }}</td>
                                <td class="p-3">
                                    <x-state-badge :state="$row->status === 'registered' ? 'ok' : ($row->status === 'absent' ? 'danger' : 'idle')"
                                                   :label="['registered' => setting('volunteer.meetings_show.registered', 'سجّل'), 'absent' => setting('volunteer.meetings_show.absent', 'غياب'), 'excused' => setting('volunteer.meetings_show.excused', 'اعتذر'), 'excused_settled' => setting('volunteer.meetings_show.excused', 'اعتذر')][$row->status] ?? $row->status" />
                                </td>
                                <td class="p-3">{{ $row->registered_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="p-3">{{ $row->hours_after_end !== null ? $row->hours_after_end.setting('volunteer.meetings_show.text_11', ' ساعة') : '—' }}</td>
                                <td class="p-3 font-semibold">{{ $attendance->valueLabel((float) $row->rep_value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="space-y-2 md:hidden">
                @foreach ($rows as $row)
                    <div class="card p-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <strong>{{ $row->user?->name }}</strong>
                            <x-state-badge :state="$row->status === 'registered' ? 'ok' : ($row->status === 'absent' ? 'danger' : 'idle')"
                                           :label="['registered' => setting('volunteer.meetings_show.registered', 'سجّل'), 'absent' => setting('volunteer.meetings_show.absent', 'غياب'), 'excused' => setting('volunteer.meetings_show.excused', 'اعتذر'), 'excused_settled' => setting('volunteer.meetings_show.excused', 'اعتذر')][$row->status] ?? $row->status" />
                        </div>
                        <div class="mt-1 text-xs" style="color: var(--text-muted)">
                            {{ $row->registered_at?->format('Y-m-d H:i') ?? '—' }} ·
                            {{ setting('volunteer.common.value', 'القيمة') }} {{ $attendance->valueLabel((float) $row->rep_value) }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    @if ($tab === 'minutes')
        <div class="card p-4">
            @if (filled($meeting->minutes))
                <div class="text-sm whitespace-pre-line">{{ $meeting->minutes }}</div>
            @else
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.meetings_show.text_12', 'المحضر لسّه ما اترفعش.') }}</p>
            @endif

            @include('volunteer.meetings.partials.attachments', [
                'items' => $attachments, 'meeting' => $meeting, 'canManage' => $canManage,
            ])
        </div>
    @endif

    @if ($tab === 'discussion')
        <form method="post" action="{{ route('volunteer.meetings.posts', $meeting) }}"
              enctype="multipart/form-data" class="card p-3 mb-4">
            @csrf
            <textarea name="body" rows="2" required maxlength="4000" placeholder="{{ setting('volunteer.meetings_show.placeholder', 'اكتب بوست للنقاش…') }}"
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
            <div class="flex items-center gap-2 mt-2">
                <input type="file" name="attachment" class="text-xs flex-1">
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.meetings_show.action_5', 'انشر') }}</button>
            </div>
        </form>

        <div class="flex items-center gap-2 mb-3 text-xs">
            <a href="{{ route('volunteer.meetings.show', ['meeting' => $meeting, 'tab' => 'discussion', 'sort' => 'new']) }}"
               class="rounded-full px-3 py-1" style="{{ $sort === 'new' ? 'background: var(--color-brand-500); color:#04201c' : 'border: 1px solid var(--border)' }}">{{ setting('volunteer.meetings_show.link_2', 'الأحدث') }}</a>
            <a href="{{ route('volunteer.meetings.show', ['meeting' => $meeting, 'tab' => 'discussion', 'sort' => 'top']) }}"
               class="rounded-full px-3 py-1" style="{{ $sort === 'top' ? 'background: var(--color-brand-500); color:#04201c' : 'border: 1px solid var(--border)' }}">{{ setting('volunteer.meetings_show.link_3', 'الأعلى تصويتًا') }}</a>
        </div>

        @if ($posts->isEmpty())
            <x-empty :message="setting('volunteer.meetings_show.empty_2', 'ابدأ أوّل بوست — النقاش بيبدأ بواحد')" />
        @else
            <div class="space-y-3">
                @foreach ($posts as $post)
                    @include('volunteer.meetings.partials.post', [
                        'post' => $post, 'meeting' => $meeting,
                        'canManage' => $canManage, 'myVotes' => $myVotes, 'depth' => 0,
                    ])
                @endforeach
            </div>
        @endif
    @endif

    @push('modals')
        @include('volunteer.meetings.partials.modals', [
            'meeting' => $meeting, 'mine' => $mine,
            'attendance' => $attendance, 'scope' => app(\App\Services\Volunteer\Meetings\MeetingScope::class),
        ])

        @if ($canManage)
            <x-modal :id="'questions-'.$meeting->id" :title="setting('volunteer.meetings_show.tooltip', 'أسئلة الحضور والـOTP')">
                <form method="post" action="{{ route('volunteer.meetings.questions', $meeting) }}" class="space-y-3">
                    @csrf
                    <p class="text-xs" style="color: var(--text-muted)">
                        {{ setting('volunteer.meetings_show.text_13', 'يضيفها صاحب الاجتماع أو أيّ أبلاين فوقه حتى السقف.') }}
                    </p>
                    <label class="block text-sm">
                        <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.meetings_show.field', 'كود حضور / OTP') }}</span>
                        <input type="text" name="attendance_code" maxlength="32" autocomplete="off"
                               value="{{ $meeting->attendance_code }}"
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <fieldset class="rounded-xl p-3" style="border: 1px solid var(--border)">
                        <legend class="text-xs px-1" style="color: var(--text-muted)">{{ setting('volunteer.meetings_show.legend', 'سؤال اختيارات جديد') }}</legend>
                        <input type="text" name="questions[0][prompt]" placeholder="{{ setting('volunteer.meetings_show.placeholder_2', 'نصّ السؤال') }}"
                               class="w-full rounded-xl px-3 py-2 text-sm mb-2"
                               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <input type="text" name="questions[0][options]" placeholder="{{ setting('volunteer.meetings_show.placeholder_3', 'الخيارات مفصولة بفاصلة') }}"
                               class="w-full rounded-xl px-3 py-2 text-sm mb-2"
                               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <input type="text" name="questions[0][correct_answer]" placeholder="{{ setting('volunteer.meetings_show.placeholder_4', 'الإجابة الصحيحة') }}"
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    </fieldset>
                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.meetings_show.action_6', 'حفظ') }}</button>
                </form>
            </x-modal>
        @endif
    @endpush

    @include('volunteer.meetings.partials.countdown')
@endsection
