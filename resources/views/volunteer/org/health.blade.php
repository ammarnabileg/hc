@extends('layouts.volunteer')

@section('title', setting('volunteer.org_health.title', 'صحّة القسم'))

@php
    /**
     * صحّة القسم (24.4-7 · 13.4-ح) — لمسؤول القسم والأبلاين المخوَّل فقط.
     * سؤال واحد للشاشة: «فريقي عامل إزّاي وفين نقاط الخطر؟».
     */
    $tabs = [
        ['key' => 'indicators', 'label' => setting('volunteer.org_health.label', 'المؤشّرات'), 'url' => request()->fullUrlWithQuery(['tab' => 'indicators'])],
        ['key' => 'oversight', 'label' => setting('volunteer.org_health.label_2', 'رقابة'), 'url' => request()->fullUrlWithQuery(['tab' => 'oversight'])],
    ];
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.org_health.title', 'صحّة القسم')"
        :subtitle="$root?->name_ar"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.org_health.label_3', 'قسمي')], ['label' => setting('volunteer.org_health.title', 'صحّة القسم')]]">
        <x-slot:action>
            @include('volunteer.org.partials.entity-switcher', ['action' => route('volunteer.health')])
            @can('team_health.export')
                <button type="button" onclick="window.print()"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.org_health.action', 'تصدير') }}</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    @if (! $root || ! $indicators)
        <x-empty :message="setting('volunteer.org_health.empty', 'الفريق صغير — المؤشّرات تحتاج بيانات أكثر')" :action="setting('volunteer.org_health.action_2', 'الأعضاء والبوزشنز')" :href="route('volunteer.department')" />
    @else
        <div class="card p-4 mb-4 flex items-center gap-4 flex-wrap">
            @include('volunteer.org.partials.health-ring', ['percent' => $overall])
            <div class="min-w-0">
                <div class="font-bold">{{ setting('volunteer.org_health.text', 'المؤشّر العامّ') }}</div>
                <div class="text-xs" style="color: var(--text-muted)">
                    {{ setting('volunteer.org_health.text_2', 'آخر') }} {{ $period }} {{ setting('volunteer.org_health.text_3', 'يومًا ·') }} {{ $indicators['tasks_total'] }} {{ setting('volunteer.org_health.text_4', 'مهمّة في المدى') }}
                </div>
            </div>

            <form method="get" action="{{ route('volunteer.health') }}" class="ms-auto flex items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if ($root)<input type="hidden" name="entity" value="{{ $root->id }}">@endif
                <select name="period" onchange="this.form.submit()" aria-label="{{ setting('volunteer.common.period', 'الفترة') }}"
                        class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($periods as $option)
                        <option value="{{ $option }}" @selected($period === (int) $option)>{{ $option }} {{ setting('volunteer.common.days', 'يومًا') }}</option>
                    @endforeach
                </select>
                <select name="sub" onchange="this.form.submit()" aria-label="{{ setting('volunteer.org_health.aria', 'الفرعيّ') }}"
                        class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('volunteer.org_health.option', 'كلّ الفرعيّات') }}</option>
                    @foreach ($subEntities as $sub)
                        <option value="{{ $sub->id }}" @selected((int) ($filters['sub'] ?? 0) === $sub->id)>{{ $sub->name_ar }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <x-tabs :tabs="$tabs" :current="$tab" />

        @if ($tab === 'indicators')
            {{-- أربعة كروت KPI بحدّ أقصى، والباقي في بلوك «مؤشّرات إضافيّة» (2.15-أ-3) --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <x-kpi :label="setting('volunteer.org_health.label_4', 'متوسّط Rep للفريق')" :value="$indicators['rep_avg'] ?? '—'" icon="evaluation" />
                <x-kpi :label="setting('volunteer.org_health.label_5', 'نسبة الالتزام')" :value="$indicators['commitment_percent'].'%'" icon="✓" />
                <x-kpi :label="setting('volunteer.org_health.label_6', 'نسبة التأخير')" :value="$indicators['late_percent'].'%'" icon="clock" />
                <x-kpi :label="setting('volunteer.org_health.label_7', 'نسبة الإرجاع')" :value="$indicators['return_percent'].'%'" icon="↩️" />
            </div>

            <div class="card p-4 mb-4">
                <div class="text-sm font-bold mb-3">{{ setting('volunteer.org_health.text_5', 'مؤشّرات إضافيّة') }}</div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span style="color: var(--text-muted)">{{ setting('volunteer.org_health.text_6', 'متوسّط سرعة المراجعة') }}</span>
                        <span class="font-bold">{{ $indicators['review_speed_hours'] ?? '—' }} {{ setting('volunteer.common.hour', 'ساعة') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span style="color: var(--text-muted)">{{ setting('volunteer.org_health.text_7', 'ضغط العمل (مهمّة/عضو)') }}</span>
                        <span class="font-bold">{{ $indicators['work_pressure'] }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span style="color: var(--text-muted)">{{ setting('volunteer.org_health.text_8', 'المهامّ الحرجة') }}</span>
                        <span class="font-bold">{{ $indicators['critical_tasks'] }}</span>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-sm font-bold mb-3">{{ setting('volunteer.org_health.text_9', 'لوحة القيادة — الأكثر مهامَّ قيد التنفيذ') }}</div>
                @if ($leaderboard->isEmpty())
                    <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.org_health.text_10', 'مفيش مهامّ جارية دلوقتي.') }}</p>
                @else
                    <ul class="text-sm">
                        @foreach ($leaderboard as $row)
                            <li class="flex items-center justify-between gap-2 py-2" style="border-top: 1px solid var(--border)">
                                <span class="min-w-0 truncate">
                                    <span class="font-semibold">{{ $row['name'] }}</span>
                                    <span class="text-xs" style="color: var(--text-muted)">· {{ $row['position'] }} · {{ $row['entity'] }}</span>
                                </span>
                                <span class="font-bold shrink-0">{{ $row['in_progress'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="card p-4">
                    <div class="text-sm font-bold mb-3">{{ setting('volunteer.org_health.text_11', 'المهامّ المتأخّرة') }}</div>
                    @forelse ($oversight['late_tasks'] as $row)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <span class="min-w-0 truncate">{{ $row['title'] }} <span class="text-xs" style="color: var(--text-muted)">· {{ $row['owner'] }}</span></span>
                            <x-state-badge state="danger" :label="$row['deadline'] ?? setting('volunteer.org_health.label_8', 'بلا موعد')" />
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.org_health.text_12', 'مفيش مهامّ متأخّرة — تمام ✓') }}</p>
                    @endforelse
                </div>

                <div class="card p-4">
                    <div class="text-sm font-bold mb-1">{{ setting('volunteer.org_health.text_13', 'تجاوزات نطاق الإشراف') }}</div>
                    <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('volunteer.org_health.text_14', 'تنبيه فقط — لا يمنع تسكينًا ولا ترقيةً ولا نقلًا.') }}</p>
                    @forelse ($oversight['span_breaches'] as $row)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <span class="min-w-0 truncate">{{ $row['name'] }} <span class="text-xs" style="color: var(--text-muted)">· {{ $row['position'] }}</span></span>
                            <x-state-badge state="danger" :label="$row['actual'].' / '.$row['max']" />
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.org_health.text_15', 'كلّ النطاقات داخل الحدود.') }}</p>
                    @endforelse
                </div>

                <div class="card p-4">
                    <div class="text-sm font-bold mb-1">{{ setting('volunteer.org_health.text_16', 'مؤشّر مخاطر الفقدان') }}</div>
                    {{-- ⛔ مؤشّر داخليّ — لا يُعرَض للمتطوّع نفسه أبدًا (13.4-م-4) --}}
                    <p class="text-xs mb-3" style="color: var(--text-muted)">
                        {{ setting('volunteer.health.retention_risk.note', 'داخليّ للأبلاين فقط — ولا يُعرَض للمتطوّع عن نفسه أبدًا') }}
                    </p>
                    @forelse ($oversight['retention_risk'] as $row)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <span class="min-w-0 truncate">{{ $row['name'] }} <span class="text-xs" style="color: var(--text-muted)">· {{ $row['entity'] }}</span></span>
                            <x-state-badge :state="$row['signals'] >= 3 ? 'danger' : 'warn'" :label="setting('volunteer.org_health.label_9', 'إشارات ').$row['signals']" />
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.org_health.text_17', 'مفيش مؤشّرات خطر دلوقتي.') }}</p>
                    @endforelse
                </div>

                {{-- الكارت كلّه **يُحذَف** لو أغلقه المالك من إعدادات Rep (13.4-ن-هـ · 2.15-أ-7) --}}
                @if ($oversight['show_behavior_grantors'] ?? true)
                    <div class="card p-4">
                        <div class="text-sm font-bold mb-3">{{ setting('volunteer.org_health.text_18', 'معاملات السلوك لكلّ مشرف') }}</div>
                        @forelse ($oversight['behavior_grantors'] as $row)
                            <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                                <span class="min-w-0 truncate">{{ $row['name'] }}</span>
                                <span class="text-xs shrink-0" style="color: var(--text-muted)">
                                    {{ setting('volunteer.org_health.text_19', 'منح') }} {{ $row['granted'] }} · {{ setting('volunteer.org_health.text_20', 'اعتراضات مقبولة') }} {{ $row['accepted_percent'] === null ? '—' : $row['accepted_percent'].'%' }}
                                </span>
                            </div>
                        @empty
                            <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.org_health.text_21', 'مفيش معاملات سلوك في المدى.') }}</p>
                        @endforelse
                    </div>
                @endif

                <div class="card p-4">
                    <div class="text-sm font-bold mb-3">{{ setting('volunteer.org_health.text_22', 'أعلام «متأخّر بسبب…»') }}</div>
                    @forelse ($oversight['late_due_to_child'] as $row)
                        <div class="py-2 text-sm" style="border-top: 1px solid var(--border)">
                            {{ $row['title'] }} <span class="text-xs" style="color: var(--text-muted)">· {{ $row['owner'] }}</span>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.org_health.text_23', 'مفيش أعلام مرفوعة.') }}</p>
                    @endforelse
                </div>

                <div class="card p-4">
                    <div class="text-sm font-bold mb-1">{{ setting('volunteer.org_health.text_24', 'الأعضاء الخاملون') }}</div>
                    <p class="text-xs mb-3" style="color: var(--text-muted)">
                        {{ setting('volunteer.org_health.text_25', 'بلا نشاط') }} {{ setting('rep.inactivity.days_before_alert', 21) }} {{ setting('volunteer.org_health.text_26', 'يومًا فأكثر — ومعه عدّاد الخصم الأسبوعيّ.') }}
                    </p>
                    @forelse ($oversight['idle_members'] as $row)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <span class="min-w-0 truncate">{{ $row['name'] }} <span class="text-xs" style="color: var(--text-muted)">· {{ $row['entity'] }}</span></span>
                            <span class="text-xs shrink-0" style="color: var(--text-muted)">
                                {{ $row['idle_days'] }} {{ setting('volunteer.org_health.text_27', 'يوم ·') }} {{ $row['weekly_deduction'] }} Rep
                            </span>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.org_health.text_28', 'كلّ الفريق نشِط — تمام ✓') }}</p>
                    @endforelse
                </div>
            </div>
        @endif
    @endif
@endsection
