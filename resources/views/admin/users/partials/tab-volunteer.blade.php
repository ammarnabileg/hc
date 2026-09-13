{{--
    تاب «التطوّع» في صفحة المستخدم بالأدمن — سجلّ المشرف (الدستور 1103 · 2886):
    البوزشنز · التسكينات · المهامّ · VXP · تاريخ الالتزام · شهادات التطوّع ·
    الاجتماعات. يدفعها `AdminVolunteerTabInjector` على ستاك `admin_user_volunteer_tab`
    (composer على `admin.users.show` في routes/parts/admin-volunteer.php).

    وعلى الموبايل كلّ قسم كارتٌ رأسيّ بلا تمرير أفقيّ (2.15-ج) — نفس نمط
    تاب «الجداول» المجاور (partials/tab-tables.blade.php).
--}}

@if (! ($record['has_volunteered'] ?? false))
    <section class="card p-4">
        <p class="text-sm" style="color: var(--text-muted)">
            {{ setting('admin.users.partials.tab_volunteer.empty', 'محدّش تطوّع من الحساب ده لسّه — التاب هيتفعّل أوّل ما يتسكّن في بوزشن.') }}
        </p>
    </section>
@else

    {{-- 1) البوزشنز --}}
    <section class="card p-4">
        <h3 class="font-bold text-sm mb-3">{{ setting('admin.users.partials.tab_volunteer.positions_title', 'البوزشنز') }}</h3>
        @if ($record['positions']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_volunteer.positions_empty', 'من غير بوزشن لسّه.') }}</p>
        @else
            <ul class="divide-y" style="border-color: var(--border)">
                @foreach ($record['positions'] as $position)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                        <span class="flex-1 min-w-0 truncate">{{ $position['position'] }}</span>
                        @if ($position['is_current'])
                            <x-state-badge state="ok" :label="setting('admin.users.partials.tab_volunteer.current_label', 'حاليًّا')" />
                        @endif
                        <span class="text-xs" style="color: var(--text-muted)">
                            {{ setting('admin.users.partials.tab_volunteer.since_label', 'من') }}
                            {{ $position['first_at']?->format('Y-m-d') }}
                            @if (! $position['is_current'] && $position['last_at'])
                                {{ setting('admin.users.partials.tab_volunteer.until_label', 'لحدّ') }}
                                {{ $position['last_at']->format('Y-m-d') }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 2) التسكينات --}}
    <section class="card p-4">
        <h3 class="font-bold text-sm mb-3">{{ setting('admin.users.partials.tab_volunteer.placements_title', 'التسكينات') }}</h3>
        @if ($record['placements']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_volunteer.placements_empty', 'من غير تسكين لسّه.') }}</p>
        @else
            <ul class="divide-y" style="border-color: var(--border)">
                @foreach ($record['placements'] as $placement)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                        <span class="flex-1 min-w-0 truncate">
                            {{ $placement['position'] }} · {{ $placement['entity'] }}
                            @if ($placement['upline'])
                                <span class="text-xs" style="color: var(--text-muted)">— {{ setting('admin.users.partials.tab_volunteer.upline_label', 'الأبلاين') }}: {{ $placement['upline'] }}</span>
                            @endif
                        </span>
                        @if ($placement['is_current'])
                            <x-state-badge state="ok" :label="setting('admin.users.partials.tab_volunteer.current_label', 'حاليًّا')" />
                        @endif
                        @if ($placement['is_acting'])
                            <x-state-badge state="warn" :label="setting('admin.users.partials.tab_volunteer.acting_label', 'قائم بأعمال')" />
                        @endif
                        <span class="text-xs" style="color: var(--text-muted)">{{ $placement['from']?->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 3) المهامّ --}}
    <section class="card p-4">
        <h3 class="font-bold text-sm mb-3">{{ setting('admin.users.partials.tab_volunteer.tasks_title', 'المهامّ') }}</h3>
        <div class="flex flex-wrap gap-3 mb-3">
            <div class="rounded-xl px-4 py-3" style="background: var(--surface-sunken)">
                <div class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_volunteer.tasks_done_label', 'معتمدة') }}</div>
                <div class="font-extrabold">{{ $record['tasks']['done'] }}</div>
            </div>
            <div class="rounded-xl px-4 py-3" style="background: var(--surface-sunken)">
                <div class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_volunteer.tasks_open_label', 'جارية') }}</div>
                <div class="font-extrabold">{{ $record['tasks']['open'] }}</div>
            </div>
            <div class="rounded-xl px-4 py-3" style="background: var(--surface-sunken)">
                <div class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_volunteer.tasks_no_delivery_label', 'عدم تسليم') }}</div>
                <div class="font-extrabold">{{ $record['tasks']['no_delivery'] }}</div>
            </div>
        </div>

        @if ($record['tasks']['recent']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_volunteer.tasks_empty', 'من غير مهامّ لسّه.') }}</p>
        @else
            <ul class="divide-y" style="border-color: var(--border)">
                @foreach ($record['tasks']['recent'] as $task)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                        <span class="flex-1 min-w-0 truncate">{{ $task->title }}</span>
                        <x-state-badge :state="\App\Services\Volunteer\Tasks\TaskStatus::state($task->status)"
                                       :label="\App\Services\Volunteer\Tasks\TaskStatus::label($task->status)" />
                        <span class="text-xs" style="color: var(--text-muted)">{{ $task->deadline_at?->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 4) VXP --}}
    <section class="card p-4">
        <h3 class="font-bold text-sm mb-3">{{ setting('admin.users.partials.tab_volunteer.vxp_title', 'VXP — نقاط الإنتاج') }}</h3>
        <div class="rounded-xl px-4 py-3 mb-3 inline-block" style="background: var(--surface-sunken)">
            <div class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_volunteer.vxp_balance_label', 'الرصيد الحاليّ') }}</div>
            <div class="font-extrabold">{{ number_format($record['vxp']['balance'], 2) }}</div>
        </div>

        @if ($record['vxp']['recent']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_volunteer.vxp_empty', 'من غير حركات VXP لسّه.') }}</p>
        @else
            <ul class="divide-y" style="border-color: var(--border)">
                @foreach ($record['vxp']['recent'] as $transaction)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                        <span class="flex-1 min-w-0 truncate">{{ $transaction->reason ?? $transaction->source }}</span>
                        <span class="font-semibold">{{ number_format((float) $transaction->amount, 2) }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $transaction->created_at?->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 5) تاريخ الالتزام (Rep) --}}
    <section class="card p-4">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <h3 class="font-bold text-sm">{{ setting('admin.users.partials.tab_volunteer.commitment_title', 'تاريخ الالتزام (Rep)') }}</h3>
            <x-state-badge :state="$record['commitment']['state']"
                           :label="setting('admin.users.partials.tab_volunteer.commitment_score_label', 'الدرجة الحاليّة').': '.number_format($record['commitment']['score'], 2)" />
        </div>

        @if ($record['commitment']['recent']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_volunteer.commitment_empty', 'من غير حركات على درجة الالتزام لسّه.') }}</p>
        @else
            <ul class="divide-y" style="border-color: var(--border)">
                @foreach ($record['commitment']['recent'] as $transaction)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                        <span class="flex-1 min-w-0 truncate">{{ $transaction->reason ?? $transaction->source }}</span>
                        <span class="font-semibold">{{ number_format((float) ($transaction->applied_amount ?? $transaction->amount), 2) }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $transaction->created_at?->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 6) شهادات التطوّع --}}
    <section class="card p-4">
        <h3 class="font-bold text-sm mb-3">{{ setting('admin.users.partials.tab_volunteer.certificates_title', 'شهادات التطوّع') }}</h3>
        @if ($record['certificates']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_volunteer.certificates_empty', 'من غير شهادات تطوّع لسّه.') }}</p>
        @else
            <ul class="divide-y" style="border-color: var(--border)">
                @foreach ($record['certificates'] as $certificate)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                        <span class="flex-1 min-w-0 truncate">{{ $certificate->certificate_type?->name_ar }}</span>
                        <x-state-badge state="honor" :label="$certificate->code" />
                        <span class="text-xs" style="color: var(--text-muted)">{{ $certificate->issued_at?->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 7) الاجتماعات --}}
    <section class="card p-4">
        <h3 class="font-bold text-sm mb-3">{{ setting('admin.users.partials.tab_volunteer.meetings_title', 'الاجتماعات') }}</h3>
        @if ($record['meetings']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_volunteer.meetings_empty', 'من غير حضور اجتماعات لسّه.') }}</p>
        @else
            <ul class="divide-y" style="border-color: var(--border)">
                @foreach ($record['meetings'] as $attendance)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                        <span class="flex-1 min-w-0 truncate">{{ $attendance['meeting']?->title }}</span>
                        <x-state-badge :state="$attendance['status'] === 'registered' ? 'ok' : ($attendance['status'] === 'excused_absence' ? 'idle' : 'danger')"
                                       :label="$attendance['status_label']" />
                        <span class="text-xs" style="color: var(--text-muted)">{{ $attendance['meeting']?->scheduled_at?->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
