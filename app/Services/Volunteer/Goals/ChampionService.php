<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Currency;
use App\Models\Membership;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * «مشرف الشهر» (الدستور 24.4 · 13.4).
 *
 * لماذا لا نخزّن نتيجةً؟ لأنّ المعيار نفسه يعرّف لحظة الحساب: **يُحدَّث يوميًّا 5:00ص
 * بتوقيت القاهرة ويثبت حتى 4:59ص**. فنثبّت نافذة الحساب على آخر حدّ 5:00ص —
 * فتخرج النتيجة نفسها طوال اليوم بلا جدول إضافيّ ولا مهمّة مجدوَلة.
 *
 * ومعيار الحسم مُعلَن في الشاشة: **معدّل Rep آخر 30 يومًا**، وعند التعادل
 * **معدّل زيادة VXP آخر 30 يومًا**.
 */
class ChampionService
{
    /** آخر حدّ 5:00ص بتوقيت القاهرة — عليه تُثبَّت النافذة */
    public function anchor(?CarbonImmutable $now = null): CarbonImmutable
    {
        $tz = (string) setting('system.timezone', 'Africa/Cairo');
        $hour = (int) setting('champion.update_hour_cairo', 5);

        $now = ($now ?? CarbonImmutable::now($tz))->setTimezone($tz);
        $today = $now->setTime($hour, 0);

        return $now->isBefore($today) ? $today->subDay() : $today;
    }

    /** عدّاد «التحديث القادم بعد …» */
    public function nextUpdateAt(?CarbonImmutable $now = null): CarbonImmutable
    {
        return $this->anchor($now)->addDay();
    }

    public function windowDays(): int
    {
        return (int) setting('champion.window_days', 30);
    }

    public function candidatesCount(): int
    {
        return (int) setting('champion.candidates_count', 10);
    }

    /** بيان معيار الحسم — يُعرَض نصًّا في الشاشة كي لا يكون الترتيب صندوقًا أسود */
    public function criteriaStatement(): string
    {
        return 'معدّل Rep آخر '.$this->windowDays().' يومًا، وعند التعادل معدّل زيادة VXP آخر '.$this->windowDays().' يومًا.';
    }

    /**
     * لوحة الشرف: الفائز + المرشّحون.
     *
     * @param  string|null  $month  Y-m لقراءة الأرشيف، أو null للنافذة الجارية
     * @return array{winner:?array,candidates:array<int,array>,from:CarbonImmutable,to:CarbonImmutable}
     */
    public function board(?int $entityId = null, ?string $month = null): array
    {
        [$from, $to] = $this->window($month);

        $key = 'volunteer.champion.'.($entityId ?? 'all').'.'.$from->toDateString().'.'.$to->toDateString();

        $rows = Cache::remember($key, $this->nextUpdateAt()->diffInSeconds(CarbonImmutable::now()) ?: 60,
            fn () => $this->compute($from, $to, $entityId));

        return [
            'winner' => $rows[0] ?? null,
            'candidates' => array_slice($rows, 0, $this->candidatesCount()),
            'from' => $from,
            'to' => $to,
        ];
    }

    /** أشهر الأرشيف المتاحة كشريط كروت */
    public function archiveMonths(int $count = 6): array
    {
        $months = [];
        $cursor = CarbonImmutable::now((string) setting('system.timezone', 'Africa/Cairo'))->startOfMonth();

        for ($i = 1; $i <= $count; $i++) {
            $cursor = $cursor->subMonthNoOverflow();
            $months[] = $cursor->format('Y-m');
        }

        return $months;
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    private function window(?string $month): array
    {
        $tz = (string) setting('system.timezone', 'Africa/Cairo');

        if ($month) {
            $start = CarbonImmutable::createFromFormat('Y-m', $month, $tz)->startOfMonth();

            return [$start, $start->endOfMonth()];
        }

        $anchor = $this->anchor();

        return [$anchor->subDays($this->windowDays()), $anchor];
    }

    /**
     * الحساب: متوسّط الرصيد اليوميّ لـRep داخل النافذة (المعدّل)،
     * وعند التعادل معدّل زيادة VXP اليوميّ.
     *
     * @return array<int,array>
     */
    private function compute(CarbonImmutable $from, CarbonImmutable $to, ?int $entityId): array
    {
        $userIds = $this->volunteerIds($entityId);

        if ($userIds === []) {
            return [];
        }

        $repId = Currency::query()->where('code', RepService::CURRENCY)->value('id');
        $vxpId = Currency::query()->where('code', VxpDistributionService::CURRENCY)->value('id');

        $days = max(1, $from->diffInDays($to));

        $repAverages = $this->averageDailyBalance($userIds, (int) $repId, $from, $to, $days);
        $vxpGain = $this->gainWithin($userIds, (int) $vxpId, $from, $to);

        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');
        $memberships = Membership::query()
            ->whereIn('user_id', $userIds)->where('status', 'active')
            ->with('entity', 'position')
            ->get()->keyBy('user_id');

        $rows = [];

        foreach ($userIds as $id) {
            $user = $users->get($id);

            if (! $user) {
                continue;
            }

            $rows[] = [
                'user' => $user,
                'membership' => $memberships->get($id),
                'rep_average' => round($repAverages[$id] ?? 0, 2),
                'vxp_rate' => round(($vxpGain[$id] ?? 0) / $days, 2),
            ];
        }

        usort($rows, static function (array $a, array $b) {
            return [$b['rep_average'], $b['vxp_rate']] <=> [$a['rep_average'], $a['vxp_rate']];
        });

        foreach ($rows as $index => $row) {
            $rows[$index]['rank'] = $index + 1;
        }

        return $rows;
    }

    /** كلّ المتطوّعين المُسكَّنين — والمرشّح يجب أن يكون داخل نطاق العرض */
    private function volunteerIds(?int $entityId): array
    {
        return Membership::query()
            ->where('status', 'active')
            // «أخوكم» لا يُحتسَب في الليدر بورد ومشرف الشهر (13.4-ص-ج)
            ->whereDoesntHave('position', fn ($q) => $q->where('is_honorary', true))
            ->when($entityId, fn ($q) => $q->where('entity_id', $entityId))
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * متوسّط الرصيد اليوميّ داخل النافذة: نمشي على الحركات ونحمل آخر رصيد لكلّ يوم.
     *
     * @return array<int,float>
     */
    private function averageDailyBalance(array $userIds, int $currencyId, CarbonImmutable $from, CarbonImmutable $to, int $days): array
    {
        $opening = Transaction::query()
            ->whereIn('user_id', $userIds)
            ->where('currency_id', $currencyId)
            ->where('created_at', '<', $from)
            ->orderBy('created_at')->orderBy('id')
            ->get(['user_id', 'balance_after'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => (float) $rows->last()->balance_after);

        $moves = Transaction::query()
            ->whereIn('user_id', $userIds)
            ->where('currency_id', $currencyId)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')->orderBy('id')
            ->get(['user_id', 'created_at', 'balance_after'])
            ->groupBy('user_id');

        $result = [];

        foreach ($userIds as $id) {
            $running = (float) ($opening[$id] ?? 0);
            $byDay = [];

            foreach ($moves[$id] ?? [] as $move) {
                $byDay[Carbon::parse($move->created_at)->toDateString()] = (float) $move->balance_after;
            }

            $sum = 0.0;
            $cursor = $from->copy();

            for ($i = 0; $i < $days; $i++) {
                $running = $byDay[$cursor->toDateString()] ?? $running;
                $sum += $running;
                $cursor = $cursor->addDay();
            }

            $result[$id] = $sum / $days;
        }

        return $result;
    }

    /** @return array<int,float> */
    private function gainWithin(array $userIds, int $currencyId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Transaction::query()
            ->whereIn('user_id', $userIds)
            ->where('currency_id', $currencyId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('user_id, COALESCE(SUM(CASE WHEN COALESCE(applied_amount, amount) > 0 THEN COALESCE(applied_amount, amount) ELSE 0 END), 0) AS total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
