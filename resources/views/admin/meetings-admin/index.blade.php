@extends('layouts.admin')

@section('title', setting('admin.meetings_admin.index.ajtmaaat_alttwa', 'اجتماعات التطوّع'))

@php
    /**
     * مرآة اجتماعات التطوّع في لوحة الإدارة (24.2-أوّلًا).
     *
     * سؤال واحد للشاشة: «إيه حال الاجتماعات دلوقتي — نوافذ حضور مفتوحة ولّا
     * محاضر ناقصة؟». والفعل الرئيسيّ من اللوحة هو **إنهاء الاجتماع** لأنّه ما
     * يفتح نافذة الحضور المغذّية لدرجة الالتزام.
     */
    $u = auth()->user();
    $canEnd = $u?->can('meetings.manage') || $u?->can('meetings.edit');
    $canGrant = $u?->can('meeting_attendance.manage') || $u?->can('meeting_attendance.edit');
    $canExport = $u?->can('meeting_attendance.export') || $u?->can('meetings.export');
    $canSettings = $u?->can('meetings.manage');
@endphp

@section('content')
    <x-page-header :title="setting('admin.meetings_admin.index.ajtmaaat_alttwa', 'اجتماعات التطوّع')"
                   :subtitle="setting('admin.meetings_admin.index.nzra_ardya_ala_alaqsam_klha_alajtmaaat_tdar', 'نظرة عرضيّة على الأقسام كلّها — الاجتماعات تُدار من لوحة التطوّع، وهنا تُراجَع وتُنهى وتُصدَّر.')"
                   :breadcrumbs="[['label' => setting('admin.meetings_admin.index.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')], ['label' => setting('admin.meetings_admin.index.ajtmaaat_alttwa', 'اجتماعات التطوّع')]]">
        <x-slot:action>
            @if ($canExport)
                <a href="{{ route('admin.meetings.export', request()->query()) }}"
                   class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.meetings_admin.index.tsdyr_alhdwr', 'تصدير الحضور') }}</a>
            @endif
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

    @if ($meetings->isEmpty())
        {{-- تمييز «مافيش اجتماعات أصلًا» عن «الفلتر ما طابقش حاجة» (24.2) —
             نطاق الصلاحيّة نفسه ليس فلترًا يختاره المستخدم فلا يُحتسَب هنا. --}}
        <x-empty :message="setting('admin_meetings.empty_text', 'مافيش اجتماعات في النطاق ده.')"
                 :filtered="$filters['q'] !== '' || $filters['entity'] !== '' || $filters['status'] !== '' || $filters['window'] !== '' || $filters['no_minutes'] !== '' || $filters['from'] !== '' || $filters['to'] !== ''" />
    @else
        <div class="card p-0 overflow-hidden hidden md:block">
            <table class="w-full text-sm"
                   {{-- حدّ الأعمدة الافتراضيّ من الإعدادات، و«وضع متقدّم» يرفعه (2.15-أ-5) --}}
                   @unless (advanced_mode()) data-columns-cap="{{ view_mode()->defaultColumns() }}" @endunless>
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.alajtmaa', 'الاجتماع') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.alntaq', 'النطاق') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.almwad', 'الموعد') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.alhdwr', 'الحضور') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.index.nafdha_altsjyl', 'نافذة التسجيل') }}</th>
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
                            <td class="px-4 py-3">
                                <x-state-badge :state="match ($meeting->status) { 'ended' => blank($meeting->minutes) ? 'warn' : 'ok', 'running' => 'warn', default => 'idle' }"
                                               :label="blank($meeting->minutes) && $meeting->status === 'ended' ? setting('admin.meetings_admin.index.bantzar_almhdr', 'بانتظار المحضر') : ($statuses[$meeting->status] ?? $meeting->status)" />
                            </td>
                            <td class="px-4 py-3">
                                @include('admin.meetings-admin.partials.row-actions', ['meeting' => $meeting])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 md:hidden">
            @foreach ($meetings as $meeting)
                <article class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <a class="text-sm font-semibold hover:underline" href="{{ route('admin.meetings.show', $meeting) }}">{{ $meeting->title }}</a>
                        <x-state-badge :state="match ($meeting->status) { 'ended' => blank($meeting->minutes) ? 'warn' : 'ok', 'running' => 'warn', default => 'idle' }"
                                       :label="$statuses[$meeting->status] ?? $meeting->status" />
                    </div>
                    <dl class="mt-2 text-xs space-y-1" style="color: var(--text-muted)">
                        <div><dt class="inline">{{ setting('admin.meetings_admin.index.alntaq_2', 'النطاق:') }}</dt> <dd class="inline">{{ $meeting->entity?->name_ar ?? setting('admin.meetings_admin.index.kl_almttwayn', 'كلّ المتطوّعين') }}</dd></div>
                        <div><dt class="inline">{{ setting('admin.meetings_admin.index.almwad_2', 'الموعد:') }}</dt> <dd class="inline">{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</dd></div>
                        <div><dt class="inline">{{ setting('admin.meetings_admin.index.alhdwr_2', 'الحضور:') }}</dt> <dd class="inline">{{ $counts[$meeting->id]['registered'] ?? 0 }} / {{ $counts[$meeting->id]['invited'] ?? 0 }}</dd></div>
                    </dl>
                    <div class="mt-3 pt-3" style="border-top: 1px solid var(--border)">
                        @include('admin.meetings-admin.partials.row-actions', ['meeting' => $meeting])
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
            'blockTitle' => setting('admin.meetings_admin.index.iadadat_mraa_alajtmaaat', 'إعدادات مرآة الاجتماعات'),
        ])
    @endif
@endsection

@push('modals')
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

            const bind = (selector, formSelector, modalId) => {
                const form = document.querySelector(formSelector);
                document.querySelectorAll(selector).forEach((btn) => {
                    btn.addEventListener('click', () => {
                        if (!form) return;
                        form.action = btn.dataset.action;
                        open(modalId);
                    });
                });
            };

            bind('[data-meeting-end]', '[data-end-form]', 'end-modal');
            bind('[data-meeting-grant]', '[data-grant-form]', 'grant-modal');
        })();
    </script>
@endpush
