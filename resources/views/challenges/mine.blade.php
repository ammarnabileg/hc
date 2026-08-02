@extends('layouts.app')
@section('title', 'تحدّياتي')

@section('content')
    <x-page-header
        title="تحدّياتي"
        subtitle="اللي شغّال دلوقتي واللي خلص — كلّه في مكان واحد."
        :breadcrumbs="[['label' => 'التحديات', 'url' => route('challenges.index')], ['label' => 'تحدّياتي']]">
        <x-slot:action>
            <a href="{{ route('challenges.index') }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">تحدّي جديد</a>
        </x-slot:action>
    </x-page-header>

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'running', 'label' => 'جارية', 'url' => route('challenges.mine', ['tab' => 'running']), 'count' => $running->count()],
        ['key' => 'done', 'label' => 'منتهية', 'url' => route('challenges.mine', ['tab' => 'done']), 'count' => $done->count()],
    ]" />

    @if ($tab === 'running')
        @if ($running->isEmpty())
            <x-empty message="مفيش تحدّي شغّال دلوقتي — أوّل خطوة مستنّياك."
                     action="التحدّيات المتاحة" :href="route('challenges.index')" />
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($running as $participation)
                    @php
                        $challenge = $participation->challenge;
                        $seconds = $service->secondsLeft($participation);
                        $total = count($service->items($challenge));
                        $answered = count($participation->progress['answers'] ?? []);
                        $percent = $total > 0 ? round($answered / $total * 100) : 0;
                        $ratio = $challenge->duration_minutes && $seconds !== null
                            ? $seconds / max(1, $challenge->duration_minutes * 60)
                            : 1;
                        $state = $ratio > 0.5 ? 'ok' : ($ratio > 0.2 ? 'warn' : 'danger');
                    @endphp

                    <article class="card p-4 flex flex-col gap-3 animate-fadeup">
                        <div class="flex items-start justify-between gap-3">
                            <span style="color: {{ $challenge->color ?: 'var(--color-brand-400)' }}">
                                @include('challenges.components.war-icon', ['type' => $challenge->limits['type'] ?? 'default', 'size' => 36, 'label' => $challenge->name_ar])
                            </span>
                            {{-- عدّاد الوقت بلون الحالة ورمزها (2.16-ب) --}}
                            <x-state-badge :state="$state" :label="$seconds === null ? 'بلا وقت' : 'باقي '.\Carbon\CarbonInterval::seconds($seconds)->cascade()->forHumans(['short' => true, 'parts' => 2])" />
                        </div>

                        <h2 class="font-bold">{{ $challenge->name_ar }}</h2>

                        <div>
                            <div class="flex items-center justify-between text-xs mb-1" style="color: var(--text-muted)">
                                <span>تقدّمي</span><span>{{ $answered }} / {{ $total }}</span>
                            </div>
                            <div class="h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                                <div class="h-full motion-standard" style="width: {{ $percent }}%; background: var(--color-brand-500)"></div>
                            </div>
                        </div>

                        <a href="{{ route('challenges.play', $participation) }}"
                           class="btn mt-auto w-full inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                           style="background: var(--color-brand-500); color: #04201c">كمّل</a>
                    </article>
                @endforeach
            </div>
        @endif
    @else
        @if ($done->isEmpty())
            <x-empty message="لسّه مخلّصتش تحدّي — أوّل واحد هيبان هنا."
                     action="التحدّيات المتاحة" :href="route('challenges.index')" />
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($done as $participation)
                    @php
                        $challenge = $participation->challenge;
                        $total = count($service->items($challenge));
                        $rewards = $challenge->rewards ?? [];
                        $labels = ['win' => 'فوز', 'lose' => 'مكتمل', 'draw' => 'تعادل'];
                        $states = ['win' => 'ok', 'lose' => 'idle', 'draw' => 'warn'];
                    @endphp

                    <article class="card p-4 flex flex-col gap-3 animate-fadeup">
                        <div class="flex items-start justify-between gap-3">
                            <span style="color: {{ $challenge->color ?: 'var(--color-brand-400)' }}">
                                @include('challenges.components.war-icon', ['type' => $challenge->limits['type'] ?? 'default', 'size' => 36, 'label' => $challenge->name_ar])
                            </span>
                            <x-state-badge :state="$states[$participation->result] ?? 'idle'"
                                           :label="$labels[$participation->result] ?? 'مكتمل'" />
                        </div>

                        <h2 class="font-bold">{{ $challenge->name_ar }}</h2>

                        <dl class="grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                                <dt style="color: var(--text-muted)">الدرجة</dt>
                                <dd class="font-bold mt-0.5">{{ (int) $participation->score }} / {{ $total }}</dd>
                            </div>
                            <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                                <dt style="color: var(--text-muted)">المكافأة المصروفة</dt>
                                <dd class="font-bold mt-0.5">
                                    {{ $participation->result === 'win' && ($rewards['xp'] ?? 0) ? $rewards['xp'].' XP' : '—' }}
                                </dd>
                            </div>
                        </dl>

                        @if (($participation->progress['auto_submitted'] ?? false))
                            <p class="text-xs" style="color: var(--text-muted)">⏱ الوقت خلص فاتسلّم تلقائيًّا — وكلّ إجاباتك اتحسبت.</p>
                        @endif

                        <a href="{{ route('challenges.result', $participation) }}"
                           class="mt-auto w-full inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                           style="background: var(--surface-sunken); color: var(--text)">شوف النتيجة</a>
                    </article>
                @endforeach
            </div>
        @endif
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('challenges.index') }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">تحدّي جديد</a>
@endsection
