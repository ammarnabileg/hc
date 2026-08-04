@extends('layouts.app')
@section('title', setting('challenges.mine.title', 'تحدّياتي'))

@section('content')
    <x-page-header
        :title="setting('challenges.mine.title', 'تحدّياتي')"
        :subtitle="setting('challenges.mine.subtitle', 'اللي شغّال دلوقتي واللي خلص — كلّه في مكان واحد.')"
        :breadcrumbs="[['label' => setting('challenges.index.title', 'التحديات'), 'url' => route('challenges.index')], ['label' => setting('challenges.mine.title', 'تحدّياتي')]]">
        <x-slot:action>
            <a href="{{ route('challenges.index') }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.mine.arenas_action', 'ساحات الحرب') }}</a>
        </x-slot:action>
    </x-page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-kpi :label="setting('challenges.mine.kpi_wins', 'فوزي')" :value="(int) $stat->wins" icon="trophy" />
        <x-kpi :label="setting('challenges.mine.kpi_losses', 'خسارتي')" :value="(int) $stat->losses" icon="warning" />
        <x-kpi :label="setting('challenges.mine.kpi_draws', 'تعادلي')" :value="(int) $stat->draws" icon="evaluation" />
        <x-kpi :label="setting('challenges.mine.kpi_focus_minutes', 'دقائق تركيزي')" :value="(int) $stat->focus_minutes" icon="shield" />
    </div>

    @if ($stat->loss_streak > 0)
        <div class="card p-3 mb-4 text-sm flex items-center gap-2" style="border-color: var(--color-state-warn)">
            <span aria-hidden="true">▲</span>
            <span>
                {{ str_replace(':n', (int) $stat->loss_streak, (string) setting('challenges.mine.loss_streak_note', 'عندك :n خسارة ورا بعض — لو وصلت للحدّ هتختفي من قائمة الجاهزين (حماية ليك)، وتفضل قادر تتحدّى الناس لحدّ ما تكسر السلسلة بفوز.')) }}
            </span>
        </div>
    @endif

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'running', 'label' => setting('challenges.mine.tab_running', 'جارية'), 'url' => route('challenges.mine', ['tab' => 'running']), 'count' => $running->count()],
        ['key' => 'done', 'label' => setting('challenges.mine.tab_done', 'منتهية'), 'url' => route('challenges.mine', ['tab' => 'done']), 'count' => $done->count()],
    ]" />

    @php $list = $tab === 'running' ? $running : $done; @endphp

    @if ($list->isEmpty())
        <x-empty
            :message="$tab === 'running' ? setting('challenges.mine.empty_running', 'مفيش مواجهة شغّالة دلوقتي — الساحة مستنّياك.') : setting('challenges.mine.empty_done', 'لسّه مخلّصتش مواجهة — أوّل واحدة هتبان هنا.')"
            :action="setting('challenges.mine.arenas_action', 'ساحات الحرب')" :href="route('challenges.index')" />
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($list as $side)
                @php
                    $challenge = $side->challenge;
                    $match = $matches[$side->war_match_id] ?? null;
                    $type = $match?->war_type ?? 'default';
                    $labels = [
                        'win' => setting('challenges.mine.result_win', 'فوز'),
                        'lose' => setting('challenges.mine.result_lose', 'خسارة'),
                        'draw' => setting('challenges.mine.result_draw', 'تعادل'),
                    ];
                    $states = ['win' => 'ok', 'lose' => 'danger', 'draw' => 'warn'];
                @endphp

                <article class="card p-4 flex flex-col gap-3 animate-fadeup">
                    <div class="flex items-start justify-between gap-3">
                        <span style="color: {{ $challenge->color ?: 'var(--color-brand-400)' }}">
                            @include('challenges.components.war-icon', ['type' => $type, 'size' => 36, 'label' => $challenge->name_ar])
                        </span>

                        @if ($tab === 'running')
                            <x-state-badge state="warn" :label="setting('challenges.mine.badge_running', 'شغّالة دلوقتي')" />
                        @else
                            <x-state-badge :state="$states[$side->result] ?? 'idle'"
                                           :label="$labels[$side->result] ?? setting('challenges.mine.badge_done', 'مكتملة')" />
                        @endif
                    </div>

                    <h2 class="font-bold">{{ $challenge->name_ar }}</h2>

                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">{{ setting('challenges.mine.my_score', 'نقاطي') }}</dt>
                            <dd class="font-bold mt-0.5">
                                {{ $type === 'survival' ? (int) $side->reached_index : (int) $side->score }}
                            </dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">{{ setting('challenges.mine.settlement', 'المحصّلة') }}</dt>
                            <dd class="font-bold mt-0.5">
                                @if ($side->result === 'win')
                                    +{{ (int) ($match->settlement['moved'] ?? 0) }} <x-icon name="ticket" size="16" />
                                @elseif ($side->result === 'lose')
                                    −{{ (int) (($match->settlement['moved'] ?? 0) + ($match->settlement['penalty'] ?? 0)) }} <x-icon name="ticket" size="16" />
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if ($side->withdrew)
                        <p class="text-xs" style="color: var(--text-muted)">↩ {{ setting('challenges.mine.withdrew_note', 'انسحبت من المواجهة دي.') }}</p>
                    @endif

                    @if ($match)
                        <a href="{{ $tab === 'running' ? route('challenges.play', $match) : route('challenges.result', $match) }}"
                           class="btn mt-auto w-full inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                           style="background: {{ $tab === 'running' ? 'var(--color-brand-500)' : 'var(--surface-sunken)' }};
                                  color: {{ $tab === 'running' ? '#04201c' : 'var(--text)' }}; min-height: 44px">
                            {{ $tab === 'running' ? setting('challenges.mine.continue_action', 'كمّل المواجهة') : setting('challenges.mine.result_action', 'شوف النتيجة') }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('challenges.index') }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.mine.arenas_action', 'ساحات الحرب') }}</a>
@endsection
