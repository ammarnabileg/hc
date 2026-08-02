@php
    use Illuminate\Support\Facades\Gate;

    /**
     * سايد بار لوحة الإدارة (الدستور 12.0) — اثنا عشر عنصرًا بالترتيب المعتمَد،
     * و«الإعدادات والنظام» آخر قسم دائمًا.
     *
     * القاعدة الحاكمة: **يظهر العنصر لمن له أيّ صلاحيّة داخله فقط** (12.2.1)،
     * والمحظور **يُخفى ولا يُعطَّل** (2.15-أ-7) — لذلك نفلتر البنود قبل تمريرها
     * للمكوّن، ونحذف المجموعة كلّها إن خلت.
     *
     * وصفحات الإعدادات تتجمّع في **صفحة واحدة بتابات جانبيّة** (2.15-ب)،
     * فلها هنا عنصر واحد لا قائمة طويلة.
     */
    $u = auth()->user();

    // بند واحد: [عنوان, مسار, صلاحيّة] — والصلاحيّة null تعني «مفتوح لمن دخل اللوحة»
    $filter = function (array $items) use ($u) {
        return collect($items)
            ->filter(fn ($item) => $item[2] === null || Gate::forUser($u)->allows($item[2]))
            ->map(fn ($item) => ['label' => $item[0], 'route' => $item[1]])
            ->values()
            ->all();
    };

    $groups = [
        // 👥 إدارة المستخدمين (12.13) — المجال ده بنبنيه هنا
        ['👥', 'إدارة المستخدمين', $filter([
            ['قائمة المستخدمين', 'admin.users.index', 'users.list'],
            ['طلبات الاعتماد', 'admin.users.approvals', 'user_approvals.list'],
            ['شرائح الجمهور', 'admin.users.segments', 'user_segments.list'],
            ['الأدوار والصلاحيّات', 'admin.roles.index', 'roles.list'],
        ])],

        // 📚 إدارة التدريب (12.4)
        ['📚', 'إدارة التدريب', $filter([
            ['المسارات والتدريبات', 'admin.courses.index', 'courses.list'],
            // الإتاحة الزمنيّة: الفترات وأوقات التشغيل اليوميّة (5)
            ['الإتاحة والتوقيت', 'admin.availability.index', 'courses.list'],
        ])],

        // 🎓 إدارة الشهادات (12.5)
        ['🎓', 'إدارة الشهادات', $filter([
            ['الاعتمادات والقوالب والسجلّ', 'admin.certificates.index', 'certificate_ledger.list'],
        ])],

        // 🤝 إدارة التطوّع
        ['🤝', 'إدارة التطوّع', $filter([
            ['الإدارة المركزيّة والهيكل', 'admin.volunteer.index', 'memberships.list'],
        ])],

        // 🎮 التلعيب والتحديات (12.10)
        ['🎮', 'التلعيب والتحديات', $filter([
            ['XP والشارات والحروب', 'admin.gamification.index', 'badges.list'],
        ])],

        // 🛒 المتجر والماليّات (12.12)
        ['🛒', 'المتجر والماليّات', $filter([
            ['المنتجات والطلبات', 'admin.store.index', 'store_products.list'],
        ])],

        // 🎁 إدارة المكافآت (12.9)
        ['🎁', 'إدارة المكافآت', $filter([
            ['منح رصيد يدويّ', 'admin.rewards.index', 'manual_rewards.list'],
        ])],

        // 📅 الفعاليّات (12.11)
        ['📅', 'الفعاليّات', $filter([
            ['الفعاليّات والمسجّلون', 'admin.events.index', 'events.list'],
        ])],

        // 📣 التوجيه والدعم (12.6)
        ['📣', 'التوجيه والدعم', $filter([
            ['التعليمات والشكاوى', 'admin.guidance.index', 'announcements.list'],
        ])],

        // 📊 الإحصائيّات (12.8)
        ['📊', 'الإحصائيّات', $filter([
            ['التقارير واللوحات', 'admin.stats.index', 'reports_users.list'],
        ])],
    ];

    // ⚙️ الإعدادات والنظام — آخر قسم دائمًا (12.0)
    $settingsItems = $filter([
        ['كلّ الإعدادات (تابات جانبيّة)', 'admin.settings.index', 'settings_general.view'],
    ]);
@endphp

<aside class="w-64 shrink-0 hidden md:block" style="border-inline-start: 1px solid var(--border)">
    <div class="sticky top-0 h-screen overflow-y-auto p-4 space-y-4">

        <div class="card p-3">
            <div class="text-sm font-extrabold">لوحة الإدارة</div>
            <div class="text-xs mt-0.5" style="color: var(--text-muted)">{{ $u->shortName() }}</div>
            @owner
                <div class="mt-2"><x-state-badge state="honor" label="مالك المنصّة" /></div>
            @endowner
        </div>

        <nav class="space-y-1">
            {{-- 🏠 لوحة القيادة (12.3) --}}
            <x-nav-link route="admin.dashboard" label="لوحة القيادة" icon="🏠" />

            @foreach ($groups as [$icon, $label, $items])
                @if ($items)
                    <x-nav-group :label="$label" :icon="$icon" :items="$items" />
                @endif
            @endforeach

            {{-- ⚙️ آخر قسم دائمًا --}}
            @if ($settingsItems)
                <x-nav-group label="الإعدادات والنظام" icon="⚙️" :items="$settingsItems" />
            @endif

            <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/') }}"
               class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm motion-standard mt-3"
               style="color: var(--text-muted)">
                <span class="w-5 text-center">↩</span>
                <span>رجوع لحسابي</span>
            </a>
        </nav>
    </div>
</aside>

<div id="mobile-drawer" class="md:hidden"></div>
