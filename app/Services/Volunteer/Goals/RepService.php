<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Currency;
use App\Models\Objection;
use App\Models\RepRule;
use App\Models\RepScore;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * درجة الالتزام Rep (الدستور 13.4-ن · 24.4).
 *
 * ⚠️ قاعدة حاكمة في هذا الملفّ كلّه: **لا رقم Rep محروق** — كلّ القيم من `rep_rule()`
 * وكلّ المواعيد من `setting()`، فتعديل الأدمن يسري فورًا بلا لمس كود.
 */
class RepService
{
    public const CURRENCY = 'rep';

    private ?Currency $currency = null;

    // ------------------------------------------------------------------ الرقم الظاهر

    public function score(User $user): float
    {
        return round(Integrations::balance($user, self::CURRENCY), 2);
    }

    /** حدود الجيج من جدول العملات نفسه — لا من رقم مكتوب في الواجهة */
    public function bounds(): array
    {
        $currency = $this->currency();

        return [
            'min' => (float) ($currency->min_value ?? -10),
            'max' => (float) ($currency->max_value ?? 10),
        ];
    }

    /** عتبة الإنذار (−5) والمؤشّر الأحمر (−8) — علامتان على الجيج */
    public function warningThreshold(): float
    {
        return rep_rule('limit.warning_threshold', -5);
    }

    public function redThreshold(): float
    {
        return rep_rule('limit.red_indicator', -8);
    }

    /** ⭐ تخطّي −8 ⟵ بانر إنذار هادئ بلا فضح (24.4) */
    public function isRedIndicator(User $user): bool
    {
        return $this->score($user) <= $this->redThreshold();
    }

    /** حالة الرقم بلون ومعنًى واحد (2.16) */
    public function state(float $score): string
    {
        if ($score <= $this->redThreshold()) {
            return 'danger';
        }

        if ($score < 0) {
            return 'warn';
        }

        return 'ok';
    }

    // ------------------------------------------------------------------ حدّ الخسارة اليوميّ

    /** حدّ الخسارة اليوميّ (−2) — من جدول Rep لا من الكود (13.4-ن-و) */
    public function dailyLossCap(): float
    {
        return rep_rule('limit.daily_loss', -2);
    }

    /** ما نزل فعلًا على الرقم الظاهر اليوم */
    public function lostToday(User $user): float
    {
        return round((float) $this->query($user)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(applied_amount, amount) < 0 THEN COALESCE(applied_amount, amount) ELSE 0 END), 0) AS total')
            ->value('total'), 2);
    }

    /**
     * ⭐ المعاملات التي تخطّت الحدّ اليوم — تُسجَّل **كاملةً** في السجلّ وتُوسَم صراحةً،
     * فتظهر الحقيقة كلّها ولو لم تنزل كلّها على الرقم الظاهر يومَها (13.4-ن-و).
     */
    public function exceededToday(User $user): Collection
    {
        return $this->query($user)
            ->where('exceeded_daily_cap', true)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->latest('created_at')
            ->get();
    }

    /** الفرق بين ما طُلِب وما طُبِّق — «تخطّت حدّ الخسارة اليوميّ — مسجَّلة كاملةً» */
    public function unappliedAmount(Transaction $transaction): float
    {
        return round(abs((float) $transaction->amount) - abs((float) ($transaction->applied_amount ?? $transaction->amount)), 2);
    }

    // ------------------------------------------------------------------ الحركات والمنحنى

    /** @return Builder<Transaction> */
    public function query(User $user)
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $this->currency()->id);
    }

    /**
     * جدول الحركات بفلاتره الثلاثة الظاهرة: المصدر · الكيان · الفترة (2.15-أ-4).
     *
     * @param  array{source?:string,entity?:int|null,days?:int}  $filters
     */
    public function movements(User $user, array $filters = []): Collection
    {
        $days = (int) ($filters['days'] ?? setting('ux.lists.default_range_days', 30));

        return $this->query($user)
            ->when(! empty($filters['source']), fn ($q) => $q->where('source', $filters['source']))
            ->when(! empty($filters['entity']), fn ($q) => $q->where('entity_id', $filters['entity']))
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->latest('created_at')
            ->limit((int) setting('rep.movements_rows', 200))
            ->get();
    }

    /**
     * منحنى Rep اليوميّ: الرصيد في نهاية كلّ يوم.
     *
     * @return array<int,array{label:string,value:float}>
     */
    public function dailySeries(User $user, ?int $days = null): array
    {
        $days = $days ?: (int) setting('performance.rep.curve_days', 30);
        $from = now()->subDays($days - 1)->startOfDay();

        $opening = (float) ($this->query($user)
            ->where('created_at', '<', $from)
            ->latest('created_at')->latest('id')
            ->value('balance_after') ?? 0);

        $moves = $this->query($user)
            ->where('created_at', '>=', $from)
            ->orderBy('created_at')->orderBy('id')
            ->get(['created_at', 'applied_amount', 'amount', 'balance_after']);

        return $this->walkDays($from, $days, $opening, $moves);
    }

    /** منحنى تراكم VXP (30 يومًا) — نفس المنطق بعملة أخرى */
    public function vxpSeries(User $user, ?int $days = null): array
    {
        $days = $days ?: (int) setting('performance.vxp.curve_days', 30);
        $from = now()->subDays($days - 1)->startOfDay();
        $currencyId = Currency::query()->where('code', VxpDistributionService::CURRENCY)->value('id');

        $base = Transaction::query()->where('user_id', $user->id)->where('currency_id', $currencyId);

        $opening = (float) ((clone $base)->where('created_at', '<', $from)
            ->latest('created_at')->latest('id')->value('balance_after') ?? 0);

        $moves = (clone $base)->where('created_at', '>=', $from)
            ->orderBy('created_at')->orderBy('id')
            ->get(['created_at', 'applied_amount', 'amount', 'balance_after']);

        return $this->walkDays($from, $days, $opening, $moves);
    }

    // ------------------------------------------------------------------ كيف تكسب

    /**
     * كارت «كيف تكسب» — القيم كلّها من `rep_rule()` والعناوين من جدول القواعد نفسه،
     * فلو عدّل الأدمن قيمةً تغيّر الشرح المعروض في اللحظة ذاتها.
     *
     * @return array<int,array{key:string,label:string,value:float,group:string}>
     */
    public function howToEarn(): array
    {
        $groups = setting('performance.rep.how_to_earn_groups', ['tasks', 'meetings', 'academy', 'leadership']);
        $groups = is_array($groups) && $groups !== [] ? $groups : ['tasks', 'meetings', 'academy', 'leadership'];

        return RepRule::query()
            ->where('is_active', true)
            ->whereIn('group', $groups)
            ->orderBy('group')->orderByDesc('value')
            ->get()
            ->map(fn (RepRule $rule) => [
                'key' => $rule->key,
                'label' => $rule->label_ar,
                // ⭐ القيمة من الدالّة لا من الصفّ — فمصدر الحقيقة واحد
                'value' => rep_rule($rule->key),
                'group' => $rule->group,
            ])
            ->all();
    }

    /**
     * جدول أثر مؤشّر القيادة على Rep — من `rep_rule()` لا محروقًا (13.4-ن-د).
     *
     * @return array<int,array{key:string,range:string,value:float}>
     */
    public function leadershipImpactTable(): array
    {
        return [
            ['key' => 'leadership.ge_9', 'range' => setting('goals.rep_service.leadership_impact_table_1', '9 فأعلى'), 'value' => rep_rule('leadership.ge_9')],
            ['key' => 'leadership.8_to_8_9', 'range' => '8 – 8.9', 'value' => rep_rule('leadership.8_to_8_9')],
            ['key' => 'leadership.6_to_7_9', 'range' => '6 – 7.9', 'value' => rep_rule('leadership.6_to_7_9')],
            ['key' => 'leadership.4_to_5_9', 'range' => '4 – 5.9', 'value' => rep_rule('leadership.4_to_5_9')],
            ['key' => 'leadership.lt_4', 'range' => setting('goals.rep_service.leadership_impact_table_2', 'أقلّ من 4'), 'value' => rep_rule('leadership.lt_4')],
        ];
    }

    /** مفتاح شريحة المتوسّط — والقيمة تُقرَأ منه دائمًا */
    public function leadershipRuleKeyFor(float $average): string
    {
        return match (true) {
            $average >= 9 => 'leadership.ge_9',
            $average >= 8 => 'leadership.8_to_8_9',
            $average >= 6 => 'leadership.6_to_7_9',
            $average >= 4 => 'leadership.4_to_5_9',
            default => 'leadership.lt_4',
        };
    }

    /** تطبيق أثر مؤشّر القيادة على Rep — بعد بلوغ عتبة المقيّمين */
    public function applyLeadershipImpact(User $evaluatee, float $average, ?int $entityId = null): ?Transaction
    {
        $key = $this->leadershipRuleKeyFor($average);
        $value = rep_rule($key);

        if ($value === 0.0) {
            return null; // الشريحة المحايدة لا تكتب حركةً بلا أثر
        }

        $reason = strtr(setting('goals.rep_service.apply_leadership_impact_1', 'مؤشّر القيادة الأسبوعيّ — متوسّط :p1'), [':p1' => (string) (number_format($average, 2))]);

        $transaction = $value > 0
            ? Integrations::credit($evaluatee, self::CURRENCY, $value, 'leadership', null, $reason)
            : Integrations::debit($evaluatee, self::CURRENCY, $value, 'leadership', null, $reason);

        if ($transaction && $entityId) {
            $transaction->forceFill(['entity_id' => $entityId])->save();
        }

        $this->syncScore($evaluatee);

        return $transaction;
    }

    // ------------------------------------------------------------------ الاعتراض

    /** مهلة الاعتراض على المعاملة — إعداد لا رقم (13.4-ط) */
    public function objectionWindowDays(): int
    {
        return (int) setting('rep.objection.window_days', 5);
    }

    public function canObject(Transaction $transaction): bool
    {
        if (Objection::query()->where('transaction_id', $transaction->id)->exists()) {
            return false;
        }

        $deadline = $transaction->objection_deadline_at
            ? Carbon::parse($transaction->objection_deadline_at)
            : Carbon::parse($transaction->created_at)->addDays($this->objectionWindowDays());

        return $deadline->isFuture();
    }

    /** فتح اعتراض — والتصحيح لاحقًا بمعاملة عكسيّة شفّافة لا بتعديل الأصل (19.4) */
    public function openObjection(Transaction $transaction, User $user, string $reason): Objection
    {
        return Objection::create([
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'reason' => $reason,
            'status' => 'open',
            'sla_due_at' => now()->addHours((int) setting('workflow.escalation.window_hours', 24)),
        ]);
    }

    // ------------------------------------------------------------------ التصفير الشهريّ

    /**
     * منطقة التصفير: مفتاحه الخاصّ أوّلًا ثمّ توقيت المنصّة — فالأدمن الذي يغيّر
     * `rep.reset.timezone` يجب أن يتغيّر معه موعد التنفيذ فعلًا لا شكلًا (2.13).
     */
    public function resetTimezone(): string
    {
        $tz = trim((string) setting('rep.reset.timezone', ''));

        return $tz !== '' ? $tz : (string) setting('system.timezone', 'Africa/Cairo');
    }

    /** هل التصفير الشهريّ مفعَّل أصلًا؟ — مفتاحٌ بلا قارئ يعني إيقافًا لا يوقِف */
    public function resetEnabled(): bool
    {
        return (bool) setting('rep.reset.enabled', true);
    }

    /** موعد التصفير القادم: يوم كذا الساعة كذا بتوقيت التصفير (13.4-ن-ز) */
    public function nextResetAt(): CarbonImmutable
    {
        $tz = $this->resetTimezone();
        $day = max(1, (int) setting('rep.reset.day_of_month', 1));
        $hour = (int) setting('rep.reset.hour', 5);

        $now = CarbonImmutable::now($tz);
        $candidate = $now->startOfMonth()->addDays($day - 1)->setTime($hour, 0);

        return $candidate->isAfter($now) ? $candidate : $candidate->addMonthNoOverflow();
    }

    /**
     * هل نحن في لحظة التصفير الآن؟ — **القرار كلّه هنا**.
     *
     * الجدولة في `routes/console.php` مسحةٌ كلّ ساعة لا موعدٌ مكتوب فيها، لأنّ
     * تعبير الكرون يُقرأ مرّةً عند تحميل الملفّ فلا يعلم بتغيير الأدمن للموعد؛
     * فكان الموعد الجديد يظهر في الشاشة بينما التنفيذ يبقى على القديم — أو لا
     * يحدث أبدًا. المسحة تسأل هذه الدالّة، وهي وحدها تقرأ الإعدادات (2.13).
     */
    public function isResetMoment(?CarbonImmutable $now = null): bool
    {
        if (! $this->resetEnabled()) {
            return false;
        }

        $tz = $this->resetTimezone();
        $now ??= CarbonImmutable::now($tz);
        $now = $now->setTimezone($tz);

        return $now->day === max(1, (int) setting('rep.reset.day_of_month', 1))
            && $now->hour === (int) setting('rep.reset.hour', 5);
    }

    /**
     * ⭐ التصفير الشهريّ: **الرقم الظاهر يعود صفرًا للجميع**، بينما
     * **سجلّ المعاملات والمكتسَب التراكميّ يبقيان كما هما** — لأنّ التراكميّ
     * هو ما تُقاس عليه الترقية (13.4-ن-ز · 23 — 0.2).
     *
     * @return int عدد المحافظ التي صُفِّرت
     */
    public function resetMonthly(): int
    {
        $currencyId = $this->currency()->id;
        $now = now();
        $count = 0;

        WalletBalance::query()
            ->where('currency_id', $currencyId)
            ->where('balance', '!=', 0)
            ->chunkById(200, function ($wallets) use (&$count, $now) {
                foreach ($wallets as $wallet) {
                    // الرصيد وحده يُصفَّر — lifetime_earned و lifetime_spent لا يُمسّان
                    $wallet->forceFill(['balance' => 0, 'last_reset_at' => $now])->save();
                    $count++;
                }
            });

        RepScore::query()->update([
            'score' => 0,
            'daily_loss_today' => 0,
            'daily_loss_date' => null,
            'last_reset_at' => $now,
            'updated_at' => $now,
        ]);

        return $count;
    }

    /** مرآة الرقم الظاهر في `rep_scores` — لتقرأه الشاشات الأخرى بلا حساب */
    public function syncScore(User $user): void
    {
        RepScore::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['score' => $this->score($user)],
        );
    }

    // ------------------------------------------------------------------ داخليّ

    /** كاش لكلّ نسخة لا `static` — كي لا يتسرّب صفٌّ قديم بين الطلبات والاختبارات */
    private function currency(): Currency
    {
        return $this->currency ??= Currency::query()->where('code', self::CURRENCY)->firstOrFail();
    }

    /**
     * مشي على الأيّام: قيمة كلّ يوم = رصيد نهايته، وإلّا رصيد اليوم السابق.
     *
     * @return array<int,array{label:string,value:float}>
     */
    private function walkDays(Carbon $from, int $days, float $opening, Collection $moves): array
    {
        $byDay = [];

        foreach ($moves as $move) {
            $byDay[Carbon::parse($move->created_at)->toDateString()] = (float) $move->balance_after;
        }

        $series = [];
        $running = $opening;
        $cursor = $from->copy();

        for ($i = 0; $i < $days; $i++) {
            $key = $cursor->toDateString();
            $running = $byDay[$key] ?? $running;
            $series[] = ['label' => $cursor->format('m/d'), 'value' => round($running, 2)];
            $cursor->addDay();
        }

        return $series;
    }
}
