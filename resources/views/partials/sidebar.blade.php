@php
    /**
     * سايد بار المتدرّب (الدستور 24.5-أ) — 13 عنصرًا.
     * ⭐ عنصر التطوّع واحد يبدّل موضعه واسمه:
     *    «لوحة التطوّع» تحت التعليمات للمتطوّع المُسكَّن،
     *    و«تطوّع معنا» فوق حسابي لغير المتطوّع — ولا يظهر في الموضعين معًا.
     * ولوحة الإدارة آخر عنصر دائمًا ولمن له أيّ صلاحيّة فقط.
     */
    $u = auth()->user();
    $isVolunteer = $u->isVolunteer();
    $pinned = collect($u->pinned_pages ?? []);
@endphp

<aside x-data="{ open: false }"
       class="w-64 shrink-0 hidden md:block"
       style="border-inline-start: 1px solid var(--border)">
    <div class="sticky top-0 h-screen overflow-y-auto p-4 space-y-4">

        {{-- بطاقة البروفايل المصغّرة --}}
        <a href="{{ \Illuminate\Support\Facades\Route::has('profile.me') ? route('profile.me') : '#' }}" class="card p-3 flex items-center gap-3 motion-standard hover:opacity-90">
            <x-avatar :user="$u" size="10" />
            <div class="min-w-0">
                <div class="truncate font-semibold text-sm">{{ $u->shortName() }}</div>
                <div class="text-xs" style="color: var(--text-muted)">#{{ $u->code }}</div>
                <div class="mt-1 h-1 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                    <div class="h-full" style="width: {{ $u->xp_percent ?? 30 }}%; background: var(--color-brand-500)"></div>
                </div>
            </div>
        </a>

        {{-- بحث سريع ⟵ صفحة البحث الكبيرة (13.1) --}}
        <form action="{{ \Illuminate\Support\Facades\Route::has('search') ? route('search') : '#' }}" method="get" class="relative">
            <input type="search" name="q" placeholder="ابحث…" aria-label="بحث سريع"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </form>

        {{--
          📌 الصفحات المثبَّتة (2.15-د) — **بديل معتمَد عن «آخر ما زرت» المرفوض**.
          بترتيب قابل للسحب، ويُحفَظ لكلّ مستخدم فور الإفلات.
        --}}
        @if ($pinned->isNotEmpty())
            <div class="space-y-1" data-pins>
                <div class="text-xs px-2" style="color: var(--text-muted)">📌 المثبَّتة</div>
                @foreach ($pinned as $pin)
                    <div class="flex items-center gap-1" draggable="true"
                         data-pin-item="{{ $pin['route'] ?? '' }}">
                        <span class="flex-1 min-w-0">
                            <x-nav-link :href="$pin['url'] ?? '#'" :label="$pin['label'] ?? ''" icon="📌" />
                        </span>
                        <button type="button" data-unpin="{{ $pin['route'] ?? '' }}" data-label="{{ $pin['label'] ?? '' }}"
                                class="shrink-0 rounded-lg text-xs motion-standard"
                                style="min-width: 44px; min-height: 44px; color: var(--text-muted)"
                                aria-label="فكّ تثبيت {{ $pin['label'] ?? '' }}" title="فكّ التثبيت">✕</button>
                    </div>
                @endforeach
            </div>
        @endif

        <nav class="space-y-1">
            <x-nav-link route="dashboard" label="الرئيسيّة" icon="🏠" />
            <x-nav-link route="announcements.index" label="التعليمات" icon="📢"
                        :badge="$u->notificationsFeed()->whereNull('read_at')->where('category','announcement')->count()" />

            {{-- ⭐ للمتطوّع: لوحة التطوّع هنا مباشرةً بعد التعليمات --}}
            @volunteer
                <x-nav-link route="volunteer.overview" label="لوحة التطوّع" icon="🤝" />
            @endvolunteer

            <x-nav-group label="تعلّمي" icon="🎓" :items="[
                ['label' => 'تدريباتي', 'route' => 'learning.courses'],
                ['label' => 'المسارات', 'route' => 'learning.paths'],
                ['label' => 'شهاداتي', 'route' => 'learning.certificates'],
            ]" />

            <x-nav-group label="مكتبتي" icon="📚" :items="[
                ['label' => 'الكلّ', 'route' => 'library.index'],
            ]" />

            <x-nav-group label="المتجر" icon="🛒" :items="[
                ['label' => 'المنتجات', 'route' => 'store.index'],
                ['label' => 'البندلز', 'route' => 'store.bundles'],
            ]" />

            <x-nav-group label="المحفظة" icon="💰" :items="[
                ['label' => 'رصيدي وشحن', 'route' => 'wallet.index'],
                ['label' => 'التذاكر', 'route' => 'wallet.tickets'],
                ['label' => 'المعاملات والفواتير', 'route' => 'wallet.transactions'],
            ]" />

            <x-nav-group label="التحديات" icon="⚔️" :items="[
                ['label' => 'المتاحة', 'route' => 'challenges.index'],
                ['label' => 'تحدّياتي', 'route' => 'challenges.mine'],
                ['label' => 'لوحة الأبطال', 'route' => 'challenges.leaderboard'],
            ]" />

            <x-nav-group label="إنجازاتي" icon="🏆" :items="[
                ['label' => 'الليدر بورد', 'route' => 'achievements.leaderboard'],
                ['label' => 'الشارات', 'route' => 'achievements.badges'],
                ['label' => 'الستريك ونادي الخامسة', 'route' => 'achievements.streak'],
            ]" />

            <x-nav-link route="events.index" label="الفعاليّات" icon="📅" />

            <x-nav-group label="خبراتي" icon="📄" :items="[
                ['label' => 'السيرة الذاتيّة', 'route' => 'cv.index'],
                ['label' => 'الإفادة', 'route' => 'attestations.index'],
            ]" />

            <x-nav-link route="referral.index" label="ادعُ أصدقاءك" icon="👥" />

            <x-nav-group label="الدعم" icon="📮" :items="[
                ['label' => 'الشكاوى والمقترحات', 'route' => 'complaints.index'],
                ['label' => 'دليل المستخدم', 'route' => 'help.index'],
            ]" />

            {{-- ⭐ لغير المتطوّع: «تطوّع معنا» فوق حسابي مباشرةً --}}
            @unless ($isVolunteer)
                <x-nav-link route="volunteering.landing" label="تطوّع معنا" icon="🤝" />
            @endunless

            <x-nav-group label="حسابي" icon="⚙️" :items="[
                ['label' => 'بروفايلي', 'route' => 'profile.me'],
                ['label' => 'الإعدادات', 'route' => 'settings.index'],
                ['label' => 'الخصوصيّة والأمان', 'route' => 'settings.privacy'],
            ]" />

            {{-- لوحة الإدارة — آخر عنصر دائمًا، ولمن له أيّ صلاحيّة فقط --}}
            @can('admin_panel.view')
                <x-nav-link route="admin.dashboard" label="لوحة الإدارة" icon="🛠️" />
            @endcan
        </nav>
    </div>
</aside>

{{-- الموبايل: Drawer يُفتَح من الهيدر ويُغلَق بالسحب (2.15-ج) --}}
<div id="mobile-drawer" class="md:hidden"></div>
