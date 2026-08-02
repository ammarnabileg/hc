<?php

namespace App\Services\Volunteer\Org;

use App\Models\Membership;
use App\Models\MembershipAbsence;
use App\Models\Position;
use App\Models\Task;
use App\Models\User;
use App\Services\Volunteer\Tasks\TaskStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * وضع «غائب» والتفويض المؤقّت (الدستور 23 — القسم 6).
 *
 * لماذا وُجِد أصلًا؟ الدستور يعلّله بنفسه: «المنظومة كلّها نوافذ 24/48 ساعة
 * متّصلة، والمتطوّع يعمل في وقت فراغه — فبلا هذا الوضع يقع **نزيف خصومات
 * تباطؤ** على غائب معذور».
 *
 * وأثره خلال الفترة أربعة أشياء لا ثالث لها:
 *  1. كلّ نوافذ القرار الواردة إليه **تُوجَّه للبديل** وتُحتسَب عليه هو.
 *  2. **لا يقع على الغائب أيّ أثر تباطؤ.**
 *  3. **لا تُسنَد إليه مهامّ جديدة ولا يُدعى مساهمًا.**
 *  4. مهامّه المفتوحة **تُجمَّد ساعاتها** بنفس ميكانيزم الصيانة/التعثّر.
 */
class AbsenceService
{
    /** أقصى مدّة غياب متّصلة (يومًا) — إعداد لا رقم محروق (2.13) */
    public function maxDays(): int
    {
        return (int) setting('volunteer.absence.max_days', 14);
    }

    /** أقصى عدد مرّات غياب في الشهر */
    public function maxPerMonth(): int
    {
        return (int) setting('volunteer.absence.max_per_month', 2);
    }

    /** البوزشنات التي تملك إضافة الغياب — لا الشخص نفسه (منعًا للتهرّب) */
    public function allowedAdderPositions(): array
    {
        $keys = setting('volunteer.absence.adder_positions', ['volunteer_gm', 'track_gm', 'director']);

        return is_array($keys) ? $keys : ['volunteer_gm', 'track_gm', 'director'];
    }

    // ------------------------------------------------------------------ القراءة

    /** الغياب الساري اليوم على عضويّةٍ بعينها */
    public function absenceOfMembership(?int $membershipId): ?MembershipAbsence
    {
        if (! $membershipId || ! Schema::hasTable('membership_absences')) {
            return null;
        }

        return MembershipAbsence::query()
            ->where('membership_id', $membershipId)
            ->whereDate('from_date', '<=', today())
            ->whereDate('to_date', '>=', today())
            ->with('delegate_membership')
            ->first();
    }

    /** الغياب الساري اليوم لشخص — في كيانٍ بعينه أو في أيّ عضويّة نشطة له */
    public function currentAbsence(?User $user, ?int $entityId = null): ?MembershipAbsence
    {
        if (! $user || ! Schema::hasTable('membership_absences')) {
            return null;
        }

        $membershipIds = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->when($entityId, fn ($q) => $q->where('entity_id', $entityId))
            ->pluck('id')
            ->all();

        if ($membershipIds === []) {
            return null;
        }

        return MembershipAbsence::query()
            ->whereIn('membership_id', $membershipIds)
            ->whereDate('from_date', '<=', today())
            ->whereDate('to_date', '>=', today())
            ->with('delegate_membership')
            ->first();
    }

    public function isAbsent(?User $user, ?int $entityId = null): bool
    {
        return $this->currentAbsence($user, $entityId) !== null;
    }

    /** البديل المفوَّض عن الغائب — ومنه تمرّ نوافذ القرار كلّها */
    public function delegateFor(?User $user, ?int $entityId = null): ?User
    {
        $absence = $this->currentAbsence($user, $entityId);

        if (! $absence) {
            return null;
        }

        return $this->delegateOfAbsence($absence);
    }

    public function delegateOfAbsence(?MembershipAbsence $absence): ?User
    {
        $membership = $absence?->delegate_membership;

        if (! $membership || $membership->status !== 'active') {
            return null;
        }

        return User::query()->find($membership->user_id);
    }

    /**
     * تصفية قائمة مرشّحين من الغائبين — «لا تُسنَد إليه مهامّ جديدة ولا يُدعى
     * مساهمًا»، فالموازن والدور الدوريّ يتخطّونه بلا استثناء.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function withoutAbsent(array $userIds): array
    {
        if ($userIds === [] || ! Schema::hasTable('membership_absences')) {
            return $userIds;
        }

        $absentIds = MembershipAbsence::query()
            ->join('memberships', 'memberships.id', '=', 'membership_absences.membership_id')
            ->whereIn('memberships.user_id', $userIds)
            ->whereDate('membership_absences.from_date', '<=', today())
            ->whereDate('membership_absences.to_date', '>=', today())
            ->pluck('memberships.user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_diff($userIds, $absentIds));
    }

    // ------------------------------------------------------------------ الكتابة

    /**
     * فتح وضع «غائب» — بتاريخين وبديل، ولا يفتحه الشخص لنفسه.
     *
     * @throws ValidationException
     */
    public function open(Membership $membership, User $actor, string $from, string $to, ?int $delegateMembershipId, ?string $reason = null): MembershipAbsence
    {
        $this->assertCanAdd($membership, $actor);

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        if ($toDate->lessThan($fromDate)) {
            throw ValidationException::withMessages([
                'to_date' => 'تاريخ النهاية لازم يكون بعد تاريخ البداية.',
            ]);
        }

        if ($fromDate->diffInDays($toDate) + 1 > $this->maxDays()) {
            throw ValidationException::withMessages([
                'to_date' => 'أقصى غياب متّصل '.$this->maxDays().' يومًا — قسّمها أو كلّم مشرف عام التطوّع.',
            ]);
        }

        $thisMonth = MembershipAbsence::query()
            ->where('membership_id', $membership->id)
            ->whereBetween('from_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        if ($thisMonth >= $this->maxPerMonth()) {
            throw ValidationException::withMessages([
                'from_date' => 'الحدّ '.$this->maxPerMonth().' مرّات غياب في الشهر، واتستعملت كلّها.',
            ]);
        }

        // البديل من سلسلته: أبلاينه المباشر افتراضيًّا، أو زميل بنفس البوزشن في كيانه
        $delegateMembershipId ??= $membership->upline_id;

        $delegate = $delegateMembershipId ? Membership::query()->find($delegateMembershipId) : null;

        if (! $delegate || $delegate->status !== 'active') {
            throw ValidationException::withMessages([
                'delegate_membership_id' => 'لازم بديل مفوَّض نشِط — الأبلاين المباشر أو زميل بنفس البوزشن.',
            ]);
        }

        return MembershipAbsence::create([
            'membership_id' => $membership->id,
            'delegate_membership_id' => $delegate->id,
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);
    }

    /** مَن يملك إضافة الغياب: مشرف عام التطوّع · مشرف المسار · دايركتور الكيان */
    public function canAdd(Membership $membership, User $actor): bool
    {
        if ((int) $membership->user_id === (int) $actor->id) {
            return false;
        }

        $allowed = $this->allowedAdderPositions();

        return Membership::query()
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->whereIn('position_id', Position::query()->whereIn('key', $allowed)->pluck('id'))
            ->exists();
    }

    private function assertCanAdd(Membership $membership, User $actor): void
    {
        if ($this->canAdd($membership, $actor)) {
            return;
        }

        throw ValidationException::withMessages([
            'membership_id' => (int) $membership->user_id === (int) $actor->id
                ? 'وضع «غائب» بيضيفه مشرفك مش إنت — كلّم دايركتور كيانك.'
                : 'الفعل ده لمشرف عام التطوّع أو مشرف المسار أو دايركتور الكيان.',
        ]);
    }

    // ------------------------------------------------------------------ تجميد الساعات

    /**
     * ⭐ فكّ التجميد بعد انتهاء الغياب: كلّ مهلة لم تكن قد فاتت قبل بدايته
     * تُزاح بمدّة الغياب — **بنفس ميكانيزم الصيانة** (23-6)، فلا يخرج الغائب
     * المعذور من إجازته على ديدلاينات محروقة.
     *
     * @return int عدد الغيابات التي فُكّ تجميدها
     */
    public function thawFinished(): int
    {
        if (! Schema::hasTable('membership_absences') || ! Schema::hasColumn('membership_absences', 'thawed_at')) {
            return 0;
        }

        $finished = MembershipAbsence::query()
            ->whereNull('thawed_at')
            ->whereDate('to_date', '<', today())
            ->get();

        foreach ($finished as $absence) {
            $membership = Membership::query()->find($absence->membership_id);
            $seconds = (int) Carbon::parse($absence->from_date)->startOfDay()
                ->diffInSeconds(Carbon::parse($absence->to_date)->endOfDay());

            if ($membership && $seconds > 0) {
                $this->shiftClocks((int) $membership->user_id, Carbon::parse($absence->from_date)->startOfDay(), $seconds);
            }

            $absence->forceFill(['thawed_at' => now()])->save();
        }

        return $finished->count();
    }

    /** إزاحة ساعات مهامّ الغائب المفتوحة بمدّة غيابه */
    private function shiftClocks(int $userId, Carbon $frozenFrom, int $seconds): void
    {
        $expression = DB::connection()->getDriverName() === 'sqlite'
            ? "datetime(%s, '+{$seconds} seconds')"
            : "DATE_ADD(%s, INTERVAL {$seconds} SECOND)";

        foreach (['deadline_at', 'merge_window_at', 'breakdown_due_at'] as $column) {
            if (! Schema::hasColumn('tasks', $column)) {
                continue;
            }

            Task::query()
                ->where('owner_id', $userId)
                ->whereIn('status', TaskStatus::OPEN)
                ->whereNotNull($column)
                // ما فات قبل بدء الغياب لا يُمدَّد — التجميد استئنافٌ لا مكافأة
                ->where($column, '>=', $frozenFrom)
                ->update([$column => DB::raw(sprintf($expression, $column))]);
        }
    }
}
