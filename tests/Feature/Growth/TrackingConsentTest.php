<?php

namespace Tests\Feature\Growth;

use App\Models\Currency;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\TrackingEvent;
use App\Services\Ads\AdEvents;
use App\Services\Ads\Consent;
use Illuminate\Support\Str;

/**
 * ⭐ القاعدة الحاكمة (21.3-د): **لا حدث يُرسَل بلا موافقة صريحة** — من المتصفّح
 *    ومن الخادم معًا. و`ads.tracking.enabled` مفتاح إيقافٍ فعليّ للتتبّع كلّه.
 */
class TrackingConsentTest extends GrowthTestCase
{
    private function enableTracking(): void
    {
        $this->setSetting('ads.tracking.enabled', '1', 'bool');
        $this->setSetting('ads.pixel.meta_id', '123456789');
    }

    public function test_no_event_is_recorded_without_consent(): void
    {
        $this->enableTracking();
        $user = $this->trainee();

        $this->assertNull(app(AdEvents::class)->record('course_page_view', $user));
        $this->assertSame(0, TrackingEvent::query()->where('event', 'course_page_view')->count());
    }

    public function test_rejection_stops_events_effectively(): void
    {
        $this->enableTracking();
        $user = $this->trainee(['tracking_consent' => Consent::REJECTED]);

        $this->assertNull(app(AdEvents::class)->record('course_page_view', $user));
        $this->assertSame(0, TrackingEvent::query()->count());
    }

    public function test_acceptance_records_the_event_with_one_shared_event_id(): void
    {
        $this->enableTracking();
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        $uid = app(AdEvents::class)->record('course_page_view', $user);

        $this->assertNotNull($uid);
        $row = TrackingEvent::query()->where('event', 'course_page_view')->firstOrFail();

        // نفس المعرّف على القناتين — مفتاح إزالة التكرار (21.3-أ)
        $this->assertSame($uid, $row->event_uid);
        $this->assertTrue((bool) $row->sent_server_side);
        $this->assertTrue((bool) $row->sent_browser_side);
    }

    /** «تخصيص» بلا اختيار غرض الإعلان = رفض — الصمت ليس موافقة */
    public function test_custom_consent_without_ads_scope_blocks_events(): void
    {
        $this->enableTracking();
        $user = $this->trainee(['tracking_consent' => Consent::CUSTOM, 'tracking_scopes' => ['analytics']]);

        $this->assertNull(app(AdEvents::class)->record('purchase_completed', $user));

        $user->forceFill(['tracking_scopes' => ['ads']])->saveQuietly();
        $this->assertNotNull(app(AdEvents::class)->record('purchase_completed', $user->fresh()));
    }

    /** ⭐ المفتاح الواحد يوقف التتبّع كلّه لا البانر وحده (21.3-و) */
    public function test_master_switch_off_stops_everything(): void
    {
        $this->setSetting('ads.tracking.enabled', '0', 'bool');
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        $this->assertNull(app(AdEvents::class)->record('wallet_topup', $user));
        $this->assertSame(0, TrackingEvent::query()->count());
    }

    /** تفعيل/إيقاف كلّ حدث على حدة (21.3-و) */
    public function test_a_single_event_can_be_switched_off(): void
    {
        $this->enableTracking();
        $this->setSetting('ads.events.wallet_topup', '0', 'bool');
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        $this->assertNull(app(AdEvents::class)->record('wallet_topup', $user));
        $this->assertNotNull(app(AdEvents::class)->record('course_page_view', $user));
    }

    /** البكسل لا يُحقَن في الصفحة إلّا بعد موافقة — ولا يظهر البانر والتتبّع مطفأ */
    public function test_pixel_script_is_absent_until_consent_is_given(): void
    {
        $this->enableTracking();

        $this->actingAs($this->trainee())->get(route('growth.articles.index'))
            ->assertOk()
            ->assertDontSee('fbevents.js', false);

        $this->actingAs($this->trainee(['tracking_consent' => Consent::ACCEPTED]))
            ->get(route('growth.articles.index'))
            ->assertOk()
            ->assertSee('fbevents.js', false);
    }

    /** ⭐ الحدث يقع **عند لحظته**: فتح صفحة المعاينة يسجّل «فتح صفحة تدريب» (21.3-أ) */
    public function test_page_events_fire_at_their_exact_moment(): void
    {
        $this->enableTracking();
        $course = $this->makeCourse();
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        $this->actingAs($user)->get(route('growth.preview.course', $course->slug))->assertOk();

        $this->assertSame(1, TrackingEvent::query()
            ->where('user_id', $user->id)
            ->where('event', 'course_page_view')
            ->count());
    }

    /** «بدأ أوّل درس» مرّة واحدة لكلّ مستخدم مهما تكرّرت الزيارة */
    public function test_once_events_do_not_repeat(): void
    {
        $this->enableTracking();
        $course = $this->makeCourse();
        $lesson = Lesson::query()->orderBy('sort_order')->firstOrFail();
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)
                ->get(route('growth.preview.lesson', ['slug' => $course->slug, 'lesson' => $lesson->id]))
                ->assertOk();
        }

        $this->assertSame(1, TrackingEvent::query()
            ->where('user_id', $user->id)
            ->where('event', 'first_lesson_started')
            ->count());
    }

    /** الشراء يقع في الخادم أو في ويب-هوك — فيُصالَح ولا يضيع (21.3-أ) */
    public function test_purchase_is_reconciled_from_the_database_not_from_a_page(): void
    {
        $this->enableTracking();
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        Order::create([
            'number' => 'ORD-'.Str::upper(Str::random(6)),
            'user_id' => $user->id,
            'currency_id' => Currency::query()->firstOrFail()->id,
            'total' => 120,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($user)->get(route('growth.articles.index'))->assertOk();
        $this->actingAs($user)->get(route('growth.articles.index'))->assertOk();

        $this->assertSame(1, TrackingEvent::query()
            ->where('user_id', $user->id)
            ->where('event', 'purchase_completed')
            ->count());
    }

    public function test_consent_endpoint_persists_choice_and_scopes(): void
    {
        $this->enableTracking();
        $user = $this->trainee();

        $this->actingAs($user)
            ->from(route('growth.articles.index'))
            ->post(route('consent.tracking'), ['choice' => 'custom', 'scopes' => ['ads']])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame(Consent::CUSTOM, $user->tracking_consent);
        $this->assertSame(['ads'], $user->tracking_scopes);

        // «تخصيص» بلا اختيار يُخزَّن رفضًا صريحًا
        $this->actingAs($user)
            ->from(route('growth.articles.index'))
            ->post(route('consent.tracking'), ['choice' => 'custom']);

        $this->assertSame(Consent::REJECTED, $user->fresh()->tracking_consent);
    }
}
