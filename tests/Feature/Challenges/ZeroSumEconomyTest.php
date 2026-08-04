<?php

namespace Tests\Feature\Challenges;

use App\Models\Currency;
use App\Models\StreakDay;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use App\Models\WarMatch;
use App\Services\Gamification\StreakService;
use App\Services\Gamification\WalletGateway;
use App\Services\Gamification\Wars\Exceptions\WarRuleException;
use App\Services\Gamification\Wars\FocusWarService;
use App\Services\Gamification\Wars\WarMatchService;
use App\Services\Wallet\TopupCommissionObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * **الاقتصاد صفريّ المجموع** — لا تذكرة تدخل النظام من العدم (15.2-6 · 15.3).
 *
 * ================== النصّ الحاكم حرفيًّا ==================
 * • **15.2-6:** «**منع الفارمينج:** الرابح **+2** والخاسر **−2** (**محصّلة صفرية**)».
 * • **15.2-4:** «**بوابة ≥ 12 تذكرة** للطرفين، **والتذاكر لا تنزل تحت الصفر**».
 * • **15.3:** «**تذكرة الانضمام تروح لـ صاحب التحدي** (تحويل مباشر بين
 *   المستخدمين — **مش minting**، فالفارمينج مقفول)».
 *
 * ================== العطب الذي يحرسه هذا الملفّ ==================
 * كان `WalletGateway::post()` يكتب `max(0, $balance + $signedAmount)`: الرصيد
 * يقف عند صفر بينما يُقيَّد في `transactions` **بالقيمة كاملة**. رصيد 3 · خصم 12
 * ⟵ الرصيد نزل **3 فقط** والسطر يقول **−12**؛ فإن قابله `credit` بـ12 لطرفٍ آخر
 * **سُكَّت تسع تذاكر من العدم**. ومعه كان كلّ موضع تحويلٍ يكتب `debit()` ثمّ
 * `credit()` **ويُهمل نتيجة الخصم** — فالإضافة تمضي ولو رُدَّ الخصم.
 */
class ZeroSumEconomyTest extends ChallengeTestCase
{
    private function wallet(): WalletGateway
    {
        return app(WalletGateway::class);
    }

    /** مجموع كلّ ما سُجِّل في الدفتر من التذاكر — يجب أن يطابق مجموع الأرصدة */
    private function ledgerTickets(): float
    {
        $currencyId = Currency::query()->where('code', 'tickets')->value('id');

        return (float) Transaction::query()
            ->where('currency_id', $currencyId)
            ->selectRaw('COALESCE(SUM(COALESCE(applied_amount, amount)), 0) AS total')
            ->value('total');
    }

    // ------------------------------------------------------ (١) الخصم لا يُبتلَع

    /** ⭐ العطب بأرقامه: رصيد 3 · خصم 12 ⟵ **يُرَدّ**، ولا سطر ولا رصيدٌ سالب */
    public function test_a_debit_beyond_the_balance_is_refused_and_writes_nothing(): void
    {
        $user = $this->trainee(tickets: 3);
        $before = Transaction::query()->count();

        $this->assertFalse(
            $this->wallet()->debit($user, 'tickets', 12, 'challenge', 'مواجهة — خسارة'),
            'الخصم الذي يتجاوز الرصيد يجب أن يرجع false',
        );

        $this->assertSame(3.0, $this->ticketsOf($user));
        $this->assertSame($before, Transaction::query()->count());
    }

    /** ⭐ ولا يفترق الدفتر عن الرصيد: كلّ سطرٍ نزل بقيمته كاملةً */
    public function test_the_ledger_total_equals_the_sum_of_all_balances(): void
    {
        $a = $this->trainee(tickets: 20);
        $b = $this->trainee(tickets: 20);

        $this->wallet()->debit($a, 'tickets', 5, 'challenge', 'خصم');
        $this->wallet()->debit($a, 'tickets', 99, 'challenge', 'خصمٌ يتجاوز'); // مردود
        $this->wallet()->transfer($a, $b, 'tickets', 7, 'challenge', 'خروج', 'دخول');

        $this->assertSame($this->systemTickets(), $this->ledgerTickets());
        $this->assertSame(8.0, $this->ticketsOf($a));
        $this->assertSame(27.0, $this->ticketsOf($b));
    }

    // ------------------------------------ (١-ب) ومسار الاحتياط حين يغيب الدفتر

    /**
     * ⭐⭐ **السطر المعطوب كان هنا** — في مسار الاحتياط الذي يكتب بنفسه حين
     * يغيب `LedgerService`. ولأنّ الدفتر موجودٌ اليوم، فمسارٌ لا يُشغَّل في أيّ
     * اختبارٍ هو **حارسٌ لا يحرس**: يكفي أن يتغيّر توقيع الدفتر فيسقط النداء
     * لمسار الاحتياط، فيعود سكّ التذاكر من العدم بلا اختبارٍ واحدٍ يسقط.
     * فنُغيّبه هنا عمدًا (كائنٌ بلا `debitOrFail`) ونشغّل المسار بعينه.
     */
    private function withoutLedger(): void
    {
        // المراقِب على `transactions` يُبنى من الحاوية ويطلب الدفتر الحقيقيّ —
        // نُثبّته مبنيًّا **قبل** التغييب فلا يسقط بسببه مسارٌ ليس محلّ الاختبار
        $this->app->instance(
            TopupCommissionObserver::class,
            $this->app->make(TopupCommissionObserver::class),
        );

        $this->app->bind('App\Services\Wallet\LedgerService', fn () => new class {});
    }

    public function test_the_fallback_path_refuses_a_debit_beyond_the_balance(): void
    {
        $user = $this->trainee(tickets: 3);
        $this->withoutLedger();
        $before = Transaction::query()->count();

        $this->assertFalse($this->wallet()->debit($user, 'tickets', 12, 'challenge', 'خسارة'));
        $this->assertSame(3.0, $this->ticketsOf($user), 'الرصيد تحرّك رغم ردّ الخصم');
        $this->assertSame($before, Transaction::query()->count(), 'سطرٌ كُتِب لخصمٍ لم يقع');
    }

    public function test_the_fallback_path_never_mints_on_a_transfer(): void
    {
        $from = $this->trainee(tickets: 3);
        $to = $this->trainee(tickets: 0);
        $this->withoutLedger();
        $supply = $this->systemTickets();

        $this->assertSame(0.0, $this->wallet()->transfer($from, $to, 'tickets', 12, 'challenge', 'خسارة', 'فوز'));
        $this->assertSame(3.0, $this->ticketsOf($from));
        $this->assertSame(0.0, $this->ticketsOf($to));
        $this->assertSame($supply, $this->systemTickets());
    }

    /** والمسار نفسه يمرّر ما يستحقّ: خصمٌ يغطّيه الرصيد يُقيَّد كاملًا */
    public function test_the_fallback_path_still_posts_what_the_balance_covers(): void
    {
        $user = $this->trainee(tickets: 20);
        $this->withoutLedger();

        $this->assertTrue($this->wallet()->debit($user, 'tickets', 12, 'challenge', 'خسارة'));
        $this->assertSame(8.0, $this->ticketsOf($user));
    }

    // ------------------------------------------------------ (٢) التحويل ذرّيّ

    /** التحويل **يخصم بالضبط ما يضيف** — أو لا يفعل شيئًا */
    public function test_transfer_moves_exactly_what_it_deducts(): void
    {
        $from = $this->trainee(tickets: 20);
        $to = $this->trainee(tickets: 0);
        $supply = $this->systemTickets();

        $moved = $this->wallet()->transfer($from, $to, 'tickets', 2, 'challenge', 'خسارة', 'فوز');

        $this->assertSame(2.0, $moved);
        $this->assertSame(18.0, $this->ticketsOf($from));
        $this->assertSame(2.0, $this->ticketsOf($to));
        $this->assertSame($supply, $this->systemTickets());
    }

    /** ⭐ **وهذا هو قلب العطب:** خصمٌ مردود ⟵ **لا إضافة للطرف الآخر** */
    public function test_a_refused_transfer_credits_nobody(): void
    {
        $from = $this->trainee(tickets: 3);
        $to = $this->trainee(tickets: 0);
        $supply = $this->systemTickets();

        $moved = $this->wallet()->transfer($from, $to, 'tickets', 12, 'challenge', 'خسارة', 'فوز');

        $this->assertSame(0.0, $moved);
        $this->assertSame(3.0, $this->ticketsOf($from));
        $this->assertSame(0.0, $this->ticketsOf($to), 'تذكرة واحدة سُكَّت = الفارمينج مفتوح');
        $this->assertSame($supply, $this->systemTickets());
    }

    // ------------------------------------------------------ (٣) مسارات الحروب

    /** مواجهةٌ يملك طرفاها أقلّ من قيمة الرهان ⟵ لا سكّ ولا رصيدٌ سالب */
    public function test_a_settled_match_never_mints_when_the_loser_is_short(): void
    {
        $a = $this->trainee(tickets: 20);
        $b = $this->trainee(tickets: 20);
        $match = $this->startMatch($a, $b);

        // نُفقِر الخاسر **بعد** بوّابة الـ12 (حالة سباقٍ لا مسارٍ عاديّ)
        $this->drain($b, to: 1);
        $supply = $this->systemTickets();

        $settled = $this->playAndSettle($match, $a, $b);

        $this->assertSame($supply, $this->systemTickets(), 'مجموع تذاكر النظام تغيّر بعد مواجهة');
        $this->assertSame(0.0, (float) $settled->settlement['moved'], 'تحويلٌ نصفيّ بدل الردّ');
        $this->assertSame(1.0, $this->ticketsOf($b), 'خرج من الخاسر ما لا يملك');
        $this->assertSame(20.0, $this->ticketsOf($a), 'دخل للرابح ما لم يخرج من أحد');
        $this->assertSame($this->systemTickets(), $this->ledgerTickets());
    }

    /** والمواجهة السليمة ما زالت تُسوّى: **+2 للرابح و−2 من الخاسر** (15.2-6) */
    public function test_a_healthy_match_still_moves_two_tickets(): void
    {
        $a = $this->trainee(tickets: 20);
        $b = $this->trainee(tickets: 20);
        $supply = $this->systemTickets();
        $match = $this->startMatch($a, $b);

        $settled = $this->playAndSettle($match, $a, $b);

        $this->assertSame($a->id, (int) $settled->winner_id);
        $this->assertSame(22.0, $this->ticketsOf($a));
        $this->assertSame(18.0, $this->ticketsOf($b));
        $this->assertSame($supply, $this->systemTickets());
    }

    // ------------------------------------------------------ (٤) حرب التركيز

    /** الانضمام **تحويلٌ لا سكّ** حتى لو نزل رصيد المنضمّ بعد الفحص */
    public function test_joining_never_credits_the_owner_without_deducting_the_member(): void
    {
        $owner = $this->trainee(tickets: 20);
        $member = $this->trainee(tickets: 20);
        $service = app(FocusWarService::class);

        $war = $service->create($owner, $this->challenge('focus_war'), 25, null, true);
        $ownerBefore = $this->ticketsOf($owner);
        $supply = $this->systemTickets();

        $service->join($member, $war);

        $this->assertSame($ownerBefore + 1.0, $this->ticketsOf($owner));
        $this->assertSame($supply, $this->systemTickets(), 'تذكرة الانضمام سُكَّت بدل أن تُحوَّل');
    }

    /** ⭐ وحين يُرَدّ الخصم **بعد** الفحص: لا انضمامَ مجّانيّ ولا عضويّة بلا ثمن */
    public function test_a_refused_join_creates_no_membership_at_all(): void
    {
        $owner = $this->trainee(tickets: 20);
        $member = $this->trainee(tickets: 20);
        $war = app(FocusWarService::class)->create($owner, $this->challenge('focus_war'), 25, null, true);

        $this->refusingWallet();
        $memberCountBefore = $war->members()->count();

        $this->expectException(WarRuleException::class);

        try {
            app(FocusWarService::class)->join($member, $war);
        } finally {
            $this->assertSame($memberCountBefore, $war->refresh()->members()->count(), 'انضمامٌ بلا تذكرة');
        }
    }

    /** ⭐ ودرعُ التجميد لا يبقى حين يُرَدّ الخصم — المعاملة تُرتجَع كاملةً */
    public function test_a_refused_freeze_leaves_no_shield_behind(): void
    {
        $user = $this->trainee(tickets: 20);

        // حضر أوّل أمس واليوم وفاته أمس ⟵ يومٌ فايتٌ يستحقّ الدرع
        $now = CarbonImmutable::now('UTC')->setTime(9, 0);
        app(StreakService::class)->checkIn($user->refresh(), $now->subDays(2));
        app(StreakService::class)->checkIn($user->refresh(), $now);

        $this->refusingWallet();
        $before = StreakDay::query()->where('user_id', $user->id)->where('is_freeze', true)->count();

        $result = app(StreakService::class)->buyFreeze($user);

        $this->assertFalse($result['ok'], 'الدرع مُنِح رغم ردّ الخصم');
        $this->assertSame(
            $before,
            StreakDay::query()->where('user_id', $user->id)->where('is_freeze', true)->count(),
            'بقي يوم درعٍ مجّانيّ بعد ردّ الخصم',
        );
    }

    /** والإلغاء يُرَدّ إن لم يغطِّ الرصيد — ولا يستلم أحدٌ تذكرةً لم تخرج */
    public function test_cancelling_without_cover_refunds_nobody(): void
    {
        $owner = $this->trainee(tickets: 20);
        $member = $this->trainee(tickets: 20);
        $service = app(FocusWarService::class);

        $war = $service->create($owner, $this->challenge('focus_war'), 25, null, true);
        $service->join($member, $war);

        $this->drain($owner, to: 0);
        $memberBefore = $this->ticketsOf($member);
        $supply = $this->systemTickets();

        try {
            $service->cancel($owner, $war->refresh());
        } catch (\Throwable) {
            // مرفوض — وهو المطلوب
        }

        $this->assertSame($memberBefore, $this->ticketsOf($member));
        $this->assertSame($supply, $this->systemTickets());
        $this->assertSame('active', $war->refresh()->status);
    }

    // ------------------------------------------------------ (٥) الستريك

    /** درعُ التجميد لا يُمنَح بلا تذكرة تخرج فعلًا (7.1-4) */
    public function test_a_freeze_is_not_granted_when_the_ticket_cannot_be_taken(): void
    {
        $user = $this->trainee(tickets: 0);
        $supply = $this->systemTickets();

        $result = app(StreakService::class)->buyFreeze($user);

        $this->assertFalse($result['ok']);
        $this->assertSame($supply, $this->systemTickets());
    }

    // ------------------------------------------------------ أدوات

    /**
     * ⭐⭐ **محفظةٌ تَرُدّ كلّ خصم** — وهي السبيل الوحيد لإثبات الحرّاس الثلاثة
     * (الانضمام · الإلغاء · الدرع). فكلّ واحدٍ منها **يفحص الرصيد أوّلًا** برسالةٍ
     * مفيدة، والحارس الذي نختبره هو **الثاني**: ماذا يفعل حين يُرَدّ الخصم **بعد**
     * أن مرّ الفحص (سباقٌ، أو قاعُ العملة)؟ ولا يمكن بلوغه برصيدٍ ناقص لأنّ الفحص
     * الأوّل يعترض قبله — فنُبدِل المحفظة بواحدةٍ تَرُدّ، فيقع الحارس المقصود
     * وحده تحت الاختبار. والرصيد يُقرَأ من الأصل فيمرّ الفحص الأوّل كما في الواقع.
     */
    private function refusingWallet(): void
    {
        $this->app->instance(WalletGateway::class, new class extends WalletGateway
        {
            public function debit(User $user, string $currencyCode, float $amount, string $source, ?string $reason = null, ?Model $reference = null): bool
            {
                return false;
            }

            public function transfer(
                User $from,
                User $to,
                string $currencyCode,
                float $amount,
                string $source,
                ?string $debitReason = null,
                ?string $creditReason = null,
                ?Model $reference = null,
            ): float {
                return 0.0;
            }
        });
    }

    /** إنزال رصيد المستخدم لقيمةٍ بعينها بكتابةٍ مباشرة — محاكاةُ سباقٍ لا مسار */
    private function drain(User $user, float $to): void
    {
        $currencyId = Currency::query()->where('code', 'tickets')->value('id');
        $wallet = WalletBalance::query()->where('user_id', $user->id)->where('currency_id', $currencyId)->first();
        $delta = $to - (float) $wallet->balance;

        $wallet->forceFill(['balance' => $to])->save();

        // سطرٌ يقابل التعديل فيبقى الدفتر مطابقًا للأرصدة
        Transaction::create([
            'user_id' => $user->id,
            'currency_id' => $currencyId,
            'amount' => $delta,
            'applied_amount' => $delta,
            'balance_after' => $to,
            'layer' => 'training',
            'source' => 'grant',
            'reason' => 'ضبط رصيد للاختبار',
        ]);
    }

    /** الفائز يجاوب كلّ الأسئلة صحّ والخاسر لا يجاوب — ثمّ تُقفَل المواجهة */
    private function playAndSettle(WarMatch $match, User $winner, User $loser): WarMatch
    {
        $service = app(WarMatchService::class);

        foreach ((array) $match->questions as $i => $question) {
            $service->answer($match, $winner, $i, $question['answer']);
        }

        $service->finishSide($match, $service->sideOf($match, $winner));
        $service->finishSide($match->refresh(), $service->sideOf($match, $loser));

        return $match->refresh();
    }
}
