@extends('layouts.app')
@section('title', $card['headline'])

@section('content')
    <x-page-header
        :title="$challenge->name_ar"
        :subtitle="$card['tagline']"
        :breadcrumbs="[
            ['label' => setting('challenges.index.title', 'التحديات'), 'url' => route('challenges.index')],
            ['label' => $challenge->name_ar],
        ]" />

    <div class="max-w-3xl">
        {{-- شاشة الساحة: أيقونة ضخمة + هيدلاين + زرّ واحد رئيسيّ (15.1) --}}
        <section class="card p-6 md:p-8 text-center">
            <span class="inline-flex" style="color: {{ $challenge->color ?: 'var(--color-brand-400)' }}">
                @include('challenges.components.war-icon', ['type' => $card['type'], 'size' => 96, 'label' => $challenge->name_ar])
            </span>

            <h1 class="text-2xl font-extrabold mt-4">{{ $card['headline'] }}</h1>
            <p class="text-sm mt-2" style="color: var(--text-muted)">{{ $card['tagline'] }}</p>

            <p class="text-xs mt-4" style="color: var(--text-muted)">
                <x-icon name="trophy" size="16" /> {{ str_replace(':n', (int) $card['win'], (string) setting('challenges.arena.win_line', 'الفوز: +:n تذكرة')) }} | <x-icon name="warning" size="16" /> {{ str_replace(':n', (int) $card['loss'], (string) setting('challenges.arena.loss_line', 'الخسارة: −:n تذكرة')) }}
            </p>
            <p class="text-xs mt-1" style="color: var(--text-muted)">
                {{ str_replace(':n', (int) $card['withdraw'], (string) setting('challenges.arena.withdraw_line', 'الانسحاب: −:n تذاكر')) }}
            </p>
            <p class="text-xs mt-1" style="color: var(--text-muted)">
                {{ str_replace([':gate', ':balance'], [(int) $card['gate'], (int) $ticketsBalance], (string) setting('challenges.arena.gate_line', 'شرط الدخول: رصيدك ≥ :gate تذكرة · رصيدك دلوقتي :balance')) }}
            </p>

            <div class="mt-6">
                @if ($running)
                    <a href="{{ route('challenges.play', $running) }}"
                       class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.arena.resume_match', 'ارجع لمواجهتك') }}</a>

                @elseif (! $challenge->is_active)
                    <x-state-badge state="idle" :label="setting('challenges.arena.paused_badge', 'الساحة موقوفة مؤقّتًا')" />

                @elseif (! $card['bank_ready'])
                    <p class="text-sm" style="color: var(--color-state-warn)">
                        ▲ {{ setting('challenges.arena.bank_not_ready', 'بنك أسئلة الساحة لسّه مش جاهز — جرّب ساحة تانية دلوقتي.') }}
                    </p>

                @elseif ($isReadyHere)
                    <x-state-badge state="ok" :label="setting('challenges.arena.ready_badge', 'إنت مستعدّ — استنّى محارب أو اتحدّى واحدًا')" />

                @elseif ($readiness)
                    {{-- الاستعداد حصريّ: نوع واحد في اللحظة الواحدة (15.0) --}}
                    <p class="text-sm mb-3" style="color: var(--color-state-warn)">
                        ▲ {{ str_replace(':arena', (string) $readiness->challenge?->name_ar, (string) setting('challenges.arena.ready_elsewhere', 'إنت مستعدّ لـ«:arena» — ألغِ استعدادك من الشريط فوق الأوّل.')) }}
                    </p>

                @else
                    <form method="post" action="{{ route('challenges.ready', $challenge) }}">
                        @csrf
                        <button type="submit"
                                class="btn inline-flex items-center justify-center rounded-xl px-8 py-3 text-base font-bold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('challenges.arena.ready_action', 'استعداد') }}</button>
                    </form>
                @endif
            </div>
        </section>

        @if ($isReadyHere)
            <section class="mt-6">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h2 class="font-bold">{{ setting('challenges.arena.fighters_title', 'المحاربون الجاهزون') }}</h2>
                    {{-- ⭐ «X جاهزين دلوقتي» بحدّ 3، وتحته القائمة نفسها + تأطير
                         الريادة «كن أوّل محارب في الساحة» (2.9-7) --}}
                    <x-social-proof context="war" icon="war" :count="count($fighters)" />
                </div>

                <div data-fighters class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @forelse ($fighters as $fighter)
                        <article class="card p-4 flex items-center gap-3">
                            <x-avatar :user="$fighter['user']" size="12" />
                            <div class="min-w-0">
                                <p class="font-bold truncate">{{ $fighter['user']->name }}</p>
                                <p class="text-xs" style="color: var(--text-muted)">
                                    {{ str_replace([':wins', ':losses'], [$fighter['wins'], $fighter['losses']], (string) setting('challenges.arena.fighter_record', 'الفوز: :wins · الخسارة: :losses')) }}
                                </p>
                            </div>
                            <form method="post" class="ms-auto"
                                  action="{{ route('challenges.duel', [$challenge, $fighter['user']]) }}">
                                @csrf
                                <button type="submit"
                                        class="btn rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('challenges.arena.duel_action', 'تحدّاه') }}</button>
                            </form>
                        </article>
                    @empty
                        <div class="sm:col-span-2">
                            <x-empty :message="setting('challenges.arena.fighters_empty', 'إنت لوحدك في الساحة دلوقتي — كن أوّل محارب فيها')" />
                        </div>
                    @endforelse
                </div>
            </section>

            @push('scripts')
                @php
                    // نصوص القائمة الحيّة من الإعدادات لا من السكربت (2.13)
                    $arenaWords = [
                        'empty' => (string) setting('challenges.arena.fighters_empty', 'إنت لوحدك في الساحة دلوقتي — كن أوّل محارب فيها'),
                        'record' => (string) setting('challenges.arena.fighter_record', 'الفوز: :wins · الخسارة: :losses'),
                        'duel' => (string) setting('challenges.arena.duel_action', 'تحدّاه'),
                    ];
                @endphp
                <script>
                    (() => {
                        // القائمة تتحدّث لحظة دخول أحدهم حربًا أو إلغائه الاستعداد (15.1)
                        const box = document.querySelector('[data-fighters]');
                        const url = @json(route('challenges.fighters', $challenge));
                        const token = document.querySelector('meta[name="csrf-token"]').content;
                        const arenaWords = @json($arenaWords);
                        const empty = arenaWords.empty;
                        if (!box) return;

                        const card = (f) => `
                            <article class="card p-4 flex items-center gap-3">
                                <span class="avatar inline-flex items-center justify-center rounded-full shrink-0 font-semibold"
                                      style="width:3rem;height:3rem;background:var(--surface-sunken);color:var(--text-muted)">${f.name.slice(0, 1)}</span>
                                <div class="min-w-0">
                                    <p class="font-bold truncate">${f.name}</p>
                                    <p class="text-xs" style="color: var(--text-muted)">${arenaWords.record.split(':wins').join(f.wins).split(':losses').join(f.losses)}</p>
                                </div>
                                <form method="post" class="ms-auto" action="${f.url}">
                                    <input type="hidden" name="_token" value="${token}">
                                    <button type="submit" class="btn rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                                            style="background: var(--color-brand-500); color:#04201c; min-height:44px">${arenaWords.duel}</button>
                                </form>
                            </article>`;

                        const refresh = async () => {
                            try {
                                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                                const data = await res.json();
                                if (!data.ready) return window.location.reload();
                                box.innerHTML = data.fighters.length
                                    ? data.fighters.map(card).join('')
                                    : `<div class="sm:col-span-2"><div class="card p-8 text-center"><p class="text-sm" style="color: var(--text-muted)">${empty}</p></div></div>`;
                            } catch {
                                // انقطاع الشبكة لا يفرّغ القائمة — نُبقي آخر لقطة كما هي
                            }
                        };

                        setInterval(refresh, 5000);
                    })();
                </script>
            @endpush
        @endif
    </div>
@endsection

@section('mobile_action')
    @if (! $running && ! $readiness && $challenge->is_active && $card['bank_ready'])
        <form method="post" action="{{ route('challenges.ready', $challenge) }}">
            @csrf
            <button type="submit"
                    class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.arena.ready_action', 'استعداد') }}</button>
        </form>
    @else
        <a href="{{ route('challenges.index') }}"
           class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.arena.all_arenas', 'كلّ الساحات') }}</a>
    @endif
@endsection
