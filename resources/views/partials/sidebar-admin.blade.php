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

    /*
     | بند واحد: [عنوان, مسار, صلاحيّة, معاملات الرابط؟]
     | والصلاحيّة null تعني «مفتوح لمن دخل اللوحة».
     |
     | والمعاملات الرابعة لبنود 12.0 التي هي **تابٌ داخل صفحة** لا صفحةٌ مستقلّة
     | (الألعاب · الاحتفالات · إعدادات التعلّم) — فتُبنى بـ`route(name, params)`
     | ويبقى اسم المسار للتفعيل والفحص.
     */
    $filter = function (array $items) use ($u) {
        return collect($items)
            ->filter(fn ($item) => $item[2] === null || Gate::forUser($u)->allows($item[2]))
            // المسار غير الموجود لا يُعرَض أصلًا — لا رابط ميّت في سايد بار الإدارة
            ->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item[1]))
            ->map(fn ($item) => [
                'label' => $item[0],
                'route' => $item[1],
                'href' => ($item[3] ?? []) === [] ? null : route($item[1], $item[3]),
            ])
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
            ['المسارات', 'admin.paths.index', 'paths.list'],
            ['التدريبات', 'admin.courses.index', 'courses.list'],
            // الإتاحة الزمنيّة: الفترات وأوقات التشغيل اليوميّة (5)
            ['الإتاحة والتوقيت', 'admin.availability.index', 'courses.list'],
            // بنك الأسئلة المركزيّ — عرضيّ عبر التدريبات كلّها (24.1-3)
            ['بنك الأسئلة والامتحانات', 'admin.question-bank.index', 'question_bank.list'],
            // مكتبة الوسائط — بند صريح في خريطة 12.0
            ['مكتبة الوسائط', 'admin.media.index', 'media_library.list'],
            // إعدادات التعلّم — بند في خريطة 12.0 كان بلا مدخل: تاب داخل صفحة الإعدادات
            ['إعدادات التعلّم', 'admin.settings.index', 'settings_general.view', ['tab' => 'learning']],
        ])],

        // 🎓 إدارة الشهادات (12.5)
        ['🎓', 'إدارة الشهادات', $filter([
            ['الاعتمادات والقوالب والسجلّ', 'admin.certificates.index', 'certificate_ledger.list'],
        ])],

        // 🤝 إدارة التطوّع
        ['🤝', 'إدارة التطوّع', $filter([
            ['الإدارة المركزيّة والهيكل', 'admin.volunteer.index', 'memberships.list'],
            // التوظيف والمرشّحون — بند صريح في 12.0 كان بلا مدخل من اللوحة (13.4-ك)
            ['التوظيف والمرشّحون', 'volunteer.recruitment', 'candidates.list'],
            // مرآة إداريّة لاجتماعات التطوّع (24.2-أوّلًا)
            ['اجتماعات التطوّع', 'admin.meetings.index', 'meetings.list'],
            ['الهيكل والبوزشنز والسعة', 'admin.volunteer.org', 'org_chart.view'],
            ['تقرير السعة', 'admin.volunteer.org.capacity', 'capacity.view'],
            ['درجة الالتزام (Rep)', 'admin.volunteer.rep', 'rep_transactions.view'],
            // الغيابات والتفويض المؤقّت (23-6) — كان المنطق كاملًا بلا شاشة إدارة
            ['الغيابات والتفويض', 'admin.volunteer.delegations', 'delegations.list'],
            // أنواع المهامّ: قالب وتشيك ليست وقيم مقترحة (23-0.3)
            ['أنواع المهامّ', 'admin.volunteer.task-types.index', 'task_types.list'],
            ['الخروج والعودة', 'admin.volunteer.offboarding', 'offboarding.view'],
            ['شهادات التطوّع', 'admin.volunteer.certificates', 'volunteer_certificates.view'],
            ['تحليلات التطوّع', 'admin.volunteer.analytics', 'reports_volunteer.view'],
        ])],

        // 🎮 التلعيب والتحديات (12.10)
        ['🎮', 'التلعيب والتحديات', $filter([
            ['XP والشارات والحروب', 'admin.gamification.index', 'badges.list'],
            // الألعاب والاحتفالات — بندان في خريطة 12.0 كانا بلا مدخل، وهما تابان
            // داخل لوحة التلعيب (24.2) فيُفتحان بمعامل التاب.
            ['الألعاب', 'admin.gamification.index', 'games.view', ['tab' => 'games']],
            ['الاحتفالات', 'admin.gamification.index', 'celebrations.view', ['tab' => 'celebrations']],
            // بنك أسئلة الحروب — بند صريح في 12.0 (12.10-ب)
            ['بنك أسئلة الحروب', 'admin.wars.bank.index', 'wars_bank.list'],
            // الطرف الإداريّ للدعوات والألقاب (24.2)
            ['الريفيرال والسفراء', 'admin.referrals.index', 'referrals.list'],
            // الرسائل الإيجابيّة لأيقونة المفاجأة (2.6-ب · 12.0)
            ['الرسائل الإيجابيّة', 'admin.positive.index', 'positive_messages.list'],
        ])],

        // 🛒 المتجر والماليّات (12.12)
        ['🛒', 'المتجر والماليّات', $filter([
            ['المنتجات والطلبات', 'admin.store.index', 'store_products.list'],
            ['طلبات الشحن', 'admin.topups.index', 'topup_requests.list'],
            // 🔒 الماليّات مجموعة محميّة **لمالك المنصّة وحده** (12.0 · 2.13-و):
            // شرط الملكيّة فوق فحص الصلاحيّة — حزامٌ وحمّالة، والبند يُخفى لا يُعطَّل.
            ...($u->isPlatformOwner() ? [
                ['🔒 الماليّات', 'admin.finance.index', 'finance.view'],
                // أسعار الصرف: تدرجها 12.0 تحت «🔒 الماليّات» وكانت بلا مدخل —
                // وشرط الملكيّة فوق فحص الصلاحيّة كبقيّة المجموعة المحميّة.
                ['🔒 أسعار الصرف', 'admin.wallet.rates', 'exchange_rates.view'],
                ['🔒 سجلّ الماليّات', 'admin.finance.audit', 'finance.view'],
            ] : []),
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
            ['التعليمات', 'admin.guidance.index', 'announcements.list'],
            // الثلاثة التالية مبنيّة ومدرَجة في 12.0 وكانت **بلا أيّ رابط وارد**
            // في المشروع — و`admin.guidance.index` لا يربط أيًّا منها (12.6-ب/ج).
            ['الإشعارات', 'admin.guidance.notifications', 'announcements.view'],
            ['دليل المستخدم', 'admin.guidance.help', 'user_guide.list'],
            ['الشكاوى والمقترحات', 'admin.guidance.complaints', 'complaints.list'],
            // المحتوى التحريريّ وقنوات الأويرنس (21.2 · 21.3)
            ['المقالات', 'admin.articles.index', 'articles.list'],
            ['الإعلان المدفوع', 'admin.ads.index', 'ad_audiences.view'],
            // إعدادات حلقات النموّ والاكتساب والتتبّع (21.1 · 21.2 · 21.3)
            ['حلقات النموّ', 'admin.growth.index', 'settings_general.view'],
        ])],

        // 📊 الإحصائيّات (12.8)
        ['📊', 'الإحصائيّات', $filter([
            ['التقارير واللوحات', 'admin.stats.index', 'reports_users.list'],
            // التقارير المجدولة وسجلّ إرسالها (24.3-خامسًا)
            ['التقارير المجدولة', 'admin.report-schedules.index', 'report_schedules.list'],
        ])],
    ];

    // ⚙️ الإعدادات والنظام — آخر قسم دائمًا (12.0)
    $settingsItems = $filter([
        ['كلّ الإعدادات (تابات جانبيّة)', 'admin.settings.index', 'settings_general.view'],
        // استوديو الصور والقوالب البصريّة (12.14) — محرّك واحد للهويّة البصريّة
        ['استوديو الصور', 'admin.studio.index', 'image_templates.list'],
        ['محتوى الـOnboarding', 'admin.ops.onboarding', 'onboarding.view'],
        ['التحديثات والترحيل', 'admin.ops.updates', 'updates.view'],
        ['النسخ الاحتياطيّ وصحّة النظام', 'admin.ops.system', 'system_health.view'],
        // سجلّ التدقيق — آخر بند في خريطة 12.0 وكان بلا مدخل (2.13-هـ)
        ['سجلّ التدقيق', 'admin.settings.audit', 'settings_general.view'],
    ]);
@endphp

{{-- لوحة منزلقة على الموبايل وعمود ثابت على الديسكتوب (13 · 2.15-ج) --}}
<aside data-sidebar data-open="false" class="w-64 shrink-0" style="border-inline-start: 1px solid var(--border)">
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

{{-- الموبايل: نفس القائمة تنزلق من زرّ الهيدر (13 · 2.15-ج) --}}
@include('partials.sidebar-drawer')
