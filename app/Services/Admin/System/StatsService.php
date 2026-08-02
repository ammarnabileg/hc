<?php

namespace App\Services\Admin\System;

use App\Models\User;
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
    /** @return array<string, array{label:string, permission:?string, owner_only:bool}> */
    public function tabs(): array
    {
        return [
            'users' => ['label' => 'المستخدمون', 'permission' => 'reports_users.view', 'owner_only' => false],
            'sales' => ['label' => '🔒 المبيعات والماليّات', 'permission' => 'finance.view', 'owner_only' => true],
            'training' => ['label' => 'التدريبات', 'permission' => 'reports_training.view', 'owner_only' => false],
            'engagement' => ['label' => 'التفاعل والتلعيب', 'permission' => 'reports_engagement.view', 'owner_only' => false],
            'attendance' => ['label' => 'الحضور', 'permission' => 'reports_engagement.view', 'owner_only' => false],
            'wars' => ['label' => 'الحروب', 'permission' => 'reports_engagement.view', 'owner_only' => false],
            'acquisition' => ['label' => 'مصادر الاكتساب', 'permission' => 'acquisition_sources.view', 'owner_only' => false],
        ];
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
            'acquisition' => $this->acquisition($period),
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
                ['label' => 'تسجيلات جديدة', 'value' => $registered, 'icon' => '👥'],
                ['label' => 'حسابات معتمَدة', 'value' => $approved, 'icon' => '✅'],
                ['label' => 'أوّل شراء', 'value' => $buyers, 'icon' => '🛒'],
                ['label' => 'نسبة الاعتماد', 'value' => $registered > 0 ? round($approved / $registered * 100).'%' : '0%', 'icon' => '📈'],
            ],
            'growth' => $growth,
            'growth_prev' => $period['compare'] ? $this->daily('users', 'created_at', $period['prev_from'], $period['prev_to']) : [],
            'funnel' => [
                ['label' => 'زائر', 'value' => $visitors],
                ['label' => 'مسجّل', 'value' => $registered],
                ['label' => 'معتمَد', 'value' => $approved],
                ['label' => 'أوّل شراء', 'value' => $buyers],
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
            'col_labels' => array_map(fn ($k) => 'شهر '.$k, range(0, $months - 1)),
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
            'row_labels' => ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
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
                ['label' => 'الإيرادات', 'value' => round($revenue, 2), 'icon' => '💰'],
                ['label' => 'طلبات مدفوعة', 'value' => $orders, 'icon' => '🧾'],
                // ⭐ لا استردادات (19.4) — البديل: تصحيحات أخطاء تقنيّة موثّقة
                ['label' => 'تصحيحات تقنيّة', 'value' => $corrections, 'icon' => '🛠️'],
                ['label' => 'كوينز متداولة', 'value' => round($circulating, 2), 'icon' => '🪙'],
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
                ['label' => 'تسجيلات في تدريبات', 'value' => $enrolled, 'icon' => '📚'],
                ['label' => 'إتمامات', 'value' => $completed, 'icon' => '🎓'],
                ['label' => 'شهادات صادرة', 'value' => $certificates, 'icon' => '🏅'],
                ['label' => 'معدّل الإكمال', 'value' => $enrolled > 0 ? round($completed / $enrolled * 100).'%' : '0%', 'icon' => '📈'],
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
            ->map(fn ($r) => ['label' => 'مستوى '.$r->level, 'value' => (float) $r->total])->all();

        $streaks = Schema::hasTable('streaks') ? (int) DB::table('streaks')->where('current_days', '>', 0)->count() : 0;
        $club = Schema::hasTable('streaks') ? (int) DB::table('streaks')->sum('club_5am_count') : 0;

        $ticketsIn = $this->currencyFlow('tickets', $period, true);
        $ticketsOut = $this->currencyFlow('tickets', $period, false);

        return [
            'kpis' => [
                ['label' => 'ستريكات نشطة', 'value' => $streaks, 'icon' => '🔥'],
                ['label' => 'حضور نادي الخامسة', 'value' => $club, 'icon' => '🌅'],
                ['label' => 'تذاكر مكتسَبة', 'value' => round($ticketsIn, 2), 'icon' => '🎟️'],
                ['label' => 'تذاكر مصروفة', 'value' => round(abs($ticketsOut), 2), 'icon' => '💸'],
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
                ['label' => 'حضور فعاليّات', 'value' => $events, 'icon' => '📅'],
                ['label' => 'حضور اجتماعات', 'value' => $meetings, 'icon' => '🤝'],
                ['label' => 'نادي الخامسة', 'value' => $club, 'icon' => '🌅'],
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
                ['label' => 'مشاركات', 'value' => $total, 'icon' => '⚔️'],
                ['label' => 'معدّل الفوز', 'value' => $total > 0 ? round($wins / $total * 100).'%' : '0%', 'icon' => '🏆'],
                ['label' => 'انسحابات', 'value' => $withdrawn, 'icon' => '🚪'],
                ['label' => 'تدفّق التذاكر', 'value' => round($this->currencyFlow('tickets', $period, true), 2), 'icon' => '🎟️'],
            ],
            'top' => $top,
        ];
    }

    // ---------------------------------------------------------------- مصادر الاكتساب

    /**
     * ⭐ لوحة مصادر الاكتساب (21.2-ح): المصدر ⟵ التسجيل ⟵ التفعيل ⟵ الشراء بـUTM
     * — فلا يُصرَف على قناةٍ لا نعرف عائدها.
     */
    private function acquisition(array $period): array
    {
        if (! Schema::hasTable('tracking_events')) {
            return ['rows' => [], 'kpis' => []];
        }

        $sources = DB::table('tracking_events')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->whereNotNull('utm_source')
            ->distinct()
            ->pluck('utm_source');

        $rows = [];

        foreach ($sources as $source) {
            $users = DB::table('tracking_events')
                ->where('utm_source', $source)
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

            $rows[] = [
                'source' => (string) $source,
                'visits' => (int) DB::table('tracking_events')
                    ->where('utm_source', $source)
                    ->whereBetween('created_at', [$period['from'], $period['to']])->count(),
                'registered' => $users->count(),
                'activated' => (int) User::query()->whereIn('id', $users)->where('status', 'active')->count(),
                'purchased' => Schema::hasTable('orders')
                    ? (int) DB::table('orders')->whereIn('user_id', $users)->where('status', 'paid')->distinct()->count('user_id')
                    : 0,
            ];
        }

        usort($rows, fn ($a, $b) => $b['registered'] <=> $a['registered']);

        return [
            'rows' => $rows,
            'kpis' => [
                ['label' => 'مصادر نشطة', 'value' => count($rows), 'icon' => '📣'],
                ['label' => 'زيارات موسومة', 'value' => array_sum(array_column($rows, 'visits')), 'icon' => '🔗'],
                ['label' => 'تسجيلات', 'value' => array_sum(array_column($rows, 'registered')), 'icon' => '👥'],
                ['label' => 'مشترون', 'value' => array_sum(array_column($rows, 'purchased')), 'icon' => '🛒'],
            ],
        ];
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

    /**
     * تصدير مرن: الأعمدة المختارة + الفترة + ضمّ المقارنة — وحدّ الصفوف إعداد.
     *
     * @return array<int, array<string,mixed>>
     */
    public function exportRows(string $tab, array $period): array
    {
        $data = $this->data($tab, $period);
        $limit = (int) setting('stats.export.max_rows', 50000);

        $rows = match ($tab) {
            'acquisition' => $data['rows'] ?? [],
            'users' => array_map(fn ($p) => ['اليوم' => $p['label'], 'تسجيلات' => $p['value']], $data['growth'] ?? []),
            'sales' => array_map(fn ($p) => ['اليوم' => $p['label'], 'الإيراد' => $p['value']], $data['series'] ?? []),
            default => array_map(
                fn ($k) => ['المؤشّر' => $k['label'], 'القيمة' => $k['value']],
                $data['kpis'] ?? [],
            ),
        };

        return array_slice($rows, 0, $limit);
    }
}
