@php
    use Illuminate\Support\Facades\Gate;
    use Illuminate\Support\Facades\Route;

    /**
     * سايد بار لوحة الإدارة (الدستور 12.0) — اثنا عشر عنصرًا بالترتيب المعتمَد،
     * و«الإعدادات والنظام» آخر قسم دائمًا.
     *
     * القاعدة الحاكمة: **يظهر العنصر لمن له أيّ صلاحيّة داخله فقط** (12.2.1)،
     * والمحظور **يُخفى ولا يُعطَّل** (2.15-أ-7) — لذلك نفلتر البنود قبل تمريرها
     * للمكوّن، ونحذف المجموعة كلّها إن خلت.
     *
     * وبنودُ 12.0 التي هي **تابٌ داخل صفحة** لا صفحةٌ مستقلّة (تابات الشهادات
     * والمتجر والتلعيب والإحصائيّات والإعدادات) تُبنى بـ`route(name, ['tab' => …])`:
     * فالمالك يصل للتاب بنقرةٍ واحدة من السايد بار كما تنصّ الخريطة، لا بنقرتين.
     * وكان القسم يختصرها في «عنصرٍ واحد» — وهو **اختصارٌ يخالف نصّ 12.0**.
     */
    $u = auth()->user();

    /*
     | بند واحد: [عنوان, مسار, صلاحيّة, معاملات الرابط؟]
     | والصلاحيّة null تعني «مفتوح لمن دخل اللوحة».
     |
     | والصلاحيّة قد تكون **قائمةً** تُقرَأ «كلّها لازمة»، وكلّ بندٍ فيها قد يكون
     | «أيٌّ من» مفصولةً بـ`|`. ليه؟ لأنّ بند التاب له صلاحيّتان لا واحدة:
     | صلاحيّة التاب نفسه، وصلاحيّة **باب الصفحة** التي تحرسها المِدل-وير.
     | ولو حرسنا بالأولى وحدها ظهر بندٌ يفتح 403 — وهو أسوأ من إخفائه (2.15-أ-7).
     */
    $can = function ($permission) use ($u) {
        foreach ((array) $permission as $clause) {
            $any = collect(explode('|', $clause))
                ->contains(fn ($key) => Gate::forUser($u)->allows($key));

            if (! $any) {
                return false;
            }
        }

        return true;
    };

    /*
     | بوّابات الصفحات ذات التابات — نسخةٌ حرفيّةٌ ممّا تحرسه المِدل-وير على المسار.
     |
     | ⭐ وبوّابتا الإحصائيّات والتلعيب **تُقرآن من مصدر الحارس نفسه** لا تُنسخان
     | بالحرف: النسخة اليدويّة تشيخ عند أوّل تابٍّ جديد، فيعود البند مخفيًّا عن
     | صاحبه — وهو العطب الذي كان قائمًا (باب `/admin/stats` أضيق من محتواه).
     */
    $certGate = 'certificate_ledger.view|certificate_templates.view|accreditations.view';
    $gameGate = implode('|', \App\Http\Controllers\Admin\GamificationController::GATE_KEYS);
    $storeGate = 'store_products.list|bundles.list|coupons.list|orders.list';
    $statsGate = implode('|', \App\Services\Admin\System\StatsService::gateKeys());

    $filter = function (array $items) use ($can) {
        return collect($items)
            ->filter(fn ($item) => $item[2] === null || $can($item[2]))
            // المسار غير الموجود لا يُعرَض أصلًا — لا رابط ميّت في سايد بار الإدارة
            ->filter(fn ($item) => Route::has($item[1]))
            ->map(fn ($item) => [
                'label' => $item[0],
                'route' => $item[1],
                'params' => $item[3] ?? [],
                'href' => route($item[1], $item[3] ?? []),
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
            // بنك الأسئلة المركزيّ — عرضيّ عبر التدريبات كلّها (24.1-3)
            ['بنك الأسئلة والامتحانات', 'admin.question-bank.index', 'question_bank.list'],
            // مكتبة الوسائط — بند صريح في خريطة 12.0
            ['مكتبة الوسائط', 'admin.media.index', 'media_library.list'],
            // إعدادات التعلّم — تابٌ داخل صفحة الإعدادات
            ['إعدادات التعلّم', 'admin.settings.index', 'settings_general.view', ['tab' => 'learning']],
            // ⬇︎ خارج نصّ 12.0: شاشةٌ مبنيّة لولاها لبقيت يتيمة (الإتاحة الزمنيّة — 5)
            ['الإتاحة والتوقيت', 'admin.availability.index', 'courses.list'],
        ])],

        // 🎓 إدارة الشهادات (12.5) — خمسة بنود كما نصّت 12.0، أربعةٌ منها تابات الصفحة
        ['🎓', 'إدارة الشهادات', $filter([
            ['الاعتمادات', 'admin.certificates.index', 'accreditations.view', ['tab' => 'accreditations']],
            ['الأنواع والقوالب', 'admin.certificates.index', 'certificate_templates.view', ['tab' => 'types']],
            ['إصدار شهادة', 'admin.certificates.index', ['certificates.create', $certGate], ['tab' => 'issue']],
            ['سجلّ الصادر', 'admin.certificates.index', 'certificate_ledger.view', ['tab' => 'ledger']],
            // صفحة التحقّق العامّة — بند صريح في 12.0 وكان بلا مدخل من اللوحة (12.5-د)
            ['صفحة التحقّق', 'verify.certificate', 'certificate_ledger.view'],
        ])],

        // 🤝 إدارة التطوّع
        ['🤝', 'إدارة التطوّع', $filter([
            ['الإدارة المركزيّة', 'admin.volunteer.index', 'volunteer_central_settings.view'],
            // التوظيف والمرشّحون — بند صريح في 12.0 كان بلا مدخل من اللوحة (13.4-ك)
            ['التوظيف والمرشّحون', 'volunteer.recruitment', 'candidates.list'],
            ['الهيكل والبوزشنز والسعة', 'admin.volunteer.org', 'org_chart.view'],
            // مرآة إداريّة لاجتماعات التطوّع (24.2-أوّلًا)
            ['الاجتماعات', 'admin.meetings.index', 'meetings.list'],
            ['شهادات التطوّع', 'admin.volunteer.certificates', 'volunteer_certificates.view'],
            ['تحليلات التطوّع', 'admin.volunteer.analytics', 'reports_volunteer.view'],
            // ⬇︎ خارج نصّ 12.0: شاشاتٌ مبنيّة لولاها لبقيت يتيمة
            ['تقرير السعة', 'admin.volunteer.org.capacity', 'capacity.view'],
            ['درجة الالتزام (Rep)', 'admin.volunteer.rep', 'rep_transactions.view'],
            ['الغيابات والتفويض', 'admin.volunteer.delegations', 'delegations.list'],
            ['أنواع المهامّ', 'admin.volunteer.task-types.index', 'task_types.list'],
            ['الخروج والعودة', 'admin.volunteer.offboarding', 'offboarding.view'],
        ])],

        // 🎮 التلعيب والتحديات (12.10 — موسّع) — أحد عشر بندًا بترتيب 12.0
        ['🎮', 'التلعيب والتحديات', $filter([
            ['XP والتذاكر', 'admin.gamification.index', 'xp_rules.view', ['tab' => 'xp']],
            // المفتاح الإداريّ أو الشخصيّ — 12.2.2 تفرّق بينهما (`streaks.list` ALL · `streaks.view` SELF)
            ['الستريك ونادي الخامسة', 'admin.gamification.index', ['streaks.view|streaks.list', $gameGate], ['tab' => 'streaks']],
            ['الليدر بورد', 'admin.gamification.index', ['leaderboards.view|leaderboards.export', $gameGate], ['tab' => 'leaderboard']],
            ['الشارات والإنجازات', 'admin.gamification.index', 'badges.view', ['tab' => 'badges']],
            /*
             | ⛔ «الألعاب» ملغاة بقرار المالك (الدستور v5.3 — 7.5)، فسقط بندها من
             | خريطة 12.0. ولا مدخل لها هنا، ولا تابّ `?tab=games`.
             */
            // الطرف الإداريّ للدعوات والألقاب (24.2)
            ['الريفيرال والسفراء', 'admin.referrals.index', 'referrals.list'],
            // الرسائل الإيجابيّة لأيقونة المفاجأة (2.6-ب · 12.0)
            ['الرسائل الإيجابيّة', 'admin.positive.index', 'positive_messages.list'],
            ['الاحتفالات', 'admin.gamification.index', 'celebrations.view', ['tab' => 'celebrations']],
            ['أسئلة المكافآت', 'admin.gamification.index', 'reward_questions.view', ['tab' => 'reward_questions']],
            // بنك أسئلة الحروب — بند صريح في 12.0 (12.10-ب)
            ['بنك أسئلة الحروب', 'admin.wars.bank.index', 'wars_bank.list'],
            ['إعدادات الحروب', 'admin.gamification.index', 'wars_settings.view', ['tab' => 'wars']],
        ])],

        // 🛒 المتجر والماليّات (12.12) — تابات المتجر الخمسة ثمّ المجموعة المحميّة
        ['🛒', 'المتجر والماليّات', $filter([
            ['المنتجات والتصنيفات', 'admin.store.index', 'store_products.list', ['tab' => 'products']],
            ['البندلز', 'admin.store.index', 'bundles.list', ['tab' => 'bundles']],
            ['الكوبونات وOrder-bump', 'admin.store.index', 'coupons.list', ['tab' => 'coupons']],
            ['الطلبات والفواتير', 'admin.store.index', 'orders.list', ['tab' => 'orders']],
            ['المكتبة الرقميّة والحماية', 'admin.store.index', ['product_protection.view', $storeGate], ['tab' => 'library']],
            // ⬇︎ خارج نصّ 12.0: شاشة طلبات الشحن المبنيّة (18)
            ['طلبات الشحن', 'admin.topups.index', 'topup_requests.list'],
            // 🔒 الماليّات مجموعة محميّة **لمالك المنصّة وحده** (12.0 · 2.13-و):
            // شرط الملكيّة فوق فحص الصلاحيّة — حزامٌ وحمّالة، والبند يُخفى لا يُعطَّل.
            ...($u->isPlatformOwner() ? [
                ['🔒 الماليّات', 'admin.finance.index', 'finance.view'],
                ['🔒 أسعار الصرف', 'admin.wallet.rates', 'exchange_rates.view'],
                ['🔒 سجلّ الماليّات', 'admin.finance.audit', 'finance.view'],
            ] : []),
        ])],

        // 🎁 إدارة المكافآت (12.9) — بندٌ مسطّح بلا دروب-داون كما في خريطة 12.0
        ['🎁', 'إدارة المكافآت', $filter([
            ['إدارة المكافآت', 'admin.rewards.index', 'manual_rewards.list'],
        ]), 'flat'],

        // 📅 الفعاليّات (12.11)
        ['📅', 'الفعاليّات', $filter([
            ['الفعاليّات', 'admin.events.index', 'events.list'],
            // بند خريطة 12.0 «الفعاليّات · المسجّلون والحضور» — كان بلا شاشة جامعة.
            // والاسم من الإعدادات لا محروقًا (2.13)، وهو نفس مفتاح عنوان الشاشة
            // فلا يفترق البند عن الصفحة التي يفتحها.
            [setting('events.registrations.page_title', 'المسجّلون والحضور'), 'admin.events.registrations.index', 'event_registrations.list'],
        ])],

        // 📣 التوجيه والدعم (12.6)
        ['📣', 'التوجيه والدعم', $filter([
            ['التعليمات', 'admin.guidance.index', 'announcements.list'],
            // الثلاثة التالية مبنيّة ومدرَجة في 12.0 وكانت **بلا أيّ رابط وارد**
            // في المشروع — و`admin.guidance.index` لا يربط أيًّا منها (12.6-ب/ج).
            ['الإشعارات', 'admin.guidance.notifications', 'announcements.view'],
            ['دليل المستخدم', 'admin.guidance.help', 'user_guide.list'],
            ['الشكاوى والمقترحات', 'admin.guidance.complaints', 'complaints.list'],
            // ⬇︎ خارج نصّ 12.0: المحتوى التحريريّ وقنوات الأويرنس (21.2 · 21.3)
            ['المقالات', 'admin.articles.index', 'articles.list'],
            ['الإعلان المدفوع', 'admin.ads.index', 'ad_audiences.view'],
            ['حلقات النموّ', 'admin.growth.index', 'settings_general.view'],
        ])],

        // 📊 الإحصائيّات (12.8) — تابات صفحة الإحصائيّات بترتيب 12.0
        ['📊', 'الإحصائيّات', $filter([
            ['المستخدمون', 'admin.stats.index', 'reports_users.view', ['tab' => 'users']],
            ['المبيعات', 'admin.stats.index', ['finance.view', $statsGate], ['tab' => 'sales']],
            ['التدريبات', 'admin.stats.index', ['reports_training.view', $statsGate], ['tab' => 'training']],
            ['التفاعل', 'admin.stats.index', ['reports_engagement.view', $statsGate], ['tab' => 'engagement']],
            ['الحضور', 'admin.stats.index', ['reports_engagement.view', $statsGate], ['tab' => 'attendance']],
            ['الحروب', 'admin.stats.index', ['reports_engagement.view', $statsGate], ['tab' => 'wars']],
            // ⭐ تابّا التطوّع والشهادات مبنيّان الآن داخل صفحة الإحصائيّات نفسها
            // (24.3-خامسًا)، فالبند يفتح **تابَّه** لا لوحةً أخرى. ومدخلا اللوحتين
            // باقيان في مجموعتيهما («تحليلات التطوّع» · «سجلّ الصادر») فلا يتيتّم شيء.
            ['التطوّع', 'admin.stats.index', ['reports_volunteer.view', $statsGate], ['tab' => 'volunteer']],
            ['الشهادات', 'admin.stats.index', ['reports_certificates.view', $statsGate], ['tab' => 'certificates']],
            // التقارير المجدولة وسجلّ إرسالها (24.3-خامسًا)
            ['التقارير المجدولة', 'admin.report-schedules.index', 'report_schedules.list'],
            // ⬇︎ خارج نصّ 12.0: مصادر الاكتساب (21.3)
            ['مصادر الاكتساب', 'admin.stats.index', ['acquisition_sources.view', $statsGate], ['tab' => 'acquisition']],
        ])],
    ];

    // ⚙️ الإعدادات والنظام — آخر قسم دائمًا (12.0)
    $settingsItems = $filter([
        ['إعدادات المنصّة', 'admin.settings.index', 'settings_general.view', ['tab' => 'platform']],
        ['الهويّة والمظهر', 'admin.settings.index', 'settings_general.view', ['tab' => 'identity']],
        ['محتوى الـOnboarding', 'admin.ops.onboarding', 'onboarding.view'],
        ['قوالب الـCV', 'admin.cv-templates.index', 'cv_templates.list'],
        ['الأمان والخصوصيّة', 'admin.settings.index', 'settings_general.view', ['tab' => 'security']],
        ['مفاتيح المزايا', 'admin.settings.index', 'settings_general.view', ['tab' => 'features']],
        ['بيانات الدول', 'admin.settings.index', 'settings_general.view', ['tab' => 'countries']],
        ['وضع الصيانة', 'admin.settings.index', 'settings_general.view', ['tab' => 'maintenance']],
        ['التحديثات والترحيل', 'admin.ops.updates', 'updates.view'],
        ['النسخ الاحتياطيّ وصحّة النظام', 'admin.ops.system', 'system_health.view'],
        /*
         | سجلّ التدقيق — آخر بند في خريطة 12.0 (2.13-هـ).
         | وكان مربوطًا بـ`admin.settings.audit`، وهو **مسار JSON** لآخر تغييرٍ
         | على مفتاحٍ واحد يردّ 422 بلا `?key=` — أي بندٌ في السايد بار يفتح خطأً.
         | والسجلّ الحقيقيّ تابٌ في صفحة الإعدادات.
         */
        ['سجلّ التدقيق', 'admin.settings.index', 'settings_general.view', ['tab' => 'audit']],
        // ⬇︎ خارج نصّ 12.0: استوديو الصور والقوالب البصريّة (12.14)
        ['استوديو الصور', 'admin.studio.index', 'image_templates.list'],
    ]);

    /*
     | ⭐ تعليم «الحاليّ» مرّةً واحدة في السايد بار كلّه.
     |
     | بنودٌ كثيرة تشترك في اسم مسارٍ واحد (تابات الإعدادات مثلًا)، و`routeIs()`
     | وحده يضيء أحد عشر بندًا معًا — فيضيع «أنت هنا» بدل أن يدلّ (2.15-أ).
     | فنختار فائزًا واحدًا: أدقّ تطابقٍ بالمسار **وبمعامل التاب**، ونُسقط اسم
     | المسار عن الباقي فيبقى الرابط شغّالًا بالـ`href` بلا إضاءةٍ كاذبة.
     */
    /*
     | والصفحة ذات التابات تفتح تابها الأوّل حين يأتيها الرابط بلا `?tab=` —
     | فنعرف نحن كذلك أيّ تابٍ هو المفتوح فعلًا، وإلّا أضاء بندٌ غير الذي يقرؤه.
     */
    $defaultTab = [
        'admin.settings.index' => 'platform',
        'admin.certificates.index' => 'accreditations',
        'admin.gamification.index' => 'xp',
        'admin.store.index' => 'products',
        'admin.stats.index' => 'users',
    ];

    $currentTab = request()->query('tab')
        ?: ($defaultTab[request()->route()?->getName()] ?? null);

    $score = function (array $item) use ($currentTab) {
        if (! Route::has($item['route']) || ! request()->routeIs($item['route'].'*')) {
            return 0;
        }

        $tab = $item['params']['tab'] ?? null;

        // تطابق التاب أقوى من تطابق المسار وحده، والبند بلا تابٍ يسبق تابًا مخالفًا
        return match (true) {
            $tab !== null && $tab === $currentTab => 3,
            $tab === null => 2,
            default => 1,
        };
    };

    $best = 0;

    foreach ([...array_column($groups, 2), $settingsItems] as $items) {
        foreach ($items as $item) {
            $best = max($best, $score($item));
        }
    }

    $mark = function (array $items) use ($score, $best, &$marked) {
        return array_map(function (array $item) use ($score, $best, &$marked) {
            $item['route'] = (! $marked && $best > 0 && $score($item) === $best)
                ? $item['route']
                : null;

            if ($item['route'] !== null) {
                $marked = true;
            }

            return $item;
        }, $items);
    };

    $marked = false;

    foreach ($groups as $i => $group) {
        $groups[$i][2] = $mark($group[2]);
    }

    $settingsItems = $mark($settingsItems);
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

            @foreach ($groups as $group)
                @php([$icon, $label, $items] = $group)
                @if ($items)
                    @if (($group[3] ?? null) === 'flat')
                        {{-- بندٌ مسطّح: 12.0 لا ترسم له دروب-داون --}}
                        <x-nav-link :route="$items[0]['route']" :href="$items[0]['href']"
                                    :label="$label" :icon="$icon" />
                    @else
                        <x-nav-group :label="$label" :icon="$icon" :items="$items" />
                    @endif
                @endif
            @endforeach

            {{-- ⚙️ آخر قسم دائمًا --}}
            @if ($settingsItems)
                <x-nav-group label="الإعدادات والنظام" icon="⚙️" :items="$settingsItems" />
            @endif

            <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}"
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
