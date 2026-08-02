@php
    /**
     * ⭐ بار «أكمل ملفك» ومكافأته 3 تذاكر (21.1-ب).
     *
     * لا يظهر إلّا لمن يحتاجه فعلًا: مستخدم مفعَّل، الحلقة مفعَّلة، والنسبة أقلّ من 100%.
     * وله **زرّ إيقاف صريح** — فلا تذكيرَ بلا مخرج (21.1-د · 2.9).
     * والنسبة والمكافأة كلاهما من الخدمة، فلا يُحسَب شيء في الواجهة.
     */
    $barUser = auth()->user();
    $barService = app(\App\Services\Growth\ProfileCompletion::class);

    /*
     | ⭐ ولا يظهر في كلّ شاشة: **شاشاته إعداد** (`bar_routes`) — لأنّ «سؤال واحد لكلّ
     | شاشة» (2.15-أ-1)، وشاشات العمل والإدارة ليست مكان تذكيرٍ تسويقيّ.
     */
    $barRoutes = (array) setting('growth.profile_completion.bar_routes', ['dashboard', 'profile.me', 'settings.index']);

    $barShow = $barUser
        && $barUser->isActive()
        && $barService->enabled()
        && ! session('growth.completion.dismissed')
        && request()->routeIs(...$barRoutes);

    $barState = $barShow ? $barService->sync($barUser) : null;
@endphp

@if ($barState && $barState['percent'] < 100)
    <section class="card p-3 mb-4 animate-fadeup" aria-label="إكمال الملفّ الشخصيّ">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="min-w-0">
                <p class="text-sm font-semibold">
                    {{ setting('growth.profile_completion.bar_title', 'كمّل ملفّك') }}
                    <span class="tabular-nums" style="color: var(--color-brand-500)">{{ $barState['percent'] }}%</span>
                </p>
                <p class="text-xs mt-0.5" style="color: var(--text-muted)">
                    @if ($barState['rewarded'])
                        {{ setting('growth.profile_completion.bar_done_hint', 'المكافأة اتصرفت — كمّل الباقي علشان بطاقاتك تطلع كاملة.') }}
                    @else
                        {{ str_replace('{tickets}', $barState['tickets'], (string) setting('growth.profile_completion.bar_hint', 'كمّله لآخره وخُد {tickets} تذاكر.')) }}
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('growth.profile.completion') }}"
                   class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('growth.profile_completion.bar_cta', 'كمّل دلوقتي') }}
                </a>

                {{-- زرّ إيقاف لكلّ تذكير (21.1-د) — ولمسة 44×44 على الموبايل (2.15-ج) --}}
                <form method="post" action="{{ route('growth.profile.completion.dismiss') }}">
                    @csrf
                    <button type="submit" aria-label="إخفاء التذكير"
                            class="inline-flex items-center justify-center rounded-xl motion-standard"
                            style="min-width: 44px; min-height: 44px; color: var(--text-muted)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        {{-- الشريط: نسبة مرئيّة ومعها الرقم — فاللون لا يحمل المعنى وحده (2.16-ب) --}}
        <div class="mt-3 h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)"
             role="progressbar" aria-valuenow="{{ $barState['percent'] }}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full motion-standard" style="width: {{ $barState['percent'] }}%; background: var(--color-brand-500)"></div>
        </div>
    </section>
@endif
