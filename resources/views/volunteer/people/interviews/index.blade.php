@extends('layouts.volunteer')

@section('title', 'المقابلات')

@php
    /**
     * المقابلات والـScorecards (13.4-د · 24.4-12).
     * تابان: تقويم يتفادى التعارض · نتائج. والتحميل كسول: التاب المفتوح وحده يُبنى (2.15-د).
     */
    $days = collect();
    $cursor = $month->copy()->startOfMonth();
    while ($cursor->lte($month->copy()->endOfMonth())) {
        $days->push($cursor->copy());
        $cursor->addDay();
    }
@endphp

@section('content')
    <x-page-header
        title="المقابلات"
        subtitle="{{ $interviews->count() }} مقابلة في السجلّ"
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'التوظيف'], ['label' => 'المقابلات']]">
        <x-slot:action>
            @if ($canSchedule)
                <button type="button" data-modal-open="schedule-modal"
                        class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">جدولة مقابلة</button>
            @endif
        </x-slot:action>
    </x-page-header>

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'calendar', 'label' => 'تقويم المقابلات', 'url' => route('volunteer.interviews', ['tab' => 'calendar', 'month' => $month->toDateString()])],
        ['key' => 'results', 'label' => 'نتائج المقابلات', 'url' => route('volunteer.interviews', ['tab' => 'results'])],
    ]" />

    <x-filters :action="route('volunteer.interviews')">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">المُقابِل</span>
            <select name="interviewer" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($interviewers as $person)
                    <option value="{{ $person->id }}" @selected((int) $filters['interviewer'] === (int) $person->id)>{{ $person->shortName() }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الحالة</span>
            <select name="status" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="اسم المرشّح…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($tab === 'calendar')
        <div class="card p-3 mb-4 flex items-center justify-between">
            <a class="btn rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)"
               href="{{ route('volunteer.interviews', ['tab' => 'calendar', 'month' => $month->copy()->subMonth()->toDateString()]) }}">الشهر السابق</a>
            <strong class="text-sm">{{ $month->translatedFormat('F Y') }}</strong>
            <a class="btn rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)"
               href="{{ route('volunteer.interviews', ['tab' => 'calendar', 'month' => $month->copy()->addMonth()->toDateString()]) }}">الشهر التالي</a>
        </div>

        {{-- التقويم كقائمة أيّام: على الموبايل بلا شبكة مضغوطة ولا تمرير أفقيّ (2.15-ج) --}}
        <div class="grid gap-2 md:grid-cols-7">
            @foreach ($days as $day)
                @php $slots = $calendar[$day->toDateString()] ?? collect(); @endphp
                <div class="card p-2 {{ $slots->isEmpty() ? 'hidden md:block' : '' }}">
                    <div class="text-xs mb-1" style="color: var(--text-muted)">{{ $day->translatedFormat('D j') }}</div>
                    @foreach ($slots as $slot)
                        <div class="rounded-xl px-2 py-1 mb-1 text-xs" style="background: var(--surface-sunken)">
                            <div class="font-semibold truncate">{{ $slot->recruitment_candidate?->user?->shortName() }}</div>
                            <div style="color: var(--text-muted)">{{ $slot->scheduled_at->translatedFormat('g:i A') }}</div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    @if ($interviews->isEmpty())
        <div class="mt-4"><x-empty message="مفيش مقابلات مجدولة" /></div>
    @else
        <div class="mt-4 space-y-3">
            @foreach ($interviews as $interview)
                @php
                    $card = $cards[$interview->id] ?? null;
                    $countdown = $interview->scheduled_at->isFuture()
                        ? 'باقي '.(int) now()->diffInHours($interview->scheduled_at).' ساعة'
                        : 'عدّى من '.(int) $interview->scheduled_at->diffInHours(now()).' ساعة';
                @endphp

                {{-- الجداول كروت رأسيّة على الموبايل — ممنوع التمرير الأفقيّ (2.15-ج) --}}
                <article class="card p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold text-sm">{{ $interview->recruitment_candidate?->user?->name ?? 'مرشّح' }}</h3>
                            <p class="text-xs" style="color: var(--text-muted)">
                                المُقابِل: {{ $interview->interviewer?->shortName() }} ·
                                {{ $interview->scheduled_at->translatedFormat('l j F — g:i A') }} · {{ $countdown }}
                            </p>
                        </div>
                        <x-state-badge :state="$scheduler->statusState($interview->status)" :label="$statuses[$interview->status] ?? $interview->status" />
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($interview->external_link)
                            <a href="{{ $interview->external_link }}" target="_blank" rel="noopener"
                               class="btn rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)">رابط المقابلة</a>
                        @endif

                        @if ($canSeeResults)
                            <a href="{{ route('volunteer.interviews.scorecard', $interview) }}"
                               class="btn rounded-xl px-3 py-2 text-sm font-semibold"
                               style="background: var(--color-brand-500); color: #04201c">
                                {{ $card ? 'فتح النتيجة' : 'املأ النتيجة' }}
                            </a>
                            @if ($card)
                                <span class="text-xs" style="color: var(--text-muted)">
                                    الدرجة {{ $card->total_score }} · {{ $card->is_draft ? 'مسودّة' : 'مكتملة' }}
                                </span>
                            @endif
                        @endif

                        {{-- تغيير الحالة من الصفّ نفسه بلا بوب-أب (2.15-ب) --}}
                        <form method="post" action="{{ route('volunteer.interviews.status', $interview) }}" class="flex items-center gap-2">
                            @csrf
                            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}" @selected($interview->status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="reason" placeholder="سبب الإلغاء" class="rounded-xl px-3 py-2 text-sm w-36"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <button type="submit" class="btn rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)">حدّث</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($canSchedule)
        <x-modal id="schedule-modal" title="جدولة مقابلة">
            <form method="post" action="{{ route('volunteer.interviews.store') }}" class="space-y-3">
                @csrf
                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">المرشّح</span>
                    <select name="recruitment_candidate_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($candidates as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->user?->name }} — #{{ $candidate->user?->code }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">المُقابِل</span>
                    <select name="interviewer_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($interviewers as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">الموعد</span>
                    <input type="datetime-local" name="scheduled_at" required class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                </label>

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">رابط ميتينج خارجيّ</span>
                    <input type="url" name="external_link" placeholder="https://…" class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)" dir="ltr">
                </label>

                <p class="text-xs" style="color: var(--text-muted)">
                    لو الموعد متعارض مع مقابلة تانية لنفس المُقابِل هنقولك ونقترح تغييره.
                </p>

                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">احجز الموعد</button>
            </form>
        </x-modal>
    @endif
@endsection
