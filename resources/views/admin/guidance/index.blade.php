@extends('layouts.admin')

@section('title', 'التعليمات')

@section('content')
    {{-- التعليمات: قناة بثّ إداريّة اتّجاه واحد (12.6-أ · 24.3) --}}
    <x-page-header
        title="التعليمات"
        subtitle="ابعت منشورًا لجمهور محدَّد، وشوف مين قرأ ومين أقرّ."
        :breadcrumbs="[['label' => 'التوجيه والدعم', 'url' => route('admin.guidance.index')], ['label' => 'التعليمات']]">
        <x-slot:action>
            @can('announcements.create')
                <button type="button" data-modal-open="announcement-form"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ منشور</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    <x-tabs :tabs="$tabs" current="announcements" />

    <x-filters :action="route('admin.guidance.index')">
        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="عنوان المنشور…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
        <label class="block">
            <span class="block text-sm mb-1">الحالة</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 text-sm mt-6">
            <input type="checkbox" name="pinned" value="1" @checked($filters['pinned'])> المثبَّت فقط
        </label>
        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">تصفية</button>
    </x-filters>

    @if ($announcements->isEmpty())
        <x-empty message="لا منشورات — ابدأ أوّل بثّ." />
    @else
        <div class="space-y-3">
            @foreach ($announcements as $announcement)
                @php $stat = $stats[$announcement->id] ?? ['reads' => 0, 'acks' => 0, 'rate' => 0]; @endphp
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <div class="font-semibold flex items-center gap-2">
                                {{ $announcement->title }}
                                @if ($announcement->is_pinned)<span title="مثبَّت" aria-label="مثبَّت"><x-icon name="placement" size="16" /></span>@endif
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                الجمهور: {{ ['all' => 'الكلّ', 'role' => 'دور', 'course' => 'تدريب', 'path' => 'مسار', 'user' => 'أشخاص', 'segment' => 'شريحة محفوظة'][$announcement->audience['type'] ?? 'all'] ?? 'الكلّ' }}
                                {{-- القنوات المفتوحة لهذا المنشور — تُقرأ من الصفّ لا من الوعد (12.6-أ) --}}
                                · القنوات:
                                @php
                                    $open = collect([
                                        'feed' => (bool) $announcement->show_in_feed,
                                        'push' => (bool) $announcement->push_to_notifications,
                                        'email' => (bool) $announcement->email_enabled,
                                    ])->filter()->keys()->map(fn ($key) => $channels[$key] ?? $key);
                                @endphp
                                {{ $open->isEmpty() ? 'مافيش' : $open->implode(' · ') }}
                                @if ($announcement->email_enabled)
                                    @php $mail = $emailStats[$announcement->id] ?? []; @endphp
                                    · بريد: {{ (int) ($mail['sent'] ?? 0) }} اتبعت
                                    @if (($mail['deferred'] ?? 0) > 0) · {{ (int) $mail['deferred'] }} اتأجّلت @endif
                                    @if (($mail['skipped'] ?? 0) > 0) · {{ (int) $mail['skipped'] }} مستبعَد @endif
                                    @if (($mail['failed'] ?? 0) > 0) · {{ (int) $mail['failed'] }} تعثّرت @endif
                                @endif
                                @if ($announcement->requires_acknowledge)
                                    · إقرار بـ{{ $announcement->acknowledge_xp }} XP (مرّة واحدة)
                                @endif
                                · التفاعل {{ $announcement->reactions_enabled ? 'مسموح' : 'ممنوع' }}
                                @if ($announcement->poll_question)
                                    · استطلاع ({{ $announcement->poll_results_public ? 'نتيجته عامّة' : 'نتيجته مخفيّة' }})
                                @endif
                                @if ($announcement->recurrence)
                                    · {{ $frequencies[$announcement->recurrence] ?? 'متكرّر' }}
                                    @if ($nextRuns[$announcement->id] ?? null)
                                        — الدورة الجاية {{ $nextRuns[$announcement->id]->diffForHumans() }}
                                    @endif
                                @endif
                                @if ($announcement->onboarding_step)
                                    · خطوة {{ $announcement->onboarding_step }} في سلسلة التعريف
                                @endif
                            </div>
                        </div>
                        <x-state-badge
                            :state="$announcement->status === 'published' ? 'ok' : ($announcement->status === 'archived' ? 'idle' : 'warn')"
                            :label="$statuses[$announcement->status] ?? $announcement->status" />
                    </div>

                    {{-- نسبة القراءة كشريط (24.3) --}}
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-xs">
                            <span>نسبة القراءة {{ $stat['rate'] }}%</span>
                            <span style="color: var(--text-muted)">{{ $stat['reads'] }} قراءة · {{ $stat['acks'] }} إقرار</span>
                        </div>
                        <div class="h-1 rounded-full overflow-hidden mt-1" style="background: var(--surface-sunken)">
                            <div class="h-full" style="width: {{ $stat['rate'] }}%; background: var(--color-brand-500)"></div>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-3 text-xs flex-wrap">
                        <a href="{{ route('admin.guidance.analytics', $announcement) }}" class="underline">تحليلات</a>
                        {{-- معاينة على الأجهزة قبل النشر (12.6-أ) --}}
                        <a href="{{ route('admin.guidance.preview', $announcement) }}" class="underline">معاينة</a>

                        @can('announcements.edit')
                            <form method="post" action="{{ route('admin.guidance.announcements.duplicate', $announcement) }}">
                                @csrf
                                <button class="underline">نسخة جديدة</button>
                            </form>
                        @endcan

                        @can('announcements.archive')
                            @if ($announcement->status !== 'archived')
                                <form method="post" action="{{ route('admin.guidance.announcements.archive', $announcement) }}">
                                    @csrf
                                    <button class="underline">أرشفة</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $announcements->links() }}</div>
    @endif

    @can('announcements.create')
        <x-modal id="announcement-form" title="منشور جديد">
            <form method="post" action="{{ route('admin.guidance.announcements.store') }}" class="space-y-3">
                @csrf

                <x-form.input name="title" label="العنوان" required />

                <label class="block">
                    <span class="block text-sm mb-1">النصّ</span>
                    <textarea name="body" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input name="cta_label" label="نصّ زرّ CTA" />
                    <x-form.input name="cta_url" label="رابط الزرّ (Deep link)" />
                </div>

                {{-- استهداف بشرائح (12.6-أ) --}}
                <fieldset class="card p-3">
                    <legend class="text-sm px-1">الجمهور</legend>
                    <label class="block text-sm">
                        الشريحة
                        <select name="audience_type" data-audience class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="all">الكلّ</option>
                            <option value="role">حسب الدور</option>
                            <option value="course">حسب التدريب</option>
                            <option value="path">حسب المسار</option>
                            <option value="user">أشخاص بعينهم</option>
                            {{-- ⭐ شريحة محفوظة بدل إعادة بناء الفلاتر (12.6-أ · 12.13) --}}
                            <option value="segment">شريحة محفوظة</option>
                        </select>
                    </label>

                    <div class="mt-2 hidden" data-audience-panel="segment">
                        <select name="audience_ids[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($audiences['segments'] as $segment)
                                <option value="{{ $segment->id }}">
                                    {{ $segment->name }} — {{ \App\Services\Admin\AudienceSegments::types()[$segment->segment_type] ?? $segment->segment_type }}
                                    ({{ number_format((int) $segment->size) }})
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ setting('announcements.audience.segment_hint', 'الشريحة بتتحلّ لأعضائها على السيرفر لحظة الإرسال — مش لحظة الحفظ.') }}
                        </p>
                    </div>
                    @if ($audiences['segments']->isEmpty())
                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                            <a href="{{ route('admin.users.segments') }}" class="underline">{{ setting('announcements.audience.segments_empty', 'مفيش شرائح محفوظة لسّه — ابنِ واحدة') }}</a>
                        </p>
                    @endif

                    <div class="mt-2 hidden" data-audience-panel="role">
                        <select name="audience_keys[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($audiences['roles'] as $role)
                                <option value="{{ $role->key }}">{{ $role->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-2 hidden" data-audience-panel="course">
                        <select name="audience_ids[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($audiences['courses'] as $course)
                                <option value="{{ $course->id }}">{{ $course->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-2 hidden" data-audience-panel="path">
                        <select name="audience_ids[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($audiences['paths'] as $path)
                                <option value="{{ $path->id }}">{{ $path->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>
                </fieldset>

                {{-- السلوك: التفاعل والإقرار بـXP بسقف مرّة لكلّ منشور (12.6-أ) --}}
                <fieldset class="card p-3 space-y-2 text-sm">
                    <legend class="text-sm px-1">السلوك</legend>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="reactions_enabled" value="1"> اسمح بالتفاعل (إيموجي)
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="requires_acknowledge" value="1"> إلزام إقرار «قرأتُ وفهمت»
                    </label>
                    <div class="grid md:grid-cols-2 gap-3">
                        <x-form.input name="acknowledge_xp" label="XP الإقرار (مرّة واحدة لكلّ منشور)" type="number"
                                      :value="setting('announcements.acknowledge.default_xp', 0)" />
                        <x-form.input name="acknowledge_tickets" label="تذاكر الإقرار" type="number"
                                      :value="setting('announcements.acknowledge.default_tickets', 0)" />
                    </div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_pinned" value="1"> ثبّته أعلى القناة
                    </label>
                </fieldset>

                {{-- ⭐ القنوات الموحّدة من مكان واحد (12.6-أ): تاب · Toast/إشعار · بريد،
                     وكلّ قناة مستقلّة — تقدر تبعت بالبريد وحده بلا ما يظهر في الفيد --}}
                <fieldset class="card p-3 space-y-2 text-sm">
                    <legend class="text-sm px-1 flex items-center gap-1">
                        <x-icon name="announcement" size="16" /> القنوات
                    </legend>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="show_in_feed" value="1"
                               @checked(setting('announcements.channels.feed_default_on', true))>
                        {{ $channels['feed'] ?? 'تاب التعليمات' }}
                    </label>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="push_to_notifications" value="1">
                        {{ $channels['push'] ?? 'إشعار / Toast' }}
                    </label>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="email_enabled" value="1">
                        <span class="flex items-center gap-1"><x-icon name="envelope" size="16" /> {{ $channels['email'] ?? 'بريد' }}</span>
                    </label>

                    <p class="text-xs" style="color: var(--text-muted)">
                        {{ setting('announcements.email.editor_hint', 'البريد بيروح لمن بريده موثَّق ومفعّل القناة بس — والزيادة بتتأجّل احترامًا لحدّ الهدوء.') }}
                    </p>
                </fieldset>

                {{-- ⭐ استطلاع داخل المنشور: عامّ النتيجة أو مخفيّها (12.6-أ) —
                     وبناؤه صلاحيّة مستقلّة، ومن لا يملكها لا يرى الحقول أصلًا (2.15-أ-7) --}}
                @can('announcement_polls.create')
                <details class="card p-3">
                    <summary class="text-sm cursor-pointer">استطلاع داخل المنشور (اختياريّ)</summary>
                    <div class="mt-3 space-y-2">
                        <x-form.input name="poll_question" label="سؤال الاستطلاع" />

                        @for ($i = 0; $i < (int) setting('announcements.poll.max_options', 6); $i++)
                            <label class="block">
                                <span class="block text-sm mb-1" for="poll-option-{{ $i }}">خيار {{ $i + 1 }}</span>
                                <input type="text" name="poll_options[]" id="poll-option-{{ $i }}"
                                       value="{{ old('poll_options.'.$i) }}" maxlength="120"
                                       class="w-full rounded-xl px-3 py-2 text-sm"
                                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            </label>
                        @endfor

                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="poll_results_public" value="1"
                                   @checked(setting('announcements.poll.results_public_default', false))>
                            النتيجة عامّة يشوفها الكلّ
                        </label>
                        <p class="text-xs" style="color: var(--text-muted)">
                            لو سِبتها مقفولة، النتيجة **مش هتوصل متصفّح المستخدم أصلًا** لحدّ ما الاستطلاع يقفل — إخفاء حقيقيّ لا شكليّ.
                        </p>

                        <x-form.input name="poll_closes_at" label="يقفل الاستطلاع في" type="datetime-local" />
                    </div>
                </details>
                @endcan

                {{-- ⭐ جدولة متكرّرة + سلسلة Onboarding متدرّجة (12.6-أ) --}}
                <details class="card p-3">
                    <summary class="text-sm cursor-pointer">تكرار وسلسلة تعريف (اختياريّ)</summary>
                    <div class="mt-3 grid md:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="block text-sm mb-1">التكرار</span>
                            <select name="recurrence" class="w-full rounded-xl px-3 py-2 text-sm"
                                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                <option value="">بلا تكرار</option>
                                @foreach ($frequencies as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <x-form.input name="recurrence_until" label="يتكرّر حتّى" type="datetime-local" />
                        <x-form.input name="onboarding_step" label="ترتيبه في سلسلة الـOnboarding" type="number" />
                        <x-form.input name="onboarding_delay_days" label="يظهر بعد كام يوم من التسجيل" type="number" value="0" />
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        قالب التكرار نفسه ما بيتبعتش — كلّ دورة بتطلع كمنشور جديد، فالقراءة والإقرار يتجدّدوا.
                        وخطوة السلسلة ما بتظهرش غير لمّا اللي قبلها تتقري.
                    </p>
                </details>

                {{-- التخصيص الديناميكيّ: وسوم تُكتَب في النصّ (12.6-أ) --}}
                <div class="text-xs card p-3" style="color: var(--text-muted)">
                    وسوم التخصيص:
                    @foreach ($tokens as $token => $meaning)
                        <code>{{ $token }}</code>@if (! $loop->last) · @endif
                    @endforeach
                </div>

                <div class="grid md:grid-cols-3 gap-3">
                    <label class="block">
                        <span class="block text-sm mb-1">الحالة</span>
                        <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-form.input name="scheduled_at" label="ينشر في" type="datetime-local" />
                    <x-form.input name="expires_at" label="يُؤرشَف في" type="datetime-local" />
                </div>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">حفظ المنشور</button>
            </form>
        </x-modal>
    @endcan

    @include('admin.courses.partials.toast')

    @push('scripts')
        <script>
            /* شرائح الجمهور: نعرض حقل الشريحة المختارة فقط (2.15 — إخفاء التعقيد) */
            const audience = document.querySelector('[data-audience]');
            audience?.addEventListener('change', () => {
                document.querySelectorAll('[data-audience-panel]').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.audiencePanel !== audience.value);
                });
            });
        </script>
    @endpush
@endsection

@section('mobile_action')
    @can('announcements.create')
        <button type="button" data-modal-open="announcement-form"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">+ منشور</button>
    @endcan
@endsection
