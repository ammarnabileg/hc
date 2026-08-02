@extends('layouts.app')
@section('title', 'الستريك ونادي الخامسة')

@section('content')
    <x-page-header
        title="الستريك ونادي الخامسة"
        subtitle="استمراريّتك اليوميّة — يوم ورا يوم، والعادة بتتبني."
        :breadcrumbs="[['label' => 'إنجازاتي'], ['label' => 'الستريك']]">
        <x-slot:action>
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
            <h2 class="font-bold text-sm mb-2">نادي الخامسة صباحًا 🌅</h2>
            <p class="text-xs leading-relaxed" style="color: var(--text-muted)">
                سجّل حضورك بين <strong style="color: var(--text)">{{ $window['start'] }}</strong>
                و<strong style="color: var(--text)">{{ $window['end'] }}</strong> بتوقيتك المحلّيّ،
                فتُحسَب لك يوم في النادي وتاخد XP الحضور.
            </p>

            <ul class="mt-3 space-y-1 text-xs" style="color: var(--text-muted)">
                <li>• الأيّام مش لازم متتابعة — الهدف بناء العادة.</li>
                <li>• كلّ {{ (int) setting('streaks.reward_days', 7) }} أيّام متواصلة = مكافأة تذكرة هدية 🎟️.</li>
                <li>• درع التجميد بيحمي يوم فايت من كسر السلسلة.</li>
            </ul>

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
