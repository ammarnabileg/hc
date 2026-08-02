@extends('layouts.app')
@section('title', 'نتيجة: '.$challenge->name_ar)

@section('content')
    @php
        $rewards = $challenge->rewards ?? [];
        $score = (int) $participation->score;
        $percent = $total > 0 ? round($score / $total * 100) : 0;
        $auto = (bool) ($participation->progress['auto_submitted'] ?? false);

        $headline = match ($participation->result) {
            'win' => 'كسبت التحدّي 🎉',
            'draw' => 'تعادل — قريّب أوي',
            default => 'خلّصت التحدّي',
        };

        // لقطة الإنجاز يبنيها استوديو الصور (مجال آخر) — نشير له بحماية Route::has
        $shareUrl = \Illuminate\Support\Facades\Route::has('images.achievement')
            ? route('images.achievement', ['type' => 'challenge', 'id' => $participation->id])
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
        @if ($auto)
            <div class="card p-3 mb-4 text-sm flex items-center gap-2" style="border-color: var(--color-state-warn)">
                <span aria-hidden="true">▲</span>
                <span>الوقت خلص فسلّمنا عنك تلقائيًّا — وكلّ إجاباتك المحفوظة اتحسبت.</span>
            </div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <x-kpi label="درجتي" :value="$score" icon="🎯" />
            <x-kpi label="من" :value="$total" icon="📋" />
            <x-kpi label="نسبتي" :value="$percent" icon="📈" />
            <x-kpi label="XP المكتسَب"
                   :value="$participation->result === 'win' ? (int) ($rewards['xp'] ?? 0) : 0" icon="⭐" />
        </div>

        <div class="card p-5">
            <div class="flex items-center gap-3">
                <span style="color: {{ $challenge->color ?: 'var(--color-brand-400)' }}">
                    @include('challenges.components.war-icon', ['type' => $challenge->limits['type'] ?? 'default', 'size' => 40, 'label' => $challenge->name_ar])
                </span>
                <div>
                    <h2 class="font-bold">{{ $challenge->name_ar }}</h2>
                    <p class="text-xs" style="color: var(--text-muted)">
                        {{ $participation->finished_at?->diffForHumans() }}
                    </p>
                </div>
                <span class="ms-auto">
                    <x-state-badge :state="['win' => 'ok', 'draw' => 'warn'][$participation->result] ?? 'idle'"
                                   :label="['win' => 'فوز', 'draw' => 'تعادل'][$participation->result] ?? 'مكتمل'" />
                </span>
            </div>

            <div class="mt-4 h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                <div class="h-full" style="width: {{ $percent }}%; background: var(--color-brand-500)"></div>
            </div>

            @if ($participation->result !== 'win')
                {{-- رسالة محايدة تشرح وتعطي الخطوة التالية — بلا تجريح (2.17-ج) --}}
                <p class="text-sm mt-4" style="color: var(--text-muted)">
                    مجهودك مش رايح — كلّ محاولة بتقرّبك. جرّب تاني وانت أقوى.
                </p>
            @endif

            <div class="mt-5 flex flex-wrap items-center gap-2">
                @if ($shareUrl)
                    <a href="{{ $shareUrl }}"
                       class="btn rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">لقطة إنجاز قابلة للمشاركة</a>
                @endif

                @if ($challenge->is_active)
                    <form method="post" action="{{ route('challenges.enter', $challenge) }}">
                        @csrf
                        <button type="submit" class="rounded-xl px-4 py-2.5 text-sm motion-standard"
                                style="background: var(--surface-sunken); color: var(--text)">إعادة المحاولة</button>
                    </form>
                @endif

                <a href="{{ route('challenges.leaderboard') }}"
                   class="rounded-xl px-4 py-2.5 text-sm motion-standard"
                   style="background: var(--surface-sunken); color: var(--text)">لوحة الأبطال</a>
            </div>
        </div>
    </div>

    @if ($celebration)
        @include('challenges.components.celebration', ['celebration' => $celebration, 'shareUrl' => $shareUrl])
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('challenges.mine', ['tab' => 'done']) }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">تحدّياتي</a>
@endsection
