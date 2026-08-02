@php
    /**
     * منصّة التتويج للتوب 3 (7.3): **الأوّل في النصّ وأعلى**، ثمّ الثاني والثالث،
     * وأعمدة بارتفاعات مختلفة. لكلٍّ: صورة + اسم + الدولة والكود + النقاط.
     *
     * المعنى لا يعتمد على اللون وحده (2.16-ب): مع كلّ عمود **رقم مركزه** ولقبه.
     * والترتيب البصريّ (2 · 1 · 3) يخدم الشكل، أمّا الترتيب المنطقيّ فبالأرقام.
     */
    $podium = $podium->keyBy('rank');
    $heights = ['1' => 'h-24', '2' => 'h-16', '3' => 'h-12'];
    $tones = ['1' => 'var(--color-state-honor)', '2' => 'var(--text-muted)', '3' => 'var(--color-brand-400)'];
    $order = [2, 1, 3];
@endphp

<section class="card p-4 mb-4" aria-label="{{ setting('leaderboard.podium.title', 'منصّة التتويج') }}">
    <h2 class="text-sm font-bold mb-3">{{ setting('leaderboard.podium.title', 'منصّة التتويج') }}</h2>

    <ol class="flex items-end justify-center gap-3 sm:gap-6">
        @foreach ($order as $place)
            @php $row = $podium->get($place); @endphp

            @if ($row)
                <li class="flex-1 max-w-40 text-center">
                    <div class="flex flex-col items-center gap-1">
                        <span class="text-xs font-extrabold tabular-nums" style="color: {{ $tones[$place] }}">
                            #{{ $place }}
                        </span>

                        <x-avatar :user="$row['user']" :size="$place === 1 ? '16' : '12'" />

                        <span class="text-xs font-semibold truncate w-full">{{ $row['user']->name }}</span>

                        <span class="text-[11px] truncate w-full" style="color: var(--text-muted)">
                            {{ $row['user']->country?->name_ar ? $row['user']->country->name_ar.' · ' : '' }}#{{ $row['user']->code }}
                        </span>

                        <span class="text-sm font-extrabold tabular-nums">
                            {{ number_format($row['delta']) }} {{ setting('leaderboard.xp_label', 'XP') }}
                        </span>
                    </div>

                    <div class="{{ $heights[$place] }} mt-2 rounded-t-xl flex items-end justify-center pb-1"
                         style="background: color-mix(in srgb, {{ $tones[$place] }} 22%, transparent)">
                        <span class="text-lg font-extrabold tabular-nums" style="color: {{ $tones[$place] }}">{{ $place }}</span>
                    </div>
                </li>
            @endif
        @endforeach
    </ol>
</section>
