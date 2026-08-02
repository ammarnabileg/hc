@extends('layouts.admin')

@section('title', 'التقارير المجدولة')

@php
    /**
     * التقارير المجدولة (24.3-خامسًا).
     *
     * سؤال واحد للشاشة: «إيه التقارير اللي بتوصل لوحدها، ووصلت ولّا لأ؟»
     * 🔒 والتقرير الماليّ لا يظهر أصلًا في قائمة التقارير لغير مالك المنصّة.
     */
    $u = auth()->user();
    $canCreate = $u?->can('report_schedules.create');
    $canEdit = $u?->can('report_schedules.edit');
    $canRun = $u?->can('report_schedules.manage');
    $canDelete = $u?->can('report_schedules.delete');

    $days = [0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];
@endphp

@section('content')
    <x-page-header title="التقارير المجدولة"
                   subtitle="عرّف التقرير مرّة، ويوصل بالبريد كلّ مرّة — بلا ما حد يفتكر."
                   :breadcrumbs="[['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')], ['label' => 'التقارير المجدولة']]">
        @if ($canCreate)
            <x-slot:action>
                <button type="button" data-modal-open="schedule-modal" data-schedule-new
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ جدولة</button>
            </x-slot:action>
        @endif
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi label="كلّ الجدولات" :value="$stats['total']" icon="calendar" />
        <x-kpi label="نشطة" :value="$stats['active']" icon="check" />
        <x-kpi label="اتبعت النهارده" :value="$stats['sent_today']" icon="upload" />
        <x-kpi label="فشلت آخر مرّة" :value="$stats['failed']" icon="warning"
               :state="$stats['failed'] > 0 ? 'danger' : null" />
    </div>

    <x-filters :action="route('admin.report-schedules.index')">
        <label class="text-sm grow min-w-40">بحث بالاسم
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-sm">التقرير المصدر
            <select name="report" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($reports as $key => $label)
                    <option value="{{ $key }}" @selected($filters['tab'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">التكرار
            <select name="frequency" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($frequencies as $key => $label)
                    <option value="{{ $key }}" @selected($filters['frequency'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">فلترة</button>

        <x-slot:advanced>
            <label class="text-sm">الحالة
                <select name="status" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    <option value="active" @selected($filters['status'] === 'active')>نشطة</option>
                    <option value="paused" @selected($filters['status'] === 'paused')>موقوفة</option>
                </select>
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($schedules->isEmpty())
        <x-empty :message="setting('report_schedules.empty_text', 'مافيش تقارير مجدولة — ابعت تقريرك الأوّل تلقائيًّا.')" />
    @else
        <div class="card p-0 overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">الاسم</th>
                        <th class="text-start px-4 py-3 font-semibold">التقرير</th>
                        <th class="text-start px-4 py-3 font-semibold">التكرار</th>
                        <th class="text-start px-4 py-3 font-semibold">آخر إرسال</th>
                        <th class="text-start px-4 py-3 font-semibold">التالي</th>
                        <th class="text-start px-4 py-3 font-semibold">الحالة</th>
                        <th class="text-start px-4 py-3 font-semibold">⋯</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($schedules as $schedule)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3">
                                {{ $schedule->name }}
                                @if ($schedule->is_financial)
                                    <span class="text-xs" style="color: var(--color-state-honor)"><x-icon name="lock" size="16" /></span>
                                @endif
                                <div class="text-xs" style="color: var(--text-muted)">
                                    {{ strtoupper($schedule->format) }} · {{ count($schedule->recipient_emails ?? []) + count($schedule->recipient_role_ids ?? []) + count($schedule->recipient_user_ids ?? []) }} مستقبِل
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $reports[$schedule->report_tab] ?? $schedule->report_tab }}</td>
                            <td class="px-4 py-3 text-xs">
                                {{ $frequencies[$schedule->frequency] ?? $schedule->frequency }}
                                @if ($schedule->frequency === 'weekly')
                                    · {{ $days[$schedule->day_of_week] ?? '' }}
                                @elseif ($schedule->frequency === 'monthly')
                                    · يوم {{ $schedule->day_of_month }}
                                @endif
                                · {{ str_pad((string) $schedule->hour, 2, '0', STR_PAD_LEFT) }}:00
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if ($schedule->last_run_at)
                                    {{ $schedule->last_run_at->format('Y-m-d H:i') }}
                                    <div class="mt-1">
                                        <x-state-badge :state="match ($schedule->last_result) { 'sent' => 'ok', 'empty' => 'idle', default => 'danger' }"
                                                       :label="match ($schedule->last_result) { 'sent' => 'وصل', 'empty' => 'فاضي', default => 'فشل' }" />
                                    </div>
                                @else
                                    <span style="color: var(--text-muted)">لسّه</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $schedule->next_run_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <x-state-badge :state="$schedule->isActive() ? 'ok' : 'idle'"
                                               :label="$schedule->isActive() ? 'نشطة' : 'موقوفة'" />
                            </td>
                            <td class="px-4 py-3">
                                @include('admin.report-schedules.partials.row-actions', ['schedule' => $schedule])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 md:hidden">
            @foreach ($schedules as $schedule)
                <article class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold">{{ $schedule->name }}</p>
                        <x-state-badge :state="$schedule->isActive() ? 'ok' : 'idle'"
                                       :label="$schedule->isActive() ? 'نشطة' : 'موقوفة'" />
                    </div>
                    <dl class="mt-2 text-xs space-y-1" style="color: var(--text-muted)">
                        <div><dt class="inline">التقرير:</dt> <dd class="inline">{{ $reports[$schedule->report_tab] ?? $schedule->report_tab }}</dd></div>
                        <div><dt class="inline">التكرار:</dt> <dd class="inline">{{ $frequencies[$schedule->frequency] ?? $schedule->frequency }} · {{ str_pad((string) $schedule->hour, 2, '0', STR_PAD_LEFT) }}:00</dd></div>
                        <div><dt class="inline">التالي:</dt> <dd class="inline">{{ $schedule->next_run_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                    </dl>
                    <div class="mt-3 pt-3" style="border-top: 1px solid var(--border)">
                        @include('admin.report-schedules.partials.row-actions', ['schedule' => $schedule])
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4">{{ $schedules->links() }}</div>
    @endif

    @if ($canRun)
        @include('admin.screens24.settings', [
            'settings' => $settings,
            'saveRoute' => route('admin.report-schedules.settings'),
            'resetRoute' => route('admin.report-schedules.settings.reset'),
            'blockTitle' => 'إعدادات التقارير المجدولة',
        ])
    @endif
@endsection

@section('mobile_action')
    @if ($canCreate)
        <button type="button" data-modal-open="schedule-modal" data-schedule-new
                class="btn w-full rounded-xl px-4 py-3 text-sm font-bold"
                style="background: var(--color-brand-500); color: #04201c">+ جدولة</button>
    @endif
@endsection

@push('modals')
    @if ($canCreate || $canEdit)
        <x-modal id="schedule-modal" title="جدولة تقرير">
            <form method="post" action="{{ route('admin.report-schedules.store') }}" data-schedule-form>
                @csrf
                <input type="hidden" name="_method" value="POST" data-schedule-method>

                <label class="block text-sm font-semibold mb-1" for="s-name">اسم التقرير</label>
                <input type="text" name="name" id="s-name" required maxlength="120"
                       placeholder="مثال: تقرير المستخدمين الأسبوعيّ"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <label class="text-sm font-semibold">التقرير المصدر
                        <select name="report_tab" id="s-tab" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($reports as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold">الصيغة
                        <select name="format" id="s-format" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($formats as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <label class="text-sm font-semibold">التكرار
                        <select name="frequency" id="s-frequency" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($frequencies as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold">ساعة الإرسال
                        <input type="number" name="hour" id="s-hour" min="0" max="23"
                               value="{{ (int) setting('report_schedules.default_hour', 7) }}"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <label class="text-sm font-semibold">يوم الأسبوع
                        <select name="day_of_week" id="s-dow" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($days as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold">يوم الشهر
                        <input type="number" name="day_of_month" id="s-dom" min="1" max="28" value="1"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <label class="text-sm font-semibold">المنطقة الزمنيّة
                        <input type="text" name="timezone" id="s-tz" maxlength="64"
                               value="{{ setting('report_schedules.default_timezone', 'Africa/Cairo') }}"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">مدى التقرير (أيّام)
                        <input type="number" name="period_days" id="s-period" min="1" max="365"
                               value="{{ (int) setting('report_schedules.default_period_days', 30) }}"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <label class="block text-sm font-semibold mb-1" for="s-emails">المستقبِلون (بريد، افصل بفاصلة)</label>
                <textarea name="emails" id="s-emails" rows="2" maxlength="2000"
                          placeholder="admin@example.com, reports@example.com"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical"></textarea>

                <label class="block text-sm font-semibold mb-1" for="s-roles">أو أدوار كاملة</label>
                <select name="role_ids[]" id="s-roles" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name_ar }}</option>
                    @endforeach
                </select>

                <label class="flex items-center gap-2 text-sm mb-2">
                    <input type="hidden" name="include_comparison" value="0">
                    <input type="checkbox" name="include_comparison" id="s-compare" value="1">
                    <span>ضمّ المقارنة بالفترة السابقة</span>
                </label>

                <label class="flex items-center gap-2 text-sm mb-4">
                    <input type="hidden" name="skip_when_empty" value="0">
                    <input type="checkbox" name="skip_when_empty" id="s-skip" value="1" checked>
                    <span>ماتبعتش لو البيانات فاضية</span>
                </label>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">احفظ الجدولة</button>
            </form>
        </x-modal>
    @endif
@endpush

@push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('schedule-modal');
            if (!modal) return;

            const form = modal.querySelector('[data-schedule-form]');
            const method = modal.querySelector('[data-schedule-method]');
            const storeUrl = @json(route('admin.report-schedules.store'));

            const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };

            document.querySelectorAll('[data-schedule-new]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    form.action = storeUrl;
                    method.value = 'POST';
                    form.reset();
                });
            });

            document.querySelectorAll('[data-schedule-edit]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    form.action = btn.dataset.action;
                    method.value = 'PUT';
                    form.querySelector('#s-name').value = btn.dataset.name;
                    form.querySelector('#s-tab').value = btn.dataset.tab;
                    form.querySelector('#s-format').value = btn.dataset.format;
                    form.querySelector('#s-frequency').value = btn.dataset.frequency;
                    form.querySelector('#s-hour').value = btn.dataset.hour;
                    form.querySelector('#s-dow').value = btn.dataset.dow || '0';
                    form.querySelector('#s-dom').value = btn.dataset.dom || '1';
                    form.querySelector('#s-tz').value = btn.dataset.tz;
                    form.querySelector('#s-period').value = btn.dataset.period;
                    form.querySelector('#s-emails').value = btn.dataset.emails || '';
                    form.querySelector('#s-compare').checked = btn.dataset.compare === '1';
                    form.querySelector('#s-skip').checked = btn.dataset.skip === '1';
                    open();
                });
            });
        })();
    </script>
@endpush
