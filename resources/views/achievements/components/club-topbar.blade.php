@php
    /**
     * شريط نادي الخامسة العلويّ (الدستور 7.2).
     *
     * «شريط علويّ يظهر من 4:50 ص إلى 5:20 ص **بتوقيت كلّ مستخدم المحلّيّ**،
     * المتدرّب يسجّل حضوره خلاله ⟵ يحصل على XP».
     *
     * لماذا يحسب نفسه هنا؟ لأنّه يظهر في كلّ الصفحات فلا كنترولر واحد يغذّيه؛
     * والفحص مرتَّب من الأرخص للأغلى: التوجل ثمّ النافذة (من كاش الإعدادات)
     * ولا نلمس قاعدة البيانات إلّا داخل الثلاثين دقيقة فعلًا.
     */
    use App\Services\Gamification\StreakService;

    $clubUser = auth()->user();
    $showClubBar = false;
    $clubXp = 0;

    if ($clubUser && setting('streaks.enabled', true) && \Illuminate\Support\Facades\Route::has('achievements.streak.checkin')) {
        $clubStreaks = app(StreakService::class);

        if ($clubStreaks->windowIsOpenFor($clubUser) && ! $clubStreaks->recordedToday($clubUser)) {
            $showClubBar = true;
            $clubXp = $clubStreaks->xpForClubDay($clubStreaks->clubDaysCount($clubUser) + 1);
        }
    }
@endphp

@if ($showClubBar)
    <div class="flex items-center justify-center gap-3 px-4 py-2 text-sm flex-wrap"
         style="background: color-mix(in srgb, var(--color-state-honor) 18%, var(--surface)); color: var(--text)">
        {{-- أيقونة الشروق: SVG مرسومة بهويّة المنصّة — بلا مكتبات (2.16-ج) --}}
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
            <path d="M4 18h16M7 18a5 5 0 0 1 10 0M12 4v3M5 8l2 2M19 8l-2 2" />
        </svg>

        <span>نادي الخامسة مفتوح دلوقتي — سجّل حضورك وخُد
            <strong style="color: var(--color-state-honor)">+{{ (int) $clubXp }} XP</strong>
        </span>

        <form method="post" action="{{ route('achievements.streak.checkin') }}">
            @csrf
            <button type="submit"
                    class="btn rounded-xl px-3 py-1.5 text-xs font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c; min-height: 36px">
                سجّل حضوري
            </button>
        </form>
    </div>
@endif
