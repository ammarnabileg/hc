@extends('layouts.app')
@section('title', 'الستريك ونادي الخامسة')

@php
    // بطاقة الستريك قابلة للاستخراج كصورة (12.14-هـ)
    $exportSubtitle = 'حتى '.now()->format('Y/m/d');
    $exportRows = [
        ['rank' => 1, 'u' => auth()->id(), 'name' => 'ستريكي الحاليّ', 'value' => (int) $streak->current_days.' يوم'],
        ['rank' => 2, 'u' => auth()->id(), 'name' => 'أطول ستريك', 'value' => (int) $streak->best_days.' يوم'],
        ['rank' => 3, 'u' => auth()->id(), 'name' => 'نادي الخامسة', 'value' => (int) $clubTotal.' يوم'],
    ];
@endphp

@section('content')
    <x-page-header
        title="الستريك ونادي الخامسة"
        subtitle="استمراريّتك اليوميّة — يوم ورا يوم، والعادة بتتبني."
        :breadcrumbs="[['label' => 'إنجازاتي'], ['label' => 'الستريك']]">
        <x-slot:action>
            {{-- ⭐ الستريك ونادي الخامسة ضمن قائمة الاستخراج كصورة (12.14-هـ) --}}
            <x-export-image kind="card" title="الستريك ونادي الخامسة" :subtitle="$exportSubtitle" :rows="$exportRows" />

            @unless ($recordedToday)
                <form method="post" action="{{ route('achievements.streak.checkin') }}">
                    @csrf
                    <button type="submit"
                            class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">سجّل حضور النهارده</button>
                </form>
            @else
                <span class="text-xs" style="color: var(--color-state-ok)">● النهارده اتسجّل</span>
            @endunless
        </x-slot:action>
    </x-page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-kpi label="ستريكي الحاليّ" :value="(int) $streak->current_days" icon="🔥" hint="أيّام متواصلة" />
        <x-kpi label="أطول ستريك (Best)" :value="(int) $streak->best_days" icon="🏅" />
        <x-kpi label="أيّام نادي الخامسة" :value="(int) $streak->club_5am_count" icon="🌅" />
        <x-kpi label="آخر يوم نشط"
               :value="$streak->last_active_date?->translatedFormat('j M') ?? '—'" icon="📆" />
    </div>

    {{-- ⭐ تذكرة مكافأة السلسلة (7.2): زرّ واحد بارز عند اكتمال الدورة --}}
    @if ($rewardDue)
        <div class="card p-4 mb-4 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-start gap-2">
                <x-state-badge state="honor" label="مكافأة جاهزة" />
                <p class="text-sm">
                    كمّلت <strong>{{ (int) $streak->current_days }}</strong> يوم متواصل —
                    تذكرة الهدية في انتظارك 🎟️
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
            <p class="font-semibold">ابدأ من جديد النهارده 🌱</p>
            <p class="mt-1" style="color: var(--text-muted)">
                الستريك اتقطع، وده بيحصل. أطول ستريك عملته ({{ (int) $streak->best_days }} يوم) لسّه محفوظ ليك —
                وأوّل يوم في السلسلة الجديدة بيبدأ بضغطة.
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section class="card p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="font-bold text-sm">أيّامي</h2>

                <form method="get" action="{{ route('achievements.streak') }}">
                    <select name="months" onchange="this.form.submit()"
                            class="rounded-xl px-3 py-1.5 text-xs"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ([1 => 'آخر شهر', 3 => 'آخر 3 شهور', 6 => 'آخر 6 شهور'] as $value => $label)
                            <option value="{{ $value }}" @selected($months === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @include('achievements.components.heatmap', ['heatmap' => $heatmap, 'from' => $from, 'to' => $to])
        </section>

        <section class="card p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="font-bold text-sm">نادي الخامسة صباحًا 🌅</h2>
                {{-- النافذة مفتوحة الآن؟ — بشارة لا بلون وحده (2.16) --}}
                <x-state-badge :state="$windowOpen ? 'ok' : 'idle'"
                               :label="$windowOpen ? 'النافذة مفتوحة' : 'النافذة مقفولة'" />
            </div>

            <p class="text-xs leading-relaxed" style="color: var(--text-muted)">
                سجّل حضورك بين <strong style="color: var(--text)">{{ $window['start'] }}</strong>
                و<strong style="color: var(--text)">{{ $window['end'] }}</strong>
                بتوقيتك المحلّيّ (<strong style="color: var(--text)">{{ $timezone }}</strong>)،
                فتُحسَب لك يوم في النادي وتاخد XP الحضور.
            </p>

            {{-- سلّم XP المتدرّج (7.2) — الرقم الذي يكسبه فعلًا لا وعدٌ مبهم --}}
            <div class="mt-3 rounded-xl px-3 py-2 text-xs" style="background: var(--surface-sunken)">
                <div class="flex items-center justify-between gap-2">
                    <span style="color: var(--text-muted)">حضورك القادم يمنحك</span>
                    <strong style="color: var(--color-brand-400)">+{{ (int) $ladderXp }} XP</strong>
                </div>
                @if ($nextStep)
                    <div class="mt-1" style="color: var(--text-muted)">
                        باقي {{ (int) $nextStep['days_left'] }} يوم حضور توصل لدرجة
                        <strong style="color: var(--text)">{{ (int) $nextStep['xp'] }} XP</strong> لكلّ يوم.
                    </div>
                @endif
            </div>

            <ul class="mt-3 space-y-1 text-xs" style="color: var(--text-muted)">
                <li>• الأيّام مش لازم متتابعة — الهدف بناء العادة.</li>
                <li>• كلّ {{ (int) $rewardEvery }} أيّام متواصلة = مكافأة تذكرة هدية 🎟️.</li>
                <li>• درع التجميد بيحمي يوم فايت من كسر السلسلة.</li>
            </ul>

            {{-- ⭐ درع التجميد (7.2 · 7.1-4): يظهر حين يوجد يوم فايت يستحقّ الحماية --}}
            <div class="mt-4 pt-4" style="border-top: 1px solid var(--border)">
                <h3 class="font-bold text-xs mb-2">درع التجميد 🛡️</h3>

                @if ($freezableDay)
                    <p class="text-xs mb-2" style="color: var(--text-muted)">
                        يوم <strong style="color: var(--text)">{{ $freezableDay->translatedFormat('j F') }}</strong>
                        فايت — احميه بـ{{ (int) $freezeCost }} تذكرة قبل ما السلسلة تنكسر.
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
                    استعملت {{ (int) $freezesUsed }} من {{ (int) $freezeCap }} دروع الشهر ده.
                </p>
            </div>

            @if ($clubDays->isNotEmpty())
                <h3 class="font-bold text-xs mt-4 mb-2">آخر أيّامي في النادي</h3>
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
                    style="background: var(--color-brand-500); color: #04201c">سجّل حضور النهارده</button>
        </form>
    @endunless
@endsection
