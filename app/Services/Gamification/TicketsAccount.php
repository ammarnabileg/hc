<?php

namespace App\Services\Gamification;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **التذاكر — المصدر الواحد الحاكم لكلّ رقمٍ يُعرَض** (7.1 · 10 · 19.2 · 24.5).
 *
 * **النصوص الحاكمة، وهي ثلاثةٌ لثلاثة أرقامٍ مختلفة لا لرقمٍ واحد:**
 *   1. **الرصيد** — 10.0-أ: «بطاقات KPI: مستوى الحساب + XP · **رصيد التذاكر 🎟️**».
 *      وهو وحده ما يُصرَف منه، وعليه بوّابة الحرب «الحالة = الرصيد ≥ 12 تذكرة» (15).
 *   2. **إجماليّ المكتسب** — 10 (مسارات الإنجازات): «**التذاكر** — إجمالي التذاكر
 *      **المكتسبة**». فالإنفاق لا يُنزِل مستوى المسار.
 *   3. **مكتسب/مصروف خلال المدى** — 24.5 (تاب «إحصائيّاتي»): «**بارات التذاكر
 *      (مكتسب/مصروف)**»، ومثلها في «المحفظة ← التذاكر»: «كارت الرصيد + **بارات
 *      مكتسب/مصروف**».
 *
 * **العطل الذي يُصلحه (ن-2):** الأرقام الثلاثة كانت تُقرَأ من **ثلاثة مخازن**:
 * الرصيد من `wallet_balances.balance`، والمكتسب من `wallet_balances.lifetime_earned`
 * (يكتبه ستّة مسارات مختلفة بلا اتّفاق)، والبارات من جدول `transactions`. فظهرت
 * على لوحةٍ واحدة أرقامٌ لا يجمعها حساب: «10» و«17» و«+33 −17» — ولا واحدةَ منها
 * تُفسِّر الأخرى، فيقرؤها المستخدم تناقضًا لا تفصيلًا.
 *
 * **القاعدة من اليوم:** **دفتر المعاملات هو الأصل** (19.2 «الجدول الموحّد»)،
 * و`wallet_balances.balance` هو **الرصيد التشغيليّ** الذي تُبنى عليه بوّابات
 * الصرف. وما سبق الدفتر (بيانات ترحيل أو تمهيد) يُنسَب صراحةً إلى **رصيدٍ
 * افتتاحيّ** فينغلق الميزان دائمًا: **المكتسب − المصروف = الرصيد**، مهما كانت
 * حالة البيانات. فلا يبقى رقمٌ بلا تفسير.
 */
class TicketsAccount
{
    /** كود عملة التذاكر — من الإعدادات لا محروقًا (2.13) */
    public function code(): string
    {
        return (string) setting('wallet.currency.tickets_code', 'tickets');
    }

    /**
     * ⭐ الصورة الواحدة الموزونة — يقرأ منها كلّ عارضٍ ما يخصّه ولا يحسب شيئًا.
     *
     * @return array{balance:int, earned:int, spent:int, opening:int}
     */
    public function snapshot(User $user): array
    {
        $balance = (int) round((float) $user->balances()
            ->whereHas('currency', fn ($q) => $q->where('code', $this->code()))
            ->value('balance'));

        $ledger = $this->ledgerTotals($user);
        $stored = $this->storedTotals($user);

        /*
         | الدفتر **أرضيّة لا سقف**: ما سجّله لا يُنكَر، وما سبقه من عدّادات
         | تراكميّة (ترحيل · بيانات تمهيد · مسارات كتبت العدّاد ولم تكتب سطرًا)
         | لا يُمحى. فنأخذ الأعلى منهما — «المكتسب» لا ينقص أبدًا بمرور الزمن.
         */
        $earned = max($ledger['earned'], $stored['earned']);
        $spent = max($ledger['spent'], $stored['spent']);

        /*
         | وما يبقى بلا تفسير يُسمّى باسمه: **رصيد افتتاحيّ**. موجبًا يُضاف
         | للمكتسب، وسالبًا للمصروف — فالميزان ينغلق ولا يُعرَض رقمٌ يتيم.
         */
        $opening = $balance - ($earned - $spent);

        if ($opening > 0) {
            $earned += $opening;
        } elseif ($opening < 0) {
            $spent += -$opening;
        }

        return [
            'balance' => $balance,
            'earned' => $earned,
            'spent' => $spent,
            'opening' => $opening,
        ];
    }

    /** **رصيد التذاكر** (10.0-أ) — ما يُصرَف منه، وكارت الـKPI يعرضه باسمه */
    public function balance(User $user): int
    {
        return $this->snapshot($user)['balance'];
    }

    /** **إجماليّ التذاكر المكتسبة** (10) — مقياس مسار الإنجاز، والإنفاق لا يُنقِصه */
    public function earned(User $user): int
    {
        return $this->snapshot($user)['earned'];
    }

    /** **إجماليّ المصروف** — الوجه الثاني للميزان نفسه */
    public function spent(User $user): int
    {
        return $this->snapshot($user)['spent'];
    }

    /**
     * **بارات «مكتسب/مصروف» خلال المدى** (24.5) — من الدفتر نفسه الذي بُني عليه
     * المكتسب الكلّيّ، فمجموع البارات لا يتجاوز الإجماليّ أبدًا.
     *
     * @return array<int, array{label:string,start:string,end:string,earned:int,spent:int}>
     */
    public function flow(User $user, int $days): array
    {
        $days = max(1, $days);
        $from = Carbon::today()->subDays($days - 1);
        $daily = $days <= (int) setting('dashboard.tickets.daily_max_days', 7);

        $rows = $this->transactions($user)
            ->where('transactions.created_at', '>=', $from)
            ->selectRaw('date(transactions.created_at) as day, COALESCE(transactions.applied_amount, transactions.amount) as amount')
            ->get();

        $buckets = [];
        $count = $daily ? $days : (int) ceil($days / 7);

        for ($i = 0; $i < $count; $i++) {
            $start = $daily ? $from->copy()->addDays($i) : $from->copy()->addWeeks($i);
            $end = $daily ? $start->copy() : $start->copy()->addDays(6);

            $buckets[] = [
                'label' => $daily ? $start->format('j/n') : $start->format('j/n').' — '.$end->format('j/n'),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'earned' => 0,
                'spent' => 0,
            ];
        }

        foreach ($rows as $row) {
            foreach ($buckets as $index => $bucket) {
                if ($row->day >= $bucket['start'] && $row->day <= $bucket['end']) {
                    $amount = (float) $row->amount;
                    $key = $amount >= 0 ? 'earned' : 'spent';
                    $buckets[$index][$key] += (int) round(abs($amount));
                    break;
                }
            }
        }

        return $buckets;
    }

    // ------------------------------------------------------------ داخليّ

    /**
     * مجموعا الدفتر: الموجب مكتسبًا والسالب مصروفًا — و`applied_amount` أوّلًا
     * لأنّه ما نزل فعلًا بعد الحدّ اليوميّ، لا ما طُلِب.
     *
     * @return array{earned:int, spent:int}
     */
    private function ledgerTotals(User $user): array
    {
        $row = $this->transactions($user)
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(transactions.applied_amount, transactions.amount) > 0 THEN COALESCE(transactions.applied_amount, transactions.amount) ELSE 0 END), 0) AS earned')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(transactions.applied_amount, transactions.amount) < 0 THEN -COALESCE(transactions.applied_amount, transactions.amount) ELSE 0 END), 0) AS spent')
            ->first();

        return [
            'earned' => (int) round((float) ($row->earned ?? 0)),
            'spent' => (int) round((float) ($row->spent ?? 0)),
        ];
    }

    /**
     * العدّادان التراكميّان المخزَّنان على صفّ المحفظة — سجلُّ ما قبل الدفتر.
     *
     * @return array{earned:int, spent:int}
     */
    private function storedTotals(User $user): array
    {
        $row = $user->balances()
            ->whereHas('currency', fn ($q) => $q->where('code', $this->code()))
            ->first(['lifetime_earned', 'lifetime_spent']);

        return [
            'earned' => (int) round((float) ($row->lifetime_earned ?? 0)),
            'spent' => (int) round((float) ($row->lifetime_spent ?? 0)),
        ];
    }

    private function transactions(User $user): Builder
    {
        return DB::table('transactions')
            ->join('currencies', 'currencies.id', '=', 'transactions.currency_id')
            ->where('transactions.user_id', $user->id)
            ->where('currencies.code', $this->code());
    }
}
