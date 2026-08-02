@extends('layouts.app')
@section('title', 'نتيجة: '.$challenge->name_ar)

@section('content')
    @php
        $result = $side->result;
        $moved = (float) ($match->settlement['moved'] ?? 0);
        $penalty = (float) ($match->settlement['penalty'] ?? 0);
        $isSurvival = $match->war_type === 'survival';
        $myScore = $isSurvival ? (int) $side->reached_index : (int) $side->score;
        $rivalScore = $isSurvival ? (int) $rivalSide->reached_index : (int) $rivalSide->score;

        $headline = match (true) {
            $waiting => 'مستنّيين خصمك يخلّص…',
            $result === 'win' => 'كسبت المواجهة',
            $result === 'draw' => 'تعادل',
            (bool) $side->withdrew => 'انسحبت من المواجهة',
            default => 'خسرت المواجهة',
        };

        $delta = match (true) {
            $result === 'win' => '+'.(int) $moved.' تذكرة',
            $result === 'draw' => 'لا خصم ولا إضافة',
            default => '−'.(int) ($moved + $penalty).' تذكرة',
        };

        $shareUrl = \Illuminate\Support\Facades\Route::has('images.achievement')
            ? route('images.achievement', ['type' => 'war', 'id' => $match->id])
            : null;
    @endphp

    <x-page-header
        :title="$headline"
        :subtitle="$challenge->name_ar"
        :breadcrumbs="[
            ['label' => 'التحديات', 'url' => route('challenges.index')],
            ['label' => 'تحدّياتي', 'url' => route('challenges.mine', ['tab' => 'done'])],
            ['label' => 'النتيجة'],
        ]" />

    <div class="max-w-2xl">
        @if ($waiting)
            <div class="card p-4 mb-4 text-sm flex items-center gap-2" role="status" style="border-color: var(--color-state-warn)">
                <span aria-hidden="true">▲</span>
                <span>
                    سلّمت وخلّصت — باقي <b data-decision-left>{{ $decisionSecondsLeft ?? 0 }}</b> ثانية
                    وتُقفَل المواجهة وتظهر النتيجة.
                </span>
            </div>
        @endif

        {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <x-kpi label="{{ $isSurvival ? 'نجوت لسؤال' : 'نقاطي' }}" :value="$myScore" icon="goal" />
            <x-kpi label="نقاط خصمي" :value="$rivalScore" icon="shield" />
            <x-kpi label="عدد الأسئلة" :value="$total" icon="note" />
            <x-kpi label="تذاكري دلوقتي" :value="(int) $ticketsBalance" icon="ticket" />
        </div>

        <div class="card p-5">
            <div class="flex items-center gap-3">
                <span style="color: {{ $challenge->color ?: 'var(--color-brand-400)' }}">
                    @include('challenges.components.war-icon', [
                        'type' => $match->war_type, 'size' => 40, 'label' => $challenge->name_ar,
                    ])
                </span>
                <div class="min-w-0">
                    <h2 class="font-bold truncate">{{ $challenge->name_ar }}</h2>
                    <p class="text-xs" style="color: var(--text-muted)">
                        {{ $side->finished_at?->diffForHumans() }}
                    </p>
                </div>
                <span class="ms-auto">
                    <x-state-badge
                        :state="$waiting ? 'warn' : (['win' => 'ok', 'draw' => 'warn'][$result] ?? 'idle')"
                        :label="$waiting ? 'في انتظار الحسم' : (['win' => 'فوز', 'draw' => 'تعادل', 'lose' => 'خسارة'][$result] ?? 'مكتمل')" />
                </span>
            </div>

            @unless ($waiting)
                <div class="mt-5 rounded-xl p-4" style="background: var(--surface-sunken)">
                    <div class="flex items-center justify-between text-sm">
                        <span style="color: var(--text-muted)">محصّلة المواجهة</span>
                        <span class="font-extrabold">{{ $delta }}</span>
                    </div>
                    {{-- شرح القاعدة صراحةً: ما يكسبه الفائز هو نفسه ما يخسره الخاسر (15.2-6) --}}
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        اللي بيكسبه الفائز هو بعينه اللي بيخسره الخاسر — مفيش تذكرة بتتولد من العدم.
                        @if ($penalty > 0)
                            وعقوبة الانسحاب ({{ (int) $penalty }} تذاكر) بتتشال من الاقتصاد ومبتروحش لحدّ.
                        @endif
                    </p>
                </div>

                @if ($result === 'draw')
                    <p class="text-sm mt-4" style="color: var(--text-muted)">
                        تعادل بعدد {{ $isSurvival ? 'الأسئلة اللي نجوتوا فيها' : 'الإجابات' }} — فمحدّش خسر ومحدّش كسب.
                    </p>
                @elseif ($result !== 'win')
                    <p class="text-sm mt-4" style="color: var(--text-muted)">
                        مجهودك مش رايح — كلّ مواجهة بتقرّبك. جهّز نفسك وارجع الساحة.
                    </p>
                @endif
            @endunless

            <div class="mt-5 flex flex-wrap items-center gap-2">
                @if ($shareUrl && $result === 'win')
                    <a href="{{ $shareUrl }}"
                       class="btn rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c; min-height: 44px">لقطة إنجاز قابلة للمشاركة</a>
                @endif

                @if ($challenge->is_active && ! $waiting)
                    <a href="{{ route('challenges.arena', $challenge) }}"
                       class="btn rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c; min-height: 44px">ارجع الساحة</a>
                @endif

                <a href="{{ route('challenges.leaderboard') }}"
                   class="rounded-xl px-4 py-2.5 text-sm motion-standard"
                   style="background: var(--surface-sunken); color: var(--text); min-height: 44px">لوحة الأبطال</a>
            </div>
        </div>
    </div>

    @if ($celebration)
        @include('challenges.components.celebration', ['celebration' => $celebration, 'shareUrl' => $shareUrl])
    @endif

    @if ($waiting)
        @push('scripts')
            <script>
                // إعادة التحميل بعد الحسم — والخادم وحده يقرّر متى (15.1)
                setTimeout(() => window.location.reload(), Math.max(2, {{ (int) ($decisionSecondsLeft ?? 5) }} + 1) * 1000);
            </script>
        @endpush
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('challenges.mine', ['tab' => 'done']) }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">تحدّياتي</a>
@endsection
