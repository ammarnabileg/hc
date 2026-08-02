<?php

namespace App\Services\Volunteer\Retention;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تعريف «النشاط» — مصدر واحد لا غير (13.4-س-ب).
 *
 * لماذا خدمة مستقلّة؟ لأنّ سلّم الخمول كلّه معلَّق على سؤالٍ واحد: «متى كان
 * آخر نشاطٍ له؟». فلو أجاب عنه كلّ موضعٍ بطريقته اختلف الخصم من شاشةٍ لأمر
 * مجدوَل، وصار العضو خاملًا في مكان ونشطًا في آخر.
 *
 * والنشاط هو **الأحدث** من أربعة آثار حقيقيّة:
 *  1) `users.last_seen_at` — آخر ظهور على المنصّة.
 *  2) آخر **مهمّة** لمسها بصفته مالكًا (`tasks.updated_at`).
 *  3) آخر **تسليم** كتبه (`task_submissions.created_at`).
 *  4) آخر **حضور** اجتماعٍ سجّله (`meeting_attendances`).
 */
class ActivityTracker
{
    /** آخر نشاطٍ لمستخدم واحد */
    public function lastActivityAt(User $user): ?Carbon
    {
        return $this->lastActivityFor([$user->id])[$user->id] ?? null;
    }

    /**
     * آخر نشاطٍ لمجموعة — استعلامات مجمَّعة كي لا يضرب الأمر المجدوَل
     * قاعدة البيانات مرّةً لكلّ متطوّع.
     *
     * @param  array<int,int>  $userIds
     * @return array<int,Carbon|null>
     */
    public function lastActivityFor(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if ($userIds === []) {
            return [];
        }

        $stamps = [];

        foreach ($this->sources($userIds) as $source) {
            foreach ($source as $userId => $at) {
                $userId = (int) $userId;

                if (! $at) {
                    continue;
                }

                $at = Carbon::parse($at);

                if (! isset($stamps[$userId]) || $at->gt($stamps[$userId])) {
                    $stamps[$userId] = $at;
                }
            }
        }

        $out = [];

        foreach ($userIds as $id) {
            $out[$id] = $stamps[$id] ?? null;
        }

        return $out;
    }

    /** عدد الأيّام منذ آخر نشاط — وبلا أثرٍ أصلًا نحسبها من إنشاء الحساب */
    public function idleDays(User $user, ?Carbon $lastActivityAt = null): int
    {
        $last = $lastActivityAt ?? $this->lastActivityAt($user) ?? $user->created_at;

        return $last ? (int) $last->diffInDays(now()) : 0;
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<int,array<int,string|null>> */
    private function sources(array $userIds): array
    {
        return [
            DB::table('users')->whereIn('id', $userIds)
                ->pluck('last_seen_at', 'id')->all(),

            DB::table('tasks')->whereIn('owner_id', $userIds)
                ->selectRaw('owner_id, MAX(updated_at) AS at')
                ->groupBy('owner_id')->pluck('at', 'owner_id')->all(),

            DB::table('task_submissions')->whereIn('user_id', $userIds)
                ->selectRaw('user_id, MAX(created_at) AS at')
                ->groupBy('user_id')->pluck('at', 'user_id')->all(),

            DB::table('meeting_attendances')->whereIn('user_id', $userIds)
                ->selectRaw('user_id, MAX(COALESCE(registered_at, created_at)) AS at')
                ->groupBy('user_id')->pluck('at', 'user_id')->all(),
        ];
    }
}
