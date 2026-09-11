<?php

namespace Tests\Feature\Challenges;

use App\Models\FocusWarMember;
use App\Services\Gamification\Wars\FocusWarService;

/**
 * عدّاد حرب التركيز (15.3).
 *
 * النصّ يشترط شيئَين معًا: **«إظهار الدقائق وهي بتكبر»** و**«العدّاد يكمل
 * طوال المدّة المختارة حتى لو قفل الشاشة أو خرج من التبويب»** (القرار ب).
 * والثاني لا يتحقّق بعدّادٍ يعيش في `setInterval`: ذاك يصفَّر مع كلّ Reload
 * ويتوقّف مع نوم الشاشة. فالسلطة الزمنيّة هنا **لحظتان مكتوبتان على الخادم**
 * (`joined_at`/`ends_at`) وكلّ رقمٍ يُعرَض مشتقٌّ منهما.
 *
 * ولذلك تقيس هذه الاختبارات **المصدر** لا الشكل: أنّ اللحظة تُكتب، وأنّ التقدّم
 * يُحسَب منها، وأنّ طلبًا جديدًا (= Reload) يقرأ الرقم نفسه، وأنّ ما يرسله
 * العميل عن الوقت **لا يُقرأ أصلًا**.
 */
class FocusWarTimerTest extends ChallengeTestCase
{
    /** بدء الجلسة يكتب لحظة البدء والنهاية على الخادم — لا على المتصفّح (15.3-ب). */
    public function test_starting_a_session_records_server_side_timestamps(): void
    {
        $owner = $this->trainee(tickets: 20);

        $this->actingAs($owner)->post(route('challenges.focus.store'), [
            'duration_minutes' => 50,
            'intention' => 'أقرأ كتاب',
        ])->assertRedirect(route('challenges.focus.index'));

        $member = FocusWarMember::query()->where('user_id', $owner->id)->firstOrFail();

        $this->assertNotNull($member->joined_at);
        $this->assertNotNull($member->ends_at);
        $this->assertSame(50, (int) $member->joined_at->diffInMinutes($member->ends_at));
    }

    /** الدقائق «بتكبر» — والتقدّم مشتقٌّ من اللحظتين لا من عدٍّ في المتصفّح (15.3). */
    public function test_elapsed_minutes_grow_and_are_computed_from_the_stored_timestamps(): void
    {
        $owner = $this->trainee(tickets: 20);
        $service = app(FocusWarService::class);

        $service->create($owner, $this->challenge('focus_war'), 50, 'أقرأ كتاب', false);

        $this->assertSame(0, $service->liveSessions($owner)->first()['elapsed_minutes']);

        $this->travel(12)->minutes();

        $session = $service->liveSessions($owner)->first();

        $this->assertSame(12, $session['elapsed_minutes']);
        $this->assertSame(38 * 60, $session['remaining_seconds']);
        $this->assertSame(24, $session['percent']);   // 12 / 50
        $this->assertFalse($session['done']);
    }

    /**
     * ⭐ **جوهر القرار (ب):** طلبٌ جديد تمامًا — أي Reload أو فتحٌ بعد قفل
     * الشاشة — يقرأ التقدّم نفسه، لأنّه محسوبٌ من فارق لحظتين لا من عدٍّ
     * متراكم يضيع مع الصفحة.
     */
    public function test_a_fresh_request_after_the_screen_was_closed_reports_the_same_progress(): void
    {
        $owner = $this->trainee(tickets: 20);

        app(FocusWarService::class)->create($owner, $this->challenge('focus_war'), 50, null, false);

        // الشاشة اتقفلت 30 دقيقة… والعدّاد مالوش وجود في المتصفّح أصلًا
        $this->travel(30)->minutes();

        $payload = $this->actingAs($owner)
            ->getJson(route('challenges.focus.status'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $payload['sessions']);
        $this->assertSame(30, $payload['sessions'][0]['elapsed_minutes']);
        $this->assertSame(20 * 60, $payload['sessions'][0]['remaining_seconds']);
        $this->assertSame(60, $payload['sessions'][0]['percent']);

        // وصفحة الشاشة نفسها ترسم الرقم الصحيح من الخادم (2.17-أ) لا من السكربت
        $this->actingAs($owner)
            ->get(route('challenges.focus.index'))
            ->assertOk()
            ->assertSee('data-focus-timer', false)
            ->assertSee('data-focus-elapsed', false)
            ->assertSee('>30<', false);
    }

    /** ⭐ ما يرسله العميل عن الوقت **لا يُقرأ**: الرقم واحدٌ مهما زُوِّر الطلب. */
    public function test_client_supplied_time_is_never_trusted(): void
    {
        $owner = $this->trainee(tickets: 20);

        app(FocusWarService::class)->create($owner, $this->challenge('focus_war'), 50, null, false);

        $this->travel(5)->minutes();

        $forged = $this->actingAs($owner)->getJson(route('challenges.focus.status', [
            'elapsed_seconds' => 3000,
            'elapsed_minutes' => 49,
            'remaining_seconds' => 0,
            'percent' => 100,
            'done' => 1,
        ]))->assertOk()->json('sessions.0');

        $this->assertSame(5, $forged['elapsed_minutes'], 'العدّاد صدّق زمنًا جاءه من المتصفّح');
        $this->assertSame(45 * 60, $forged['remaining_seconds']);
        $this->assertSame(10, $forged['percent']);
        $this->assertFalse($forged['done']);
    }

    /**
     * انقضاء المدّة = **الأثر الذي ينصّ عليه الدستور**: دقائق تركيز تتجمّع
     * (بلا تذاكر — مكافأة غير اقتصاديّة)، والجلسة تخرج من العدّاد الحيّ (15.3).
     */
    public function test_completing_the_duration_credits_focus_minutes_and_ends_the_live_session(): void
    {
        $owner = $this->trainee(tickets: 20);
        $service = app(FocusWarService::class);

        $service->create($owner, $this->challenge('focus_war'), 5, null, false);

        $this->travel(6)->minutes();

        $payload = $this->actingAs($owner)
            ->getJson(route('challenges.focus.status'))
            ->assertOk()
            ->json();

        $this->assertSame([], $payload['sessions']);
        $this->assertSame(5, $payload['focus_minutes']);
        $this->assertSame(5, $service->focusMinutes($owner));

        // ولا تُصرَف مرّتين مهما تكرّر النداء
        $this->actingAs($owner)->getJson(route('challenges.focus.status'))->assertOk();
        $this->assertSame(5, $service->focusMinutes($owner));
    }

    /** التحدّي الملغيّ عدّاده وقف — فلا جلسة حيّة تكمل بعد إقفاله (15.3-2). */
    public function test_a_cancelled_war_leaves_no_live_session(): void
    {
        $owner = $this->trainee(tickets: 30);
        $joiner = $this->trainee(tickets: 20);

        $service = app(FocusWarService::class);
        $war = $service->create($owner, $this->challenge('focus_war'), 50, null, true);
        $service->join($joiner, $war);

        $this->assertCount(1, $service->liveSessions($joiner));

        $this->travel(10)->minutes();
        $service->cancel($owner, $war);

        $this->assertCount(0, $service->liveSessions($joiner));
        $this->assertSame(10, $service->focusMinutes($joiner));
    }

    /** الشاشة بلا جلسة جارية لا ترسم عدّادًا — ولا تُحمِّل سكربته (2.15). */
    public function test_the_screen_has_no_timer_when_nothing_is_running(): void
    {
        $owner = $this->trainee(tickets: 20);

        $this->actingAs($owner)
            ->get(route('challenges.focus.index'))
            ->assertOk()
            ->assertDontSee('data-focus-timer', false);
    }
}
