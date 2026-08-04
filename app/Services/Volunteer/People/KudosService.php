<?php

namespace App\Services\Volunteer\People;

use App\Models\Kudos;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Kudos (13.4-ي · 24.4-11).
 *
 * الحدود موجودة لمنع الفارمينج لا لتقليل الشكر — ولذلك:
 *  - **سبب مكتوب إلزاميّ** (القصّة أهمّ من العدّاد).
 *  - **أشخاص مختلفون فقط** داخل الأسبوع.
 *  - عند بلوغ الحدّ **رسالة لطيفة** لا رسالة منع جافّة (2.17-ج).
 */
class KudosService
{
    public function __construct(private readonly PeopleBridge $bridge) {}

    public function dailyLimit(): int
    {
        return max(0, (int) setting('kudos.daily_limit', 2));
    }

    public function weeklyPeopleLimit(): int
    {
        return max(0, (int) setting('kudos.weekly_people_limit', 7));
    }

    public function vxpValue(): float
    {
        return (float) setting('kudos.vxp_value', 20);
    }

    public function sentToday(User $sender): int
    {
        return Kudos::query()
            ->where('sender_id', $sender->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    /** أشخاص مختلفون هذا الأسبوع — العدّ على الأشخاص لا على الرسائل */
    public function peopleThisWeek(User $sender): int
    {
        return Kudos::query()
            ->where('sender_id', $sender->id)
            ->where('created_at', '>=', now()->startOfWeek())
            ->distinct()
            ->count('receiver_id');
    }

    public function thankedThisWeek(User $sender, User $receiver): bool
    {
        return Kudos::query()
            ->where('sender_id', $sender->id)
            ->where('receiver_id', $receiver->id)
            ->where('created_at', '>=', now()->startOfWeek())
            ->exists();
    }

    /**
     * فحص الحدود قبل الإرسال.
     *
     * @return string|null رسالة لطيفة تشرح الحدّ، أو null إن كان الطريق مفتوحًا
     */
    public function blockedReason(User $sender, User $receiver, string $reason): ?string
    {
        if ($sender->id === $receiver->id) {
            return (string) setting('kudos.self.message', 'الشكر بيروح لغيرك — اختر زميلًا 🙂');
        }

        if (trim($reason) === '') {
            return (string) setting('kudos.reason_required.message', 'اكتب سبب الشكر — القصّة هي اللي بتفرق مش الرقم.');
        }

        if ($this->thankedThisWeek($sender, $receiver)) {
            return (string) setting('kudos.duplicate.message', 'شكرت الشخص ده الأسبوع ده بالفعل — دوّر على حد تاني يستاهل.');
        }

        if ($this->sentToday($sender) >= $this->dailyLimit()) {
            return (string) setting('kudos.daily_limit.message', 'وصلت لحدّ اليوم — بكرة تقدر تشكر تاني.');
        }

        if ($this->peopleThisWeek($sender) >= $this->weeklyPeopleLimit()) {
            return (string) setting('kudos.weekly_limit.message', 'وصلت لحدّ الأسبوع — الأسبوع الجاي مفتوح.');
        }

        return null;
    }

    /**
     * إرسال شكر.
     *
     * @throws \RuntimeException عند بلوغ حدّ أو نقص سبب
     */
    public function send(User $sender, User $receiver, string $reason): Kudos
    {
        $blocked = $this->blockedReason($sender, $receiver, $reason);

        if ($blocked !== null) {
            throw new \RuntimeException($blocked);
        }

        $kudos = Kudos::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'reason' => trim($reason),
            'vxp_awarded' => $this->vxpValue(),
        ]);

        $this->bridge->credit($receiver, 'vxp', $this->vxpValue(), 'kudos.received', $kudos, trim($reason));
        $this->bridge->celebrate($receiver, 'kudos.received', $kudos);
        $this->bridge->notify($receiver, 'recognition', setting('recruitment.kudos_service.send_1', 'وصلك شكر 💛'),
            $sender->shortName().': '.trim($reason), route('volunteer.kudos'));

        return $kudos;
    }

    /** @return Collection<int, Kudos> */
    public function received(User $user, array $filters = []): Collection
    {
        return $this->feed(Kudos::query()->with('sender')->where('receiver_id', $user->id), $filters);
    }

    /** @return Collection<int, Kudos> */
    public function sent(User $user, array $filters = []): Collection
    {
        return $this->feed(Kudos::query()->with('receiver')->where('sender_id', $user->id), $filters);
    }

    private function feed($query, array $filters): Collection
    {
        $days = (int) ($filters['days'] ?? setting('ux.lists.default_range_days', 30));
        $q = trim((string) ($filters['q'] ?? ''));

        return $query
            ->when($days > 0, fn ($b) => $b->where('kudos.created_at', '>=', now()->subDays($days)))
            ->when($q !== '', fn ($b) => $b->where('reason', 'like', "%{$q}%"))
            ->orderByDesc('kudos.created_at')
            ->get();
    }
}
