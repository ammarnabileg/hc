<?php

namespace App\Services\Admin;

use App\Models\Complaint;
use App\Models\Course;
use App\Models\Event;
use App\Models\Order;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * لوحة القيادة (الدستور 12.3 · 24.1).
 *
 * سؤال الشاشة واحد: «إيه حالة المنصّة، وإيه اللي مستنّي قرارك؟»
 * فالصفّ الأوّل **أربعة كروت بحدّ أقصى** (2.15-أ-3) والباقي في تاب «تفاصيل».
 * وكلّ رقم يقارَن بالفترة السابقة ويُلوَّن بمعنى واحد من قاموس 2.16.
 */
class AdminDashboard
{
    /** خيارات فلتر الفترة العامّ — إعداد لا قائمة محروقة (2.13) */
    public function rangeOptions(): array
    {
        $options = setting('admin.dashboard.range_options', [1, 7, 30, 90]);

        return is_array($options) && $options !== [] ? array_map('intval', $options) : [1, 7, 30, 90];
    }

    public function resolveDays(?int $requested): int
    {
        $options = $this->rangeOptions();
        $default = (int) setting('admin.dashboard.default_days', 30);

        return in_array($requested, $options, true) ? $requested : ($default ?: 30);
    }

    /**
     * أربعة كروت KPI بالحدّ الأقصى — ومعها سهم ونسبة تغيّر وتلوين صحّة (12.3-2 · 12.3-18).
     *
     * @return array<int, array<string, mixed>>
     */
    public function kpis(int $days): array
    {
        [$from, $to, $prevFrom, $prevTo] = $this->windows($days);

        $cards = [
            $this->card('كلّ المستخدمين', '👥',
                User::where('created_at', '<=', $to)->count(),
                User::where('created_at', '<=', $prevTo)->count(),
                'إجمالي الحسابات حتى نهاية الفترة'),

            $this->card('مسجّلون جدد', '✨',
                User::whereBetween('created_at', [$from, $to])->count(),
                User::whereBetween('created_at', [$prevFrom, $prevTo])->count(),
                'حسابات اتسجّلت داخل الفترة'),

            $this->card('مبيعات (كوينز)', '🛒',
                $this->sales($from, $to),
                $this->sales($prevFrom, $prevTo),
                'إجمالي الطلبات المدفوعة داخل الفترة'),

            $this->card('تدريبات نشطة', '📚',
                Course::where('status', 'published')->count(),
                Course::where('status', 'published')->where('created_at', '<=', $prevTo)->count(),
                'تدريبات منشورة ومتاحة دلوقتي'),
        ];

        return array_slice($cards, 0, max(1, (int) setting('admin.dashboard.kpi_max_cards', 4)));
    }

    /** أرقام تاب «تفاصيل» — كلّ ما زاد عن الأربعة ينتقل هنا (2.15-أ-3) */
    public function details(int $days): array
    {
        [$from, $to, $prevFrom, $prevTo] = $this->windows($days);

        $paid = Order::where('status', 'paid')->whereBetween('paid_at', [$from, $to]);
        $paidCount = (clone $paid)->count();
        $revenue = (clone $paid)->sum('total');

        return [
            'cards' => [
                $this->card('فعاليّات قادمة', '📅',
                    Event::where('starts_at', '>=', now())->where('status', 'published')->count(),
                    Event::where('starts_at', '>=', $prevTo)->where('status', 'published')->count(),
                    'فعاليّات لسّه ماجتش'),

                $this->card('الإيرادات', '💰', (int) round($revenue),
                    (int) round(Order::where('status', 'paid')->whereBetween('paid_at', [$prevFrom, $prevTo])->sum('total')),
                    'إجمالي المدفوع داخل الفترة'),

                $this->card('متوسّط قيمة الطلب', '🧾',
                    $paidCount > 0 ? (int) round($revenue / $paidCount) : 0,
                    0,
                    'الإيرادات ÷ عدد الطلبات المدفوعة'),

                $this->card('سحوبات مستحقّة', '🏦',
                    (int) round(abs((float) $this->pendingWithdrawQuery()->sum('amount'))),
                    0,
                    'إجمالي طلبات السحب اللي لسّه مستنّية'),

                $this->card('عمولة الريفيرال', '🤝',
                    (int) round(Referral::whereBetween('updated_at', [$from, $to])->sum('commission_earned')),
                    (int) round(Referral::whereBetween('updated_at', [$prevFrom, $prevTo])->sum('commission_earned')),
                    'العمولة المصروفة داخل الفترة'),

                // نافذة «النشطون الآن» إعدادٌ لا رقمٌ محروق (2.13) — تختلف بحسب طبيعة المنصّة
                $this->card('النشطون الآن', '💚',
                    User::where('last_seen_at', '>=', now()->subMinutes((int) setting('admin_dashboard.online_window_minutes', 15)))->count(),
                    0,
                    'مستخدمون ظهروا في آخر ربع ساعة'),
            ],
            'funnel' => $this->funnel($from, $to),
            'topReferrers' => $this->topReferrers(),
        ];
    }

    /** سلسلة زمنيّة للتسجيلات والمبيعات — تُرسَم SVG بأيدينا بلا مكتبة خارجيّة */
    public function series(int $days): array
    {
        [$from] = $this->windows($days);
        $buckets = min($days, 30);
        $step = max(1, (int) ceil($days / $buckets));
        $points = [];

        for ($i = 0; $i < $buckets; $i++) {
            $start = CarbonImmutable::parse($from)->addDays($i * $step);
            $end = $start->addDays($step);

            $points[] = [
                'label' => $start->translatedFormat('j M'),
                'short' => $start->format('j/n'),
                'signups' => User::whereBetween('created_at', [$start, $end])->count(),
                'sales' => (int) round(Order::where('status', 'paid')->whereBetween('paid_at', [$start, $end])->sum('total')),
            ];
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
            ['label' => 'مسجّل', 'value' => $registered],
            ['label' => 'معتمَد', 'value' => $approved],
            ['label' => 'مشترٍ', 'value' => $buyers],
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
                'type' => 'سحب',
                'icon' => '🏦',
                'title' => ($row->user?->shortName() ?? 'مستخدم').' — '.number_format(abs((float) $row->amount)).' كوينز',
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
                'type' => $row->type === 'suggestion' ? 'مقترح' : 'شكوى',
                'icon' => '📮',
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
            'سحوبات' => $this->pendingWithdrawQuery()->count(),
            'شكاوى' => Complaint::where('status', 'open')->count(),
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

    // ------------------------------------------------------------------ داخليّ

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

    private function sales(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) round(Order::where('status', 'paid')->whereBetween('paid_at', [$from, $to])->sum('total'));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable} */
    private function windows(int $days): array
    {
        $to = CarbonImmutable::now();
        $from = $to->subDays($days);

        return [$from, $to, $from->subDays($days), $from];
    }

    /**
     * كارت واحد: القيمة + نسبة التغيّر + حالة اللون.
     * التلوين معنًى واحد (2.16): أخضر = صعود صحّيّ · أصفر = ثابت · أحمر = هبوط.
     */
    private function card(string $label, string $icon, int $value, int $previous, string $hint): array
    {
        $delta = $previous > 0 ? round((($value - $previous) / $previous) * 100, 1) : null;

        return [
            'label' => $label,
            'icon' => $icon,
            'value' => $value,
            'previous' => $previous,
            'delta' => $delta,
            'hint' => $hint,
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
}
