@extends('layouts.app')
@section('title', 'الألعاب')

@section('content')
    <x-page-header
        title="الألعاب"
        subtitle="تُلعب بتذكرة، وفايدتها XP يزيد رصيدك في الليدر بورد."
        :breadcrumbs="[['label' => 'إنجازاتي'], ['label' => 'الألعاب']]" />

    @if ($games === [])
        {{-- الحالة الفارغة: سطر واحد + زرّ واحد — وتشجّع ولا تعاتب (2.15-د · 2.17-ج) --}}
        <x-empty message="قريبًا — بنجهّز أوّل لعبة، وهتلاقيها هنا أوّل ما تنزل."
                 action="التحدّيات المتاحة" :href="route('challenges.index')" />
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($games as $game)
                <article class="card p-4 flex flex-col gap-3 animate-fadeup">
                    <span style="color: var(--color-brand-400)">
                        @include('challenges.components.war-icon', ['type' => $game['icon'] ?? 'default', 'size' => 40, 'label' => $game['name'] ?? 'لعبة'])
                    </span>

                    <h2 class="font-bold">{{ $game['name'] ?? '' }}</h2>
                    <p class="text-xs" style="color: var(--text-muted)">{{ $game['description'] ?? '' }}</p>

                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">التكلفة</dt>
                            <dd class="font-bold mt-0.5">{{ (int) $ticketCost }} 🎟️</dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">المكافأة</dt>
                            <dd class="font-bold mt-0.5">{{ (int) ($game['xp'] ?? 0) }} XP</dd>
                        </div>
                    </dl>

                    <div class="mt-auto pt-1">
                        @if (! empty($game['challenge_id']))
                            <form method="post" action="{{ route('challenges.enter', $game['challenge_id']) }}">
                                @csrf
                                <button type="submit"
                                        class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                                        style="background: var(--color-brand-500); color: #04201c">العب</button>
                            </form>
                        @else
                            <p class="text-xs text-center" style="color: var(--text-muted)">قريبًا</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
