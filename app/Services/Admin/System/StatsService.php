<?php

namespace App\Services\Admin\System;

use App\Models\User;
use App\Services\Growth\AcquisitionFunnel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الإحصائيّات (12.8 · 24.3-خامسًا) — عرضٌ فقط بفلتر فترة موحّد ومقارنة وتصدير مرن.
 *
 * قاعدتان تحكمان الملفّ:
 *  1) **خطأ حساب تقرير واحد لا يُسقط الصفحة** — كلّ استعلام محميّ بفحص وجود الجدول.
 *  2) **التاب الماليّ لا يظهر لغير المخوَّل** — والتصفية تتمّ في `tabsFor()` لا في الواجهة.
 */
class StatsService
{
    /**
     * ⭐ **مفتاحُ كلّ تابّ — بلا أيّ لمسة لقاعدة البيانات.**
     *
     * مفصولةٌ عن `tabs()` عمدًا: `routeGate()` تُنادى **وقت تسجيل المسارات**
     * (قبل الطلب وقبل وجود جدول الإعدادات في التنصيب الأوّل)، فلو قرأت اللافتات
     * لكسرت `artisan migrate` على قاعدةٍ فارغة. والباب لا يحتاج لافتةً أصلًا.
     *
     * @return array<string, array{permission:?string, owner_only:bool}>
     */
    private static function tabGuards(): array
    {
        return [
            'users' => ['permission' => 'reports_users.view', 'owner_only' => false],
            'sales' => ['permission' => 'finance.view', 'owner_only' => true],
            'training' => ['permission' => 'reports_training.view', 'owner_only' => false],
            'engagement' => ['permission' => 'reports_engagement.view', 'owner_only' => false],
            'attendance' => ['permission' => 'reports_engagement.view', 'owner_only' => false],
            'wars' => ['permission' => 'reports_engagement.view', 'owner_only' => false],
            'volunteer' => ['permission' => 'reports_volunteer.view', 'owner_only' => false],
            'certificates' => ['permission' => 'reports_certificates.view', 'owner_only' => false],
            'acquisition' => ['permission' => 'acquisition_sources.view', 'owner_only' => false],
            /*
             | ⭐ تاب **«تقرير أثر المكافآت»** (24.3-خامسًا: «… · التطوّع · الشهادات ·
             | تقرير أثر المكافآت») — 12.9 يسمّي مفتاحه حرفيًّا في 12.2.2: صفّ
             | `manual_rewards.export` وحدها تحمل نصّ «**تصدير سجلّ المنح/الخصم
             | وتقرير الأثر**»، وليس مفتاحًا جديدًا باسم `reports_rewards.*` —
             | 12.2.2 لا تذكر مفتاحًا كهذا في مصفوفتها المعتمَدة إطلاقًا.
             |
             | وخلافًا لبقيّة تابات `reports_*` («دائمًا»)، مجموعة `manual_rewards`
             | كلّها 🔒 «مالك المنصّة فقط» (`is_owner_only=true` في
             | database/data/permissions.json) — فالتاب owner_only هنا كتاب
             | المبيعات (12.7)، لا كتاب التطوّع/الشهادات.
             */
            'rewards' => ['permission' => 'manual_rewards.export', 'owner_only' => true],
        ];
    }

    /** @return array<string, array{label:string, permission:?string, owner_only:bool}> */
    public function tabs(): array
    {
        $labels = [
            'users' => setting('stats.stats_service.tabs_1', 'المستخدمون'),
            'sales' => setting('stats.stats_service.tabs_2', '🔒 المبيعات والماليّات'),
            'training' => setting('stats.stats_service.tabs_3', 'التدريبات'),
            'engagement' => setting('stats.stats_service.tabs_4', 'التفاعل والتلعيب'),
            'attendance' => setting('stats.stats_service.tabs_5', 'الحضور'),
            'wars' => setting('stats.stats_service.tabs_6', 'الحروب'),
            /*
             | ⭐ تابّا **التطوّع** و**الشهادات** — منصوصان في 24.3-خامسًا ضمن سطر
             | التبويبات نفسه: «… · **الحضور** … · **الحروب** … · **التطوّع** ·
             | **الشهادات** · **تقرير أثر المكافآت**». وكانا البندين الوحيدين في
             | خريطة السايد بار اللذين يفتحان **لوحةً أخرى** بدل تابِّهما، فيقرأ
             | صاحبُ `reports_volunteer.view` شاشة إدارة التطوّع لا تقريرَه.
             |
             | ومادّتهما من وصف المفتاحين في 12.2.2 حرفيًّا:
             |  · «`reports_volunteer.view` … تقرير التطوّع: **التسكين · المهامّ ·
             |     SLA المستويات**».
             |  · «`reports_certificates.view` … تقرير الشهادات: **معدّل الإصدار ·
             |     الإلغاءات · حسب الاعتماد**».
             */
            'volunteer' => (string) setting('stats.tabs.volunteer.label', 'التطوّع'),
            'certificates' => (string) setting('stats.tabs.certificates.label', 'الشهادات'),
            'acquisition' => setting('stats.stats_service.tabs_7', 'مصادر الاكتساب'),
            'rewards' => (string) setting('stats.tabs.rewards.label', 'تقرير أثر المكافآت'),
        ];

        $tabs = [];

        foreach (self::tabGuards() as $key => $guard) {
            $tabs[$key] = ['label' => (string) ($labels[$key] ?? $key)] + $guard;
        }

        return $tabs;
    }

    /**
     * ⭐⭐ **مفاتيح باب الصفحة — الباب بسعة محتواه** (12.8 · 12.2.1-أ).
     *
     * كان المسار محروسًا بـ`reports_users.view` **وحدها**، فصاحب
     * `reports_training.view` — وله تابٌّ منصوصٌ في 12.8 وسطرٌ في 12.2.2 يقول
     * «**دائمًا**» لا «مالك المنصّة فقط» — **لا يصل تابَّه إطلاقًا**: يُردّ عند
     * الباب قبل أن يُسأل عن التاب. وهو نقيض 12.2.1-أ نصًّا: «**ممنوع صلاحيّة
     * باسم شاشة** — الشاشة نتيجةٌ للصلاحيّات لا صلاحيّةً بذاتها».
     *
     * والعلاج **ليس** إخفاء البند من السايد بار: الإخفاء يمنع 403 ولا يعطي
     * صاحبَ الحقّ حقَّه. فالباب يقبل الآن **كلّ مفتاحٍ يملك صاحبُه تابًّا**،
     * ثمّ `tabsFor()` تعطي كلَّ واحدٍ تابَّه وحده — ومَن لا تابَّ له لا يجد في
     * القائمة مفتاحًا فيُردّ عند الباب كما كان.
     *
     * والقائمة تُشتقّ من `tabGuards()` — **مصدر التابات نفسه** — لا تُكتَب ثانيةً
     * في ملفّ المسارات: قائمةٌ ثانية تنسى التابَّ الجديد فيعود الباب أضيق من
     * محتواه بعد أوّل إضافة، وهو عين العطب الذي عولج هنا.
     *
     * @return array<int, string>
     */
    public static function gateKeys(): array
    {
        return array_values(array_unique(array_filter(array_column(self::tabGuards(), 'permission'))));
    }

    /** نفس القائمة لسطر الميدل-وير في ملفّ المسارات — `permission:a,b,c` */
    public static function routeGate(): string
    {
        return 'permission:'.implode(',', self::gateKeys());
    }

    /** التابات التي يراها هذا المستخدم — وما لا يملكه لا يظهر أصلًا (2.15-أ-7) */
    public function tabsFor(User $user): array
    {
        return array_filter(
            $this->tabs(),
            function (array $tab) use ($user) {
                if ($tab['owner_only'] && ! $user->isPlatformOwner()) {
                    return false;
                }

                return $tab['permission'] === null || $user->allows($tab['permission']);
            },
        );
    }

    /**
     * فلتر الفترة العامّ + المقارنة بالفترة السابقة.
     *
     * @return array{from:CarbonImmutable, to:CarbonImmutable, prev_from:CarbonImmutable, prev_to:CarbonImmutable, days:int, compare:bool}
     */
    public function period(?string $from, ?string $to, bool $compare): array
    {
        $defaultDays = (int) setting('stats.period.default_days', 30);

        $end = $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::now()->endOfDay();
        $start = $from ? CarbonImmutable::parse($from)->startOfDay() : $end->subDays($defaultDays - 1)->startOfDay();
        $days = max(1, (int) $start->diffInDays($end) + 1);

        return [
            'from' => $start,
            'to' => $end,
            'prev_from' => $start->subDays($days),
            'prev_to' => $start->subSecond(),
            'days' => $days,
            'compare' => $compare,
        ];
    }

    /** بيانات تاب بعينه — تحميل كسول: لا يُحسَب إلّا التاب المفتوح (2.15-ب) */
    public function data(string $tab, array $period): array
    {
        return match ($tab) {
            'sales' => $this->sales($period),
            'training' => $this->training($period),
            'engagement' => $this->engagement($period),
            'attendance' => $this->attendance($period),
            'wars' => $this->wars($period),
            'volunteer' => $this->volunteer($period),
            'certificates' => $this->certificates($period),
            'acquisition' => $this->acquisition($period),
            'rewards' => $this->rewards($period),
            default => $this->users($period),
        };
    }

    // ---------------------------------------------------------------- المستخدمون

    private function users(array $period): array
    {
        $growth = $this->daily('users', 'created_at', $period['from'], $period['to']);

        $registered = (int) User::query()->whereBetween('created_at', [$period['from'], $period['to']])->count();
        $approved = (int) User::query()->whereBetween('created_at', [$period['from'], $period['to']])->where('status', 'active')->count();
        $buyers = $this->countBuyers($period);
        // «الزائر» يُقاس بأحداث التتبّع إن وُجدت، وإلّا فالمسجّلون هم قمّة القمع الظاهرة
        $visitors = $this->trackingCount('page_view', $period) ?: $registered;

        return [
            'kpis' => [
                ['label' => setting('stats.stats_service.users_1', 'تسجيلات جديدة'), 'value' => $registered, 'icon' => '👥'],
                ['label' => setting('stats.stats_service.users_2', 'حسابات معتمَدة'), 'value' => $approved, 'icon' => '✅'],
                ['label' => setting('stats.stats_service.users_3', 'أوّل شراء'), 'value' => $buyers, 'icon' => '🛒'],
                ['label' => setting('stats.stats_service.users_4', 'نسبة الاعتماد'), 'value' => $registered > 0 ? round($approved / $registered * 100).'%' : '0%', 'icon' => '📈'],
            ],
            'growth' => $growth,
            'growth_prev' => $period['compare'] ? $this->daily('users', 'created_at', $period['prev_from'], $period['prev_to']) : [],
            'funnel' => [
                ['label' => setting('stats.stats_service.users_5', 'زائر'), 'value' => $visitors],
                ['label' => setting('stats.stats_service.users_6', 'مسجّل'), 'value' => $registered],
                ['label' => setting('stats.stats_service.users_7', 'معتمَد'), 'value' => $approved],
                ['label' => setting('stats.stats_service.users_8', 'أوّل شراء'), 'value' => $buyers],
            ],
            'cohorts' => $this->cohorts(),
            'geo' => $this->geo(),
            'hours' => $this->busiestHours($period),
        ];
    }

    /** الاحتفاظ (Cohorts): كلّ فوج تسجيل × نشاطه في الشهور التالية */
    private function cohorts(): array
    {
        $months = (int) setting('stats.cohorts.months', 6);
        $rows = [];
        $labels = [];

        for ($m = $months - 1; $m >= 0; $m--) {
            $start = CarbonImmutable::now()->startOfMonth()->subMonths($m);
            $end = $start->endOfMonth();
            $labels[] = $start->translatedFormat('M Y');

            $cohort = User::query()->whereBetween('created_at', [$start, $end])->pluck('id');
            $size = $cohort->count();
            $row = [];

            for ($k = 0; $k < $months; $k++) {
                if ($k > $m || $size === 0) {
                    $row[] = 0;

                    continue;
                }

                $windowStart = $start->addMonths($k);
                $active = User::query()
                    ->whereIn('id', $cohort)
                    ->whereBetween('last_seen_at', [$windowStart, $windowStart->endOfMonth()])
                    ->count();

                $row[] = round($active / $size * 100, 1);
            }

            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'row_labels' => $labels,
            'col_labels' => array_map(fn ($k) => strtr(setting('stats.stats_service.cohorts_1', 'شهر :p1'), [':p1' => (string) ($k)]), range(0, $months - 1)),
        ];
    }

    /** خريطة حراريّة جغرافيّة: الدولة ⟵ المحافظة */
    private function geo(): array
    {
        if (! Schema::hasTable('countries')) {
            return ['rows' => []];
        }

        return [
            'rows' => DB::table('users')
                ->leftJoin('countries', 'countries.id', '=', 'users.country_id')
                ->leftJoin('governorates', 'governorates.id', '=', 'users.governorate_id')
                ->whereNull('users.deleted_at')
                ->select(
                    DB::raw('coalesce(countries.name_ar, "—") as country'),
                    DB::raw('coalesce(governorates.name_ar, "—") as governorate'),
                    DB::raw('count(*) as total'),
                )
                ->groupBy('country', 'governorate')
                ->orderByDesc('total')
                ->limit((int) setting('stats.geo.max_rows', 20))
                ->get()
                ->map(fn ($r) => ['label' => $r->country.' · '.$r->governorate, 'value' => (float) $r->total])
                ->all(),
        ];
    }

    /** أنشط الأوقات: يوم × ساعة من آخر ظهور للمستخدمين */
    private function busiestHours(array $period): array
    {
        $matrix = array_fill(0, 7, array_fill(0, 24, 0));

        DB::table('users')
            ->whereNotNull('last_seen_at')
            ->whereBetween('last_seen_at', [$period['from'], $period['to']])
            ->pluck('last_seen_at')
            ->each(function ($stamp) use (&$matrix) {
                $moment = Carbon::parse($stamp);
                $matrix[$moment->dayOfWeek][$moment->hour]++;
            });

        return [
            'matrix' => $matrix,
            'row_labels' => [setting('stats.stats_service.busiest_hours_1', 'الأحد'), setting('stats.stats_service.busiest_hours_2', 'الإثنين'), setting('stats.stats_service.busiest_hours_3', 'الثلاثاء'), setting('stats.stats_service.busiest_hours_4', 'الأربعاء'), setting('stats.stats_service.busiest_hours_5', 'الخميس'), setting('stats.stats_service.busiest_hours_6', 'الجمعة'), setting('stats.stats_service.busiest_hours_7', 'السبت')],
            'col_labels' => array_map(fn ($h) => str_pad((string) $h, 2, '0', STR_PAD_LEFT), range(0, 23)),
        ];
    }

    // ---------------------------------------------------------------- المبيعات والماليّات 🔒

    private function sales(array $period): array
    {
        $revenue = 0.0;
        $orders = 0;

        if (Schema::hasTable('orders')) {
            $query = DB::table('orders')->where('status', 'paid')->whereBetween('created_at', [$period['from'], $period['to']]);
            $revenue = (float) (clone $query)->sum('total');
            $orders = (int) (clone $query)->count();
        }

        $corrections = Schema::hasTable('transactions')
            ? (int) DB::table('transactions')->where('is_correction', true)->whereBetween('created_at', [$period['from'], $period['to']])->count()
            : 0;

        $circulating = Schema::hasTable('wallet_balances')
            ? (float) DB::table('wallet_balances')
                ->join('currencies', 'currencies.id', '=', 'wallet_balances.currency_id')
                ->where('currencies.code', 'coins')
                ->sum('wallet_balances.balance')
            : 0.0;

        $referral = Schema::hasTable('transactions')
            ? (float) DB::table('transactions')->where('source', 'referral')->whereBetween('created_at', [$period['from'], $period['to']])->sum('amount')
            : 0.0;

        return [
            'kpis' => [
                ['label' => setting('stats.stats_service.sales_1', 'الإيرادات'), 'value' => round($revenue, 2), 'icon' => '💰'],
                ['label' => setting('stats.stats_service.sales_2', 'طلبات مدفوعة'), 'value' => $orders, 'icon' => '🧾'],
                // ⭐ لا استردادات (19.4) — البديل: تصحيحات أخطاء تقنيّة موثّقة
                ['label' => setting('stats.stats_service.sales_3', 'تصحيحات تقنيّة'), 'value' => $corrections, 'icon' => '🛠️'],
                ['label' => setting('stats.stats_service.sales_4', 'كوينز متداولة'), 'value' => round($circulating, 2), 'icon' => '🪙'],
            ],
            'series' => $this->dailySum('orders', 'created_at', 'total', $period['from'], $period['to'], ['status' => 'paid']),
            'series_prev' => $period['compare']
                ? $this->dailySum('orders', 'created_at', 'total', $period['prev_from'], $period['prev_to'], ['status' => 'paid'])
                : [],
            'referral_impact' => round($referral, 2),
            'topup' => $this->topupBreakdown($period),
            'sources' => $this->incomeSources($period),
        ];
    }

    /** تقارير الشحن (19.5-هـ): إجماليّ الطريقتين ونسبة نجاح البوّابة وزمن المراجعة الداخليّ */
    private function topupBreakdown(array $period): array
    {
        $manual = Schema::hasTable('topup_requests')
            ? (float) DB::table('topup_requests')->where('status', 'completed')
                ->whereBetween('created_at', [$period['from'], $period['to']])->sum('credited_amount')
            : 0.0;

        $gatewayPaid = 0;
        $gatewayAll = 0;
        $gatewayTotal = 0.0;

        if (Schema::hasTable('gateway_invoices')) {
            $base = DB::table('gateway_invoices')->whereBetween('created_at', [$period['from'], $period['to']]);
            $gatewayAll = (int) (clone $base)->count();
            $gatewayPaid = (int) (clone $base)->where('status', 'paid')->count();
            $gatewayTotal = (float) (clone $base)->where('status', 'paid')->sum('amount');
        }

        // ⛔ متوسّط زمن المراجعة **داخليّ للأدمن** ولا يُعلَن للمُرسِل (19.5-أ)
        $avgReviewHours = 0.0;

        if (Schema::hasTable('topup_requests')) {
            $rows = DB::table('topup_requests')->whereNotNull('reviewed_at')
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->get(['created_at', 'reviewed_at']);

            if ($rows->isNotEmpty()) {
                $avgReviewHours = round($rows->avg(
                    fn ($r) => Carbon::parse($r->created_at)->diffInMinutes(Carbon::parse($r->reviewed_at)) / 60
                ), 1);
            }
        }

        return [
            'manual_total' => round($manual, 2),
            'gateway_total' => round($gatewayTotal, 2),
            'gateway_success_rate' => $gatewayAll > 0 ? round($gatewayPaid / $gatewayAll * 100, 1) : 0.0,
            'avg_review_hours_internal' => $avgReviewHours,
        ];
    }

    private function incomeSources(array $period): array
    {
        if (! Schema::hasTable('order_items')) {
            return [];
        }

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'paid')
            ->whereBetween('orders.created_at', [$period['from'], $period['to']])
            ->select('order_items.purchasable_type', DB::raw('sum(order_items.price) as total'))
            ->groupBy('order_items.purchasable_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['label' => class_basename((string) $r->purchasable_type), 'value' => (float) $r->total])
            ->all();
    }

    // ---------------------------------------------------------------- التدريبات

    private function training(array $period): array
    {
        $enrolled = Schema::hasTable('enrollments')
            ? (int) DB::table('enrollments')->whereBetween('created_at', [$period['from'], $period['to']])->count()
            : 0;

        $completed = Schema::hasTable('course_completions')
            ? (int) DB::table('course_completions')->whereBetween('completed_at', [$period['from'], $period['to']])->count()
            : 0;

        $certificates = Schema::hasTable('certificates')
            ? (int) DB::table('certificates')->whereBetween('issued_at', [$period['from'], $period['to']])->count()
            : 0;

        return [
            'kpis' => [
                ['label' => setting('stats.stats_service.training_1', 'تسجيلات في تدريبات'), 'value' => $enrolled, 'icon' => '📚'],
                ['label' => setting('stats.stats_service.training_2', 'إتمامات'), 'value' => $completed, 'icon' => '🎓'],
                ['label' => setting('stats.stats_service.training_3', 'شهادات صادرة'), 'value' => $certificates, 'icon' => '🏅'],
                ['label' => setting('stats.stats_service.training_4', 'معدّل الإكمال'), 'value' => $enrolled > 0 ? round($completed / $enrolled * 100).'%' : '0%', 'icon' => '📈'],
            ],
            'dropoff' => $this->dropoff(),
            'series' => $this->daily('course_completions', 'completed_at', $period['from'], $period['to']),
        ];
    }

    /** Drop-off: التدريبات الأكثر تعثّرًا — تسجيلٌ بلا إتمام */
    private function dropoff(): array
    {
        if (! Schema::hasTable('enrollments') || ! Schema::hasTable('courses')) {
            return [];
        }

        return DB::table('enrollments')
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->leftJoin('course_completions', function ($join) {
                $join->on('course_completions.course_id', '=', 'enrollments.course_id')
                    ->on('course_completions.user_id', '=', 'enrollments.user_id');
            })
            ->whereNull('course_completions.id')
            ->select('courses.name_ar as title', DB::raw('count(*) as total'))
            ->groupBy('courses.name_ar')
            ->orderByDesc('total')
            ->limit((int) setting('stats.top_list_size', 8))
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->title, 'value' => (float) $r->total])
            ->all();
    }

    // ---------------------------------------------------------------- التفاعل والحضور والحروب

    private function engagement(array $period): array
    {
        $levels = DB::table('users')->whereNull('deleted_at')
            ->select('level', DB::raw('count(*) as total'))
            ->groupBy('level')->orderBy('level')->get()
            ->map(fn ($r) => ['label' => strtr(setting('stats.stats_service.engagement_1', 'مستوى :p1'), [':p1' => (string) ($r->level)]), 'value' => (float) $r->total])->all();

        $streaks = Schema::hasTable('streaks') ? (int) DB::table('streaks')->where('current_days', '>', 0)->count() : 0;
        $club = Schema::hasTable('streaks') ? (int) DB::table('streaks')->sum('club_5am_count') : 0;

        $ticketsIn = $this->currencyFlow('tickets', $period, true);
        $ticketsOut = $this->currencyFlow('tickets', $period, false);

        return [
            'kpis' => [
                ['label' => setting('stats.stats_service.engagement_2', 'ستريكات نشطة'), 'value' => $streaks, 'icon' => '🔥'],
                ['label' => setting('stats.stats_service.engagement_3', 'حضور نادي الخامسة'), 'value' => $club, 'icon' => '🌅'],
                ['label' => setting('stats.stats_service.engagement_4', 'تذاكر مكتسَبة'), 'value' => round($ticketsIn, 2), 'icon' => '🎟️'],
                ['label' => setting('stats.stats_service.engagement_5', 'تذاكر مصروفة'), 'value' => round(abs($ticketsOut), 2), 'icon' => '💸'],
            ],
            'levels' => $levels,
        ];
    }

    private function attendance(array $period): array
    {
        $events = Schema::hasTable('event_registrations')
            ? (int) DB::table('event_registrations')->where('attended', true)
                ->whereBetween('attended_at', [$period['from'], $period['to']])->count()
            : 0;

        $meetings = Schema::hasTable('meeting_attendances')
            ? (int) DB::table('meeting_attendances')->whereBetween('created_at', [$period['from'], $period['to']])->count()
            : 0;

        $club = Schema::hasTable('streaks') ? (int) DB::table('streaks')->sum('club_5am_count') : 0;

        return [
            'kpis' => [
                ['label' => setting('stats.stats_service.attendance_1', 'حضور فعاليّات'), 'value' => $events, 'icon' => '📅'],
                ['label' => setting('stats.stats_service.attendance_2', 'حضور اجتماعات'), 'value' => $meetings, 'icon' => '🤝'],
                ['label' => setting('stats.stats_service.attendance_3', 'نادي الخامسة'), 'value' => $club, 'icon' => '🌅'],
            ],
            'series' => Schema::hasTable('event_registrations')
                ? $this->daily('event_registrations', 'created_at', $period['from'], $period['to'])
                : [],
        ];
    }

    private function wars(array $period): array
    {
        if (! Schema::hasTable('challenge_participations')) {
            return ['kpis' => [], 'top' => []];
        }

        $base = DB::table('challenge_participations')->whereBetween('created_at', [$period['from'], $period['to']]);
        $total = (int) (clone $base)->count();
        $wins = (int) (clone $base)->where('result', 'win')->count();
        $withdrawn = (int) (clone $base)->where('status', 'withdrawn')->count();

        $top = Schema::hasTable('challenges')
            ? DB::table('challenge_participations')
                ->join('challenges', 'challenges.id', '=', 'challenge_participations.challenge_id')
                ->select('challenges.name_ar as title', DB::raw('count(*) as total'))
                ->groupBy('challenges.name_ar')->orderByDesc('total')->limit((int) setting('stats.top_list_size', 8))->get()
                ->map(fn ($r) => ['label' => (string) $r->title, 'value' => (float) $r->total])->all()
            : [];

        return [
            'kpis' => [
                ['label' => setting('stats.stats_service.wars_1', 'مشاركات'), 'value' => $total, 'icon' => '⚔️'],
                ['label' => setting('stats.stats_service.wars_2', 'معدّل الفوز'), 'value' => $total > 0 ? round($wins / $total * 100).'%' : '0%', 'icon' => '🏆'],
                ['label' => setting('stats.stats_service.wars_3', 'انسحابات'), 'value' => $withdrawn, 'icon' => '🚪'],
                ['label' => setting('stats.stats_service.wars_4', 'تدفّق التذاكر'), 'value' => round($this->currencyFlow('tickets', $period, true), 2), 'icon' => '🎟️'],
            ],
            'top' => $top,
        ];
    }

    // ---------------------------------------------------------------- التطوّع

    /**
     * ⭐ تاب **التطوّع** (24.3-خامسًا) — ومادّته من 12.2.2 حرفيًّا:
     * «`reports_volunteer.view` … تقرير التطوّع: **التسكين · المهامّ · SLA
     * المستويات**» — ثلاثتها لا واحدةً منها.
     *
     * و«SLA المستويات» تُقاس من محرّك التصعيد نفسه (23-5): **نافذة كلّ مستوى 24
     * ساعة ونافذة السقف 48 ساعة**، فالحالة التي قُرِّرت قبل `window_due_at` داخل
     * النافذة، والتي سُوّيت آليًّا (`auto_settled`) هي **الفائتة** — لأنّ التسوية
     * الآليّة لا تقع إلّا بعد فوات النافذة.
     */
    private function volunteer(array $period): array
    {
        $placed = Schema::hasTable('placement_requests')
            ? (int) DB::table('placement_requests')->where('status', 'accepted')
                ->whereBetween('responded_at', [$period['from'], $period['to']])->count()
            : 0;

        $sent = Schema::hasTable('placement_requests')
            ? (int) DB::table('placement_requests')->whereBetween('created_at', [$period['from'], $period['to']])->count()
            : 0;

        $delivered = Schema::hasTable('tasks')
            ? (int) DB::table('tasks')->whereNull('deleted_at')
                ->whereBetween('delivered_at', [$period['from'], $period['to']])->count()
            : 0;

        $approved = Schema::hasTable('tasks')
            ? (int) DB::table('tasks')->whereNull('deleted_at')
                ->whereBetween('approved_at', [$period['from'], $period['to']])->count()
            : 0;

        $sla = $this->escalationSla($period);
        $onTime = array_sum(array_column($sla, 'on_time'));
        $closed = array_sum(array_column($sla, 'closed'));

        return [
            'kpis' => [
                ['label' => (string) setting('stats.volunteer.kpi.placed', 'تسكينات مقبولة'), 'value' => $placed, 'icon' => '🪑'],
                ['label' => (string) setting('stats.volunteer.kpi.delivered', 'مهامّ مسلَّمة'), 'value' => $delivered, 'icon' => '📦'],
                ['label' => (string) setting('stats.volunteer.kpi.approved', 'مهامّ معتمَدة'), 'value' => $approved, 'icon' => '✅'],
                ['label' => (string) setting('stats.volunteer.kpi.sla', 'التزام نوافذ التصعيد'), 'value' => $closed > 0 ? round($onTime / $closed * 100).'%' : '0%', 'icon' => '⏱️'],
            ],
            // «التسكين»: المُرسَل مقابل المقبول عبر الفترة
            'series' => Schema::hasTable('placement_requests')
                ? $this->daily('placement_requests', 'created_at', $period['from'], $period['to'])
                : [],
            'series_prev' => $period['compare'] && Schema::hasTable('placement_requests')
                ? $this->daily('placement_requests', 'created_at', $period['prev_from'], $period['prev_to'])
                : [],
            // «المهامّ»: الأكثر حملًا من الكيانات — أين يقع العمل فعلًا
            'entities' => $this->tasksByEntity($period),
            // «SLA المستويات»: نسبة الالتزام لكلّ مستوى في سلّم التصعيد
            'sla' => $sla,
            'placement_sent' => $sent,
        ];
    }

    /** التزام نافذة القرار لكلّ مستوًى في سلّم التصعيد (23-5) */
    private function escalationSla(array $period): array
    {
        if (! Schema::hasTable('escalations')) {
            return [];
        }

        return DB::table('escalations')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->whereIn('status', ['decided', 'approved', 'rejected', 'auto_settled'])
            ->select('level', DB::raw('count(*) as closed'), DB::raw(
                "sum(case when status <> 'auto_settled' and decided_at is not null and decided_at <= window_due_at then 1 else 0 end) as on_time"
            ))
            ->groupBy('level')
            ->orderBy('level')
            ->get()
            ->map(fn ($r) => [
                'level' => (int) $r->level,
                'closed' => (int) $r->closed,
                'on_time' => (int) $r->on_time,
                'rate' => (int) $r->closed > 0 ? round((int) $r->on_time / (int) $r->closed * 100, 1) : 0.0,
            ])
            ->all();
    }

    private function tasksByEntity(array $period): array
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasTable('entities')) {
            return [];
        }

        return DB::table('tasks')
            ->join('entities', 'entities.id', '=', 'tasks.entity_id')
            ->whereNull('tasks.deleted_at')
            ->whereBetween('tasks.created_at', [$period['from'], $period['to']])
            ->select('entities.name_ar as title', DB::raw('count(*) as total'))
            ->groupBy('entities.name_ar')
            ->orderByDesc('total')
            ->limit((int) setting('stats.top_list_size', 8))
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->title, 'value' => (float) $r->total])
            ->all();
    }

    // ---------------------------------------------------------------- الشهادات

    /**
     * ⭐ تاب **الشهادات** (24.3-خامسًا) — ومادّته من 12.2.2 حرفيًّا:
     * «`reports_certificates.view` … تقرير الشهادات: **معدّل الإصدار ·
     * الإلغاءات · حسب الاعتماد**».
     *
     * و«الإلغاء» غير «الانتهاء» (13.4-ق): المنتهية شهادةٌ صحيحةٌ انقضى العمل بها،
     * والملغاة **تزويرٌ مثبَت** — فخلطهما في رقمٍ واحد اتّهامٌ لأصحاب الأولى.
     */
    private function certificates(array $period): array
    {
        if (! Schema::hasTable('certificates')) {
            return ['kpis' => [], 'series' => [], 'accreditations' => [], 'types' => []];
        }

        $issued = (int) DB::table('certificates')->whereBetween('issued_at', [$period['from'], $period['to']])->count();
        $revoked = (int) DB::table('certificates')->whereNotNull('revoked_at')
            ->whereBetween('revoked_at', [$period['from'], $period['to']])->count();
        $expired = (int) DB::table('certificates')->whereNotNull('expired_at')
            ->whereBetween('expired_at', [$period['from'], $period['to']])->count();

        return [
            'kpis' => [
                ['label' => (string) setting('stats.certificates.kpi.issued', 'شهادات صادرة'), 'value' => $issued, 'icon' => '🏅'],
                ['label' => (string) setting('stats.certificates.kpi.rate', 'معدّل الإصدار اليوميّ'), 'value' => round($issued / max(1, (int) $period['days']), 2), 'icon' => '📈'],
                ['label' => (string) setting('stats.certificates.kpi.revoked', 'إلغاءات'), 'value' => $revoked, 'icon' => '⛔'],
                ['label' => (string) setting('stats.certificates.kpi.expired', 'منتهية'), 'value' => $expired, 'icon' => '🕓'],
            ],
            'series' => $this->daily('certificates', 'issued_at', $period['from'], $period['to']),
            'series_prev' => $period['compare'] ? $this->daily('certificates', 'issued_at', $period['prev_from'], $period['prev_to']) : [],
            'accreditations' => $this->certificatesByAccreditation($period),
            'types' => $this->certificatesByType($period),
        ];
    }

    /** «حسب الاعتماد»: النوع يحمل جهة اعتماده، والشهادة تحمل نوعها */
    private function certificatesByAccreditation(array $period): array
    {
        if (! Schema::hasTable('certificate_types') || ! Schema::hasTable('certificate_accreditations')) {
            return [];
        }

        $fallback = (string) setting('certificates.accreditation.default_name', 'اعتماد المنصّة');

        return DB::table('certificates')
            ->join('certificate_types', 'certificate_types.id', '=', 'certificates.certificate_type_id')
            ->leftJoin('certificate_accreditations', 'certificate_accreditations.id', '=', 'certificate_types.accreditation_id')
            ->whereBetween('certificates.issued_at', [$period['from'], $period['to']])
            ->select('certificate_accreditations.name_ar as title', DB::raw('count(*) as total'))
            ->groupBy('title')
            ->orderByDesc('total')
            ->limit((int) setting('stats.top_list_size', 8))
            ->get()
            ->map(fn ($r) => ['label' => (string) ($r->title ?: $fallback), 'value' => (float) $r->total])
            ->all();
    }

    private function certificatesByType(array $period): array
    {
        if (! Schema::hasTable('certificate_types')) {
            return [];
        }

        return DB::table('certificates')
            ->join('certificate_types', 'certificate_types.id', '=', 'certificates.certificate_type_id')
            ->whereBetween('certificates.issued_at', [$period['from'], $period['to']])
            ->select('certificate_types.name_ar as title', DB::raw('count(*) as total'))
            ->groupBy('title')
            ->orderByDesc('total')
            ->limit((int) setting('stats.top_list_size', 8))
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->title, 'value' => (float) $r->total])
            ->all();
    }

    // ---------------------------------------------------------------- مصادر الاكتساب

    /**
     * ⭐ لوحة مصادر الاكتساب (21.2-ح): المصدر ⟵ التسجيل ⟵ التفعيل ⟵ الشراء بـUTM
     * — فلا يُصرَف على قناةٍ لا نعرف عائدها.
     */
    private function acquisition(array $period): array
    {
        /*
         | ⭐ الحساب نفسه في `App\Services\Growth\AcquisitionFunnel` — مجال النموّ
         | هو صاحب 21.2-ح، وهذه الشاشة **قارئٌ** له. وكان يُحسَب هنا من
         | `tracking_events` وحدها، فتخرج أعمدة «التسجيل/التفعيل/الشراء» صفرًا
         | دائمًا لأنّ تلك المسارات بلا وسم في الـquery — والسلسلة تُقرأ الآن من
         | المصدر المثبَّت على المستخدم.
         */
        return app(AcquisitionFunnel::class)->report($period['from'], $period['to']);
    }

    // ---------------------------------------------------------------- تقرير أثر المكافآت

    /**
     * ⭐ تقرير أثر المكافآت (24.3-خامسًا · 12.9) — ومادّته من 12.9 حرفيًّا:
     * «**تقرير الأثر** (إجماليّ الممنوح/المخصوم لكلّ عملة في فترة) — ضمن
     * الإحصائيّات (12.8)».
     *
     * المصدر نفسه الذي تقرأ منه `RewardGrantService::ledger()`: صفوف
     * `transactions` بـ`source='admin'` — كلّ منحة/خصم يدويّ من 12.9 يمرّ عبر
     * `Integrations::post(..., 'admin', ...)` ولا مصدر آخر يكتب بهذا الوسم.
     */
    private function rewards(array $period): array
    {
        if (! Schema::hasTable('transactions')) {
            return ['kpis' => [], 'series' => [], 'series_prev' => [], 'currencies' => []];
        }

        $base = fn () => DB::table('transactions')->where('source', 'admin')
            ->whereBetween('created_at', [$period['from'], $period['to']]);

        $granted = (float) $base()->where('amount', '>', 0)->sum('amount');
        $deducted = (float) $base()->where('amount', '<', 0)->sum('amount'); // سالبة أصلًا
        $currenciesTouched = (int) $base()->distinct()->count('currency_id');

        return [
            'kpis' => [
                ['label' => (string) setting('stats.rewards.kpi.granted', 'إجماليّ الممنوح'), 'value' => round($granted, 2), 'icon' => '🎁'],
                ['label' => (string) setting('stats.rewards.kpi.deducted', 'إجماليّ المخصوم'), 'value' => round(abs($deducted), 2), 'icon' => '➖'],
                ['label' => (string) setting('stats.rewards.kpi.net', 'الصافي'), 'value' => round($granted + $deducted, 2), 'icon' => '⚖️'],
                ['label' => (string) setting('stats.rewards.kpi.currencies', 'عملات متأثّرة'), 'value' => $currenciesTouched, 'icon' => '🪙'],
            ],
            // «الصافي» اليوميّ: الممنوح موجبٌ والمخصوم سالبٌ فيتّضح الاتّجاه بخطّ واحد
            'series' => $this->dailySum('transactions', 'created_at', 'amount', $period['from'], $period['to'], ['source' => 'admin']),
            'series_prev' => $period['compare']
                ? $this->dailySum('transactions', 'created_at', 'amount', $period['prev_from'], $period['prev_to'], ['source' => 'admin'])
                : [],
            // «جدول تفصيليّ قابل للتصدير» — إجماليّ الممنوح/المخصوم لكلّ عملة حرفيًّا (12.9)
            'currencies' => $this->rewardsByCurrency($period),
        ];
    }

    /** إجماليّ الممنوح/المخصوم لكلّ عملة — نصّ 12.9 حرفيًّا */
    private function rewardsByCurrency(array $period): array
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasTable('currencies')) {
            return [];
        }

        return DB::table('transactions')
            ->join('currencies', 'currencies.id', '=', 'transactions.currency_id')
            ->where('transactions.source', 'admin')
            ->whereBetween('transactions.created_at', [$period['from'], $period['to']])
            ->select(
                'currencies.name_ar as title',
                DB::raw('sum(case when transactions.amount > 0 then transactions.amount else 0 end) as granted'),
                DB::raw('sum(case when transactions.amount < 0 then -transactions.amount else 0 end) as deducted'),
            )
            ->groupBy('currencies.name_ar')
            ->orderByDesc('granted')
            ->get()
            ->map(fn ($r) => [
                'label' => (string) $r->title,
                'granted' => round((float) $r->granted, 2),
                'deducted' => round((float) $r->deducted, 2),
                'net' => round((float) $r->granted - (float) $r->deducted, 2),
            ])
            ->all();
    }

    // ---------------------------------------------------------------- أدوات

    /** @return array<int, array{label:string, value:float}> */
    public function daily(string $table, string $column, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $rows = DB::table($table)
            ->whereBetween($column, [$from, $to])
            ->select(DB::raw("date({$column}) as day"), DB::raw('count(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        return $this->fillDays($rows->all(), $from, $to);
    }

    private function dailySum(string $table, string $column, string $sum, CarbonImmutable $from, CarbonImmutable $to, array $where = []): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $rows = DB::table($table)
            ->where($where)
            ->whereBetween($column, [$from, $to])
            ->select(DB::raw("date({$column}) as day"), DB::raw("sum({$sum}) as total"))
            ->groupBy('day')
            ->pluck('total', 'day');

        return $this->fillDays($rows->all(), $from, $to);
    }

    /** الأيّام الفارغة تظهر أصفارًا — فلا يبدو الرسم مضلّلًا بقفزة وهميّة */
    private function fillDays(array $rows, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $series = [];
        $cursor = $from->startOfDay();

        while ($cursor->lessThanOrEqualTo($to)) {
            $key = $cursor->format('Y-m-d');
            $series[] = ['label' => $cursor->format('m/d'), 'value' => (float) ($rows[$key] ?? 0)];
            $cursor = $cursor->addDay();
        }

        return $series;
    }

    private function countBuyers(array $period): int
    {
        if (! Schema::hasTable('orders')) {
            return 0;
        }

        return (int) DB::table('orders')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->distinct()
            ->count('user_id');
    }

    private function trackingCount(string $event, array $period): int
    {
        if (! Schema::hasTable('tracking_events')) {
            return 0;
        }

        return (int) DB::table('tracking_events')
            ->where('event', $event)
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->count();
    }

    private function currencyFlow(string $code, array $period, bool $positive): float
    {
        if (! Schema::hasTable('transactions')) {
            return 0.0;
        }

        return (float) DB::table('transactions')
            ->join('currencies', 'currencies.id', '=', 'transactions.currency_id')
            ->where('currencies.code', $code)
            ->whereBetween('transactions.created_at', [$period['from'], $period['to']])
            ->where('transactions.amount', $positive ? '>' : '<', 0)
            ->sum('transactions.amount');
    }

    // ------------------------------------------------------------------- التصدير

    /**
     * ⭐ مفتاح عمود المقارنة — Toggle «**ضمّ المقارنة**» يضيفه، ولا يُختار من القائمة:
     * النصّ يعدّه بندًا مستقلًّا عن «الأعمدة المختارة» (24.3-خامسًا).
     */
    public const COMPARE_COLUMN = '__compare';

    /**
     * ⭐⭐ **«[تصدير] الصيغة + الأعمدة المختارة + الفترة + Toggle ضمّ المقارنة»**
     * (24.3-خامسًا) — وهذه هي **قائمة الأعمدة** التي يختار منها الأدمن.
     *
     * لماذا مفاتيح ثابتة ولافتات من `setting()`؟ لأنّ الصفّ المصدَّر كان يُبنى
     * **بلافتاته عناوينَ**، فلو غيّر الأدمن لافتة عمودٍ من الإعدادات انكسر أيّ
     * اختيارٍ محفوظ أو رابطٍ منسوخ. فالمفتاح للاختيار، واللافتة للعنوان — والاثنان
     * لا يختلطان (2.13).
     *
     * والقائمة **مصدرٌ واحد**: تبنيها الشاشة مربّعاتِ اختيار، ويصفّيها التصدير
     * نفسه — فلا يظهر في البوب-أب عمودٌ لا يقع في الملفّ.
     *
     * @return array<string, string> مفتاح العمود ⟵ لافتته
     */
    public function exportColumns(string $tab): array
    {
        return match ($tab) {
            'acquisition' => [
                'source' => (string) setting('stats.acquisition.col.source', 'المصدر (utm_source)'),
                'visits' => (string) setting('stats.acquisition.col.visits', 'زيارات'),
                'registered' => (string) setting('stats.acquisition.col.registered', 'تسجيل'),
                'activated' => (string) setting('stats.acquisition.col.activated', 'تفعيل'),
                'purchased' => (string) setting('stats.acquisition.col.purchased', 'شراء'),
            ],
            'users' => [
                'day' => (string) setting('stats.stats_service.export_rows_1', 'اليوم'),
                'registered' => (string) setting('stats.stats_service.export_rows_2', 'تسجيلات'),
            ],
            'sales' => [
                'day' => (string) setting('stats.stats_service.export_rows_3', 'اليوم'),
                'revenue' => (string) setting('stats.stats_service.export_rows_4', 'الإيراد'),
            ],
            // «جدول تفصيليّ قابل للتصدير» لكلّ تابّ (24.3-خامسًا) — لا كروتُه وحدها
            'volunteer' => [
                'level' => (string) setting('stats.volunteer.col.level', 'مستوى التصعيد'),
                'closed' => (string) setting('stats.volunteer.col.closed', 'حالات مغلقة'),
                'on_time' => (string) setting('stats.volunteer.col.on_time', 'داخل النافذة'),
                'rate' => (string) setting('stats.volunteer.col.rate', 'نسبة الالتزام %'),
            ],
            'certificates' => [
                'accreditation' => (string) setting('stats.certificates.col.accreditation', 'جهة الاعتماد'),
                'issued' => (string) setting('stats.certificates.col.issued', 'شهادات صادرة'),
            ],
            // «إجماليّ الممنوح/المخصوم لكلّ عملة» حرفيًّا (12.9)
            'rewards' => [
                'currency' => (string) setting('stats.rewards.col.currency', 'العملة'),
                'granted' => (string) setting('stats.rewards.col.granted', 'الممنوح'),
                'deducted' => (string) setting('stats.rewards.col.deducted', 'المخصوم'),
                'net' => (string) setting('stats.rewards.col.net', 'الصافي'),
            ],
            default => [
                'metric' => (string) setting('stats.stats_service.export_rows_5', 'المؤشّر'),
                'value' => (string) setting('stats.stats_service.export_rows_6', 'القيمة'),
            ],
        };
    }

    /**
     * الأعمدة المختارة بعد تنقيتها — بترتيب القائمة لا بترتيب ما وصل.
     *
     * ولماذا «لا اختيار ⟵ الكلّ»؟ لأنّ رابط تصديرٍ قديمًا (أو مجدولًا) بلا
     * `columns[]` يجب أن يظلّ يُخرِج الملفّ كاملًا — لا ملفًّا فارغ الأعمدة.
     *
     * @param  array<int,string>|null  $columns
     * @return array<int, string>
     */
    public function selectedExportColumns(string $tab, ?array $columns): array
    {
        $available = array_keys($this->exportColumns($tab));

        if ($columns === null) {
            return $available;
        }

        $picked = array_values(array_intersect($available, array_map('strval', $columns)));

        return $picked !== [] ? $picked : $available;
    }

    /**
     * تصدير مرن: **الأعمدة المختارة + الفترة + ضمّ المقارنة** — وحدّ الصفوف إعداد.
     *
     * @param  array<int,string>|null  $columns  مفاتيح الأعمدة المطلوبة (null = الكلّ)
     * @return array<int, array<string,mixed>>
     */
    public function exportRows(string $tab, array $period, ?array $columns = null): array
    {
        $labels = $this->exportColumns($tab);
        $selected = $this->selectedExportColumns($tab, $columns);
        $limit = (int) setting('stats.export.max_rows', 50000);

        $data = $this->data($tab, $period);
        $rows = $this->rawExportRows($tab, $data);

        if ($period['compare']) {
            $rows = $this->withComparison($tab, $rows, $data, $period);
            $labels[self::COMPARE_COLUMN] = (string) setting('stats.export.col.compare', 'الفترة السابقة');
            $selected[] = self::COMPARE_COLUMN;
        }

        return array_map(
            function (array $row) use ($labels, $selected): array {
                $out = [];

                foreach ($selected as $key) {
                    $out[$labels[$key] ?? $key] = $row[$key] ?? '';
                }

                return $out;
            },
            array_slice($rows, 0, $limit),
        );
    }

    /**
     * صفوف التابّ **بمفاتيح الأعمدة** لا بلافتاتها — طبقةٌ وسطى تجعل التصفية
     * والمقارنة ممكنتين قبل أن تتحوّل المفاتيح إلى عناوين في الملفّ.
     *
     * @param  array<string,mixed>  $data
     * @return array<int, array<string,mixed>>
     */
    private function rawExportRows(string $tab, array $data): array
    {
        return match ($tab) {
            'acquisition' => array_map(fn ($r) => [
                'source' => $r['source'] ?? '',
                'visits' => $r['visits'] ?? 0,
                'registered' => $r['registered'] ?? 0,
                'activated' => $r['activated'] ?? 0,
                'purchased' => $r['purchased'] ?? 0,
            ], $data['rows'] ?? []),
            'users' => array_map(fn ($p) => ['day' => $p['label'], 'registered' => $p['value']], $data['growth'] ?? []),
            'sales' => array_map(fn ($p) => ['day' => $p['label'], 'revenue' => $p['value']], $data['series'] ?? []),
            'volunteer' => array_map(fn ($r) => [
                'level' => $r['level'],
                'closed' => $r['closed'],
                'on_time' => $r['on_time'],
                'rate' => $r['rate'],
            ], $data['sla'] ?? []),
            'certificates' => array_map(fn ($r) => [
                'accreditation' => $r['label'],
                'issued' => $r['value'],
            ], $data['accreditations'] ?? []),
            'rewards' => array_map(fn ($r) => [
                'currency' => $r['label'],
                'granted' => $r['granted'],
                'deducted' => $r['deducted'],
                'net' => $r['net'],
            ], $data['currencies'] ?? []),
            default => array_map(
                fn ($k) => ['metric' => $k['label'], 'value' => $k['value']],
                $data['kpis'] ?? [],
            ),
        };
    }

    /**
     * ⭐ Toggle «**ضمّ المقارنة**» — عمودٌ يقع فعلًا في الملفّ، لا علامةٌ في الرابط.
     *
     * والمطابقة تختلف بطبيعة الصفّ، ولا نتظاهر بغير ذلك:
     *  · **تابّا السلاسل اليوميّة** (المستخدمون/المبيعات): الصفّ يومٌ، وتاريخُ
     *    الفترة السابقة **لا يساوي** تاريخ الحاليّة — فالمطابقة **بالترتيب**
     *    (اليوم الأوّل باليوم الأوّل)، وهي نفس مطابقة الخطّ المتقطّع في الرسم.
     *  · **التابّات التصنيفيّة** (عملة/جهة اعتماد/مصدر/مستوى/مؤشّر): المطابقة
     *    **بمفتاح الصفّ نفسه**، وما لا مقابل له في الفترة السابقة يخرج صفرًا.
     *
     * @param  array<int, array<string,mixed>>  $rows
     * @param  array<string,mixed>  $data
     * @return array<int, array<string,mixed>>
     */
    private function withComparison(string $tab, array $rows, array $data, array $period): array
    {
        // سلسلة الفترة السابقة محسوبةٌ أصلًا مع بيانات التابّ — لا نحسبها ثانيةً
        $series = match ($tab) {
            'users' => array_column($data['growth_prev'] ?? [], 'value'),
            'sales' => array_column($data['series_prev'] ?? [], 'value'),
            default => null,
        };

        if ($series !== null) {
            foreach ($rows as $i => $row) {
                $rows[$i][self::COMPARE_COLUMN] = $series[$i] ?? 0;
            }

            return $rows;
        }

        $keys = array_keys($this->exportColumns($tab));
        $keyColumn = $keys[0] ?? null;
        $valueColumn = $keys[1] ?? null;

        if ($keyColumn === null || $valueColumn === null) {
            return $rows;
        }

        $previous = $this->rawExportRows($tab, $this->data($tab, $this->period(
            $period['prev_from']->toDateString(),
            $period['prev_to']->toDateString(),
            false,
        )));

        $index = [];

        foreach ($previous as $row) {
            $index[(string) ($row[$keyColumn] ?? '')] = $row[$valueColumn] ?? 0;
        }

        foreach ($rows as $i => $row) {
            $rows[$i][self::COMPARE_COLUMN] = $index[(string) ($row[$keyColumn] ?? '')] ?? 0;
        }

        return $rows;
    }
}
