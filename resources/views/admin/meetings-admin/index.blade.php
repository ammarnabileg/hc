@extends('layouts.admin')

@section('title', setting('admin.meetings_admin.index.ajtmaaat_alttwa', 'اجتماعات التطوّع'))

@php
    /**
     * اجتماعات التطوّع في لوحة الإدارة (24.2-أوّلًا).
     *
     * ⭐ [2026-09-11] الشاشة كانت **مرآةً** تُراجِع وتُنهي وتُصدِّر، ونصّ 24.2
     * يصفها أوسع: **+ اجتماع** · **تبديل (تقويم/جدول)** · عمودا **المحضر
     * والمرفقات** و**التسجيل** · وإجراءات **إدارة الكود/الأسئلة** و**رفع
     * المحضر** و**تثبيت بوست** و**إلغاء بسبب**. فاتّسعت إلى النصّ.
     *
     * وكلّ فعلٍ منها يستدعي **خدمة لوحة التطوّع نفسها** (`MeetingManager` ·
     * `AttendanceService`) — فلا عقدَ ثانيًا لنفس الفعل، وما يُنشأ من هنا
     * هو حرفيًّا ما يُنشأ من هناك.
     */
    $u = auth()->user();
    $canCreate = $u?->can('meetings.create');
    $canEnd = $u?->can('meetings.manage') || $u?->can('meetings.edit');
    $canGrant = $u?->can('meeting_attendance.manage') || $u?->can('meeting_attendance.edit');
    $canExport = $u?->can('meeting_attendance.export') || $u?->can('meetings.export');
    $canSettings = $u?->can('meetings.manage');
    // رابط التبديل يحافظ على الفلاتر الحاليّة — تبديلُ العرض ليس إعادةَ فلترة
    $viewQuery = fn (string $mode, ?string $month = null) => route('admin.meetings.index', array_filter(
        array_merge(request()->query(), ['view' => $mode, 'month' => $month ?? request()->string('month')->toString()]),
        fn ($value) => $value !== '' && $value !== null,
    ));
@endphp

@section('content')
    <x-page-header :title="setting('admin.meetings_admin.index.ajtmaaat_alttwa', 'اجتماعات التطوّع')"
                   :subtitle="setting('admin.meetings_admin.index.nzra_ardya_ala_alaqsam_klha_anshy_alajtmaa', 'نظرة عرضيّة على الأقسام كلّها — أنشئ الاجتماع، وأدِر كوده وأسئلته، وارفع محضره وتسجيله، وثبّت بوسته، وأنهِه أو ألغِه بسبب.')"
                   :breadcrumbs="[['label' => setting('admin.meetings_admin.index.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')], ['label' => setting('admin.meetings_admin.index.ajtmaaat_alttwa', 'اجتماعات التطوّع')]]">
        <x-slot:action>
            <div class="flex flex-wrap items-center gap-2">
                @if ($canCreate)
                    <button type="button" data-modal-open="meeting-new"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.meetings_admin.index.ajtmaa_2', '+ اجتماع') }}</button>
                @endif

                @if ($canExport)
                    <a href="{{ route('admin.meetings.export', request()->query()) }}"
                       class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.meetings_admin.index.tsdyr_alhdwr', 'تصدير الحضور') }}</a>
                @endif
            </div>
        </x-slot:action>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('admin.meetings_admin.index.ajtmaaat_almda', 'اجتماعات المدى')" :value="$stats['total']" icon="calendar" />
        <x-kpi :label="setting('admin.meetings_admin.index.nwafdh_hdwr_mftwha', 'نوافذ حضور مفتوحة')" :value="$stats['open_windows']" icon="clock" />
        <x-kpi :label="setting('admin.meetings_admin.index.mnth_bla_mhdr', 'منتهٍ بلا محضر')" :value="$stats['without_minutes']" icon="edit"
               :state="$stats['without_minutes'] > 0 ? 'warn' : null" />
        <x-kpi :label="setting('admin.meetings_admin.index.nsba_alhdwr', 'نسبة الحضور')" :value="$stats['attendance_rate'].'%'" icon="people" />
    </div>

    <x-filters :action="route('admin.meetings.index')">
        <input type="hidden" name="view" value="{{ $view }}">

        <label class="text-sm grow min-w-40">{{ setting('admin.meetings_admin.index.bhth_balanwan', 'بحث بالعنوان') }}
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-sm">{{ setting('admin.meetings_admin.index.alntaq', 'النطاق') }}
            <select name="entity" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.meetings_admin.index.alkl', 'الكلّ') }}</option>
                @foreach ($entities as $entity)
                    <option value="{{ $entity->id }}" @selected($filters['entity'] === (string) $entity->id)>{{ $entity->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">{{ setting('admin.meetings_admin.index.alhala', 'الحالة') }}
            <select name="status" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.meetings_admin.index.alkl', 'الكلّ') }}</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.meetings_admin.index.fltra', 'فلترة') }}</button>

        <x-slot:advanced>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="window" value="1" @checked($filters['window'] === '1')>
                <span>{{ setting('admin.meetings_admin.index.nafdha_alhdwr_mftwha', 'نافذة الحضور مفتوحة') }}</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="no_minutes" value="1" @checked($filters['no_minutes'] === '1')>
                <span>{{ setting('admin.meetings_admin.index.bla_mhdr', 'بلا محضر') }}</span>
            </label>
            <label class="text-sm">{{ setting('admin.meetings_admin.index.mn_tarykh', 'من تاريخ') }}
                <input type="date" name="from" value="{{ $filters['from'] }}"
                       class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="text-sm">{{ setting('admin.meetings_admin.index.ila_tarykh', 'إلى تاريخ') }}
                <input type="date" name="to" value="{{ $filters['to'] }}"
                       class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
        </x-slot:advanced>
    </x-filters>

    {{--
        ⭐ [2026-09-11] «تبديل (تقويم / جدول)» (24.2-أوّلًا). التقويم مرسوم
        بأيدينا بشبكة شهرٍ بلا أيّ مكتبة خارجيّة — نفس اصطلاح `view=` وشبكة
        `admin/events/index.blade.php` المبنيّة لـ12.11، فلا widget ثانٍ.
    --}}
    <div class="flex items-center gap-2 my-4">
        <a href="{{ $viewQuery('table') }}" class="rounded-xl px-4 py-2 text-sm font-semibold"
           style="background: {{ $view === 'table' ? 'var(--color-brand-500)' : 'var(--surface-raised)' }}; color: {{ $view === 'table' ? '#04201c' : 'var(--text)' }}">{{ setting('admin.meetings_admin.index.jdwl', 'جدول') }}</a>
        <a href="{{ $viewQuery('calendar') }}" class="rounded-xl px-4 py-2 text-sm font-semibold"
           style="background: {{ $view === 'calendar' ? 'var(--color-brand-500)' : 'var(--surface-raised)' }}; color: {{ $view === 'calendar' ? '#04201c' : 'var(--text)' }}">{{ setting('admin.meetings_admin.index.tqwym', 'تقويم') }}</a>
    </div>

    @if ($view === 'calendar')
        <div class="card p-3 md:p-4 mb-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <a href="{{ $viewQuery('calendar', $month->subMonthNoOverflow()->format('Y-m')) }}"
                   class="rounded-lg px-3 py-1.5 text-sm" style="background: var(--surface-sunken)">‹ {{ setting('admin.meetings_admin.index.alshhr_alsabq', 'الشهر السابق') }}</a>
                <span class="font-semibold text-sm">{{ $month->translatedFormat('F Y') }}</span>
                <a href="{{ $viewQuery('calendar', $month->addMonthNoOverflow()->format('Y-m')) }}"
                   class="rounded-lg px-3 py-1.5 text-sm" style="background: var(--surface-sunken)">{{ setting('admin.meetings_admin.index.alshhr_altaly', 'الشهر التالي') }} ›</a>
            </div>

            {{-- شبكة الشهر تمرّر أفقيًّا داخل حاويتها وحدها لا الصفحة (2.15-ج) --}}
            <div class="min-w-0 overflow-x-auto">
                <div class="grid grid-cols-7 gap-1" style="min-width: 560px">
                    @foreach ([
                        setting('admin.meetings_admin.index.ahd', 'أحد'), setting('admin.meetings_admin.index.athnyn', 'إثنين'), setting('admin.meetings_admin.index.thlatha', 'ثلاثاء'),
                        setting('admin.meetings_admin.index.arbaa', 'أربعاء'), setting('admin.meetings_admin.index.khmys', 'خميس'), setting('admin.meetings_admin.index.jmaa', 'جمعة'), setting('admin.meetings_admin.index.sbt', 'سبت'),
                    ] as $dayName)
                        <div class="text-xs font-semibold text-center py-1" style="color: var(--text-muted)">{{ $dayName }}</div>
                    @endforeach

                    @for ($i = 0; $i < $month->startOfMonth()->dayOfWeek; $i++)
                        <div></div>
                    @endfor

                    @for ($day = 1; $day <= $month->daysInMonth; $day++)
                        <div class="rounded-lg p-1.5" style="background: var(--surface-sunken); min-height: 5.5rem">
                            <div class="text-xs tabular-nums" style="color: var(--text-muted)">{{ $day }}</div>
                            <div class="space-y-1 mt-1">
                                @foreach ($calendarMeetings->get((string) $day, collect()) as $item)
                                    <a href="{{ route('admin.meetings.show', $item) }}"
                                       class="block truncate rounded px-1.5 py-0.5 text-xs"
                                       style="background: {{ $item->status === 'cancelled' ? 'var(--color-state-danger)' : 'var(--color-brand-500)' }}; color: #04201c"
                                       title="{{ $item->title }} — {{ $item->scheduled_at?->format('H:i') }}">
                                        {{ $item->scheduled_at?->format('H:i') }} {{ $item->title }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            @if ($calendarMeetings->isEmpty())
                <p class="text-xs mt-3" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.index.mfysh_ajtmaaat_fy_alshhr_dh', 'مفيش اجتماعات في الشهر ده.') }}</p>
            @endif
        </div>
    @elseif ($meetings->isEmpty())
        {{-- تمييز «مافيش اجتماعات أصلًا» عن «الفلتر ما طابقش حاجة» (24.2) —
             نطاق الصلاحيّة نفسه ليس فلترًا يختاره المستخدم فلا يُحتسَب هنا. --}}
        <x-empty :message="setting('admin_meetings.empty_text', 'مافيش اجتماعات في النطاق ده.')"
                 :filtered="$filters['q'] !== '' || $filters['entity'] !== '' || $filters['status'] !== '' || $filters['window'] !== '' || $filters['no_minutes'] !== '' || $filters['from'] !== '' || $filters['to'] !== ''" />
    @else
        <div class="card p-0 overflow-hidden hidden md:block min-w-0">
            <div class="overflow-x-auto min-w-0">
            {{--
                ⭐ [2026-09-11] بلا `data-columns-cap` هنا عمدًا. حدّ الستّة أعمدة
                (2.15-أ-5) كان يخفي **العمود السابع** — وهو عمود **«إجراءات»**
                نفسه، أي كلّ أفعال الصفّ التي يوجبها 24.2-أوّلًا. وفعلٌ لا
                تصل إليه ليس فعلًا: 2.15-أ-7 يخفي **ما لا تملكه** لا ما تملكه.
                و24.2-أوّلًا يعدّد أعمدة هذه الشاشة صراحةً، وسابقتُها المباشرة
                `admin/events/index.blade.php` (12.11) جدولٌ معدَّدٌ في 24 كذلك
                وبلا حدّ. والعرض محفوظ: الجدول يمرّر أفقيًّا **داخل حاويته
                وحدها** لا الصفحة (2.15-ج)، والموبايل كروتٌ لا جدول.
            --}}
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.alajtmaa', 'الاجتماع') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.alntaq', 'النطاق') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.almwad', 'الموعد') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.alhdwr', 'الحضور') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.nafdha_altsjyl', 'نافذة التسجيل') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.almhdr_walmrfqat', 'المحضر والمرفقات') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.altsjyl', 'التسجيل') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.alhala', 'الحالة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">⋯</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($meetings as $meeting)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3">
                                <a class="hover:underline font-semibold" href="{{ route('admin.meetings.show', $meeting) }}">{{ $meeting->title }}</a>
                                <div class="text-xs" style="color: var(--text-muted)">{{ $meeting->owner?->name }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $meeting->entity?->name_ar ?? setting('admin.meetings_admin.index.kl_almttwayn', 'كلّ المتطوّعين') }}</td>
                            <td class="px-4 py-3 text-xs">{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-xs">
                                {{ $counts[$meeting->id]['registered'] ?? 0 }} / {{ $counts[$meeting->id]['invited'] ?? 0 }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if ($attendance->windowOpen($meeting))
                                    <x-state-badge :state="$attendance->tierState($meeting)"
                                                   :label="setting('admin.meetings_admin.index.tqfl', 'تقفل ').$meeting->attendance_closes_at?->diffForHumans()" />
                                @else
                                    <span style="color: var(--text-muted)">{{ setting('admin.meetings_admin.index.mqfwla', 'مقفولة') }}</span>
                                @endif
                            </td>
                            @include('admin.meetings-admin.partials.minutes-cells', [
                                'meeting' => $meeting,
                                'attachmentCount' => $attachmentCounts[$meeting->id] ?? 0,
                            ])
                            <td class="px-4 py-3">
                                <x-state-badge :state="match ($meeting->status) { 'cancelled' => 'danger', 'ended' => blank($meeting->minutes) ? 'warn' : 'ok', 'running' => 'warn', default => 'idle' }"
                                               :label="blank($meeting->minutes) && $meeting->status === 'ended' ? setting('admin.meetings_admin.index.bantzar_almhdr', 'بانتظار المحضر') : ($statuses[$meeting->status] ?? $meeting->status)" />
                            </td>
                            <td class="px-4 py-3">
                                @include('admin.meetings-admin.partials.row-actions', ['meeting' => $meeting, 'posts' => $posts[$meeting->id] ?? collect()])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>

        <div class="grid gap-3 md:hidden">
            @foreach ($meetings as $meeting)
                <article class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <a class="text-sm font-semibold hover:underline" href="{{ route('admin.meetings.show', $meeting) }}">{{ $meeting->title }}</a>
                        <x-state-badge :state="match ($meeting->status) { 'cancelled' => 'danger', 'ended' => blank($meeting->minutes) ? 'warn' : 'ok', 'running' => 'warn', default => 'idle' }"
                                       :label="$statuses[$meeting->status] ?? $meeting->status" />
                    </div>
                    <dl class="mt-2 text-xs space-y-1" style="color: var(--text-muted)">
                        <div><dt class="inline">{{ setting('admin.meetings_admin.index.alntaq_2', 'النطاق:') }}</dt> <dd class="inline">{{ $meeting->entity?->name_ar ?? setting('admin.meetings_admin.index.kl_almttwayn', 'كلّ المتطوّعين') }}</dd></div>
                        <div><dt class="inline">{{ setting('admin.meetings_admin.index.almwad_2', 'الموعد:') }}</dt> <dd class="inline">{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</dd></div>
                        <div><dt class="inline">{{ setting('admin.meetings_admin.index.alhdwr_2', 'الحضور:') }}</dt> <dd class="inline">{{ $counts[$meeting->id]['registered'] ?? 0 }} / {{ $counts[$meeting->id]['invited'] ?? 0 }}</dd></div>
                        <div><dt class="inline">{{ setting('admin.meetings_admin.index.almhdr_walmrfqat_2', 'المحضر والمرفقات:') }}</dt>
                            <dd class="inline">{{ filled($meeting->minutes) ? setting('admin.meetings_admin.index.mrfwa', 'مرفوع') : setting('admin.meetings_admin.index.la_ywjd', 'لا يوجد') }}
                                · {{ $attachmentCounts[$meeting->id] ?? 0 }} {{ setting('admin.meetings_admin.index.mrfq', 'مرفقًا') }}</dd></div>
                        <div><dt class="inline">{{ setting('admin.meetings_admin.index.altsjyl_2', 'التسجيل:') }}</dt>
                            <dd class="inline">
                                @if (filled($meeting->recording_url))
                                    <a class="underline" href="{{ $meeting->recording_url }}" rel="noopener" target="_blank">{{ setting('admin.meetings_admin.index.fth_altsjyl', 'افتح التسجيل') }}</a>
                                @else
                                    {{ filled($meeting->external_link) ? setting('admin.meetings_admin.index.bla_tsjyl', 'بلا تسجيل') : setting('admin.meetings_admin.index.hdwry', 'حضوريّ') }}
                                @endif
                            </dd></div>
                    </dl>
                    <div class="mt-3 pt-3" style="border-top: 1px solid var(--border)">
                        @include('admin.meetings-admin.partials.row-actions', ['meeting' => $meeting, 'posts' => $posts[$meeting->id] ?? collect()])
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4">{{ $meetings->links() }}</div>
    @endif

    @if ($canSettings)
        @include('admin.screens24.settings', [
            'settings' => $settings,
            'saveRoute' => route('admin.meetings.settings'),
            'resetRoute' => route('admin.meetings.settings.reset'),
            'blockTitle' => setting('admin.meetings_admin.index.iadadat_shasha_alajtmaaat', 'إعدادات شاشة الاجتماعات'),
        ])
    @endif
@endsection

@push('modals')
    @if ($canCreate)
        @include('admin.meetings-admin.partials.create-modal', ['entities' => $entities])
    @endif

    @if ($canEnd)
        <x-modal id="end-modal" :title="setting('admin.meetings_admin.index.inha_alajtmaa_wfth_nafdha_alhdwr', 'إنهاء الاجتماع وفتح نافذة الحضور')">
            <form method="post" action="{{ route('admin.meetings.index') }}" data-end-form>
                @csrf
                <p class="text-sm mb-3" style="color: var(--text-muted)">
                    {{ setting('admin.meetings_admin.index.alinha_byfth_nafdha_tsjyl_alhdwr_waltsjyl', 'الإنهاء بيفتح نافذة تسجيل الحضور — والتسجيل خلال أوّل ساعات بيدّي درجة التزام أعلى.') }}
                </p>

                <label class="block text-sm font-semibold mb-1" for="end-hours">{{ setting('admin.meetings_admin.index.add_saaat_alnafdha', 'عدد ساعات النافذة') }}</label>
                <input type="number" name="window_hours" id="end-hours" min="1"
                       max="{{ $attendance->maxWindowHours() }}"
                       value="{{ $attendance->defaultWindowHours() }}" required
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="end-minutes">{{ setting('admin.meetings_admin.index.almhdr_akhtyary_alan_mtlwb_lltwthyq', 'المحضر (اختياريّ الآن، مطلوب للتوثيق)') }}</label>
                <textarea name="minutes" id="end-minutes" rows="4"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-4"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical"></textarea>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.meetings_admin.index.anh_alajtmaa', 'أنهِ الاجتماع') }}</button>
            </form>
        </x-modal>

        @include('admin.meetings-admin.partials.manage-modals')
    @endif

    @if ($canGrant)
        <x-modal id="grant-modal" :title="setting('admin.meetings_admin.index.mnh_hdwr_astthnayy', 'منح حضور استثنائيّ')">
            <form method="post" action="{{ route('admin.meetings.index') }}" data-grant-form>
                @csrf
                <label class="block text-sm font-semibold mb-1" for="grant-user">{{ setting('admin.meetings_admin.index.kwd_aladw_aw_rqmh', 'كود العضو أو رقمه') }}</label>
                <input type="number" name="user_id" id="grant-user" required min="1"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="grant-reason">{{ setting('admin.meetings_admin.index.sbb_almnh', 'سبب المنح') }}</label>
                <textarea name="reason" id="grant-reason" rows="2" required maxlength="500"
                          placeholder="{{ setting('admin.meetings_admin.index.mthal_anqtaa_alnt_athna_alajtmaa_athbt_hdwrh', 'مثال: انقطاع النت أثناء الاجتماع — أثبت حضوره بالتسجيل.') }}"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-4"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical"></textarea>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.meetings_admin.index.amnh_alhdwr', 'امنح الحضور') }}</button>
            </form>
        </x-modal>
    @endif
@endpush

@push('scripts')
    <script>
        (() => {
            const open = (id) => {
                const modal = document.getElementById(id);
                if (!modal) return;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            /*
             | بوب-أب واحد لكلّ فعل، ومسارُه يتبدّل بزرّ الصفّ — لا بوب-أب لكلّ
             | صفّ: صفحةٌ فيها عشرون اجتماعًا تصير مئةَ نسخةٍ من نفس الفورم.
             */
            const bind = (selector, formSelector, modalId, fill) => {
                const form = document.querySelector(formSelector);
                document.querySelectorAll(selector).forEach((btn) => {
                    btn.addEventListener('click', () => {
                        if (!form) return;
                        form.action = btn.dataset.action;
                        if (fill) fill(form, btn);
                        open(modalId);
                    });
                });
            };

            bind('[data-meeting-end]', '[data-end-form]', 'end-modal');
            bind('[data-meeting-grant]', '[data-grant-form]', 'grant-modal');
            bind('[data-meeting-questions]', '[data-questions-form]', 'questions-modal');
            bind('[data-meeting-cancel]', '[data-cancel-form]', 'cancel-modal');

            bind('[data-meeting-minutes]', '[data-minutes-form]', 'minutes-modal', (form, btn) => {
                form.querySelector('[name="minutes"]').value = btn.dataset.minutes || '';
                form.querySelector('[name="recording_url"]').value = btn.dataset.recording || '';
            });

            // خيارات «تثبيت بوست» تأتي من صفّها هي — فلا تُبنى قائمةٌ لكلّ اجتماع مسبقًا
            bind('[data-meeting-pin]', '[data-pin-form]', 'pin-modal', (form, btn) => {
                const select = form.querySelector('[name="post_id"]');
                select.innerHTML = '';
                JSON.parse(btn.dataset.posts || '[]').forEach((post) => {
                    const option = document.createElement('option');
                    option.value = post.id;
                    option.textContent = post.label;
                    select.appendChild(option);
                });
            });
        })();
    </script>
@endpush
