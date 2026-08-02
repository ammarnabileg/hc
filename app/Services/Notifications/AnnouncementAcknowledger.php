<?php

namespace App\Services\Notifications;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use Illuminate\Support\Facades\DB;

/**
 * الإقرار الإلزاميّ «قرأتُ وفهمت» للمنشورات الحرجة (13.2 · 24.5).
 *
 * لماذا كلّ شيء هنا داخل معاملة واحدة؟ لأنّ المكافأة تُمنح **مرّة واحدة**،
 * والتحقّق من ذلك في الخادم لا في الواجهة — فلا يُكرَّرها المستخدم بإعادة الطلب.
 */
class AnnouncementAcknowledger
{
    /**
     * يُرجِع مقدار الـXP الممنوح في هذه المرّة (صفر لو سبق الإقرار أو بلا مكافأة).
     */
    public function acknowledge(Announcement $announcement, User $user): int
    {
        return DB::transaction(function () use ($announcement, $user) {
            $read = AnnouncementRead::query()
                ->where('announcement_id', $announcement->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            // سبق الإقرار: لا مكافأة ثانية مهما تكرّر الطلب
            if ($read?->acknowledged_at) {
                return 0;
            }

            $now = now();

            AnnouncementRead::updateOrCreate(
                ['announcement_id' => $announcement->id, 'user_id' => $user->id],
                ['read_at' => $read?->read_at ?? $now, 'acknowledged_at' => $now],
            );

            $xp = $this->rewardAmount($announcement);

            if ($xp > 0) {
                $this->awardXp($user, $announcement, $xp);
            }

            return $xp;
        });
    }

    /** المكافأة كما ضبطها الأدمن للمنشور، مسقوفةً بحدّ عامّ من الإعدادات (2.13). */
    public function rewardAmount(Announcement $announcement): int
    {
        $cap = (int) setting('announcements.acknowledge.max_xp', 50);

        return max(0, min((int) $announcement->acknowledge_xp, $cap));
    }

    /** إيداع XP في المحفظة بمعاملة موثّقة — لا أرقام تُعدَّل بلا أثر. */
    private function awardXp(User $user, Announcement $announcement, int $xp): void
    {
        $currency = Currency::where('code', (string) setting('announcements.acknowledge.currency', 'xp'))->first();

        // المحفظة قد لا تكون مهيّأة في بيئة مبسّطة — يبقى رصيد المستخدم صحيحًا على أيّ حال
        if ($currency) {
            $balance = WalletBalance::firstOrCreate(
                ['user_id' => $user->id, 'currency_id' => $currency->id],
                ['balance' => 0, 'lifetime_earned' => 0, 'lifetime_spent' => 0],
            );

            $balance->forceFill([
                'balance' => $balance->balance + $xp,
                'lifetime_earned' => $balance->lifetime_earned + $xp,
            ])->save();

            Transaction::create([
                'user_id' => $user->id,
                'currency_id' => $currency->id,
                'amount' => $xp,
                'balance_after' => $balance->balance,
                'layer' => 'training',
                'source' => 'announcement',
                'reason' => 'إقرار قراءة تعليمات: '.$announcement->title,
                'reference_type' => $announcement->getMorphClass(),
                'reference_id' => $announcement->id,
            ]);
        }

        $user->forceFill(['xp' => (int) $user->xp + $xp])->saveQuietly();
    }
}
