<?php

namespace Tests\Feature\Growth;

use App\Services\Ads\AdEvents;
use App\Services\Ads\Consent;

/**
 * ⭐ زرّ التأجيل («لاحقًا») على بانر الموافقة (21.1-د · 21.3-د).
 *
 * النصّ الحاكم (21.1-د): «**بلا Dark Patterns:** لا عدّادات وهميّة ولا ندرة
 * مزيّفة ولا إزعاج» — وبانرٌ يحاصر الزائر بلا وسيلة تأجيلٍ **إزعاجٌ في ذاته**.
 * والحاكم الثاني (2.9): **الصمت ليس موافقة** — فالتأجيل **ليس قرارًا**:
 *
 *  - لا يُكتَب `accepted` ولا `rejected` — لا على المستخدم ولا في كوكي القرار.
 *  - التتبّع يبقى **موقوفًا** طوال فترة التأجيل (كأنّه لم يُقرَّر بعد).
 *  - البانر **يعود من تلقاء نفسه** بعد انقضاء مدّة التأجيل (إعدادٌ — 2.13).
 *
 * ⛔ **ولا يُمَسّ الصامد:** `Consent::adopt()` وحارس «الرفض يوقف التتبّع فعليًّا»
 *    قراءةً لا تعديلًا — هذا الملفّ يضيف مسارًا مستقلًّا (`consent.snooze`) ولا
 *    يغيّر سطرًا واحدًا في `App\Services\Ads\Consent`.
 */
class ConsentSnoozeTest extends GrowthTestCase
{
    private function enableTracking(): void
    {
        $this->setSetting('ads.tracking.enabled', '1', 'bool');
        $this->setSetting('ads.pixel.meta_id', '123456789');
    }

    public function test_banner_shows_by_default_when_undecided(): void
    {
        $this->enableTracking();

        $this->actingAs($this->trainee())
            ->get(route('growth.articles.index'))
            ->assertOk()
            ->assertSee('data-consent-banner', false);
    }

    /** ⭐ التأجيل يُخفي البانر فورًا — بلا كتابة أيّ قرار */
    public function test_snoozing_hides_the_banner_without_recording_a_decision(): void
    {
        $this->enableTracking();
        $user = $this->trainee();

        $this->actingAs($user)
            ->from(route('growth.articles.index'))
            ->post(route('consent.snooze'))
            ->assertRedirect()
            ->assertCookie('tracking_consent_snoozed_until');

        // القاعدة: لا accepted ولا rejected — العمود يبقى NULL
        $this->assertNull($user->fresh()->tracking_consent);

        // ونفس الأمر على الكوكي — يقرأها `Consent::choice()` ولا تجد فيها قرارًا
        $this->withCookies(['tracking_consent_snoozed_until' => now()->addDays(7)->toIso8601String()])
            ->actingAs($user)
            ->get(route('growth.articles.index'))
            ->assertOk()
            ->assertDontSee('data-consent-banner', false);
    }

    /** ⭐ والتتبّع موقوف طوال التأجيل — غياب القرار يبقى رفضًا عمليًّا (2.9) */
    public function test_tracking_stays_off_while_snoozed(): void
    {
        $this->enableTracking();
        $user = $this->trainee();

        $this->assertNull(app(AdEvents::class)->record('course_page_view', $user));
    }

    /** ⭐ زيارة بعد انقضاء مدّة التأجيل — البانر يعود من تلقاء نفسه */
    public function test_banner_returns_after_the_snooze_period_expires(): void
    {
        $this->enableTracking();
        $user = $this->trainee();

        $this->withCookies(['tracking_consent_snoozed_until' => now()->subMinute()->toIso8601String()])
            ->actingAs($user)
            ->get(route('growth.articles.index'))
            ->assertOk()
            ->assertSee('data-consent-banner', false);
    }

    /** ⭐ مدّة التأجيل من إعداد لا رقمًا محروقًا (2.13) */
    public function test_snooze_duration_is_configurable(): void
    {
        $this->enableTracking();
        $this->setSetting('ads.consent.snooze_days', '1', 'number');

        $response = $this->actingAs($this->trainee())
            ->from(route('growth.articles.index'))
            ->post(route('consent.snooze'));

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'tracking_consent_snoozed_until');

        $this->assertNotNull($cookie);
        // يوم واحد ± دقيقة تسامحًا لزمن تنفيذ الاختبار
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $cookie->getExpiresTime(), 60);
    }

    /** ⛔ الصامد: الرفض الصريح يبقى يوقف التتبّع فعليًّا — لا علاقة له بالتأجيل */
    public function test_explicit_rejection_still_stops_tracking_effectively(): void
    {
        $this->enableTracking();
        $user = $this->trainee(['tracking_consent' => Consent::REJECTED]);

        $this->assertNull(app(AdEvents::class)->record('course_page_view', $user));

        $this->actingAs($user)
            ->get(route('growth.articles.index'))
            ->assertOk()
            ->assertDontSee('data-consent-banner', false);
    }
}
