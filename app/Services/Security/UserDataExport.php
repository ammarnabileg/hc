<?php

namespace App\Services\Security;

use App\Models\Referral;
use App\Models\User;
use App\Models\WalletWithdrawal;

/**
 * **تصدير بيانات المستخدم كملفّ** (12.1-متقدّم-6).
 *
 * لماذا JSON لا CSV؟ لأنّ البيانات هنا **متداخلة** (أرصدة وجداول وشهادات معًا)،
 * وتفكيكها لجداول مسطّحة يضيّع العلاقة بينها. الملفّ مقروء بالعين وبالبرنامج معًا.
 *
 * ⛔ ولا تخرج منه **كلمة السرّ ولا التوكنات ولا الملاحظات الإداريّة الداخليّة** —
 *    الملفّ قد يصل ليد صاحب الحساب، والملاحظات «للفريق فقط» بنصّ 12.1.
 */
class UserDataExport
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $rows = max(1, (int) setting('admin.users.export_rows', 500));

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'code' => $user->code,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'gender' => $user->gender,
                'birthdate' => $user->birthdate,
                'country' => $user->country?->name_ar,
                'governorate' => $user->governorate?->name_ar,
                'xp' => (int) $user->xp,
                'level' => (int) $user->level,
                'email_verified_at' => (string) $user->email_verified_at,
                'created_at' => (string) $user->created_at,
                'last_seen_at' => (string) $user->last_seen_at,
            ],
            'roles' => $user->roles->pluck('name_ar')->values()->all(),
            'balances' => $user->balances()->with('currency')->get()
                ->map(fn ($balance) => [
                    'currency' => $balance->currency?->name_ar,
                    'balance' => (float) $balance->balance,
                ])->values()->all(),
            'transactions' => $user->transactions()->with('currency')->latest('id')->limit($rows)->get()
                ->map(fn ($transaction) => [
                    'date' => (string) $transaction->created_at,
                    'currency' => $transaction->currency?->name_ar,
                    'amount' => (float) $transaction->amount,
                    'reason' => $transaction->reason ?? $transaction->source,
                ])->values()->all(),
            'withdrawals' => WalletWithdrawal::where('user_id', $user->id)->latest('id')->limit($rows)->get()
                ->map(fn ($withdrawal) => [
                    'number' => $withdrawal->number,
                    'date' => (string) $withdrawal->created_at,
                    'amount' => (float) $withdrawal->amount,
                    'net_amount' => (float) $withdrawal->net_amount,
                    'status' => $withdrawal->statusLabel(),
                ])->values()->all(),
            'certificates' => $user->certificates()->latest('id')->limit($rows)->get()
                ->map(fn ($certificate) => [
                    'number' => $certificate->number,
                    'date' => (string) $certificate->created_at,
                ])->values()->all(),
            'referrals' => Referral::where('referrer_id', $user->id)->with('referred')->latest('id')->limit($rows)->get()
                ->map(fn ($referral) => [
                    'date' => (string) $referral->created_at,
                    'invited' => $referral->referred?->shortName(),
                    'gift_granted' => (bool) $referral->welcome_ticket_granted,
                ])->values()->all(),
        ];
    }
}
