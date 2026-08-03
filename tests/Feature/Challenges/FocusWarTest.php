<?php

namespace Tests\Feature\Challenges;

use App\Models\FocusWar;
use App\Models\FocusWarMember;
use App\Services\Gamification\WalletGateway;
use App\Services\Gamification\Wars\FocusWarService;

/**
 * حرب التركيز (15.3): تكاليفها وتحويلاتها وإلغاؤها ودقائقها.
 */
class FocusWarTest extends ChallengeTestCase
{
    /** الإنشاء بـ5 تذاكر — والرسوم غير قابلة للاسترجاع (15.3). */
    public function test_creating_a_focus_war_costs_five_tickets(): void
    {
        $owner = $this->trainee(tickets: 20);

        $this->actingAs($owner)->post(route('challenges.focus.store'), [
            'duration_minutes' => 25,
            'intention' => 'أخلّص فصل من كتاب',
            'is_group' => 1,
        ])->assertRedirect(route('challenges.focus.index'));

        $this->assertEqualsWithDelta(15, $this->ticketsOf($owner), 0.001);
        $this->assertDatabaseHas('focus_wars', ['owner_id' => $owner->id, 'duration_minutes' => 25, 'is_group' => true]);
        // صاحب التحدّي عضوٌ فيه بلا تذكرة انضمام
        $this->assertDatabaseHas('focus_war_members', ['user_id' => $owner->id, 'paid' => 0]);
    }

    /** ⭐ تذكرة الانضمام **تُحوَّل لصاحب التحدّي** — تحويل لا سكّ (15.3). */
    public function test_join_ticket_is_transferred_to_the_owner_not_minted(): void
    {
        $owner = $this->trainee(tickets: 20);
        $joiner = $this->trainee(tickets: 20);

        $war = app(FocusWarService::class)->create($owner, $this->challenge('focus_war'), 15, null, true);

        $before = $this->systemTickets();

        $this->actingAs($joiner)->post(route('challenges.focus.join', $war))->assertRedirect();

        $this->assertEqualsWithDelta(16, $this->ticketsOf($owner), 0.001); // 20 − 5 + 1
        $this->assertEqualsWithDelta(19, $this->ticketsOf($joiner), 0.001);
        // مجموع النظام ثابت: التذكرة انتقلت ولم تُخلَق
        $this->assertEqualsWithDelta($before, $this->systemTickets(), 0.001);
    }

    /** حدّ أقصى 5 تحديات نشطة لكلّ حساب (15.3). */
    public function test_active_focus_wars_are_capped(): void
    {
        $owner = $this->trainee(tickets: 100);
        $service = app(FocusWarService::class);
        $challenge = $this->challenge('focus_war');
        $max = (int) setting('wars.shared.max_active_focus', 5);

        for ($i = 0; $i < $max; $i++) {
            $service->create($owner, $challenge, 5, null, false);
        }

        $this->actingAs($owner)
            ->from(route('challenges.focus.index'))
            ->post(route('challenges.focus.store'), ['duration_minutes' => 5])
            ->assertRedirect(route('challenges.focus.index'));

        $this->assertSame($max, FocusWar::query()->where('owner_id', $owner->id)->where('status', 'active')->count());
    }

    /**
     * الإلغاء: يُسترجَع لمن **لم يُكمِل وقته** فقط، ومَن أكمله لا يُسترجَع له،
     * والدقائق تبقى للجميع (15.3).
     */
    public function test_cancelling_refunds_only_members_who_did_not_finish_their_time(): void
    {
        $owner = $this->trainee(tickets: 30);
        $early = $this->trainee(tickets: 20);
        $done = $this->trainee(tickets: 20);

        $service = app(FocusWarService::class);
        $war = $service->create($owner, $this->challenge('focus_war'), 50, null, true);

        $service->join($early, $war);
        $service->join($done, $war);

        // مَن انقضى وقته فعلًا لا يستحقّ استرجاعًا — استفاد كاملًا
        FocusWarMember::query()
            ->where('focus_war_id', $war->id)
            ->where('user_id', $done->id)
            ->update(['ends_at' => now()->subMinute()]);

        $before = $this->systemTickets();

        $this->actingAs($owner)->post(route('challenges.focus.cancel', $war))->assertRedirect();

        $this->assertSame('cancelled', $war->refresh()->status);
        $this->assertEqualsWithDelta(20, $this->ticketsOf($early), 0.001);   // 20 − 1 + 1
        $this->assertEqualsWithDelta(19, $this->ticketsOf($done), 0.001);    // 20 − 1 بلا استرجاع
        $this->assertEqualsWithDelta(26, $this->ticketsOf($owner), 0.001);   // 30 − 5 + 2 − 1
        $this->assertEqualsWithDelta($before, $this->systemTickets(), 0.001);

        // إشعار المنضمّين بالإلغاء عبر مركز الإشعارات (2.8)
        $this->assertDatabaseHas('app_notifications', ['user_id' => $early->id, 'category' => 'focus_war']);
    }

    /** الإلغاء مرفوض إن لم يغطِّ الرصيد التذاكر المُسترجَعة (15.3). */
    public function test_cancelling_is_refused_when_the_owner_cannot_cover_refunds(): void
    {
        $owner = $this->trainee(tickets: 5);
        $joiner = $this->trainee(tickets: 20);

        $service = app(FocusWarService::class);
        $war = $service->create($owner, $this->challenge('focus_war'), 50, null, true);
        $service->join($joiner, $war);

        // صاحب التحدّي أنفق ما لديه فلم يبقَ ما يغطّي الاسترجاع
        app(WalletGateway::class)
            ->debit($owner, 'tickets', 1, 'إنفاق اختباريّ');

        $this->actingAs($owner)
            ->from(route('challenges.focus.index'))
            ->post(route('challenges.focus.cancel', $war))
            ->assertRedirect(route('challenges.focus.index'))
            ->assertSessionHas('topup_needed');

        $this->assertSame('active', $war->refresh()->status);
    }

    /** الدقائق تُسجَّل بعد انقضاء المدّة حتى لو أُغلقت الشاشة (15.3-ب). */
    public function test_focus_minutes_are_credited_when_the_duration_elapses(): void
    {
        $owner = $this->trainee(tickets: 20);
        $service = app(FocusWarService::class);

        $war = $service->create($owner, $this->challenge('focus_war'), 25, 'أقرأ كتاب', false);

        FocusWarMember::query()->where('focus_war_id', $war->id)->update(['ends_at' => now()->subMinute()]);

        $this->assertSame(25, $service->settleDue($owner));
        $this->assertSame(25, $service->focusMinutes($owner));
        // ولا تُصرَف مرّتين
        $this->assertSame(0, $service->settleDue($owner));
    }

    /**
     * ⭐ الحرب الملغاة لا تسكّ دقائق من العدم (15.3).
     *
     * المنضمّ استرجع تذكرته لأنّه لم يُكمِل وقته، فلا يجوز أن يأخذ **فوقها**
     * المدّة كاملة — «يحتفظون بدقائق التركيز اللي جمّعوها بالفعل» ولا شيء غيرها،
     * وهنا لم يجمّع شيئًا لأنّ الإلغاء وقع لحظة الانضمام.
     */
    public function test_cancelling_does_not_mint_focus_minutes_from_nothing(): void
    {
        $owner = $this->trainee(tickets: 30);
        $joiner = $this->trainee(tickets: 20);

        $service = app(FocusWarService::class);
        $war = $service->create($owner, $this->challenge('focus_war'), 25, null, true);
        $service->join($joiner, $war);

        $before = $this->systemTickets();

        $service->cancel($owner, $war);

        // المدّة الأصليّة انقضت على التقويم — والحرب ملغاة من قبل أن يركّز دقيقة
        $this->travel(26)->minutes();

        $this->assertSame(0, $service->settleDue($joiner), 'الحرب الملغاة منحت دقائق لم تُبذَل');
        $this->assertSame(0, $service->focusMinutes($joiner));
        $this->assertDatabaseHas('focus_war_members', [
            'focus_war_id' => $war->id,
            'user_id' => $joiner->id,
            'minutes_awarded' => 0,
        ]);

        // التذكرة رجعت مرّة واحدة ومجموع النظام ثابت
        $this->assertEqualsWithDelta(20, $this->ticketsOf($joiner), 0.001);
        $this->assertEqualsWithDelta(25, $this->ticketsOf($owner), 0.001); // 30 − 5 + 1 − 1
        $this->assertEqualsWithDelta($before, $this->systemTickets(), 0.001);
    }

    /** الإلغاء يحفظ الدقائق **المجمَّعة فعلًا** حتى لحظته — لا كلّها ولا صفرها (15.3-2). */
    public function test_a_cancelled_war_keeps_only_the_minutes_actually_accumulated(): void
    {
        $owner = $this->trainee(tickets: 30);
        $joiner = $this->trainee(tickets: 20);

        $service = app(FocusWarService::class);
        $war = $service->create($owner, $this->challenge('focus_war'), 50, null, true);
        $service->join($joiner, $war);

        $this->travel(20)->minutes();   // ركّز 20 دقيقة حقيقيّة
        $service->cancel($owner, $war);

        $this->assertSame(20, $service->focusMinutes($joiner), 'الدقائق المحفوظة ليست ما بُذل فعلًا');
        $this->assertDatabaseHas('focus_war_members', [
            'focus_war_id' => $war->id,
            'user_id' => $joiner->id,
            'minutes_awarded' => 20,
        ]);

        // ومرور المدّة الأصليّة بعد الإلغاء لا يضيف دقيقةً واحدة
        $this->travel(31)->minutes();
        $this->assertSame(0, $service->settleDue($joiner));
        $this->assertSame(20, $service->focusMinutes($joiner));
    }

    /** مَن أكمل وقته قبل الإلغاء يأخذ مدّته كاملة — «استفاد كامل» (15.3-1). */
    public function test_a_member_who_finished_before_the_cancellation_keeps_the_full_duration(): void
    {
        $owner = $this->trainee(tickets: 30);
        $joiner = $this->trainee(tickets: 20);

        $service = app(FocusWarService::class);
        $war = $service->create($owner, $this->challenge('focus_war'), 5, null, true);
        $service->join($joiner, $war);

        $this->travel(6)->minutes();    // خلّص مدّته فعلًا
        $service->cancel($owner, $war);

        $this->assertSame(5, $service->focusMinutes($joiner), 'مَن أكمل وقته حُرم دقائقه');
        $this->assertEqualsWithDelta(19, $this->ticketsOf($joiner), 0.001); // لا استرجاع لمن أكمل
    }

    /**
     * الحارس الثاني: **التسوية نفسها** تقرأ حالة التحدّي لا الساعةَ وحدها —
     * حتى لو أُقفل التحدّي من مسارٍ آخر لم يُسوِّ عضويّاته.
     */
    public function test_settlement_reads_the_war_status_not_only_the_clock(): void
    {
        $owner = $this->trainee(tickets: 30);
        $joiner = $this->trainee(tickets: 20);

        $service = app(FocusWarService::class);
        $war = $service->create($owner, $this->challenge('focus_war'), 50, null, true);
        $service->join($joiner, $war);

        $this->travel(10)->minutes();
        // إقفالٌ خارج cancel() — عضويّات لم تُسوَّ بعدُ ومدّتها لم تنقضِ
        $war->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

        $this->assertSame(10, $service->settleDue($joiner), 'التسوية منحت دقائق ما بعد إقفال الحرب');
        $this->assertSame(10, $service->focusMinutes($joiner));
    }

    /** شارة 24 ساعة تركيز تراكميّة تُفتَح تلقائيًّا (15.3). */
    public function test_twenty_four_hours_of_focus_unlocks_the_badge(): void
    {
        $owner = $this->trainee(tickets: 400);
        $service = app(FocusWarService::class);
        $challenge = $this->challenge('focus_war');

        // 1440 دقيقة = 24 ساعة — نصنعها بجلسات 50 دقيقة منتهية
        for ($i = 0; $i < 29; $i++) {
            $war = $service->create($owner, $challenge, 50, null, false);
            FocusWarMember::query()->where('focus_war_id', $war->id)->update(['ends_at' => now()->subMinute()]);
            $war->forceFill(['status' => 'ended'])->save();
        }

        $service->settleDue($owner);

        $this->assertGreaterThanOrEqual(1440, $service->focusMinutes($owner));
        $this->assertDatabaseHas('badge_user', ['user_id' => $owner->id]);
    }

    /** شاشة حرب التركيز تعرض رسالة الأمانة والمدد وأكوام الأفاتار. */
    public function test_focus_screen_shows_the_honesty_message_and_durations(): void
    {
        $owner = $this->trainee(tickets: 20);

        $this->actingAs($owner)
            ->get(route('challenges.focus.index'))
            ->assertOk()
            ->assertSee('هذا التحدي أمانة بينك وبين نفسك', false)
            ->assertSee('50 دقيقة', false)
            ->assertSee('دقائق تركيزي', false);
    }
}
