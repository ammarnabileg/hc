<?php

namespace Tests\Feature\Home;

use App\Models\AppNotification;
use App\Models\CelebrationConsumption;
use App\Models\Referral;
use App\Models\Setting;
use App\Services\Engagement\AmbassadorService;

/** لقب السفير (7.6.1 · 2.9-8 · 21.1-ج · 2.14) */
class AmbassadorTest extends HomeTestCase
{
    private function service(): AmbassadorService
    {
        return app(AmbassadorService::class);
    }

    /** لا لقب قبل العتبة — ولا مجاملة بأرقام غير حقيقيّة (2.9-7) */
    public function test_no_title_before_the_threshold(): void
    {
        $user = $this->user();
        $this->giveActivatedInvites($user, 4);

        $this->service()->sync($user);

        $this->assertNull($user->fresh()->ambassador_title);
        $this->assertSame(4, (int) $user->fresh()->ambassador_invites);
    }

    /** اللقب يُمنَح آليًّا عند بلوغ العتبة، ومعه إشعار واحتفال (2.14) */
    public function test_title_is_granted_automatically_with_a_notification_and_a_celebration(): void
    {
        $user = $this->user();
        $this->giveActivatedInvites($user, 5);

        $celebration = $this->service()->sync($user);

        $this->assertSame('سفير برونزيّ', $user->fresh()->ambassador_title);
        $this->assertSame('bronze', $user->fresh()->ambassador_tier);
        $this->assertNotNull($user->fresh()->ambassador_granted_at);

        // احتفال بمستواه — متوسّط لا ذروة (2.14-أ)
        $this->assertNotNull($celebration);
        $this->assertSame(2, $celebration['tier']);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'category' => 'ambassador',
        ]);
    }

    /** الاحتفال مرّة واحدة لكلّ لقب — لا يتكرّر بإعادة التحميل (2.14-ب) */
    public function test_celebration_fires_once_per_title(): void
    {
        $user = $this->user();
        $this->giveActivatedInvites($user, 5);

        $this->assertNotNull($this->service()->sync($user));
        $this->assertNull($this->service()->sync($user));

        $this->assertSame(1, CelebrationConsumption::where('user_id', $user->id)->count());
        $this->assertSame(1, AppNotification::where('user_id', $user->id)->where('category', 'ambassador')->count());
    }

    /** الترقية للقب أعلى تُمنَح باحتفال جديد مستقلّ */
    public function test_reaching_a_higher_tier_grants_the_next_title(): void
    {
        $user = $this->user();
        $this->giveActivatedInvites($user, 5);
        $this->service()->sync($user);

        $this->giveActivatedInvites($user, 10);
        $celebration = $this->service()->sync($user);

        $this->assertSame('سفير فضّيّ', $user->fresh()->ambassador_title);
        $this->assertNotNull($celebration);
        $this->assertSame(2, CelebrationConsumption::where('user_id', $user->id)->count());
    }

    /** الدعوة غير المفعَّلة لا تُحسَب — العدّاد صادق (2.9-7) */
    public function test_pending_invites_are_not_counted(): void
    {
        $user = $this->user();
        $this->giveActivatedInvites($user, 4);

        $pending = $this->user('trainee', 'مدعوّ لم يفعّل', 'pending');
        Referral::create([
            'referrer_id' => $user->id, 'referred_id' => $pending->id,
            'code' => $user->code, 'commission_percent' => 7,
        ]);

        $this->service()->sync($user);

        $this->assertSame(4, (int) $user->fresh()->ambassador_invites);
        $this->assertNull($user->fresh()->ambassador_title);
    }

    /** العتبات من setting() لا من الكود — يغيّرها الأدمن فيتغيّر السلوك (2.13) */
    public function test_thresholds_come_from_settings(): void
    {
        Setting::where('key', 'ambassadors.tiers')->update(['value' => json_encode([
            ['key' => 'starter', 'label' => 'سفير مبتدئ', 'threshold' => 2],
        ], JSON_UNESCAPED_UNICODE)]);
        cache()->forget('settings');

        $user = $this->user();
        $this->giveActivatedInvites($user, 2);

        $this->service()->sync($user);

        $this->assertSame('سفير مبتدئ', $user->fresh()->ambassador_title);
    }

    /** اللقب يظهر في اللوحة العامّة وعلى بطاقة العضو */
    public function test_title_appears_on_the_public_leaderboard_and_member_card(): void
    {
        $user = $this->user('trainee', 'هدى سامي');
        $this->giveActivatedInvites($user, 5);
        $this->service()->sync($user);

        $this->get(route('ambassadors.index'))
            ->assertOk()
            ->assertSee('سفير برونزيّ')
            ->assertSee('هدى سامي')
            ->assertSee('دعوة مفعّلة');
    }

    /** اللقب يظهر في الصفحة الرئيسيّة العامّة كذلك (21.1-ج) */
    public function test_ambassadors_strip_shows_on_the_landing_page(): void
    {
        $user = $this->user('trainee', 'مروان فؤاد');
        $this->giveActivatedInvites($user, 5);
        $this->service()->sync($user);

        $this->get('/')->assertOk()->assertSee('سفراء المنصّة')->assertSee('سفير برونزيّ');
    }

    /** إقفال اللوحة من الإعدادات يقفلها فعلًا (2.13-أ) */
    public function test_leaderboard_can_be_closed_from_settings(): void
    {
        $this->forceSetting('ambassadors.leaderboard.public', '0');

        $this->get(route('ambassadors.index'))->assertNotFound();
    }

    /** اللوحة تزامن الألقاب عند فتحها فلا يتأخّر لقبٌ استحقّه صاحبه */
    public function test_opening_the_board_syncs_pending_titles(): void
    {
        $user = $this->user('trainee', 'ياسمين طارق');
        $this->giveActivatedInvites($user, 5);

        $this->get(route('ambassadors.index'))->assertOk();

        $this->assertSame('سفير برونزيّ', $user->fresh()->ambassador_title);
    }

    /** «باقي القليل» بصدق: كم دعوة على اللقب التالي (2.9-3) */
    public function test_next_tier_shows_the_honest_remaining_count(): void
    {
        $user = $this->user();
        $this->giveActivatedInvites($user, 5);

        $this->actingAs($user)->get(route('ambassadors.index'))
            ->assertOk()
            ->assertSee('باقي 10 دعوة مفعّلة على لقب «سفير فضّيّ».');
    }
}
