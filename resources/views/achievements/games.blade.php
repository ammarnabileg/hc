@extends('layouts.app')
@section('title', 'الألعاب')

@section('content')
    <x-page-header
        title="الألعاب"
        subtitle="تُلعب بتذكرة، وفايدتها XP يزيد رصيدك في الليدر بورد."
        :breadcrumbs="[['label' => 'إنجازاتي'], ['label' => 'الألعاب']]" />

    @if ($games->isEmpty())
        {{-- الحالة الفارغة: سطر واحد + زرّ واحد — وتشجّع ولا تعاتب (2.15-د · 2.17-ج) --}}
        <x-empty message="قريبًا — بنجهّز أوّل لعبة، وهتلاقيها هنا أوّل ما تنزل."
                 action="ساحات الحرب" :href="route('challenges.index')" />
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($games as $game)
                <article class="card p-4 flex flex-col gap-3 animate-fadeup">
                    {{-- الغلاف SVG مرسوم — بلا أيّ مكتبة أيقونات (2.16-ج) --}}
                    <span style="color: var(--color-brand-400)">
                        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.6"
                             stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="{{ $game->name_ar }}">
                            <rect x="2.5" y="7" width="19" height="10" rx="4" />
                            <path d="M7 10.5v3M5.5 12h3" />
                            <circle cx="16" cy="11.2" r=".9" fill="currentColor" stroke="none" />
                            <circle cx="18" cy="13.4" r=".9" fill="currentColor" stroke="none" />
                        </svg>
                    </span>

                    <div>
                        <h2 class="font-bold">{{ $game->name_ar }}</h2>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $game->description }}</p>
                    </div>

                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">التكلفة</dt>
                            <dd class="font-bold mt-0.5">{{ (int) $game->ticket_cost }} 🎟️</dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">المكافأة</dt>
                            <dd class="font-bold mt-0.5">{{ (int) $game->xp_reward }} XP</dd>
                        </div>
                    </dl>

                    <div class="mt-auto pt-1">
                        <x-state-badge
                            :state="['active' => 'ok', 'soon' => 'warn', 'paused' => 'idle'][$game->status] ?? 'idle'"
                            :label="['active' => 'متاحة', 'soon' => 'قريبًا', 'paused' => 'موقوفة'][$game->status] ?? 'قريبًا'" />

                        @if ($game->status === 'soon' && $game->soon_text)
                            <p class="text-xs mt-2" style="color: var(--text-muted)">{{ $game->soon_text }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
