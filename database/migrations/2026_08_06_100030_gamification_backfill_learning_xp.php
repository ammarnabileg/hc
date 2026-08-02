<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ترميم: XP التعلّم لم يكن يظهر في لوحة الصدارة إطلاقًا (7 · 7.3).
 *
 * العطل: XP الدروس كان يُكتَب في `enrollments.xp_earned` وحده، بينما
 * **الليدر بورد والمستوى والشارات تقرأ من `users.xp` ومن دفتر المحفظة** —
 * فكان المتعلّم المجتهد صفرًا في الترتيب.
 *
 * العلاج هنا: نقل ما اكتُسِب سابقًا إلى المصدر الموحّد (users.xp + محفظة XP)
 * بمعاملةٍ تاريخيّة **بتاريخ التسجيل الأصليّ لا تاريخ اليوم**، حتى لا تتلوّث
 * فروق الليدر بورد (آخر 7/30 يومًا) بقفزةٍ وهميّة لحظة الترحيل.
 *
 * وهي عمليّة **مرّة واحدة**: العلامة `meta.backfill` تمنع تكرارها لو أُعيد التشغيل.
 */
return new class extends Migration
{
    private const MARK = 'learning_xp_backfill';

    public function up(): void
    {
        foreach (['enrollments', 'transactions', 'wallet_balances', 'currencies', 'users'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        $currencyId = DB::table('currencies')->where('code', 'xp')->value('id');

        if (! $currencyId) {
            return;
        }

        $rows = DB::table('enrollments')
            ->where('xp_earned', '>', 0)
            ->orderBy('user_id')
            ->orderBy('id')
            ->get(['id', 'user_id', 'xp_earned', 'updated_at', 'created_at']);

        if ($rows->isEmpty()) {
            return;
        }

        // ما رُحِّل من قبل لا يُرحَّل ثانيةً
        $done = DB::table('transactions')
            ->where('currency_id', $currencyId)
            ->where('reference_type', 'App\Models\Enrollment')
            ->where('source', self::MARK)
            ->pluck('reference_id')
            ->all();

        $done = array_flip(array_map('intval', $done));

        foreach ($rows->groupBy('user_id') as $userId => $enrollments) {
            $userId = (int) $userId;

            $wallet = DB::table('wallet_balances')
                ->where('user_id', $userId)
                ->where('currency_id', $currencyId)
                ->first();

            $balance = (float) ($wallet->balance ?? 0);
            $earned = (float) ($wallet->lifetime_earned ?? 0);
            $added = 0.0;

            foreach ($enrollments as $enrollment) {
                if (isset($done[(int) $enrollment->id])) {
                    continue;
                }

                $amount = (float) $enrollment->xp_earned;
                $balance += $amount;
                $earned += $amount;
                $added += $amount;
                $at = $enrollment->updated_at ?? $enrollment->created_at ?? now();

                DB::table('transactions')->insert([
                    'user_id' => $userId,
                    'currency_id' => $currencyId,
                    'amount' => $amount,
                    'applied_amount' => $amount,
                    'balance_after' => $balance,
                    'layer' => 'training',
                    'source' => self::MARK,
                    'reason' => 'ترميم XP التعلّم إلى المصدر الموحّد',
                    'reference_type' => 'App\Models\Enrollment',
                    'reference_id' => $enrollment->id,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }

            if ($added <= 0) {
                continue;
            }

            if ($wallet) {
                DB::table('wallet_balances')->where('id', $wallet->id)->update([
                    'balance' => $balance,
                    'lifetime_earned' => $earned,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('wallet_balances')->insert([
                    'user_id' => $userId,
                    'currency_id' => $currencyId,
                    'balance' => $balance,
                    'lifetime_earned' => $earned,
                    'lifetime_spent' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('users')->where('id', $userId)->update([
                'xp' => DB::raw('xp + '.(int) $added),
            ]);
        }
    }
};
