@extends('layouts.app')
@section('title', setting('streaks.screen.title', 'الستريك ونادي الخامسة'))

@php
    // بطاقة الستريك قابلة للاستخراج كصورة (12.14-هـ)
    $exportSubtitle = str_replace(':date', now()->format('Y/m/d'), (string) setting('streaks.screen.export_subtitle', 'حتى :date'));
    $exportDays = fn (int $days) => str_replace(':days', (string) $days, (string) setting('streaks.screen.export_days', ':days يوم'));
    $exportRows = [
        ['rank' => 1, 'u' => auth()->id(), 'name' => setting('streaks.screen.kpi_current', 'ستريكي الحاليّ'), 'value' => $exportDays((int) $streak->current_days)],
        ['rank' => 2, 'u' => auth()->id(), 'name' => setting('streaks.screen.export_best', 'أطول ستريك'), 'value' => $exportDays((int) $streak->best_days)],
        ['rank' => 3, 'u' => auth()->id(), 'name' => setting('streaks.screen.export_club', 'نادي الخامسة'), 'value' => $exportDays((int) $clubTotal)],
    ];
@endphp

@section('content')
    <x-page-header
        :title="setting('streaks.screen.title', 'الستريك ونادي الخامسة')"
        :subtitle="setting('streaks.screen.subtitle', 'استمراريّتك اليوميّة — يوم ورا يوم، والعادة بتتبني.')"
        :breadcrumbs="[['label' => setting('streaks.screen.breadcrumb_root', 'إنجازاتي')], ['label' => setting('streaks.screen.breadcrumb_self', 'الستريك')]]">
        <x-slot:action>
            {{-- ⭐ الستريك ونادي الخامسة ضمن قائمة الاستخراج كصورة (12.14-هـ) --}}
            <x-export-image kind="card" :title="setting('streaks.screen.title', 'الستريك ونادي الخامسة')" :subtitle="$exportSubtitle" :rows="$exportRows" />

            @unless ($recordedToday)
                <form method="post" action="{{ route('achievements.streak.checkin') }}">
                    @csrf
                    <button type="submit"
                            class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('streaks.screen.checkin_action', 'سجّل حضور النهارده') }}</button>
                </form>
            @else
                <span class="text-xs" style="color: var(--color-state-ok)">● {{ setting('streaks.screen.checked_in_today', 'النهارده اتسجّل') }}</span>
            @endunless
        </x-slot:action>
    </x-page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-kpi :label="setting('streaks.screen.kpi_current', 'ستريكي الحاليّ')" :value="(int) $streak->current_days" icon="streak"
               :hint="setting('streaks.screen.kpi_current_hint', 'أيّام متواصلة')" />
        <x-kpi :label="setting('streaks.screen.kpi_best', 'أطول ستريك (Best)')" :value="(int) $streak->best_days" icon="badge" />
        <x-kpi :label="setting('streaks.screen.kpi_club_days', 'أيّام نادي الخامسة')" :value="(int) $streak->club_5am_count" icon="streak" />
        <x-kpi :label="setting('streaks.screen.kpi_last_active', 'آخر يوم نشط')"
               :value="$streak->last_active_date?->translatedFormat('j M') ?? '—'" icon="calendar" />
    </div>

    {{-- ⭐ تذكرة مكافأة السلسلة (7.2): زرّ واحد بارز عند اكتمال الدورة --}}
    @if ($rewardDue)
        <div class="card p-4 mb-4 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-start gap-2">
                <x-state-badge state="honor" :label="setting('streaks.screen.reward_ready_badge', 'مكافأة جاهزة')" />
                <p class="text-sm">
                    {!! str_replace(':days', '<strong>'.(int) $streak->current_days.'</strong>', e(setting('streaks.screen.reward_ready_message', 'كمّلت :days يوم متواصل — تذكرة الهدية في انتظارك'))) !!} <x-icon name="ticket" size="16" />
                </p>
            </div>

            <form method="post" action="{{ route('achievements.streak.reward') }}">
                @csrf
                <button type="submit"
                        class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('streaks.reward.cta', 'استلم تذكرة المكافأة') }}
                </button>
            </form>
        </div>
    @endif

    @if ($broken)
        {{-- الستريك المنقطع برسالة محايدة تشجّع بلا تجريح (2.17-ج) --}}
        <div class="card p-4 mb-4 text-sm" style="border-color: var(--border)">
            <p class="font-semibold">{{ setting('streaks.screen.broken_title', 'ابدأ من جديد النهارده') }} <x-icon name="contribution" size="16" /></p>
            <p class="mt-1" style="color: var(--text-muted)">
                {{ str_replace(':best', (int) $streak->best_days, (string) setting('streaks.screen.broken_message', 'الستريك اتقطع، وده بيحصل. أطول ستريك عملته (:best يوم) لسّه محفوظ ليك — وأوّل يوم في السلسلة الجديدة بيبدأ بضغطة.')) }}
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section class="card p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="font-bold text-sm">{{ setting('streaks.screen.days_title', 'أيّامي') }}</h2>

                <form method="get" action="{{ route('achievements.streak') }}">
                    <select name="months" onchange="this.form.submit()"
                            class="rounded-xl px-3 py-1.5 text-xs"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ([
                            1 => setting('streaks.screen.range_1_month', 'آخر شهر'),
                            3 => setting('streaks.screen.range_3_months', 'آخر 3 شهور'),
                            6 => setting('streaks.screen.range_6_months', 'آخر 6 شهور'),
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($months === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @include('achievements.components.heatmap', ['heatmap' => $heatmap, 'from' => $from, 'to' => $to])
        </section>

        <section class="card p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="font-bold text-sm">{{ setting('streaks.screen.club_title', 'نادي الخامسة صباحًا') }} <x-icon name="streak" size="16" /></h2>
                {{-- النافذة مفتوحة الآن؟ — بشارة لا بلون وحده (2.16) --}}
                <x-state-badge :state="$windowOpen ? 'ok' : 'idle'"
                               :label="$windowOpen ? setting('streaks.screen.window_open', 'النافذة مفتوحة') : setting('streaks.screen.window_closed', 'النافذة مقفولة')" />
            </div>

            {{-- ⭐ «X صحيوا معاك» بحدّ 10، وتحته «كن من أوائل الصاحيين» (2.9-7) --}}
            <x-social-proof context="club_5am" class="mb-2"
                :count="app(App\Services\Engagement\SocialProof::class)->clubPeersToday(auth()->id())" />

            <p class="text-xs leading-relaxed" style="color: var(--text-muted)">
                {!! str_replace(
                    [':start', ':end', ':timezone'],
                    [
                        '<strong style="color: var(--text)">'.e($window['start']).'</strong>',
                        '<strong style="color: var(--text)">'.e($window['end']).'</strong>',
                        '<strong style="color: var(--text)">'.e($timezone).'</strong>',
                    ],
                    e(setting('streaks.screen.club_window_hint', 'سجّل حضورك بين :start و:end بتوقيتك المحلّيّ (:timezone)، فتُحسَب لك يوم في النادي وتاخد XP الحضور.')),
                ) !!}
            </p>

            {{-- سلّم XP المتدرّج (7.2) — الرقم الذي يكسبه فعلًا لا وعدٌ مبهم --}}
            <div class="mt-3 rounded-xl px-3 py-2 text-xs" style="background: var(--surface-sunken)">
                <div class="flex items-center justify-between gap-2">
                    <span style="color: var(--text-muted)">{{ setting('streaks.screen.next_checkin_gives', 'حضورك القادم يمنحك') }}</span>
                    <strong style="color: var(--color-brand-400)">+{{ (int) $ladderXp }} XP</strong>
                </div>
                @if ($nextStep)
                    <div class="mt-1" style="color: var(--text-muted)">
                        {!! str_replace(
                            [':days', ':xp'],
                            [(int) $nextStep['days_left'], '<strong style="color: var(--text)">'.(int) $nextStep['xp'].' XP</strong>'],
                            e(setting('streaks.screen.next_ladder_step', 'باقي :days يوم حضور توصل لدرجة :xp لكلّ يوم.')),
                        ) !!}
                    </div>
                @endif
            </div>

            <ul class="mt-3 space-y-1 text-xs" style="color: var(--text-muted)">
                <li>• {{ setting('streaks.screen.rule_not_consecutive', 'الأيّام مش لازم متتابعة — الهدف بناء العادة.') }}</li>
                <li>• {{ str_replace(':days', (int) $rewardEvery, (string) setting('streaks.screen.rule_reward_cycle', 'كلّ :days أيّام متواصلة = مكافأة تذكرة هدية')) }} <x-icon name="ticket" size="16" />.</li>
                <li>• {{ setting('streaks.screen.rule_freeze', 'درع التجميد بيحمي يوم فايت من كسر السلسلة.') }}</li>
            </ul>

            {{-- ⭐ درع التجميد (7.2 · 7.1-4): يظهر حين يوجد يوم فايت يستحقّ الحماية --}}
            <div class="mt-4 pt-4" style="border-top: 1px solid var(--border)">
                <h3 class="font-bold text-xs mb-2">{{ setting('streaks.screen.freeze_title', 'درع التجميد') }} <x-icon name="shield" size="16" /></h3>

                @if ($freezableDay)
                    <p class="text-xs mb-2" style="color: var(--text-muted)">
                        {!! str_replace(
                            [':day', ':cost'],
                            ['<strong style="color: var(--text)">'.e($freezableDay->translatedFormat('j F')).'</strong>', (int) $freezeCost],
                            e(setting('streaks.screen.freezable_day', 'يوم :day فايت — احميه بـ:cost تذكرة قبل ما السلسلة تنكسر.')),
                        ) !!}
                    </p>

                    <form method="post" action="{{ route('achievements.streak.freeze') }}">
                        @csrf
                        <button type="submit"
                                class="btn w-full rounded-xl px-3 py-2 text-xs font-semibold motion-standard"
                                style="background: var(--surface-raised); color: var(--text)">
                            {{ setting('streaks.freeze.cta', 'اشترِ درع تجميد') }}
                        </button>
                    </form>
                @else
                    <p class="text-xs" style="color: var(--text-muted)">
                        {{ setting('streaks.freeze.nothing_message', 'مفيش يوم فايت محتاج حماية دلوقتي — سلسلتك سليمة.') }}
                    </p>
                @endif

                <p class="text-xs mt-2" style="color: var(--text-muted)">
                    {{ str_replace([':used', ':cap'], [(int) $freezesUsed, (int) $freezeCap], (string) setting('streaks.screen.freezes_used', 'استعملت :used من :cap دروع الشهر ده.')) }}
                </p>
            </div>

            @if ($clubDays->isNotEmpty())
                <h3 class="font-bold text-xs mt-4 mb-2">{{ setting('streaks.screen.recent_club_days', 'آخر أيّامي في النادي') }}</h3>
                <ul class="space-y-1 text-xs">
                    @foreach ($clubDays as $day)
                        <li class="flex items-center justify-between rounded-lg px-2 py-1.5" style="background: var(--surface-sunken)">
                            <span>{{ $day->day->translatedFormat('j F Y') }}</span>
                            <span style="color: var(--color-state-honor)">★</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection

@section('mobile_action')
    @unless ($recordedToday)
        <form method="post" action="{{ route('achievements.streak.checkin') }}">
            @csrf
            <button type="submit"
                    class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('streaks.screen.checkin_action', 'سجّل حضور النهارده') }}</button>
        </form>
    @endunless
@endsection
