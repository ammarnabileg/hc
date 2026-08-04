@php
    /** تاب «أوفر فيو» في طبقة التطوّع (13.4-م-1). */
    $o = $panel;
@endphp

{{-- تنبيه هادئ للأبلاين وحده لو الدرجة عند حدّ الإنذار — بلا فضح أمام الزملاء --}}
@if ($o['upline_alert'])
    <div class="card p-4 mb-4 flex items-start gap-3">
        <x-state-badge state="warn" :label="setting('volunteer.profile_tab_overview.label', 'انتبه')" />
        <p class="text-sm">{{ $o['upline_alert'] }}</p>
    </div>
@endif

{{-- أربعة كروت KPI بحدّ أقصى في صفّ واحد (2.15-أ-3) — والباقي في شريط التفاصيل تحته --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
    <div class="card p-4 animate-fadeup">
        <div class="flex items-center justify-between">
            <span class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_overview.text', 'درجة الالتزام') }}</span>
            <span aria-hidden="true"><x-icon name="evaluation" size="16" /></span>
        </div>
        <div class="mt-2 text-2xl font-extrabold" data-count-to="{{ $o['rep']['score'] }}">{{ $o['rep']['score'] }}</div>

        {{-- بار من −10 إلى +10 وعلامة عند حدّ الإنذار (13.4-م-1) --}}
        <div class="relative mt-3 h-2 rounded-full" style="background: var(--surface-sunken)">
            <span class="absolute inset-y-0 start-0 rounded-full"
                  style="width: {{ $o['rep']['percent'] }}%; background: var(--color-state-{{ state_color($o['rep']['state'])['color'] }})"></span>
            <span class="absolute -top-1 h-4 w-0.5" title="{{ setting('volunteer.profile_tab_overview.tooltip', 'حدّ الإنذار') }} {{ $o['rep']['warning'] }}"
                  style="inset-inline-start: {{ $o['rep']['warning_percent'] }}%; background: var(--color-state-danger)"></span>
        </div>
        <div class="mt-1 flex justify-between text-xs" style="color: var(--text-muted)">
            <span>{{ $o['rep']['min'] }}</span><span>{{ $o['rep']['max'] }}</span>
        </div>
    </div>

    <x-kpi :label="setting('volunteer.profile_tab_overview.label_2', 'نقاط الخبرة VXP')" :value="$o['vxp']['balance']" icon="spark" :hint="setting('volunteer.profile_tab_overview.hint', 'الترتيب #').$o['vxp']['rank']" />
    <x-kpi :label="setting('volunteer.profile_tab_overview.label_3', 'مدّة الخدمة')" :value="$o['service_duration']" icon="hourglass" :hint="$o['entity']" />
    <x-kpi :label="setting('volunteer.common.position', 'البوزشن')" :value="$o['position'] ?? '—'" icon="badge" />
</div>

{{-- الأرقام الثانويّة في شريط واحد بدل كروت زائدة (2.15-أ-3) --}}
<div class="card p-4 mb-4 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
    <div>
        <span class="block text-xs" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_overview.text_2', 'المهامّ') }}</span>
        <span class="font-bold">{{ $o['tasks']['done'] }} {{ setting('volunteer.profile_tab_overview.text_3', 'مكتملة ·') }} {{ $o['tasks']['open'] }} {{ setting('volunteer.profile_tab_overview.text_4', 'جارية') }}</span>
    </div>
    <div>
        <span class="block text-xs" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_overview.text_5', 'متوسّط التقييم الأسبوعيّ') }}</span>
        <span class="font-bold">
            {{ $o['evaluation_average'] !== null ? $o['evaluation_average'].' / '.$o['evaluation_scale'] : setting('volunteer.profile_tab_overview.text_6', 'لسّه بدري') }}
        </span>
    </div>
    <div>
        <span class="block text-xs" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_overview.text_7', 'حضور الاجتماعات') }}</span>
        <span class="font-bold">{{ $o['attendance']['percent'] !== null ? $o['attendance']['percent'].'%' : setting('volunteer.profile_tab_overview.text_6', 'لسّه بدري') }}</span>
    </div>
    <div>
        <span class="block text-xs" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_overview.text_8', 'حالة النشاط') }}</span>
        <span class="inline-flex items-center gap-2 font-bold">
            @if ($o['is_active_now'])
                <span class="w-2 h-2 rounded-full animate-pulse" style="background: var(--color-state-ok)"></span> {{ setting('volunteer.profile_tab_overview.text_9', 'نشط دلوقتي') }}
            @else
                <span class="w-2 h-2 rounded-full" style="background: var(--color-state-idle)"></span> —
            @endif
        </span>
    </div>
</div>

{{-- منحنيان صغيران: الاتّجاه أهمّ من الرقم المجرّد (13.4-م-1) --}}
<div class="grid md:grid-cols-2 gap-3 mb-4">
    @include('volunteer.profile.partials.sparkline', [
        'title' => setting('volunteer.profile_tab_overview.title', 'تطوّر درجة الالتزام'),
        'series' => $o['rep_series'],
        'days' => $o['series_days'],
        'tone' => 'var(--color-brand-500)',
    ])
    @include('volunteer.profile.partials.sparkline', [
        'title' => setting('volunteer.profile_tab_overview.title_2', 'تراكم VXP'),
        'series' => $o['vxp_series'],
        'days' => $o['series_days'],
        'tone' => 'var(--color-state-ok)',
    ])
</div>

<div class="grid md:grid-cols-2 gap-3">

    {{-- «رحلتي في التطوّع» — تايم-لاين مضغوط (13.4-م-1) --}}
    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">{{ setting('volunteer.profile_tab_overview.heading', 'رحلتي في التطوّع') }}</h2>
        @if ($o['journey']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_overview.text_10', 'لسّه بدري — أوّل محطّة مستنّياك.') }}</p>
        @else
            <ol class="space-y-3">
                @foreach ($o['journey'] as $step)
                    <li class="flex items-start gap-3">
                        <span class="mt-1 w-2 h-2 rounded-full shrink-0" style="background: var(--color-brand-500)"></span>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold">{{ $step['label'] }}</div>
                            <div class="text-xs" style="color: var(--text-muted)"
                                 title="{{ $step['at']->format('Y-m-d') }}">{{ $step['at']->diffForHumans() }}</div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- Kudos: العدد + آخر 3 شكرات **بأسبابها** — القصّة أقوى من العدّاد --}}
    <section class="card p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold text-sm">{{ setting('volunteer.profile_tab_overview.heading_2', 'الشكر (Kudos)') }}</h2>
            <span class="text-sm font-extrabold" data-count-to="{{ $o['kudos_count'] }}">{{ $o['kudos_count'] }}</span>
        </div>
        @if ($o['kudos_latest']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_overview.text_11', 'لسّه مفيش شكر — أوّل واحدة جايّة.') }}</p>
        @else
            <ul class="space-y-3">
                @foreach ($o['kudos_latest'] as $kudos)
                    <li>
                        <div class="text-sm">{{ $kudos->reason }}</div>
                        <div class="text-xs" style="color: var(--text-muted)">
                            {{ $kudos->sender?->shortName() }} · {{ $kudos->created_at->diffForHumans() }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- الشهادات من **المصدر الواحد** (12.5 / مكتبتي 20) --}}
    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">{{ setting('volunteer.profile_tab_overview.heading_3', 'الشهادات') }}</h2>
        @if ($o['certificates']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_overview.text_12', 'لسّه بدري — أوّل شهادة مستنّياك.') }}</p>
        @else
            <ul class="space-y-2">
                @foreach ($o['certificates'] as $certificate)
                    <li class="flex items-center justify-between gap-2 text-sm">
                        <span class="min-w-0 truncate">{{ $certificate->certificate_type?->name_ar ?? $certificate->code }}</span>
                        <x-state-badge state="honor" :label="setting('volunteer.profile_tab_overview.label_4', 'معتمدة')" />
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- بار «طريقك للبوزشن الجاي» — تدرّج الهدف (13.4-م-1) --}}
    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">{{ setting('volunteer.profile_tab_overview.heading_4', 'طريقك للبوزشن الجاي') }}</h2>
        @if (! $o['next_position'])
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.profile_tab_overview.text_13', 'إنت في أعلى بوزشن في السلّم دلوقتي.') }}</p>
        @else
            <div class="flex items-center justify-between text-sm mb-2">
                <span>{{ $o['next_position']['label'] }}</span>
                <span class="font-bold">{{ $o['next_position']['percent'] }}%</span>
            </div>
            <div class="h-2 rounded-full" style="background: var(--surface-sunken)">
                <span class="block h-2 rounded-full"
                      style="width: {{ $o['next_position']['percent'] }}%; background: var(--color-brand-500)"></span>
            </div>
            <p class="mt-2 text-xs" style="color: var(--text-muted)">
                {{ $o['next_position']['served_months'] }} {{ setting('volunteer.profile_tab_overview.text_14', 'شهر من') }} {{ $o['next_position']['required_months'] }}
                · {{ setting('volunteer.profile_tab_overview.text_15', 'درجة الالتزام المطلوبة') }} {{ $o['next_position']['rep_target'] }}
            </p>
        @endif
    </section>

    {{-- اختصار لوحة التطوّع — لصاحب البروفايل وحده --}}
    @if ($o['my_open_tasks']->isNotEmpty() || $o['next_meeting'])
        <section class="card p-4 md:col-span-2">
            <h2 class="font-bold text-sm mb-3">{{ setting('volunteer.profile_tab_overview.heading_5', 'شغلك الجاري') }}</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($o['my_open_tasks'] as $task)
                    <li class="flex items-center justify-between gap-2">
                        <span class="min-w-0 truncate">{{ $task->title }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $task->deadline_at?->diffForHumans() }}</span>
                    </li>
                @endforeach
                @if ($o['next_meeting'])
                    <li class="flex items-center justify-between gap-2">
                        <span class="min-w-0 truncate">{{ setting('volunteer.profile_tab_overview.bullet', 'أقرب اجتماع:') }} {{ $o['next_meeting']->title }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $o['next_meeting']->scheduled_at?->diffForHumans() }}</span>
                    </li>
                @endif
            </ul>
        </section>
    @endif
</div>

{{-- [تقرير PDF] للمخوَّل — مادّة قرار الترقية، برقم مرجع ومسجّل في الأوديت --}}
@if ($o['can_export_report'])
    <div class="mt-4">
        <a href="{{ route('volunteer.profile.report', ['code' => $owner->code]) }}" target="_blank" rel="noopener"
           class="btn inline-flex rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.profile_tab_overview.link', 'تقرير PDF') }}</a>
    </div>
@endif
