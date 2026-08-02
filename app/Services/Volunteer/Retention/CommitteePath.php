<?php

namespace App\Services\Volunteer\Retention;

use App\Models\Currency;
use App\Models\Membership;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Notifications\Notifier;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * مسار لجنة التحقيق (13.4-س-ج).
 *
 * السلّم منصوص: −8 إنذار ⟵ −9.5 بتر الاختياريّ ⟵ **−10 تعليق ولجنة**.
 * وفوقه بابٌ ثانٍ يسدّ ثغرة التصفير الشهريّ:
 * «**مجموع Rep المكتسَب خلال آخر 90 يومًا ≤ −15 ⟵ نفس مسار اللجنة**».
 *
 * لماذا بابان ومسار واحد؟ لأنّ الرقم الظاهر يعود صفرًا كلّ شهر (13.4-ن-ز)،
 * فمن يهبط −4 كلّ شهر لا يبلغ −10 أبدًا بينما مكتسَبه التراكميّ −12 ثمّ −16.
 * والتصفير أُريد به فرصةً جديدة لا بابًا خلفيًّا للإفلات — فالعتبة التراكميّة
 * تقيس على **سجلّ المعاملات** الذي لا يُصفَّر.
 */
class CommitteePath
{
    public const TABLE = 'volunteer_committee_referrals';

    public const TRIGGER_DISPLAYED = 'displayed_threshold';

    public const TRIGGER_CUMULATIVE = 'cumulative_90d';

    public function __construct(private readonly LedgerService $ledger) {}

    /** نافذة قياس المكتسَب التراكميّ — إعداد لا رقم (2.13) */
    public function windowDays(): int
    {
        return (int) setting('volunteer.offboarding.cumulative_window_days', 90);
    }

    /** عتبة المكتسَب التراكميّ خلال النافذة (−15) */
    public function cumulativeThreshold(): float
    {
        return rep_rule('limit.cumulative_90d', -15);
    }

    /** عتبة التعليق على الرقم الظاهر (−10) */
    public function displayedThreshold(): float
    {
        return rep_rule('limit.suspension', -10);
    }

    /**
     * مجموع Rep المكتسَب خلال النافذة — من **سجلّ المعاملات** لا من الرقم المسقوف،
     * فالتصفير الشهريّ لا يمحو السجلّ.
     *
     * ⭐ ولماذا `amount` لا `applied_amount`؟ لأنّ `applied_amount` هو ما نزل على
     * **الرقم الظاهر** بعد قصّ حدّ الخسارة اليوميّ (13.4-ن-و)، وقراءته هنا تُعيد
     * عين الثغرة التي كُتبت هذه العتبة لسدّها: ثلاث مخالفات −6 في يومٍ واحد
     * تُقاس −2 فلا تبلغ −15 أبدًا. و13.4-ن-و ينصّ أنّ الفائض «يُسجَّل كاملًا…
     * وفي **المكتسَب التراكميّ (المستعمَل في الترقية)**» — و`amount` هو الكامل.
     *
     * @param  array<int,int>  $userIds
     * @return array<int,float>
     */
    public function cumulativeEarned(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if ($userIds === []) {
            return [];
        }

        $currencyId = Currency::query()->where('code', LedgerService::REP)->value('id');

        $totals = Transaction::query()
            ->whereIn('user_id', $userIds)
            ->where('currency_id', $currencyId)
            ->where('created_at', '>=', now()->subDays($this->windowDays()))
            ->selectRaw('user_id, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $out = [];

        foreach ($userIds as $id) {
            $out[$id] = round((float) ($totals[$id] ?? 0), 2);
        }

        return $out;
    }

    /**
     * مسحة دوريّة على البابين معًا.
     *
     * @return array{scanned:int,referred:int}
     */
    public function sweep(): array
    {
        $userIds = Membership::query()
            ->where('status', 'active')
            // «أخوكم» لا تُقاس عليه عتبةٌ ولا تُفتَح له لجنة (13.4-ص-ج)
            ->whereDoesntHave('position', fn ($q) => $q->where('is_honorary', true))
            ->pluck('user_id')->unique()->values()->all();

        $result = ['scanned' => count($userIds), 'referred' => 0];

        if ($userIds === []) {
            return $result;
        }

        $cumulative = $this->cumulativeEarned($userIds);
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        foreach ($userIds as $userId) {
            $user = $users->get($userId);

            if (! $user) {
                continue;
            }

            $displayed = round($this->ledger->balance($user, LedgerService::REP), 2);

            // نفس المسار من البابين — والرقم الظاهر أوّلًا لأنّه العتبة المعلَنة
            $referral = match (true) {
                $displayed <= $this->displayedThreshold() => $this->refer($user, self::TRIGGER_DISPLAYED, $displayed, $this->displayedThreshold(), null),
                ($cumulative[$userId] ?? 0.0) <= $this->cumulativeThreshold() => $this->refer($user, self::TRIGGER_CUMULATIVE, $cumulative[$userId], $this->cumulativeThreshold(), $this->windowDays()),
                default => null,
            };

            if ($referral) {
                $result['referred']++;
            }
        }

        return $result;
    }

    /** هل له إحالة مفتوحة الآن؟ — فلا تُفتَح لجنتان لنفس الشخص */
    public function hasOpenReferral(User $user): bool
    {
        return DB::table(self::TABLE)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->exists();
    }

    /**
     * فتح مسار اللجنة — إشعار + Audit + صفٌّ موثَّق.
     * يعيد `null` لو كان المسار مفتوحًا أصلًا (المسحة يوميّة ولا تكرّر الإحالة).
     */
    public function refer(User $user, string $trigger, float $value, float $threshold, ?int $windowDays): ?int
    {
        if ($this->hasOpenReferral($user)) {
            return null;
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'user_id' => $user->id,
            'trigger' => $trigger,
            'threshold' => $threshold,
            'value' => $value,
            'window_days' => $windowDays,
            'status' => 'open',
            'opened_at' => now(),
            'note' => $this->reasonOf($trigger, $value, $windowDays),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ⭐ الرسالة تشرح ماذا حدث وماذا يفعل — ولا تفضح ولا تعاتب (2.17 · 24.4)
        Notifier::send(
            $user,
            'account',
            'اتفتح ملفّ لجنة تحقيق على درجة الالتزام',
            $this->reasonOf($trigger, $value, $windowDays)
                .' — اللجنة هتسمع منك، ومن قراراتها فرصة بـ'
                .number_format(rep_rule('task.committee_chance', 1), 2).' لمعدّل الالتزام وإعادة تفعيل.',
            null,
            'volunteer',
        );

        AuditTrail::log(null, 'volunteer_committee.refer', null, [], [
            'referral_id' => $id,
            'user_id' => $user->id,
            'trigger' => $trigger,
            'value' => $value,
            'threshold' => $threshold,
            'window_days' => $windowDays,
        ]);

        return $id;
    }

    private function reasonOf(string $trigger, float $value, ?int $windowDays): string
    {
        return $trigger === self::TRIGGER_CUMULATIVE
            ? 'مجموع درجة الالتزام المكتسَبة خلال آخر '.$windowDays.' يومًا ('.number_format($value, 2).') بلغ العتبة'
            : 'درجة الالتزام الظاهرة ('.number_format($value, 2).') بلغت عتبة التعليق';
    }
}
