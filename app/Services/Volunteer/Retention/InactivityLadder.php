<?php

namespace App\Services\Volunteer\Retention;

use App\Models\Membership;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * سلّم الخمول (الدستور 13.4-س-ب) — منصوص بالحرف:
 * «**21 يومًا بلا نشاط ⟵ تنبيه**، ثمّ **−0.5 من Rep أسبوعيًّا** ما دام خاملًا
 * حتى يعود أو يبلغ العتبات».
 *
 * ثلاث قواعد حاكمة هنا:
 *  1) **التنبيه واحد** لا يوميّ — لأنّه إشعار بداية سلّم لا مطرقة تُقرَع كلّ صباح.
 *  2) **الخصم أسبوعيّ** من `rep_rule('inactivity.weekly')` بمصدر `inactivity`،
 *     ويمرّ من `LedgerService` وحده فيسري عليه حدّ الخسارة اليوميّ وسقف العملة.
 *  3) **يتوقّف فور عودة النشاط** — يُمسَح صفّ الحالة، فلو خمل ثانيةً بدأ السلّم
 *     من أوّله بتنبيهٍ جديد لا بخصمٍ مباشر.
 *
 * والخمول **مدخل للسلّم لا نوع خروج** (13.4-س) — فلا يُنهي عضويّةً هنا أحد،
 * وإذا بلغ العتبات تسلّمه `CommitteePath` لمسار اللجنة.
 */
class InactivityLadder
{
    public const SOURCE = 'inactivity';

    private const TABLE = 'volunteer_inactivity_states';

    public function __construct(
        private readonly ActivityTracker $activity,
        private readonly LedgerService $ledger,
    ) {}

    /** عتبة التنبيه — إعداد لا رقم محروق (2.13) */
    public function alertDays(): int
    {
        return (int) setting('rep.inactivity.days_before_alert', 21);
    }

    /** دورة الخصم بالأيّام — أسبوع، وهو إعداد أيضًا */
    public function deductionEveryDays(): int
    {
        return (int) setting('rep.inactivity.deduction_every_days', 7);
    }

    /** قيمة الخصم الأسبوعيّ من جدول Rep الموحَّد (13.4-ن) */
    public function weeklyValue(): float
    {
        return rep_rule('inactivity.weekly', -0.5);
    }

    /**
     * مسحة يوم واحد على كلّ المتطوّعين المُسكَّنين.
     *
     * @return array{scanned:int,alerted:int,deducted:int,recovered:int}
     */
    public function sweep(): array
    {
        $result = ['scanned' => 0, 'alerted' => 0, 'deducted' => 0, 'recovered' => 0];

        $memberships = Membership::query()
            ->where('status', 'active')
            // «أخوكم» خارج كلّ العدّادات والمسارات التشغيليّة (13.4-ص-ج)
            ->whereDoesntHave('position', fn ($q) => $q->where('is_honorary', true))
            ->get(['user_id']);

        $userIds = $memberships->pluck('user_id')->unique()->values()->all();

        if ($userIds === []) {
            return $result;
        }

        $lastActivity = $this->activity->lastActivityFor($userIds);
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');
        $states = DB::table(self::TABLE)->whereIn('user_id', $userIds)->get()->keyBy('user_id');

        $cutoff = now()->subDays($this->alertDays());

        foreach ($userIds as $userId) {
            $user = $users->get($userId);

            if (! $user) {
                continue;
            }

            $result['scanned']++;

            $last = $lastActivity[$userId] ?? $user->created_at;
            $state = $states->get($userId);

            // ⭐ عاد؟ يتوقّف كلّ شيء فورًا ويُمحى أثر السلّم
            if ($last && $last->gt($cutoff)) {
                if ($state) {
                    DB::table(self::TABLE)->where('user_id', $userId)->delete();
                    $result['recovered']++;
                }

                continue;
            }

            $state = $this->ensureState($userId, $last, $state);

            if ($state->alerted_at === null) {
                $this->alert($user, $last);
                DB::table(self::TABLE)->where('user_id', $userId)->update([
                    'alerted_at' => now(),
                    'updated_at' => now(),
                ]);
                $result['alerted']++;

                continue; // التنبيه أوّلًا، والخصم يبدأ بعد أسبوعٍ منه
            }

            if ($this->deduct($user, $state)) {
                $result['deducted']++;
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------ داخليّ

    private function ensureState(int $userId, ?Carbon $last, ?object $state): object
    {
        if ($state) {
            DB::table(self::TABLE)->where('user_id', $userId)->update([
                'last_activity_at' => $last,
                'updated_at' => now(),
            ]);

            return $state;
        }

        DB::table(self::TABLE)->insert([
            'user_id' => $userId,
            'last_activity_at' => $last,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table(self::TABLE)->where('user_id', $userId)->first();
    }

    /** التنبيه الواحد — بنبرة تشجّع على العودة لا تعاتب (2.17) */
    private function alert(User $user, ?Carbon $last): void
    {
        $days = $this->activity->idleDays($user, $last);

        Notifier::send(
            $user,
            'account',
            'وحشتنا 👋 — بقى لك '.$days.' يومًا بلا نشاط',
            'رجوعك في أيّ وقت يوقف الخصم الأسبوعيّ ('.number_format($this->weeklyValue(), 2)
                .') من درجة الالتزام. افتح مهمّة أو سجّل حضور اجتماع وهنعتبرك رجعت.',
            null,
            'volunteer',
        );
    }

    /** الخصم الأسبوعيّ — مرّة كلّ دورة، ويتوقّف عند بلوغ عتبة التعليق */
    private function deduct(User $user, object $state): bool
    {
        $since = Carbon::parse($state->last_deduction_at ?? $state->alerted_at);

        if ($since->addDays($this->deductionEveryDays())->isFuture()) {
            return false;
        }

        // «حتى يعود **أو يبلغ العتبات**» — عند العتبة يتسلّمه مسار اللجنة لا السلّم
        if ($this->ledger->balance($user, LedgerService::REP) <= rep_rule('limit.suspension', -10)) {
            return false;
        }

        $value = $this->weeklyValue();

        if ($value === 0.0) {
            return false;
        }

        $this->ledger->debit(
            $user,
            LedgerService::REP,
            $value,
            self::SOURCE,
            null,
            'volunteer',
            'خمول '.$this->activity->idleDays($user, $state->last_activity_at ? Carbon::parse($state->last_activity_at) : null)
                .' يومًا بلا نشاط — خصم أسبوعيّ (13.4-س-ب)',
        );

        // ⭐ خصم الخمول قد يبلغ بصاحبه −9.5، والدرجة الوسطى تقع **فورًا** (23-0.2-2)
        OptionalCutService::afterRepMovement($user, LedgerService::REP, $value);

        DB::table(self::TABLE)->where('user_id', $user->id)->update([
            'last_deduction_at' => now(),
            'deductions_count' => (int) $state->deductions_count + 1,
            'updated_at' => now(),
        ]);

        return true;
    }
}
