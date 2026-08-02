<?php

namespace App\Services\Notifications;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\EconomyLedger;
use Illuminate\Support\Facades\DB;

/**
 * الإقرار الإلزاميّ «قرأتُ وفهمت» للمنشورات الحرجة (13.2 · 24.5).
 *
 * لماذا كلّ شيء هنا داخل معاملة واحدة؟ لأنّ المكافأة تُمنح **مرّة واحدة**،
 * والتحقّق من ذلك في الخادم لا في الواجهة — فلا يُكرَّرها المستخدم بإعادة الطلب.
 */
class AnnouncementAcknowledger
{
    /** التذاكر الممنوحة في آخر نداء — حالةُ طلبٍ واحد لا أكثر. */
    private int $lastTickets = 0;

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

            // ⭐ السقف اليوميّ يُطبَّق قبل المنح — «بحذر بلا إغراق» (13.2)
            $xp = $this->grantableXp($user, $announcement);

            if ($xp > 0) {
                $this->awardXp($user, $announcement, $xp);
            }

            /*
             | ⭐ **تذاكر الإقرار تُصرَف فعلًا** (12.6-أ): كان الحقل يُدخَل في الفورم
             | ويُتحقَّق منه ويُحفَظ في العمود — **وبلا قارئ**، فينشر الأدمن بـ7 تذاكر
             | ولا تصل تذكرةٌ واحدة. والمنح يمرّ من `EconomyLedger` كأيّ حركة عملة.
             */
            $this->lastTickets = $this->awardTickets($user, $announcement);

            return $xp;
        });
    }

    /** التذاكر الممنوحة في آخر إقرارٍ ناجح — للرسالة المعروضة وحدها (12.6-أ). */
    public function lastTickets(): int
    {
        return $this->lastTickets;
    }

    /**
     * المكافأة كما ضبطها الأدمن للمنشور، مسقوفةً بحدّ عامّ من الإعدادات (2.13).
     *
     * ⭐ الافتراضيّ **500** — نفس ما في السيدر وفي تحقّق الفورم. كان هنا 50 وهناك
     * 500، فيسمح الفورم بـ300 ويُصرَف 50 بلا تنبيه: مفتاحٌ واحد بافتراضيّين.
     */
    public function rewardAmount(Announcement $announcement): int
    {
        $cap = (int) setting('announcements.acknowledge.max_xp', 500);

        return max(0, min((int) $announcement->acknowledge_xp, $cap));
    }

    /**
     * ⭐ السقف **اليوميّ** لمكافآت الإقرار مجتمعةً (13.2: «بحذر بلا إغراق»).
     *
     * `announcements.acknowledge.max_xp` سقفٌ **لكلّ منشور** وحده، فسبعة منشورات
     * بـ25 XP تعطي 175 XP في جلسة واحدة بلا أيّ حدّ — إغراقٌ صريح يفسد الليدر بورد
     * والمستوى. و**صفر يعني بلا سقف** كما في بقيّة حدود الاقتصاد (12.10).
     */
    public function dailyXpCap(): int
    {
        return max(0, (int) setting('announcements.acknowledge.daily_max_xp', 100));
    }

    /** ما مُنِح فعلًا اليوم من هذا المصدر — الأساس الذي يُقاس عليه السقف اليوميّ */
    public function xpAwardedToday(User $user): int
    {
        // نفس عملة الدفتر — فالمقياس والمنح على مسطرة واحدة
        $currencyId = Currency::query()
            ->where('code', (string) setting('announcements.acknowledge.currency', app(EconomyLedger::class)->xpCode()))
            ->value('id');

        if (! $currencyId) {
            return 0;
        }

        return (int) round((float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $currencyId)
            ->where('source', $this->ledgerSource())
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->selectRaw('COALESCE(SUM(COALESCE(applied_amount, amount)), 0) AS total')
            ->value('total'));
    }

    /** مكافأة هذا المنشور بعد قصّ ما تبقّى من سقف اليوم */
    public function grantableXp(User $user, Announcement $announcement): int
    {
        $xp = $this->rewardAmount($announcement);
        $cap = $this->dailyXpCap();

        if ($xp <= 0 || $cap <= 0) {
            return $xp;
        }

        return (int) max(0, min($xp, $cap - $this->xpAwardedToday($user)));
    }

    /** دلو المصدر في دفتر الأستاذ — إعداد لا نصّ محروق (2.13) */
    private function ledgerSource(): string
    {
        return (string) setting('announcements.acknowledge.ledger_source', 'announcement');
    }

    /** تذاكر الإقرار كما ضبطها الأدمن، مسقوفةً بحدّ عامّ (12.6-أ · 2.13). */
    public function ticketsAmount(Announcement $announcement): int
    {
        $cap = (int) setting('announcements.acknowledge.max_tickets', 20);

        return max(0, min((int) $announcement->acknowledge_tickets, $cap));
    }

    private function awardTickets(User $user, Announcement $announcement): int
    {
        $tickets = $this->ticketsAmount($announcement);

        if ($tickets <= 0) {
            return 0;
        }

        app(EconomyLedger::class)->awardTickets(
            user: $user,
            amount: $tickets,
            source: $this->ledgerSource(),
            reference: $announcement,
            reason: 'إقرار قراءة تعليمات: '.$announcement->title,
        );

        return $tickets;
    }

    /**
     * ⭐ إيداع XP **من `EconomyLedger` وحده** (7.3 · 19).
     *
     * كان هنا مسارٌ موازٍ يكتب `WalletBalance` و`Transaction` و`users.xp` بيده:
     * بلا قفل صفّ المحفظة (فيتسابق نداءان على رصيدٍ واحد)، وبلا `applied_amount`
     * ولا `objection_deadline_at`، وبلا حدود الكسب اليوميّة — أيْ خارج كلّ ما
     * بُنِيت نقطةُ المنح الموحّدة لتضمنه. والدفتر يكتب الثلاثة معًا في معاملة واحدة.
     */
    private function awardXp(User $user, Announcement $announcement, int $xp): void
    {
        app(EconomyLedger::class)->awardXp(
            user: $user,
            amount: $xp,
            source: $this->ledgerSource(),
            reference: $announcement,
            reason: 'إقرار قراءة تعليمات: '.$announcement->title,
        );
    }
}
