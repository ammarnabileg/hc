@extends('layouts.volunteer')

@section('title', 'الاجتماعات')

@php
    /**
     * الاجتماعات — القادمة والمنتهية (24.4 · 13.4-ن-ب).
     * سؤال واحد للشاشة: «فيه إيه في نطاقي وهل عليّ تسجيل حضور؟»
     * وفعل رئيسيّ واحد: [اجتماع جديد] لمن يملك الصلاحيّة.
     */
@endphp

@section('content')
    <x-page-header
        title="الاجتماعات"
        subtitle="اجتماعات نطاقك وتسجيل حضورك بالكود"
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/dashboard')], ['label' => 'الاجتماعات']]">
        <x-slot:action>
            @if ($canCreate)
                <button type="button" data-modal-open="meeting-new"
                        class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">اجتماع جديد</button>
            @endif
        </x-slot:action>
    </x-page-header>

    {{-- بانر أحمر متحرّك: نافذة حضور مفتوحة ولسّه ما سجّلتش (24.4) --}}
    @if ($openWindow)
        <a href="{{ route('volunteer.meetings.show', $openWindow) }}"
           class="card p-3 mb-4 flex items-center gap-3 animate-fadeup"
           style="background: color-mix(in srgb, var(--color-state-danger) 14%, var(--surface-raised));
                  border-color: var(--color-state-danger)">
            <span class="animate-shimmer rounded-full px-2 py-0.5 text-xs"
                  style="background: color-mix(in srgb, var(--color-state-danger) 25%, transparent)">
                <span aria-hidden="true">◉</span> سجّل حضورك
            </span>
            <span class="text-sm font-semibold truncate">{{ $openWindow->title }}</span>
            <span class="text-sm ms-auto" data-countdown="{{ $openWindow->attendance_closes_at->toIso8601String() }}"
                  data-prefix="باقي">{{ $openWindow->attendance_closes_at->diffForHumans() }}</span>
        </a>
    @endif

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'upcoming', 'label' => 'قادمة', 'count' => $counts['upcoming'], 'url' => route('volunteer.meetings', ['tab' => 'upcoming'])],
        ['key' => 'ended', 'label' => 'منتهية', 'count' => $counts['ended'], 'url' => route('volunteer.meetings', ['tab' => 'ended'])],
    ]" />

    {{-- ثلاثة فلاتر ظاهرة + بحث، والمدى خلف «فلاتر متقدّمة» (2.15-أ-4) --}}
    <x-filters :action="route('volunteer.meetings')">
        <input type="hidden" name="tab" value="{{ $tab }}">

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

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">حالتي</span>
            <select name="mine" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="registered" @selected($filters['mine'] === 'registered')>سجّلت</option>
                <option value="pending" @selected($filters['mine'] === 'pending')>لم أسجّل</option>
                <option value="excused" @selected($filters['mine'] === 'excused')>اعتذرت</option>
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="ابحث بعنوان الاجتماع…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <x-slot:advanced>
            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">المدى (أيّام)</span>
                <input type="number" name="days" min="1" max="365" value="{{ $filters['days'] }}"
                       class="rounded-xl px-3 py-2 text-sm w-28"
                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            </label>
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm" style="border: 1px solid var(--border)">تطبيق</button>
        </x-slot:advanced>
    </x-filters>

    @if ($meetings->isEmpty())
        <x-empty message="مفيش اجتماعات في نطاقك — أوّل واحد لسّه جاي" />
    @else
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($meetings as $meeting)
                @include('volunteer.meetings.partials.card', [
                    'meeting' => $meeting, 'mine' => $mine,
                    'attendance' => $attendance, 'scope' => $scope,
                ])
            @endforeach
        </div>
    @endif

    @push('modals')
        @foreach ($meetings as $meeting)
            @include('volunteer.meetings.partials.modals', [
                'meeting' => $meeting, 'mine' => $mine,
                'attendance' => $attendance, 'scope' => $scope,
            ])
        @endforeach

        @if ($canCreate)
            <x-modal id="meeting-new" title="اجتماع جديد">
                <form method="post" action="{{ route('volunteer.meetings.store') }}"
                      enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <label class="block text-sm">
                        <span class="block text-xs mb-1" style="color: var(--text-muted)">العنوان</span>
                        <input type="text" name="title" required maxlength="180"
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    </label>

                    <label class="block text-sm">
                        <span class="block text-xs mb-1" style="color: var(--text-muted)">الوصف</span>
                        <textarea name="description" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm">
                            <span class="block text-xs mb-1" style="color: var(--text-muted)">الموعد</span>
                            <input type="datetime-local" name="scheduled_at" required
                                   class="w-full rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        </label>

                        <label class="block text-sm">
                            <span class="block text-xs mb-1" style="color: var(--text-muted)">الجمهور</span>
                            <select name="audience" class="w-full rounded-xl px-3 py-2 text-sm"
                                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                                <option value="entity">القسم</option>
                                <option value="sub_entity">القسم الفرعيّ</option>
                                <option value="all">الكلّ</option>
                            </select>
                        </label>
                    </div>

                    <label class="block text-sm">
                        <span class="block text-xs mb-1" style="color: var(--text-muted)">الكيان</span>
                        <select name="entity_id" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($entities as $entity)
                                <option value="{{ $entity->id }}">{{ $entity->name_ar }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="block text-xs mb-1" style="color: var(--text-muted)">الرابط الخارجيّ</span>
                        <input type="url" name="external_link" placeholder="https://…"
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    </label>

                    {{-- «خيارات متقدّمة» مطويّة، والفورم يعمل كاملًا بدونها (2.15-د) --}}
                    <details class="rounded-xl p-3" style="border: 1px solid var(--border)">
                        <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">خيارات متقدّمة: تذكير · كود حضور · أسئلة · مرفقات</summary>

                        <div class="mt-3 space-y-3">
                            <label class="block text-sm">
                                <span class="block text-xs mb-1" style="color: var(--text-muted)">تذكير قبل الموعد (ساعات)</span>
                                <input type="number" name="reminder_hours" min="0" max="168"
                                       value="{{ setting('meetings.reminder.hours_before', 2) }}"
                                       class="w-full rounded-xl px-3 py-2 text-sm"
                                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                            </label>

                            <label class="block text-sm">
                                <span class="block text-xs mb-1" style="color: var(--text-muted)">كود حضور / OTP</span>
                                <input type="text" name="attendance_code" maxlength="32" autocomplete="off"
                                       class="w-full rounded-xl px-3 py-2 text-sm"
                                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                            </label>

                            <fieldset class="rounded-xl p-3" style="border: 1px solid var(--border)">
                                <legend class="text-xs px-1" style="color: var(--text-muted)">سؤال اختيارات</legend>
                                <input type="text" name="questions[0][prompt]" placeholder="نصّ السؤال"
                                       class="w-full rounded-xl px-3 py-2 text-sm mb-2"
                                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                                <input type="text" name="questions[0][options]" placeholder="الخيارات مفصولة بفاصلة"
                                       class="w-full rounded-xl px-3 py-2 text-sm mb-2"
                                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                                <input type="text" name="questions[0][correct_answer]" placeholder="الإجابة الصحيحة"
                                       class="w-full rounded-xl px-3 py-2 text-sm"
                                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                            </fieldset>

                            <label class="block text-sm">
                                <span class="block text-xs mb-1" style="color: var(--text-muted)">مرفقات</span>
                                <input type="file" name="attachments[]" multiple class="w-full text-sm">
                            </label>
                        </div>
                    </details>

                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">أنشئ الاجتماع</button>
                </form>
            </x-modal>
        @endif
    @endpush

    @include('volunteer.meetings.partials.countdown')
@endsection

@if ($canCreate)
    @section('mobile_action')
        <button type="button" data-modal-open="meeting-new"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">اجتماع جديد</button>
    @endsection
@endif
