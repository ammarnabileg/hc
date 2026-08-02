@extends('layouts.app')

@section('title', 'حضوري والمحاضر')

@php
    /** حضوري والمحاضر (24.4): سجلّ حضوري وأثره، وأرشيف المحاضر والمرفقات. */
    $rateState = $rate >= 80 ? 'ok' : ($rate >= 50 ? 'warn' : 'danger');
    $tabUrl = fn (string $key) => route('volunteer.attendance', array_filter([
        'tab' => $key, 'days' => $filters['days'], 'entity' => $filters['entity'] ?: null, 'q' => $filters['q'] ?: null,
    ]));
@endphp

@section('content')
    <x-page-header
        title="حضوري والمحاضر"
        subtitle="سجلّ حضورك وأثره على درجة الالتزام"
        :breadcrumbs="[['label' => 'الاجتماعات', 'url' => route('volunteer.meetings')], ['label' => 'حضوري والمحاضر']]" />

    {{-- كارت نسبة الحضور (وحده — لا نزحم الشاشة بأربعة) --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-4">
        <x-kpi label="نسبة الحضور" :value="$rate.'%'" icon="◷" :state="$rateState"
               :hint="'خلال آخر '.$filters['days'].' يوم'" />
        <x-kpi label="اجتماعات سجّلت فيها" :value="$registered" icon="✓" />
    </div>

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'attendance', 'label' => 'حضوري', 'url' => $tabUrl('attendance')],
        ['key' => 'minutes', 'label' => 'المحاضر والمرفقات', 'url' => $tabUrl('minutes')],
    ]" />

    <x-filters :action="route('volunteer.attendance')">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الفترة (أيّام)</span>
            <input type="number" name="days" min="1" max="365" value="{{ $filters['days'] }}"
                   class="rounded-xl px-3 py-2 text-sm w-28"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الكيان</span>
            <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($entities as $entity)
                    <option value="{{ $entity->id }}" @selected($filters['entity'] === $entity->id)>{{ $entity->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="بالعنوان أو داخل المحضر…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($tab === 'attendance')
        @if ($rows->isEmpty())
            <x-empty message="مفيش سجلّ حضور بعد — أوّل اجتماع في الطريق" :action="'شوف الاجتماعات'" :href="route('volunteer.meetings')" />
        @else
            <div class="card overflow-x-auto hidden md:block">
                <table class="w-full text-sm">
                    <thead style="color: var(--text-muted)">
                        <tr>
                            <th class="p-3 text-start">الاجتماع</th>
                            <th class="p-3 text-start">التاريخ</th>
                            <th class="p-3 text-start">وقت تسجيلي</th>
                            <th class="p-3 text-start">بعد الانتهاء</th>
                            <th class="p-3 text-start">القيمة على Rep</th>
                            <th class="p-3 text-start">الكيان</th>
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
                                <td class="p-3">{{ $row->hours_after_end !== null ? $row->hours_after_end.' ساعة' : '—' }}</td>
                                <td class="p-3"><x-state-badge :state="$state" :label="$attendance->valueLabel((float) $row->rep_value)" /></td>
                                <td class="p-3">{{ $row->meeting?->entity?->name_ar ?? 'الكلّ' }}</td>
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
                            {{ $row->registered_at ? 'سجّلت '.$row->registered_at->format('H:i') : 'ما سجّلتش' }}
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    @else
        @if ($minutes->isEmpty())
            <x-empty message="مفيش محاضر في المدى ده" />
        @else
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($minutes as $meeting)
                    <article class="card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('volunteer.meetings.show', ['meeting' => $meeting, 'tab' => 'minutes']) }}"
                               class="font-bold hover:underline truncate">{{ $meeting->title }}</a>
                            <span class="text-xs" style="color: var(--text-muted)">{{ $meeting->ended_at?->format('Y-m-d') }}</span>
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">صاحب المحضر: {{ $meeting->owner?->name }}</p>

                        <div class="mt-3 flex items-center gap-2">
                            <button type="button" data-modal-open="minutes-{{ $meeting->id }}"
                                    class="btn rounded-xl px-3 py-1.5 text-xs" style="border: 1px solid var(--border)">معاينة</button>
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
            <x-modal :id="'att-'.$row->id" title="معاملة الحضور">
                <div class="space-y-2 text-sm">
                    <p><strong>{{ $row->meeting?->title }}</strong></p>
                    <p style="color: var(--text-muted)">
                        وقت تسجيلي: {{ $row->registered_at?->format('Y-m-d H:i') ?? '—' }} ·
                        بعد الانتهاء: {{ $row->hours_after_end !== null ? $row->hours_after_end.' ساعة' : '—' }}
                    </p>
                    <p>القيمة على Rep: <strong>{{ $attendance->valueLabel((float) $row->rep_value) }}</strong></p>

                    @if ($row->transaction)
                        @php $left = $objections->daysLeft($row->transaction); @endphp
                        @if ($left !== null && ! $objections->existingFor($row->transaction))
                            <form method="post" action="{{ route('volunteer.objections.store') }}"
                                  enctype="multipart/form-data" class="space-y-2 pt-2" style="border-top: 1px solid var(--border)">
                                @csrf
                                <input type="hidden" name="transaction_id" value="{{ $row->transaction->id }}">
                                <p class="text-xs" style="color: var(--text-muted)">
                                    باقي {{ $left }} يوم على مهلة الاعتراض — واعتراض واحد لكلّ معاملة.
                                </p>
                                <textarea name="reason" rows="3" required minlength="5" placeholder="سبب الاعتراض"
                                          class="w-full rounded-xl px-3 py-2 text-sm"
                                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
                                <input type="file" name="attachment" class="w-full text-xs">
                                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                                        style="background: var(--color-brand-500); color: #04201c">أرسل الاعتراض</button>
                            </form>
                        @elseif ($objections->existingFor($row->transaction))
                            <a href="{{ route('volunteer.objections', ['objection' => $objections->existingFor($row->transaction)->id]) }}"
                               class="btn inline-flex rounded-xl px-3 py-1.5 text-xs" style="border: 1px solid var(--border)">عرض الاعتراض</a>
                        @else
                            <p class="text-xs" style="color: var(--text-muted)">انتهت مهلة الاعتراض.</p>
                        @endif
                    @endif
                </div>
            </x-modal>
        @endforeach

        @foreach ($minutes as $meeting)
            {{-- معاينة المحضر برأس ثابت وجسم متمرّر (2.10.1-17) --}}
            <x-modal :id="'minutes-'.$meeting->id" :title="'محضر: '.$meeting->title">
                <div class="text-sm whitespace-pre-line">{{ $meeting->minutes ?: 'المحضر لسّه ما اترفعش.' }}</div>
            </x-modal>
        @endforeach
    @endpush
@endsection
