@php
    /**
     * ⭐ كارت ترتيب صاحب الحساب — **في الأعلى** (7.3): اسمه + صورته + الدولة
     * والمحافظة + ترتيبه (#52) بأسلوبٍ مليء بالتلعيب.
     *
     * ومعه **مقارنة اجتماعيّة قريبة** (2.9-5): كم XP يفصلني عمّن أمامي مباشرةً —
     * رقمٌ صادق يُحفِّز، لا نسبة مبهمة. و«أفضل من X%» لا تظهر تحت حدّ 20 مشاركًا
     * في نطاق المقارنة (2.9-7) فنعرض الترتيب وحده بدل رقمٍ لا معنى له.
     */
    $rank = (int) $me['rank'];
    $total = max(1, (int) $total);
    $user = $me['user'];
    $minPeers = (int) setting('leaderboard.percentile_min_peers', 20);
    $betterThan = $total > 1 ? (int) round(($total - $rank) / ($total - 1) * 100) : 0;
    $place = trim(($user->country?->name_ar ?? '').($user->governorate?->name_ar ? ' · '.$user->governorate->name_ar : ''));

    /*
     | ⭐ الشقّ الثاني من 2.9-5: **«محتاج N XP تتخطّى [منافس قريب]»**.
     | الفارق محسوب من الصفّ الذي فوقي مباشرةً — رقمٌ حقيقيّ لا تقدير (2.9)،
     | ولا يظهر أصلًا لو لم يكن المنافس معروفًا في هذه الصفحة.
     */
    $rival = $rival ?? null;
    $gap = app(App\Services\Engagement\SocialProof::class)->gapToNext(
        (int) $me['delta'],
        $rival ? (int) $rival['delta'] : null,
        $rival ? (string) $rival['user']->name : null,
    );
@endphp

<section class="card p-4 mb-4 overflow-hidden relative"
         style="border-color: var(--color-brand-500)"
         aria-label="{{ setting('leaderboard.me_card.title', 'ترتيبك') }}">

    <div class="flex items-center gap-4 flex-wrap">
        <div class="relative shrink-0">
            <x-avatar :user="$user" size="16" />
            <span class="absolute -bottom-1 -start-1 rounded-full px-2 py-0.5 text-[11px] font-extrabold tabular-nums"
                  style="background: var(--color-brand-500); color: #04201c">#{{ number_format($rank) }}</span>
        </div>

        <div class="min-w-0 flex-1">
            <div class="font-extrabold truncate">
                {{ $user->name }}
                {{-- الكارت هو صفّي أنا — والتسمية تقولها صراحةً كما في صفّ اللوحة --}}
                <span class="text-xs font-normal" style="color: var(--color-brand-400)">— {{ setting('leaderboard.you_label', 'ده إنت') }}</span>
            </div>
            <div class="text-xs truncate" style="color: var(--text-muted)">
                {{ $place !== '' ? $place.' · ' : '' }}#{{ $user->code }}
            </div>
            <div class="text-xs mt-1" style="color: var(--text-muted)">{{ $rangeLabel }}</div>
        </div>

        <div class="text-center rounded-2xl px-4 py-3"
             style="background: color-mix(in srgb, var(--color-brand-500) 12%, transparent)">
            <div class="text-2xl font-extrabold tabular-nums" style="color: var(--color-brand-500)"
                 data-count-to="{{ (int) $me['delta'] }}">{{ number_format((int) $me['delta']) }}</div>
            <div class="text-[11px] mt-0.5" style="color: var(--text-muted)">{{ setting('leaderboard.xp_label', 'XP') }}</div>
        </div>
    </div>

    <p class="text-xs mt-3" style="color: var(--text-muted)">
        {{ setting('leaderboard.me_card.total_prefix', 'من') }} {{ number_format($total) }}
        @if ($total >= $minPeers)
            · {{ setting('leaderboard.me_card.better_than', 'أفضل من') }} {{ $betterThan }}%
        @endif
        · {{ setting('leaderboard.me_card.lifetime', 'رصيدك الكلّيّ') }} {{ number_format((int) $me['xp']) }}
    </p>

    {{-- ⭐ المقارنة القريبة: هدفٌ واحد واضح على بُعد خطوة (2.9-5) --}}
    @if ($gap['show'])
        <p class="text-xs mt-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1"
           style="background: color-mix(in srgb, var(--color-brand-500) 10%, transparent); color: var(--color-brand-500)">
            <x-icon name="xp" size="14" />
            <span>{{ $gap['text'] }}</span>
        </p>
    @endif
</section>
