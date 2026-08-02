<?php

namespace App\Services\Engagement;

use App\Models\PositiveMessage;
use App\Models\User;
use App\Services\Events\LedgerBridge;
use Illuminate\Support\Collection;

/**
 * الرسائل الإيجابيّة (2.6-ب).
 *
 * لماذا خدمة واحدة؟ لأنّ الرسالة التشجيعيّة تُقال في أكثر من مكان (بعد درس ·
 * عند انكسار ستريك · في حالة فارغة · وفي الأيقونة العائمة المفاجئة)، فلو كتب
 * كلّ مجال قائمته صارت للمنصّة نبرات متعدّدة — والنبرة واحدة (2.17-ج).
 *
 * والضوابط الأخلاقيّة محفوظة هنا (2.9 · 21.1-د):
 *  - **بلا مبالغة**: النصّ كلّه من مكتبة الأدمن، ولا نضيف عليه علامات صراخ.
 *  - **بلا Dark Patterns**: لا تأنيب ولا ندرة كاذبة ولا عدّاد وهميّ —
 *    وسياق «انكسار الستريك» رسالته مواساة لا لوم.
 *  - **بلا تكرار ممل**: آخر N رسالة ظهرت لا تتكرّر حتى تنفد المكتبة.
 */
class PositiveMessages
{
    /** ذاكرة قصيرة في الجلسة تمنع تكرار نفس الرسالة مرّتين متتاليتين */
    public const RECENT_KEY = 'engagement.positive.recent';

    /** سياق عامّ يصلح لأيّ لحظة — تُخلَط رسائله مع رسائل السياق المطلوب */
    public const ANY = 'any';

    public function __construct(private readonly LedgerBridge $ledger) {}

    public function enabled(): bool
    {
        return (bool) setting('engagement.positive.enabled', true);
    }

    /**
     * السياقات المعتمَدة ولافتاتها — إعداد لا قائمة محروقة (2.13)،
     * فالأدمن يضيف سياقًا جديدًا بلا سطر كود.
     *
     * @return array<string,string>
     */
    public function contexts(): array
    {
        $contexts = setting('engagement.positive.contexts', []);

        if (! is_array($contexts) || $contexts === []) {
            return [self::ANY => 'أيّ لحظة'];
        }

        return $contexts;
    }

    public function contextLabel(string $context): string
    {
        return $this->contexts()[$context] ?? $context;
    }

    /**
     * رسالة مناسبة لهذا السياق — أو null إن كانت المكتبة فاضية أو الميزة موقوفة.
     * وترتيب الاختيار: رسائل السياق نفسه + رسائل السياق العامّ، مطروحًا منها
     * ما ظهر مؤخّرًا؛ فإن نفد الكلّ أعدنا فتح المكتبة من أوّلها.
     */
    public function forContext(string $context): ?PositiveMessage
    {
        if (! $this->enabled()) {
            return null;
        }

        $pool = $this->pool($context);

        if ($pool->isEmpty()) {
            return null;
        }

        $recent = $this->recent();
        $fresh = $pool->reject(fn (PositiveMessage $m) => in_array($m->id, $recent, true));

        // نفدت الرسائل الجديدة ⟵ نبدأ دورة جديدة بدل أن نصمت
        $picked = ($fresh->isNotEmpty() ? $fresh : $pool)->random();

        $this->remember($picked->id);
        $picked->increment('shown_count');

        return $picked;
    }

    /**
     * آليّة ظهور الأيقونة العائمة (2.6-ب): رقم عشوائيّ 1..100 ونسبته من الإعدادات
     * (الافتراضيّ 3%) — والاحتمال حقيقيّ لا موجَّه، فلا خداع (2.9).
     */
    public function shouldShowIcon(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return $this->roll((int) setting('engagement.positive.icon_chance_percent', 3));
    }

    /** زرّ «استلام تذكرة» يظهر في نسبة من ظهورات الأيقونة (الافتراضيّ 20%) */
    public function shouldOfferTicket(): bool
    {
        return $this->roll((int) setting('engagement.positive.ticket_chance_percent', 20));
    }

    public function ticketAmount(): int
    {
        return max(0, (int) setting('engagement.positive.ticket_amount', 1));
    }

    /**
     * منح تذكرة المفاجأة — **بحدّ يوميّ** كي تبقى مفاجأةً لا مصدر دخل،
     * والقيمة الحقيقيّة مكتوبة للمستخدم قبل الضغط (21.1-د).
     */
    public function grantTicket(User $user): bool
    {
        $amount = $this->ticketAmount();

        if (! $this->enabled() || $amount <= 0 || ! $this->canClaimToday($user)) {
            return false;
        }

        $granted = $this->ledger->credit(
            $user,
            (string) setting('engagement.positive.ticket_currency', 'tickets'),
            $amount,
            'positive_message',
            (string) setting('engagement.positive.ticket_reason', 'تذكرة رسالة إيجابيّة'),
        );

        if ($granted) {
            $this->markClaimed($user);
        }

        return $granted;
    }

    /** كم تذكرة مفاجأة بقيت له اليوم — يُعرَض صراحةً بلا غموض */
    public function canClaimToday(User $user): bool
    {
        $cap = (int) setting('engagement.positive.daily_ticket_cap', 1);

        if ($cap <= 0) {
            return false;
        }

        return $this->claimsToday($user) < $cap;
    }

    // ------------------------------------------------------------ داخليّات

    /**
     * @return Collection<int,PositiveMessage>
     */
    private function pool(string $context): Collection
    {
        return PositiveMessage::query()
            ->where('is_active', true)
            ->whereIn('context', array_unique([$context, self::ANY]))
            ->orderBy('sort_order')
            ->get();
    }

    /** @return array<int,int> */
    private function recent(): array
    {
        if (! app()->bound('session') || ! app('session')->isStarted()) {
            return [];
        }

        return (array) session()->get(self::RECENT_KEY, []);
    }

    private function remember(int $id): void
    {
        if (! app()->bound('session') || ! app('session')->isStarted()) {
            return;
        }

        $window = max(1, (int) setting('engagement.positive.no_repeat_last', 5));
        $recent = array_values(array_slice([...$this->recent(), $id], -$window));

        session()->put(self::RECENT_KEY, $recent);
    }

    private function claimsToday(User $user): int
    {
        return (int) $user->transactions()
            ->where('source', 'positive_message')
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    private function markClaimed(User $user): void
    {
        // العلامة الحقيقيّة هي قيد المحفظة نفسه — فلا جدول موازٍ ولا عدّاد يمكن تزويره
        $user->touch();
    }

    private function roll(int $percent): bool
    {
        $percent = max(0, min(100, $percent));

        if ($percent <= 0) {
            return false;
        }

        return random_int(1, 100) <= $percent;
    }
}
