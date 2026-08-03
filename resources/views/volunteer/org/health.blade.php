@extends('layouts.volunteer')

@section('title', 'صحّة القسم')

@php
    /**
     * صحّة القسم (24.4-7 · 13.4-ح) — لمسؤول القسم والأبلاين المخوَّل فقط.
     * سؤال واحد للشاشة: «فريقي عامل إزّاي وفين نقاط الخطر؟».
     */
    $tabs = [
        ['key' => 'indicators', 'label' => 'المؤشّرات', 'url' => request()->fullUrlWithQuery(['tab' => 'indicators'])],
        ['key' => 'oversight', 'label' => 'رقابة', 'url' => request()->fullUrlWithQuery(['tab' => 'oversight'])],
    ];
@endphp

@section('content')
    <x-page-header
        title="صحّة القسم"
        :subtitle="$root?->name_ar"
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'قسمي'], ['label' => 'صحّة القسم']]">
        <x-slot:action>
            @include('volunteer.org.partials.entity-switcher', ['action' => route('volunteer.health')])
            @can('team_health.export')
                <button type="button" onclick="window.print()"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">تصدير</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    @if (! $root || ! $indicators)
        <x-empty message="الفريق صغير — المؤشّرات تحتاج بيانات أكثر" action="الأعضاء والبوزشنز" :href="route('volunteer.department')" />
    @else
        <div class="card p-4 mb-4 flex items-center gap-4 flex-wrap">
            @include('volunteer.org.partials.health-ring', ['percent' => $overall])
            <div class="min-w-0">
                <div class="font-bold">المؤشّر العامّ</div>
                <div class="text-xs" style="color: var(--text-muted)">
                    آخر {{ $period }} يومًا · {{ $indicators['tasks_total'] }} مهمّة في المدى
                </div>
            </div>

            <form method="get" action="{{ route('volunteer.health') }}" class="ms-auto flex items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if ($root)<input type="hidden" name="entity" value="{{ $root->id }}">@endif
                <select name="period" onchange="this.form.submit()" aria-label="الفترة"
                        class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($periods as $option)
                        <option value="{{ $option }}" @selected($period === (int) $option)>{{ $option }} يومًا</option>
                    @endforeach
                </select>
                <select name="sub" onchange="this.form.submit()" aria-label="الفرعيّ"
                        class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">كلّ الفرعيّات</option>
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
                <x-kpi label="متوسّط Rep للفريق" :value="$indicators['rep_avg'] ?? '—'" icon="evaluation" />
                <x-kpi label="نسبة الالتزام" :value="$indicators['commitment_percent'].'%'" icon="✓" />
                <x-kpi label="نسبة التأخير" :value="$indicators['late_percent'].'%'" icon="clock" />
                <x-kpi label="نسبة الإرجاع" :value="$indicators['return_percent'].'%'" icon="↩️" />
            </div>

            <div class="card p-4 mb-4">
                <div class="text-sm font-bold mb-3">مؤشّرات إضافيّة</div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span style="color: var(--text-muted)">متوسّط سرعة المراجعة</span>
                        <span class="font-bold">{{ $indicators['review_speed_hours'] ?? '—' }} ساعة</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span style="color: var(--text-muted)">ضغط العمل (مهمّة/عضو)</span>
                        <span class="font-bold">{{ $indicators['work_pressure'] }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span style="color: var(--text-muted)">المهامّ الحرجة</span>
                        <span class="font-bold">{{ $indicators['critical_tasks'] }}</span>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-sm font-bold mb-3">لوحة القيادة — الأكثر مهامَّ قيد التنفيذ</div>
                @if ($leaderboard->isEmpty())
                    <p class="text-sm" style="color: var(--text-muted)">مفيش مهامّ جارية دلوقتي.</p>
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
                    <div class="text-sm font-bold mb-3">المهامّ المتأخّرة</div>
                    @forelse ($oversight['late_tasks'] as $row)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <span class="min-w-0 truncate">{{ $row['title'] }} <span class="text-xs" style="color: var(--text-muted)">· {{ $row['owner'] }}</span></span>
                            <x-state-badge state="danger" :label="$row['deadline'] ?? 'بلا موعد'" />
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">مفيش مهامّ متأخّرة — تمام ✓</p>
                    @endforelse
                </div>

                <div class="card p-4">
                    <div class="text-sm font-bold mb-1">تجاوزات نطاق الإشراف</div>
                    <p class="text-xs mb-3" style="color: var(--text-muted)">تنبيه فقط — لا يمنع تسكينًا ولا ترقيةً ولا نقلًا.</p>
                    @forelse ($oversight['span_breaches'] as $row)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <span class="min-w-0 truncate">{{ $row['name'] }} <span class="text-xs" style="color: var(--text-muted)">· {{ $row['position'] }}</span></span>
                            <x-state-badge state="danger" :label="$row['actual'].' / '.$row['max']" />
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">كلّ النطاقات داخل الحدود.</p>
                    @endforelse
                </div>

                <div class="card p-4">
                    <div class="text-sm font-bold mb-1">مؤشّر مخاطر الفقدان</div>
                    {{-- ⛔ مؤشّر داخليّ — لا يُعرَض للمتطوّع نفسه أبدًا (13.4-م-4) --}}
                    <p class="text-xs mb-3" style="color: var(--text-muted)">
                        {{ setting('volunteer.health.retention_risk.note', 'داخليّ للأبلاين فقط — ولا يُعرَض للمتطوّع عن نفسه أبدًا') }}
                    </p>
                    @forelse ($oversight['retention_risk'] as $row)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <span class="min-w-0 truncate">{{ $row['name'] }} <span class="text-xs" style="color: var(--text-muted)">· {{ $row['entity'] }}</span></span>
                            <x-state-badge :state="$row['signals'] >= 3 ? 'danger' : 'warn'" :label="'إشارات '.$row['signals']" />
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">مفيش مؤشّرات خطر دلوقتي.</p>
                    @endforelse
                </div>

                {{-- الكارت كلّه **يُحذَف** لو أغلقه المالك من إعدادات Rep (13.4-ن-هـ · 2.15-أ-7) --}}
                @if ($oversight['show_behavior_grantors'] ?? true)
                    <div class="card p-4">
                        <div class="text-sm font-bold mb-3">معاملات السلوك لكلّ مشرف</div>
                        @forelse ($oversight['behavior_grantors'] as $row)
                            <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                                <span class="min-w-0 truncate">{{ $row['name'] }}</span>
                                <span class="text-xs shrink-0" style="color: var(--text-muted)">
                                    منح {{ $row['granted'] }} · اعتراضات مقبولة {{ $row['accepted_percent'] === null ? '—' : $row['accepted_percent'].'%' }}
                                </span>
                            </div>
                        @empty
                            <p class="text-sm" style="color: var(--text-muted)">مفيش معاملات سلوك في المدى.</p>
                        @endforelse
                    </div>
                @endif

                <div class="card p-4">
                    <div class="text-sm font-bold mb-3">أعلام «متأخّر بسبب…»</div>
                    @forelse ($oversight['late_due_to_child'] as $row)
                        <div class="py-2 text-sm" style="border-top: 1px solid var(--border)">
                            {{ $row['title'] }} <span class="text-xs" style="color: var(--text-muted)">· {{ $row['owner'] }}</span>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">مفيش أعلام مرفوعة.</p>
                    @endforelse
                </div>

                <div class="card p-4">
                    <div class="text-sm font-bold mb-1">الأعضاء الخاملون</div>
                    <p class="text-xs mb-3" style="color: var(--text-muted)">
                        بلا نشاط {{ setting('rep.inactivity.days_before_alert', 21) }} يومًا فأكثر — ومعه عدّاد الخصم الأسبوعيّ.
                    </p>
                    @forelse ($oversight['idle_members'] as $row)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <span class="min-w-0 truncate">{{ $row['name'] }} <span class="text-xs" style="color: var(--text-muted)">· {{ $row['entity'] }}</span></span>
                            <span class="text-xs shrink-0" style="color: var(--text-muted)">
                                {{ $row['idle_days'] }} يوم · {{ $row['weekly_deduction'] }} Rep
                            </span>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">كلّ الفريق نشِط — تمام ✓</p>
                    @endforelse
                </div>
            </div>
        @endif
    @endif
@endsection
