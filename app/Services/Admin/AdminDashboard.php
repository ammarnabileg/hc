<?php

namespace App\Services\Admin;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\Complaint;
use App\Models\Course;
use App\Models\Event;
use App\Models\Order;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * لوحة القيادة (الدستور 12.3 · 24.1).
 *
 * سؤال الشاشة واحد: «إيه حالة المنصّة، وإيه اللي مستنّي قرارك؟»
 * فالصفّ الأوّل **أربعة كروت بحدّ أقصى** (2.15-أ-3) والباقي في تاب «تفاصيل»
 * — والزائد **يُنقَل ولا يُحذَف**. وكلّ رقم يقارَن بالفترة السابقة ويُلوَّن
 * بمعنى واحد من قاموس 2.16.
 *
 * ⭐ **فلتر الفترة موحَّد مع 12.8**: «من/إلى» مفهومٌ واحد فسلوكٌ واحد — وكانت
 * اللوحة تعرض قائمة `[1,7,30,90]` بينما الإحصائيّات تعرض تاريخين، فيتعلّم
 * الأدمن الفكرة نفسها مرّتين بشكلين. والقائمة بقيت **اختصارات** تملأ التاريخين.
 *
 * ⭐ **الماليّات لمالك المنصّة وحده**: كارت الإيرادات وAOV والأعلى مبيعًا
 * والسحوبات وعمولة الريفيرال **لا تُحسَب ولا تُعرَض** لغيره — يُحذف الكارت
 * ولا يُعطَّل (2.15-أ-7 · 12.7).
 *
 * ⭐ **وكارت «مبيعات (كوينز)» منها**: قيمته هي **مجموع الطلبات المدفوعة** نفسه
 * الذي يحمله كارت «🔒 الإيرادات»، وكان يخرج بقيمته لكلّ أدمن بينما **رابطه
 * وحده** محروس — فيقرأ مسؤول الدعم إيراد المنصّة من كارتٍ بلا قفل. و«بلا
 * صلاحيّة = **مخفيّ فعلًا**، لا معطَّل ولا رماديّ» (2.15-أ-7)، و12.3 ينصّ على
 * أنّ الكارت الممنوع **يُحذف لا يُعطَّل**. ومعه **خطّ المبيعات في الرسم**:
 * إخفاءُ الكارت وحده يترك الرقم نفسه مرسومًا بجواره فيكون إخفاءً بالاسم.
 */
class AdminDashboard
{
    /**
     * فلتر الفترة العامّ — **بنفس عقد 12.8 حرفيًّا** (من/إلى/مقارنة).
     *
     * @return array{from:CarbonImmutable, to:CarbonImmutable, prev_from:CarbonImmutable, prev_to:CarbonImmutable, days:int, compare:bool}
     */
    public function period(?string $from, ?string $to, bool $compare): array
    {
        $defaultDays = max(1, (int) setting('admin.dashboard.default_days', 30));

        $end = $this->parse($to)?->endOfDay() ?? CarbonImmutable::now()->endOfDay();
        $start = $this->parse($from)?->startOfDay() ?? $end->subDays($defaultDays - 1)->startOfDay();

        // تاريخان مقلوبان خطأ إنسانيّ لا خطأ نظام — نصحّحه بلا رسالة عتاب (2.17-ب)
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

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

    /**
     * اختصارات الفترة (اليوم/أسبوع/شهر — 12.3-1): تملأ «من/إلى» ولا تستبدلهما،
     * فالمفهوم يبقى واحدًا والاختصار مجرّد راحة.
     *
     * @return array<int, array{days:int, label:string, from:string, to:string, active:bool}>
     */
    public function quickRanges(array $period): array
    {
        $options = setting('admin.dashboard.range_options', [1, 7, 30, 90]);
        $options = is_array($options) && $options !== [] ? array_map('intval', $options) : [1, 7, 30, 90];

        $today = CarbonImmutable::now()->endOfDay();

        return array_values(array_map(function (int $days) use ($today, $period) {
            $from = $today->subDays(max($days, 1) - 1)->startOfDay();

            return [
                'days' => $days,
                'label' => match ($days) {
                    1 => setting('admin_dashboard.admin_dashboard.quick_ranges_1', 'اليوم'),
                    7 => setting('admin_dashboard.admin_dashboard.quick_ranges_2', 'أسبوع'),
                    30 => setting('admin_dashboard.admin_dashboard.quick_ranges_3', 'شهر'),
                    default => strtr(setting('admin_dashboard.admin_dashboard.quick_ranges_4', 'آخر :p1 يوم'), [':p1' => (string) ($days)]),
                },
                'from' => $from->toDateString(),
                'to' => $today->toDateString(),
                'active' => $period['days'] === $days
                    && $period['to']->toDateString() === $today->toDateString(),
            ];
        }, $options));
    }

    /**
     * كلّ كروت اللوحة مرتّبةً بتخصيص دور المستخدم — والمحظور محذوف لا معطَّل.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cards(array $period, User $user): array
    {
        $from = $period['from'];
        $to = $period['to'];
        $prevFrom = $period['prev_from'];
        $prevTo = $period['prev_to'];

        /*
         | 🔒 بوّابة **كلّ رقمٍ ماليّ** في اللوحة: مفتاح `finance.view` (12.7) —
         | وهو في مصفوفة 12.2.2 «**مالك المنصّة فقط**» (`is_owner_only`)، فمحرّك
         | الصلاحيّات يردّه عن أيّ دورٍ آخر مهما مُنِح. والحراسة **بالمفتاح لا
         | باسم الدور**: الشاشة نتيجةُ الصلاحيّة لا صلاحيّةٌ بذاتها (12.2.1-أ).
         */
        $money = $user->allows('finance.view');

        /*
         | و«**لا تُحسَب ولا تُعرَض**» حرفيًّا: لا استعلامَ إيرادٍ أصلًا لمن لا
         | يملك المفتاح — فلا رقم يُبنى ثمّ يُخفى، ولا استعلامَ بلا مستهلك.
         */
        $paidCount = 0;
        $revenue = 0.0;
        $prevRevenue = 0.0;

        if ($money) {
            $paid = Order::where('status', 'paid')->whereBetween('paid_at', [$from, $to]);
            $paidCount = (clone $paid)->count();
            $revenue = (float) (clone $paid)->sum('total');
            $prevRevenue = (float) Order::where('status', 'paid')
                ->whereBetween('paid_at', [$prevFrom, $prevTo])
                ->sum('total');
        }

        $cards = array_values(array_filter([
            $this->card('users', setting('admin_dashboard.admin_dashboard.cards_1', 'كلّ المستخدمين'), 'people',
                User::where('created_at', '<=', $to)->count(),
                User::where('created_at', '<=', $prevTo)->count(),
                setting('admin_dashboard.admin_dashboard.cards_2', 'إجمالي الحسابات حتى نهاية الفترة'),
                $this->urlFor('admin.users.index', $user, 'users.list')),

            $this->card('signups', setting('admin_dashboard.admin_dashboard.cards_3', 'مسجّلون جدد'), 'user',
                User::whereBetween('created_at', [$from, $to])->count(),
                User::whereBetween('created_at', [$prevFrom, $prevTo])->count(),
                setting('admin_dashboard.admin_dashboard.cards_4', 'حسابات اتسجّلت داخل الفترة'),
                $this->urlFor('admin.users.approvals', $user, 'user_approvals.list')),

            /*
             | 🔒 «مبيعات (كوينز)» = مجموع الطلبات المدفوعة = **الإيراد نفسه**،
             | فيُحذف الكارت كلّه لمن لا يملك `finance.view` ولا يُكتفى بنزع
             | رابطه: الرابط المنزوع يمنع الباب ويُبقي الرقم — وهو عين ما نهت
             | عنه 2.15-أ-7 («بلا صلاحيّة = مخفيّ فعلًا») و12.3 («الكارت الممنوع
             | يُحذف لا يُعطَّل»).
             */
            $money ? $this->card('sales', setting('admin_dashboard.admin_dashboard.cards_5', 'مبيعات (كوينز)'), 'store',
                (int) round($revenue),
                (int) round($prevRevenue),
                setting('admin_dashboard.admin_dashboard.cards_6', 'إجمالي الطلبات المدفوعة داخل الفترة'),
                $this->urlFor('admin.store.index', $user, 'orders.list')) : null,

            $this->card('courses', setting('admin_dashboard.admin_dashboard.cards_7', 'تدريبات نشطة'), 'training',
                Course::where('status', 'published')->count(),
                Course::where('status', 'published')->where('created_at', '<=', $prevTo)->count(),
                setting('admin_dashboard.admin_dashboard.cards_8', 'تدريبات منشورة ومتاحة دلوقتي'),
                $this->urlFor('admin.courses.index', $user, 'courses.list')),

            $this->card('events', setting('admin_dashboard.admin_dashboard.cards_9', 'فعاليّات قادمة'), 'event',
                Event::where('starts_at', '>=', now())->where('status', 'published')->count(),
                Event::where('starts_at', '>=', $prevTo)->where('status', 'published')->count(),
                setting('admin_dashboard.admin_dashboard.cards_10', 'فعاليّات لسّه ماجتش'),
                $this->urlFor('admin.events.index', $user, 'events.list')),

            $this->card('online', setting('admin_dashboard.admin_dashboard.cards_11', 'النشطون الآن'), 'eye',
                User::where('last_seen_at', '>=', now()->subMinutes((int) setting('admin_dashboard.online_window_minutes', 15)))->count(),
                0,
                setting('admin_dashboard.admin_dashboard.cards_12', 'مستخدمون ظهروا في آخر ربع ساعة'),
                $this->urlFor('admin.users.index', $user, 'users.list')),
        ], static fn (?array $card): bool => $card !== null));

        // 🔒 الكروت الماليّة (12.3-6 · 12.3-8 · 12.3-9): لصاحب `finance.view` وحده
        if ($money) {
            $cards[] = $this->card('revenue', setting('admin_dashboard.admin_dashboard.cards_13', '🔒 الإيرادات'), 'money', (int) round($revenue),
                (int) round($prevRevenue),
                setting('admin_dashboard.admin_dashboard.cards_14', 'إجمالي المدفوع داخل الفترة'),
                $this->urlFor('admin.finance.index', $user, 'finance.view'));

            $cards[] = $this->card('aov', setting('admin_dashboard.admin_dashboard.cards_15', '🔒 متوسّط قيمة الطلب'), 'transaction',
                $paidCount > 0 ? (int) round($revenue / $paidCount) : 0,
                $this->previousAov($prevFrom, $prevTo),
                setting('admin_dashboard.admin_dashboard.cards_16', 'الإيرادات ÷ عدد الطلبات المدفوعة'),
                $this->urlFor('admin.finance.index', $user, 'finance.view'));

            $cards[] = $this->card('withdrawals', setting('admin_dashboard.admin_dashboard.cards_17', '🔒 سحوبات مستحقّة'), 'withdraw',
                (int) round(abs((float) $this->pendingWithdrawQuery()->sum('amount'))), 0,
                setting('admin_dashboard.admin_dashboard.cards_18', 'إجمالي طلبات السحب اللي لسّه مستنّية'),
                $this->urlFor('admin.finance.index', $user, 'finance.view'));

            $cards[] = $this->card('referral', setting('admin_dashboard.admin_dashboard.cards_19', '🔒 عمولة الريفيرال'), 'referral',
                (int) round((float) Referral::whereBetween('updated_at', [$from, $to])->sum('commission_earned')),
                (int) round((float) Referral::whereBetween('updated_at', [$prevFrom, $prevTo])->sum('commission_earned')),
                setting('admin_dashboard.admin_dashboard.cards_20', 'العمولة المصروفة داخل الفترة'),
                $this->urlFor('admin.referrals.index', $user, 'referrals.list'));
        }

        return $this->applyLayout($cards, $this->layoutFor($user));
    }

    /**
     * أربعة كروت بالحدّ الأقصى في الصفّ الأوّل (2.15-أ-3).
     *
     * @return array<int, array<string, mixed>>
     */
    public function kpis(array $period, User $user): array
    {
        return array_slice(
            $this->cards($period, $user),
            0,
            max(1, (int) setting('admin.dashboard.kpi_max_cards', 4)),
        );
    }

    /**
     * تاب «تفاصيل»: كلّ كارت زائد عن الأربعة **يُنقَل هنا لا يُحذَف** (2.15-أ-3)،
     * ومعه اللوحات العميقة (قمع · خريطة · تلعيب · Drop-off · حروب · هدف شهريّ).
     */
    public function details(array $period, User $user): array
    {
        $all = $this->cards($period, $user);
        $shown = max(1, (int) setting('admin.dashboard.kpi_max_cards', 4));

        return [
            'cards' => array_slice($all, $shown),
            'funnel' => $this->funnel($period['from'], $period['to']),
            'topReferrers' => $this->topReferrers(),
            'geo' => $this->geoHeat(),
            'gamification' => $this->gamificationHealth(),
            'dropoff' => $this->courseDropoff(),
            'wars' => $this->runningWars(),
            // 🔒 الهدف الشهريّ وأعلى مبيعًا رقمان ماليّان — لمالك المنصّة وحده
            'target' => $user->isPlatformOwner() ? $this->monthlyTarget() : null,
            'topSelling' => $user->isPlatformOwner() ? $this->topSelling($period) : collect(),
        ];
    }

    /**
     * سلسلة زمنيّة للتسجيلات والمبيعات — تُرسَم SVG بأيدينا بلا مكتبة خارجيّة.
     *
     * 🔒 و**خطّ المبيعات رقمٌ ماليّ** كسائر الأرقام الماليّة: لا يُحسَب ولا
     * يُرسَم لمن لا يملك `finance.view` (12.7). فحذفُ كارت المبيعات وحده كان
     * سيبقي الرقم نفسه معروضًا في الرسم بجواره — على محور القيم وفي تلميح كلّ
     * نقطة — فيصير الإخفاء اسمًا بلا معنى (2.15-أ-7).
     *
     * والمستخدم اختياريّ ويقفل عند غيابه: **ما لا نعرف صاحبه لا نكشف له مالًا**.
     */
    public function series(array $period, ?User $user = null): array
    {
        $money = $user !== null && $user->allows('finance.view');

        $days = max(1, (int) $period['days']);
        $buckets = min($days, 30);
        $step = max(1, (int) ceil($days / $buckets));
        $points = [];

        for ($i = 0; $i < $buckets; $i++) {
            $start = $period['from']->addDays($i * $step);
            $end = $start->addDays($step);

            $point = [
                'label' => $start->translatedFormat('j M'),
                'short' => $start->format('j/n'),
                'signups' => User::whereBetween('created_at', [$start, $end])->count(),
            ];

            if ($money) {
                $point['sales'] = (int) round((float) Order::where('status', 'paid')->whereBetween('paid_at', [$start, $end])->sum('total'));
            }

            $points[] = $point;
        }

        return $points;
    }

    /** قمع التحويل: مسجّل ⟵ معتمَد ⟵ مشترٍ (12.3-7) */
    public function funnel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $registered = User::whereBetween('created_at', [$from, $to])->count();
        $approved = User::whereBetween('created_at', [$from, $to])->where('status', 'active')->count();
        $buyers = Order::where('status', 'paid')->whereBetween('paid_at', [$from, $to])->distinct('user_id')->count('user_id');

        return [
            ['label' => setting('admin_dashboard.admin_dashboard.funnel_1', 'مسجّل'), 'value' => $registered],
            ['label' => setting('admin_dashboard.admin_dashboard.funnel_2', 'معتمَد'), 'value' => $approved],
            ['label' => setting('admin_dashboard.admin_dashboard.funnel_3', 'مشترٍ'), 'value' => $buyers],
        ];
    }

    /** حسابات محتاجة موافقة — والكارت بزرّ [عرض الكلّ] (12.3) */
    public function pendingAccounts(): Collection
    {
        return User::where('status', 'pending')
            ->orderBy('created_at')
            ->limit((int) setting('admin.dashboard.approvals_preview_rows', 5))
            ->get();
    }

    public function pendingAccountsCount(): int
    {
        return User::where('status', 'pending')->count();
    }

    /**
     * المهامّ المعلّقة: طلبات سحب + شكاوى — مرتّبة بالأقدم أوّلًا (12.3-16)،
     * وكلّ بند بزرّ [مراجعة] ينقل لمكانه مباشرةً.
     */
    public function pendingWork(): Collection
    {
        $limit = (int) setting('admin.dashboard.pending_preview_rows', 6);
        $lateHours = (float) setting('admin.dashboard.withdraw_late_hours', 48);

        $withdrawals = $this->pendingWithdrawQuery()
            ->with('user')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $row) => [
                'type' => setting('admin_dashboard.admin_dashboard.pending_work_1', 'سحب'),
                'icon' => 'withdraw',
                'title' => ($row->user?->shortName() ?? setting('admin_dashboard.admin_dashboard.pending_work_2', 'مستخدم')).' — '.number_format(abs((float) $row->amount)).setting('admin_dashboard.admin_dashboard.pending_work_3', ' كوينز'),
                'at' => $row->created_at,
                'state' => $row->created_at->diffInHours(now()) >= $lateHours ? 'danger' : 'warn',
                'url' => Route::has('admin.store.index') ? route('admin.store.index') : null,
            ]);

        $complaints = Complaint::where('status', 'open')
            ->with('user')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Complaint $row) => [
                'type' => $row->type === 'suggestion' ? setting('admin_dashboard.admin_dashboard.pending_work_4', 'مقترح') : setting('admin_dashboard.admin_dashboard.pending_work_5', 'شكوى'),
                'icon' => 'complaint',
                'title' => $row->title,
                'at' => $row->created_at,
                'state' => $row->created_at->diffInDays(now()) >= 3 ? 'danger' : 'warn',
                'url' => Route::has('admin.guidance.index') ? route('admin.guidance.index') : null,
            ]);

        return $withdrawals->concat($complaints)->sortBy('at')->take($limit)->values();
    }

    public function pendingWorkCounts(): array
    {
        return [
            (string) setting('admin_dashboard.admin_dashboard.pending_work_counts_1', 'سحوبات') => $this->pendingWithdrawQuery()->count(),
            (string) setting('admin_dashboard.admin_dashboard.pending_work_counts_2', 'شكاوى') => Complaint::where('status', 'open')->count(),
        ];
    }

    /** تنبيهات استباقيّة بعتباتها من الإعدادات (12.3-17) */
    public function alerts(): array
    {
        $alerts = [];

        $lateHours = (float) setting('admin.dashboard.withdraw_late_hours', 48);
        $lateWithdrawals = $this->pendingWithdrawQuery()
            ->where('created_at', '<=', now()->subHours($lateHours))
            ->count();

        if ($lateWithdrawals > 0) {
            $alerts[] = [
                'state' => 'danger',
                'text' => strtr((string) setting('admin.dashboard.alert_withdraw', 'في :count طلب سحب فات عليه :hours ساعة'), [
                    ':count' => $lateWithdrawals,
                    ':hours' => (int) $lateHours,
                ]),
            ];
        }

        $lateDays = (float) setting('admin.dashboard.approval_late_days', 2);
        $lateApprovals = User::where('status', 'pending')
            ->where('created_at', '<=', now()->subDays($lateDays))
            ->count();

        if ($lateApprovals > 0) {
            $alerts[] = [
                'state' => 'warn',
                'text' => strtr((string) setting('admin.dashboard.alert_approvals', 'في :count حساب مستنّي اعتماد من :days يوم'), [
                    ':count' => $lateApprovals,
                    ':days' => (int) $lateDays,
                ]),
            ];
        }

        return $alerts;
    }

    /** أبرز المؤثّرين — أعلى الدعوات (12.3-12) */
    public function topReferrers(): Collection
    {
        return Referral::query()
            ->selectRaw('referrer_id, count(*) as invites')
            ->whereNotNull('referred_id')
            ->groupBy('referrer_id')
            ->orderByDesc('invites')
            ->limit((int) setting('admin.dashboard.top_rows', 10))
            ->get()
            ->map(fn ($row) => [
                'user' => User::find($row->referrer_id),
                'invites' => (int) $row->invites,
            ])
            ->filter(fn ($row) => $row['user'] !== null)
            ->values();
    }

    // ------------------------------------------------------- اللوحات العميقة (12.3)

    /**
     * 🔒 هدف شهريّ قابل للتخصيص مع بار تقدّم (12.3-10).
     * الهدف إعدادٌ يكتبه الأدمن، والمتحقّق **يُحسَب من الطلبات المدفوعة** لا يُكتَب.
     */
    public function monthlyTarget(): array
    {
        $target = (float) setting('admin.dashboard.monthly_target', 0);
        $monthStart = CarbonImmutable::now()->startOfMonth();

        $achieved = (float) Order::where('status', 'paid')
            ->whereBetween('paid_at', [$monthStart, CarbonImmutable::now()])
            ->sum('total');

        return [
            'target' => round($target, 2),
            'achieved' => round($achieved, 2),
            'percent' => $target > 0 ? min(100, round($achieved / $target * 100, 1)) : 0.0,
            'label' => (string) setting('admin.dashboard.target_label', 'الهدف الشهريّ'),
            'month' => $monthStart->translatedFormat('F Y'),
        ];
    }

    /**
     * خريطة حراريّة جغرافيّة (12.3-13): الدولة · المحافظة ⟵ عدد المستخدمين.
     * والحرارة **رقمٌ ونسبةٌ مكتوبان** لا لونًا وحده (2.16: اللون لا يحمل المعنى).
     *
     * @return array<int, array{label:string, value:int, percent:float}>
     */
    public function geoHeat(): array
    {
        if (! Schema::hasTable('countries')) {
            return [];
        }

        $rows = DB::table('users')
            ->leftJoin('countries', 'countries.id', '=', 'users.country_id')
            ->leftJoin('governorates', 'governorates.id', '=', 'users.governorate_id')
            ->whereNull('users.deleted_at')
            ->select(
                DB::raw(setting('admin_dashboard.admin_dashboard.geo_heat_1', 'coalesce(countries.name_ar, \'غير محدّد\') as country')),
                DB::raw(setting('admin_dashboard.admin_dashboard.geo_heat_2', 'coalesce(governorates.name_ar, \'غير محدّد\') as governorate')),
                DB::raw('count(*) as total'),
            )
            ->groupBy('country', 'governorate')
            ->orderByDesc('total')
            ->limit((int) setting('admin.dashboard.geo_rows', 8))
            ->get();

        $max = max(1, (int) $rows->max('total'));

        return $rows->map(fn ($row) => [
            'label' => $row->country.' · '.$row->governorate,
            'value' => (int) $row->total,
            'percent' => round((int) $row->total / $max * 100, 1),
        ])->all();
    }

    /**
     * صحّة التلعيب (12.3-14): الستريكات النشطة · نادي الخامسة اليوم · التذاكر المتداولة.
     *
     * @return array<int, array{label:string, value:int, hint:string}>
     */
    public function gamificationHealth(): array
    {
        $activeStreaks = Schema::hasTable('streaks')
            ? (int) DB::table('streaks')->where('current_days', '>', 0)->count()
            : 0;

        $avgStreak = Schema::hasTable('streaks')
            ? (float) DB::table('streaks')->where('current_days', '>', 0)->avg('current_days')
            : 0.0;

        $clubToday = Schema::hasTable('streak_days')
            ? (int) DB::table('streak_days')
                ->where('club_5am', true)
                ->whereDate('day', CarbonImmutable::now()->toDateString())
                ->count()
            : 0;

        $tickets = Schema::hasTable('wallet_balances')
            ? (float) DB::table('wallet_balances')
                ->join('currencies', 'currencies.id', '=', 'wallet_balances.currency_id')
                ->where('currencies.code', 'tickets')
                ->sum('wallet_balances.balance')
            : 0.0;

        return [
            ['label' => setting('admin_dashboard.admin_dashboard.gamification_health_1', 'ستريكات نشطة'), 'value' => $activeStreaks, 'hint' => strtr(setting('admin_dashboard.admin_dashboard.gamification_health_2', 'متوسّط الستريك: :p1 يوم'), [':p1' => (string) (round($avgStreak, 1))])],
            ['label' => setting('admin_dashboard.admin_dashboard.gamification_health_3', 'نادي الخامسة اليوم'), 'value' => $clubToday, 'hint' => setting('admin_dashboard.admin_dashboard.gamification_health_4', 'حضور نافذة 4:50–5:20 ص')],
            ['label' => setting('admin_dashboard.admin_dashboard.gamification_health_5', 'تذاكر متداولة'), 'value' => (int) round($tickets), 'hint' => setting('admin_dashboard.admin_dashboard.gamification_health_6', 'رصيد التذاكر في المحافظ كلّها')],
        ];
    }

    /**
     * أكثر التدريبات تعثّرًا (12.3-15): تسجيلٌ بلا إتمام — لتحسين المحتوى.
     *
     * @return array<int, array{label:string, value:int, percent:float}>
     */
    public function courseDropoff(): array
    {
        if (! Schema::hasTable('enrollments') || ! Schema::hasTable('course_completions')) {
            return [];
        }

        $rows = DB::table('enrollments')
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->leftJoin('course_completions', function ($join) {
                $join->on('course_completions.course_id', '=', 'enrollments.course_id')
                    ->on('course_completions.user_id', '=', 'enrollments.user_id');
            })
            ->whereNull('course_completions.id')
            ->select('courses.name_ar as title', DB::raw('count(*) as total'))
            ->groupBy('courses.name_ar')
            ->orderByDesc('total')
            ->limit((int) setting('admin.dashboard.top_rows', 10))
            ->get();

        $max = max(1, (int) $rows->max('total'));

        return $rows->map(fn ($row) => [
            'label' => (string) $row->title,
            'value' => (int) $row->total,
            'percent' => round((int) $row->total / $max * 100, 1),
        ])->all();
    }

    /**
     * الحروب الجارية (12.3-11 — نبض المجتمع): مشاركات لسّه شغّالة الآن.
     *
     * @return array<int, array{label:string, value:int}>
     */
    public function runningWars(): array
    {
        if (! Schema::hasTable('challenge_participations')) {
            return [];
        }

        return ChallengeParticipation::query()
            ->where('challenge_participations.status', 'running')
            ->join('challenges', 'challenges.id', '=', 'challenge_participations.challenge_id')
            ->select('challenges.name_ar as title', DB::raw('count(*) as total'))
            ->groupBy('challenges.name_ar')
            ->orderByDesc('total')
            ->limit((int) setting('admin.dashboard.top_rows', 10))
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->title, 'value' => (int) $row->total])
            ->all();
    }

    /**
     * 🔒 أعلى التدريبات/المنتجات مبيعًا (12.3-6) — من أسطر الطلبات المدفوعة.
     *
     * @return Collection<int, array{label:string, value:float, count:int}>
     */
    public function topSelling(array $period): Collection
    {
        if (! Schema::hasTable('order_items')) {
            return collect();
        }

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'paid')
            ->whereBetween('orders.paid_at', [$period['from'], $period['to']])
            ->select('order_items.title', DB::raw('sum(order_items.price) as revenue'), DB::raw('count(*) as sold'))
            ->groupBy('order_items.title')
            ->orderByDesc('revenue')
            ->limit((int) setting('admin.dashboard.top_rows', 10))
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->title,
                'value' => round((float) $row->revenue, 2),
                'count' => (int) $row->sold,
            ]);
    }

    // ------------------------------------------------- تخصيص اللوحة لكلّ دور (12.3-3)

    /**
     * تخصيص اللوحة **لكلّ دور** — يعيش في جدول الإعدادات الواحد (2.13) لا في
     * عمودٍ جديد: `{"<دور>": {"order": [...], "hidden": [...]}}`.
     *
     * @return array{order: array<int, string>, hidden: array<int, string>}
     */
    public function layoutFor(User $user): array
    {
        $stored = setting('admin.dashboard.role_layouts', []);
        $stored = is_array($stored) ? $stored : [];

        foreach ($user->roles()->pluck('key') as $roleKey) {
            if (isset($stored[$roleKey]) && is_array($stored[$roleKey])) {
                return [
                    'order' => array_values(array_map('strval', (array) ($stored[$roleKey]['order'] ?? []))),
                    'hidden' => array_values(array_map('strval', (array) ($stored[$roleKey]['hidden'] ?? []))),
                ];
            }
        }

        return ['order' => [], 'hidden' => []];
    }

    /** حفظ تخصيص دورٍ بعينه — والباقي كما هو فلا يدهس دورٌ دورًا */
    public function saveLayout(string $roleKey, array $order, array $hidden): array
    {
        $stored = setting('admin.dashboard.role_layouts', []);
        $stored = is_array($stored) ? $stored : [];

        $stored[$roleKey] = [
            'order' => array_values(array_unique(array_map('strval', $order))),
            'hidden' => array_values(array_unique(array_map('strval', $hidden))),
        ];

        return $stored;
    }

    /**
     * ترتيب/إخفاء الكروت بتخصيص الدور.
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<int, array<string, mixed>>
     */
    private function applyLayout(array $cards, array $layout): array
    {
        $byKey = [];

        foreach ($cards as $card) {
            if (! in_array($card['key'], $layout['hidden'], true)) {
                $byKey[$card['key']] = $card;
            }
        }

        $ordered = [];

        foreach ($layout['order'] as $key) {
            if (isset($byKey[$key])) {
                $ordered[] = $byKey[$key];
                unset($byKey[$key]);
            }
        }

        return array_merge($ordered, array_values($byKey));
    }

    // ------------------------------------------------------------------ داخليّ

    private function parse(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            // تاريخ غير صالح يعود للافتراضيّ بلا انفجار — الشاشة لا تسقط بمدخلٍ سيّئ
            return null;
        }
    }

    /**
     * وجهة الكارت عند النقر (12.3-4) — والرابط **لا يظهر لمن لا يملك الصفحة**،
     * فيبقى الكارت رقمًا ولا يَعِد بباب مقفول (2.15-أ-7).
     */
    private function urlFor(string $route, User $user, string $permission): ?string
    {
        if (! Route::has($route) || ! $user->allows($permission)) {
            return null;
        }

        return route($route);
    }

    private function previousAov(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $query = Order::where('status', 'paid')->whereBetween('paid_at', [$from, $to]);
        $count = (clone $query)->count();

        return $count > 0 ? (int) round((float) (clone $query)->sum('total') / $count) : 0;
    }

    /**
     * طلب السحب معاملةٌ من مصدر `withdraw` حالتها `pending` في الـmeta —
     * فالدفتر الموحّد هو مصدر الحقيقة الوحيد لأيّ حركة رصيد (19).
     */
    private function pendingWithdrawQuery()
    {
        return Transaction::query()
            ->where('source', 'withdraw')
            ->where('meta->status', 'pending');
    }

    /**
     * كارت واحد: القيمة + نسبة التغيّر + حالة اللون + وجهة النقر.
     * التلوين معنًى واحد (2.16): أخضر = صعود صحّيّ · أصفر = ثابت · أحمر = هبوط.
     */
    private function card(string $key, string $label, string $icon, int $value, int $previous, string $hint, ?string $url = null): array
    {
        // خطّ أساسٍ صفريّ ليس انعدام تغيّرٍ بل غياب مقارنةٍ رياضيّةٍ ممكنة (12.3-2):
        // 0 → قيمة موجبة صعودٌ كاملٌ (100%)، و0 → 0 ثباتٌ فعليّ (0%) — كلاهما دلتا محدَّدة
        // بدل null، فلا يسقط الكارت من مقارنة الفترة السابقة على خطّ أساسٍ صفريّ.
        $delta = match (true) {
            $previous > 0 => round((($value - $previous) / $previous) * 100, 1),
            $value > 0 => 100.0,
            default => 0.0,
        };

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'value' => $value,
            'previous' => $previous,
            'delta' => $delta,
            'hint' => $hint,
            'url' => $url,
            'state' => $this->health($delta),
        ];
    }

    private function health(?float $delta): ?string
    {
        if (! setting('admin.dashboard.health_colors', true) || $delta === null) {
            return null;
        }

        $green = (float) setting('admin.dashboard.kpi_green_percent', 5);
        $red = (float) setting('admin.dashboard.kpi_red_percent', -5);

        return match (true) {
            $delta >= $green => 'ok',
            $delta <= $red => 'danger',
            default => 'warn',
        };
    }

    /** أسماء كروت اللوحة كلّها — يستهلكها بوب-أب «تخصيص اللوحة» */
    public function cardCatalog(): array
    {
        return [
            'users' => setting('admin_dashboard.admin_dashboard.card_catalog_1', 'كلّ المستخدمين'),
            'signups' => setting('admin_dashboard.admin_dashboard.card_catalog_2', 'مسجّلون جدد'),
            'sales' => setting('admin_dashboard.admin_dashboard.card_catalog_3', 'مبيعات (كوينز)'),
            'courses' => setting('admin_dashboard.admin_dashboard.card_catalog_4', 'تدريبات نشطة'),
            'events' => setting('admin_dashboard.admin_dashboard.card_catalog_5', 'فعاليّات قادمة'),
            'online' => setting('admin_dashboard.admin_dashboard.card_catalog_6', 'النشطون الآن'),
            'revenue' => setting('admin_dashboard.admin_dashboard.card_catalog_7', '🔒 الإيرادات'),
            'aov' => setting('admin_dashboard.admin_dashboard.card_catalog_8', '🔒 متوسّط قيمة الطلب'),
            'withdrawals' => setting('admin_dashboard.admin_dashboard.card_catalog_9', '🔒 سحوبات مستحقّة'),
            'referral' => setting('admin_dashboard.admin_dashboard.card_catalog_10', '🔒 عمولة الريفيرال'),
        ];
    }

    /** الحروب المفعَّلة الآن — لعرض «الحروب الجارية» حتى قبل أوّل مشاركة */
    public function activeWarsCount(): int
    {
        return Schema::hasTable('challenges') ? Challenge::where('is_active', true)->count() : 0;
    }
}
