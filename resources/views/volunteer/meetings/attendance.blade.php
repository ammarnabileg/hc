@extends('layouts.volunteer')

@section('title', setting('volunteer.meetings_attendance.title', 'حضوري والمحاضر'))

@php
    /** حضوري والمحاضر (24.4): سجلّ حضوري وأثره، وأرشيف المحاضر والمرفقات. */
    $rateState = $rate >= 80 ? 'ok' : ($rate >= 50 ? 'warn' : 'danger');
    $tabUrl = fn (string $key) => route('volunteer.attendance', array_filter([
        'tab' => $key, 'days' => $filters['days'], 'entity' => $filters['entity'] ?: null, 'q' => $filters['q'] ?: null,
    ]));
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.meetings_attendance.title', 'حضوري والمحاضر')"
        :subtitle="setting('volunteer.meetings_attendance.subtitle', 'سجلّ حضورك وأثره على درجة الالتزام')"
        :breadcrumbs="[['label' => setting('volunteer.meetings_attendance.label', 'الاجتماعات'), 'url' => route('volunteer.meetings')], ['label' => setting('volunteer.meetings_attendance.title', 'حضوري والمحاضر')]]" />

    {{-- كارت نسبة الحضور (وحده — لا نزحم الشاشة بأربعة) --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-4">
        <x-kpi :label="setting('volunteer.meetings_attendance.label_2', 'نسبة الحضور')" :value="$rate.'%'" icon="◷" :state="$rateState"
               :hint="setting('volunteer.meetings_attendance.hint', 'خلال آخر ').$filters['days'].setting('volunteer.meetings_attendance.hint_2', ' يوم')" />
        <x-kpi :label="setting('volunteer.meetings_attendance.label_3', 'اجتماعات سجّلت فيها')" :value="$registered" icon="✓" />
    </div>

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'attendance', 'label' => setting('volunteer.meetings_attendance.label_4', 'حضوري'), 'url' => $tabUrl('attendance')],
        ['key' => 'minutes', 'label' => setting('volunteer.meetings_attendance.label_5', 'المحاضر والمرفقات'), 'url' => $tabUrl('minutes')],
    ]" />

    <x-filters :action="route('volunteer.attendance')">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.meetings_attendance.field', 'الفترة (أيّام)') }}</span>
            <input type="number" name="days" min="1" max="365" value="{{ $filters['days'] }}"
                   class="rounded-xl px-3 py-2 text-sm w-28"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.entity', 'الكيان') }}</span>
            <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                @foreach ($entities as $entity)
                    <option value="{{ $entity->id }}" @selected($filters['entity'] === $entity->id)>{{ $entity->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('volunteer.meetings_attendance.placeholder', 'بالعنوان أو داخل المحضر…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($tab === 'attendance')
        @if ($rows->isEmpty())
            <x-empty :message="setting('volunteer.meetings_attendance.empty', 'مفيش سجلّ حضور بعد — أوّل اجتماع في الطريق')" :action="setting('volunteer.meetings_attendance.action', 'شوف الاجتماعات')" :href="route('volunteer.meetings')" />
        @else
            <div class="card min-w-0 overflow-x-auto hidden md:block">
                <table class="w-full text-sm">
                    <thead style="color: var(--text-muted)">
                        <tr>
                            <th class="p-3 text-start">{{ setting('volunteer.meetings_attendance.col', 'الاجتماع') }}</th>
                            <th class="p-3 text-start">{{ setting('volunteer.common.date', 'التاريخ') }}</th>
                            <th class="p-3 text-start">{{ setting('volunteer.meetings_attendance.col_2', 'وقت تسجيلي') }}</th>
                            <th class="p-3 text-start">{{ setting('volunteer.meetings_attendance.col_3', 'بعد الانتهاء') }}</th>
                            <th class="p-3 text-start">{{ setting('volunteer.meetings_attendance.col_4', 'القيمة على Rep') }}</th>
                            <th class="p-3 text-start">{{ setting('volunteer.common.entity', 'الكيان') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $state = $row->status === 'registered' ? 'ok' : ($row->status === 'absent' ? 'danger' : 'idle');
                            @endphp
                            <tr style="border-top: 1px solid var(--border)" class="cursor-pointer"
                                data-modal-open="att-{{ $row->id }}">
                                <td class="p-3">{{ $row->meeting?->title }}</td>
                                <td class="p-3">{{ $row->meeting?->scheduled_at?->format('Y-m-d') }}</td>
                                <td class="p-3">{{ $row->registered_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="p-3">{{ $row->hours_after_end !== null ? $row->hours_after_end.setting('volunteer.meetings_attendance.text', ' ساعة') : '—' }}</td>
                                <td class="p-3"><x-state-badge :state="$state" :label="$attendance->valueLabel((float) $row->rep_value)" /></td>
                                <td class="p-3">{{ $row->meeting?->entity?->name_ar ?? setting('volunteer.common.all', 'الكلّ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- الموبايل: كروت رأسيّة بأهمّ الحقول بلا تمرير أفقيّ (2.15-ج) --}}
            <div class="space-y-2 md:hidden">
                @foreach ($rows as $row)
                    @php $state = $row->status === 'registered' ? 'ok' : ($row->status === 'absent' ? 'danger' : 'idle'); @endphp
                    <button type="button" data-modal-open="att-{{ $row->id }}" class="card p-3 w-full text-start">
                        <div class="flex items-center justify-between gap-2">
                            <strong class="text-sm truncate">{{ $row->meeting?->title }}</strong>
                            <x-state-badge :state="$state" :label="$attendance->valueLabel((float) $row->rep_value)" />
                        </div>
                        <div class="mt-1 text-xs" style="color: var(--text-muted)">
                            {{ $row->meeting?->scheduled_at?->format('Y-m-d') }} ·
                            {{ $row->registered_at ? setting('volunteer.meetings_attendance.text_2', 'سجّلت ').$row->registered_at->format('H:i') : setting('volunteer.meetings_attendance.text_3', 'ما سجّلتش') }}
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    @else
        @if ($minutes->isEmpty())
            <x-empty :message="setting('volunteer.meetings_attendance.empty_2', 'مفيش محاضر في المدى ده')" />
        @else
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($minutes as $meeting)
                    <article class="card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('volunteer.meetings.show', ['meeting' => $meeting, 'tab' => 'minutes']) }}"
                               class="font-bold hover:underline truncate">{{ $meeting->title }}</a>
                            <span class="text-xs" style="color: var(--text-muted)">{{ $meeting->ended_at?->format('Y-m-d') }}</span>
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('volunteer.meetings_attendance.field_2', 'صاحب المحضر:') }} {{ $meeting->owner?->name }}</p>

                        <div class="mt-3 flex items-center gap-2">
                            <button type="button" data-modal-open="minutes-{{ $meeting->id }}"
                                    class="btn rounded-xl px-3 py-1.5 text-xs" style="border: 1px solid var(--border)">{{ setting('volunteer.meetings_attendance.action_2', 'معاينة') }}</button>
                        </div>

                        @include('volunteer.meetings.partials.attachments', [
                            'items' => $attachments[$meeting->id] ?? [], 'meeting' => $meeting, 'canManage' => false,
                        ])
                    </article>
                @endforeach
            </div>
        @endif
    @endif

    @push('modals')
        @foreach ($rows as $row)
            <x-modal :id="'att-'.$row->id" :title="setting('volunteer.meetings_attendance.tooltip', 'معاملة الحضور')">
                <div class="space-y-2 text-sm">
                    <p><strong>{{ $row->meeting?->title }}</strong></p>
                    <p style="color: var(--text-muted)">
                        {{ setting('volunteer.meetings_attendance.field_3', 'وقت تسجيلي:') }} {{ $row->registered_at?->format('Y-m-d H:i') ?? '—' }} ·
                        {{ setting('volunteer.meetings_attendance.field_4', 'بعد الانتهاء:') }} {{ $row->hours_after_end !== null ? $row->hours_after_end.setting('volunteer.meetings_attendance.text', ' ساعة') : '—' }}
                    </p>
                    <p>{{ setting('volunteer.meetings_attendance.field_5', 'القيمة على Rep:') }} <strong>{{ $attendance->valueLabel((float) $row->rep_value) }}</strong></p>

                    @if ($row->transaction)
                        @php $left = $objections->daysLeft($row->transaction); @endphp
                        @if ($left !== null && ! $objections->existingFor($row->transaction))
                            <form method="post" action="{{ route('volunteer.objections.store') }}"
                                  enctype="multipart/form-data" class="space-y-2 pt-2" style="border-top: 1px solid var(--border)">
                                @csrf
                                <input type="hidden" name="transaction_id" value="{{ $row->transaction->id }}">
                                <p class="text-xs" style="color: var(--text-muted)">
                                    {{ setting('volunteer.meetings_attendance.field_6', 'باقي') }} {{ $left }} {{ setting('volunteer.meetings_attendance.field_7', 'يوم على مهلة الاعتراض — واعتراض واحد لكلّ معاملة.') }}
                                </p>
                                <textarea name="reason" rows="3" required minlength="5" placeholder="{{ setting('volunteer.meetings_attendance.placeholder_2', 'سبب الاعتراض') }}"
                                          class="w-full rounded-xl px-3 py-2 text-sm"
                                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
                                <input type="file" name="attachment" class="w-full text-xs">
                                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.meetings_attendance.action_3', 'أرسل الاعتراض') }}</button>
                            </form>
                        @elseif ($objections->existingFor($row->transaction))
                            <a href="{{ route('volunteer.objections', ['objection' => $objections->existingFor($row->transaction)->id]) }}"
                               class="btn inline-flex rounded-xl px-3 py-1.5 text-xs" style="border: 1px solid var(--border)">{{ setting('volunteer.meetings_attendance.link', 'عرض الاعتراض') }}</a>
                        @else
                            <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.meetings_attendance.field_8', 'انتهت مهلة الاعتراض.') }}</p>
                        @endif
                    @endif
                </div>
            </x-modal>
        @endforeach

        @foreach ($minutes as $meeting)
            {{-- معاينة المحضر برأس ثابت وجسم متمرّر (2.10.1-17) --}}
            <x-modal :id="'minutes-'.$meeting->id" :title="setting('volunteer.meetings_attendance.tooltip_2', 'محضر: ').$meeting->title">
                <div class="text-sm whitespace-pre-line">{{ $meeting->minutes ?: setting('volunteer.meetings_attendance.text_4', 'المحضر لسّه ما اترفعش.') }}</div>
            </x-modal>
        @endforeach
    @endpush
@endsection
