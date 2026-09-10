@extends('layouts.admin')

@section('title', setting('events.registrations.page_title', 'المسجّلون والحضور'))

@php
    /**
     * 🖥️ **المسجّلون والحضور** — الشاشة الجامعة (12.11 · 24.3).
     *
     * سؤال واحد للشاشة (2.15-أ-1): «مين سجّل، ومين حضر فعلًا؟»
     *
     * ⛔ **ولا نصّ محروق هنا** (2.13): كلّ كلمةٍ تُقرأ من `setting()` بقيمةٍ
     * افتراضيّة، فالمالك يعدّلها من لوحته ولا يفتح ملفّ قالب.
     * و**المحظور يُخفى لا يُعطَّل** (2.15-أ-7): كلّ زرٍّ خلف `@can` صلاحيّته.
     */
    $u = auth()->user();
    $canScan = $u?->can('event_attendance.create');
    $canToggle = $u?->can('event_attendance.edit');
    $canExport = $u?->can('event_registrations.export');
    $canNotify = $u?->can('events.edit');
    $canGrant = $u?->can('manual_rewards.create');

    $attendedLabel = setting('events.registrations.attended_label', 'حضر');
    $absentLabel = setting('events.registrations.absent_label', 'غاب');
    $dateFormat = setting('events.registrations.date_format', 'Y-m-d · H:i');
    $allWord = setting('events.registrations.all_word', 'الكلّ');
    $dash = setting('events.registrations.dash', '—');
@endphp

@section('content')
    {{-- الهيدر: العنوان + الفعل الرئيسيّ الواحد، والباقي أزرارٌ ثانويّة (2.15-أ-2) --}}
    <x-page-header
        :title="$event
            ? $event->title_ar
            : setting('events.registrations.page_title', 'المسجّلون والحضور')"
        :subtitle="$event
            ? $event->starts_at?->format($dateFormat)
            : setting('events.registrations.page_subtitle', 'مين سجّل ومين حضر فعلًا — عبر كلّ الفعاليّات.')"
        :breadcrumbs="[
            ['label' => setting('events.registrations.crumb_admin', 'لوحة الإدارة'), 'url' => route('admin.dashboard')],
            ['label' => setting('events.registrations.crumb_events', 'الفعاليّات'), 'url' => route('admin.events.index')],
            ['label' => setting('events.registrations.page_title', 'المسجّلون والحضور')],
        ]">
        @if ($canScan)
            <x-slot:action>
                <button type="button" data-modal-open="qr-scan-modal"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('events.registrations.scan_button', 'مسح QR للتشيك-إن') }}
                </button>
            </x-slot:action>
        @endif
    </x-page-header>

    {{-- العدّادات الثلاثة المنصوصة: مسجّل · حاضر · غائب (12.11) — وهي دون سقف الأربعة (2.15-أ-3) --}}
    <div class="grid grid-cols-3 gap-3 mb-4">
        <x-kpi :label="setting('events.registrations.kpi_registered', 'مسجّل')" :value="$counts['registered']" icon="edit" />
        <x-kpi :label="setting('events.registrations.kpi_attended', 'حاضر')" :value="$counts['attended']" icon="check" />
        <x-kpi :label="setting('events.registrations.kpi_absent', 'غائب')" :value="$counts['absent']" icon="clock" />
    </div>

    {{-- ثلاثة فلاتر ظاهرة + بحث، و«وقت التشيك-إن» مطويّ (2.15-أ-4) --}}
    <x-filters :action="route('admin.events.registrations.index')" screen="admin.events.registrations">
        <label class="text-sm grow min-w-40">{{ setting('events.registrations.filter_search', 'بحث بالاسم أو الكود') }}
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-sm">{{ setting('events.registrations.filter_event', 'الفعاليّة') }}
            <select name="event_id" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ $allWord }}</option>
                @foreach ($events as $row)
                    <option value="{{ $row->id }}" @selected($filters['event_id'] === (int) $row->id)>{{ $row->title_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">{{ setting('events.registrations.filter_attendance', 'حالة الحضور') }}
            <select name="attended" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ $allWord }}</option>
                <option value="yes" @selected($filters['attended'] === 'yes')>{{ $attendedLabel }}</option>
                <option value="no" @selected($filters['attended'] === 'no')>{{ $absentLabel }}</option>
            </select>
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            {{ setting('events.registrations.filter_apply', 'فلترة') }}
        </button>

        <x-slot:advanced>
            <label class="text-sm">{{ setting('events.registrations.filter_mode', 'نمط الحضور') }}
                <select name="attend_mode" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ $allWord }}</option>
                    <option value="offline" @selected($filters['attend_mode'] === 'offline')>{{ setting('events.registrations.mode_offline', 'حضوريّ') }}</option>
                    <option value="online" @selected($filters['attend_mode'] === 'online')>{{ setting('events.registrations.mode_online', 'أونلاين') }}</option>
                </select>
            </label>

            <label class="text-sm">{{ setting('events.registrations.filter_checkin', 'وقت التشيك-إن') }}
                <select name="checked_in" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ $allWord }}</option>
                    <option value="today" @selected($filters['checked_in'] === 'today')>{{ setting('events.registrations.range_today', 'آخر يوم') }}</option>
                    <option value="week" @selected($filters['checked_in'] === 'week')>{{ setting('events.registrations.range_week', 'آخر أسبوع') }}</option>
                    <option value="month" @selected($filters['checked_in'] === 'month')>{{ setting('events.registrations.range_month', 'آخر شهر') }}</option>
                </select>
            </label>
        </x-slot:advanced>
    </x-filters>

    {{-- أفعال الشاشة الثانويّة: تصدير CSV · إشعار المسجّلين (12.11 · 24.3) --}}
    <div class="flex flex-wrap gap-2 mb-4">
        @if ($canExport)
            <a href="{{ route('admin.events.registrations.export', request()->query()) }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                {{ setting('events.registrations.export_button', 'تصدير CSV') }}
            </a>
        @endif

        @if ($canNotify && $event)
            <button type="button" data-modal-open="notify-modal"
                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                {{ setting('events.registrations.notify_button', 'إشعار المسجّلين') }}
            </button>
        @endif

        @if ($canScan && $event)
            <button type="button" data-modal-open="manual-checkin-modal"
                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                {{ setting('events.registrations.manual_button', 'تشيك-إن يدويّ') }}
            </button>
        @endif
    </div>

    @if ($registrations->isEmpty())
        {{-- الحالة الفارغة: سطر واحد + زرّ واحد (2.15-د) — تشجّع ولا تعاتب (2.17-ج).
             وتمييز «لا مسجّلين أصلًا» عن «الفلتر ما طابقش حاجة» (24.2). --}}
        <x-empty :message="setting('events.registrations.empty_text', 'لا مسجّلين بعد — شارك رابط الفعاليّة.')"
                 :action="setting('events.registrations.empty_action', 'روح للفعاليّات')"
                 :href="route('admin.events.index')"
                 :filtered="$filters['q'] !== '' || $filters['event_id'] !== 0 || $filters['attended'] !== '' || $filters['attend_mode'] !== '' || $filters['checked_in'] !== ''" />
    @else
        {{-- الديسكتوب: جدول بسبعة أعمدة (2.15-أ-5) --}}
        {{--
          `min-w-0` على الحاويتين شرطُ عمل التمرير الداخليّ (2.15-ج): عنصر
          الشبكة/الـFlex افتراضيّه `min-width: auto` فلا يصغر تحت مقاس محتواه،
          فيمدّ الجدولُ الصندوقَ فيمدّ الصفحة ويبقى التمرير حِلْيةً لا تعمل.
        --}}
        <div class="card p-0 overflow-hidden hidden md:block min-w-0">
            <div class="overflow-x-auto min-w-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background: var(--surface-sunken)">
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('events.registrations.col_user', 'المستخدم') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('events.registrations.col_code', 'الكود') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('events.registrations.col_event', 'الفعاليّة') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('events.registrations.col_mode', 'نمط الحضور') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('events.registrations.col_state', 'حالة الحضور') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('events.registrations.col_checkin', 'وقت التشيك-إن') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('events.registrations.col_tier', 'الدرجة المستحقّة') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">⋯</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($registrations as $registration)
                            @php $tier = $registration->event ? $attendance->currentTier($registration->event) : null; @endphp
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-2">
                                        <x-avatar :user="$registration->user" size="8" />
                                        <span>{{ $registration->user?->name ?? $dash }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $registration->user?->code ?? $dash }}</td>
                                <td class="px-4 py-3">{{ $registration->event?->title_ar ?? $dash }}</td>
                                <td class="px-4 py-3 text-xs">
                                    {{ $registration->attend_mode
                                        ? setting('events.registrations.mode_'.$registration->attend_mode, $registration->attend_mode)
                                        : $dash }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-state-badge :state="$registration->attended ? 'ok' : 'idle'"
                                                   :label="$registration->attended ? $attendedLabel : $absentLabel" />
                                </td>
                                <td class="px-4 py-3 text-xs">{{ $registration->attended_at?->format($dateFormat) ?? $dash }}</td>
                                <td class="px-4 py-3 text-xs">
                                    {{ $tier ? $tier['xp'].' XP · '.$tier['tickets'] : $dash }}
                                </td>
                                <td class="px-4 py-3">
                                    @include('admin.events.partials.registration-actions', [
                                        'registration' => $registration,
                                        'canToggle' => $canToggle,
                                        'canGrant' => $canGrant,
                                        'attendedLabel' => $attendedLabel,
                                        'absentLabel' => $absentLabel,
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- الموبايل: كروت رأسيّة بأهمّ الحقول — بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="md:hidden space-y-3">
            @foreach ($registrations as $registration)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-semibold truncate">{{ $registration->user?->name ?? $dash }}</div>
                            <div class="text-xs font-mono" style="color: var(--text-muted)">{{ $registration->user?->code ?? $dash }}</div>
                        </div>
                        <x-state-badge :state="$registration->attended ? 'ok' : 'idle'"
                                       :label="$registration->attended ? $attendedLabel : $absentLabel" />
                    </div>

                    <div class="text-xs mt-2 break-words" style="color: var(--text-muted)">
                        {{ $registration->event?->title_ar ?? $dash }}
                        @if ($registration->attended_at)
                            · {{ $registration->attended_at->format($dateFormat) }}
                        @endif
                    </div>

                    <div class="mt-3">
                        @include('admin.events.partials.registration-actions', [
                            'registration' => $registration,
                            'canToggle' => $canToggle,
                            'canGrant' => $canGrant,
                            'attendedLabel' => $attendedLabel,
                            'absentLabel' => $absentLabel,
                        ])
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- [مسح QR] بوب-أب: نتيجة فوريّة صحيح/خاطئ/مستخدَم من قبل (24.3) --}}
    @if ($canScan)
        <x-modal id="qr-scan-modal" :title="setting('events.registrations.scan_button', 'مسح QR للتشيك-إن')">
            <form method="post" action="{{ route('admin.events.scan.submit') }}" class="space-y-3">
                @csrf
                <p class="text-xs" style="color: var(--text-muted)">
                    {{ setting('events.registrations.scan_hint', 'صوّر رمز المتدرّب بكاميرا موبايلك — الرابط بيفتح ويسجّل الحضور فورًا. ولو الكاميرا مش شغّالة، الصق الرمز هنا.') }}
                </p>

                <label class="block text-sm font-semibold">
                    {{ setting('events.registrations.scan_token_label', 'رمز التشيك-إن') }}
                    <input type="text" name="token" required maxlength="120"
                           class="w-full rounded-xl px-3 py-2 text-sm mt-1 font-mono"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('events.registrations.scan_submit', 'سجّل الحضور') }}
                </button>
            </form>
        </x-modal>
    @endif

    {{-- [تشيك-إن يدويّ] بكود المستخدم + كود الحضور (24.3) --}}
    @if ($canScan && $event)
        <x-modal id="manual-checkin-modal" :title="setting('events.registrations.manual_button', 'تشيك-إن يدويّ')">
            <form method="post" action="{{ route('admin.events.check-in', $event) }}" class="space-y-3">
                @csrf
                <label class="block text-sm font-semibold">
                    {{ setting('events.registrations.manual_user_code', 'كود المستخدم') }}
                    <input type="text" name="code" required maxlength="32"
                           class="w-full rounded-xl px-3 py-2 text-sm mt-1 font-mono"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <label class="block text-sm font-semibold">
                    {{ setting('events.registrations.manual_attendance_code', 'كود الحضور') }}
                    <input type="text" name="attendance_code" required maxlength="32"
                           class="w-full rounded-xl px-3 py-2 text-sm mt-1 font-mono"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('events.registrations.scan_submit', 'سجّل الحضور') }}
                </button>
            </form>
        </x-modal>
    @endif

    {{-- [إشعار المسجّلين] نصّ + قناة + الآن/مجدول (24.3) --}}
    @if ($canNotify && $event)
        <x-modal id="notify-modal" :title="setting('events.registrations.notify_button', 'إشعار المسجّلين')">
            <form method="post" action="{{ route('admin.events.registrations.notify', $event) }}" class="space-y-3">
                @csrf
                <label class="block text-sm font-semibold">
                    {{ setting('events.registrations.notify_body_label', 'نصّ الإشعار') }}
                    <textarea name="body" rows="4" required
                              maxlength="{{ (int) setting('events.notice.max_chars', 2000) }}"
                              placeholder="{{ setting('events.registrations.notify_placeholder', 'مثال: اترفع رابط التسجيل — تقدر تتفرّج عليه من صفحة الفعاليّة.') }}"
                              class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="block text-sm font-semibold">
                        {{ setting('events.registrations.notify_channel_label', 'القناة') }}
                        <select name="channel" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="bell">{{ setting('events.registrations.channel_bell', 'الجرس') }}</option>
                            <option value="email">{{ setting('events.registrations.channel_email', 'البريد') }}</option>
                        </select>
                    </label>

                    <label class="block text-sm font-semibold">
                        {{ setting('events.registrations.notify_when_label', 'الموعد (سيبه فاضي = دلوقتي)') }}
                        <input type="datetime-local" name="send_at"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('events.registrations.notify_submit', 'ابعت') }}
                </button>
            </form>
        </x-modal>
    @endif
@endsection

@section('mobile_action')
    @if (auth()->user()?->can('event_attendance.create'))
        <button type="button" data-modal-open="qr-scan-modal"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">
            {{ setting('events.registrations.scan_button', 'مسح QR للتشيك-إن') }}
        </button>
    @endif
@endsection
