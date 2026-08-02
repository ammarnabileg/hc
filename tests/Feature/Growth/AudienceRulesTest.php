<?php

namespace Tests\Feature\Growth;

use App\Models\AdAudience;
use App\Models\Currency;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Order;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Ads\AudienceResolver;
use App\Services\Ads\Consent;
use Illuminate\Support\Str;

/**
 * ⭐ شرط كلّ شريحة يرجّع الجمهور الصحيح (21.3-ب) — وكان المتحكّم يرجّع
 *    **كلّ المستخدمين النشطين** لكلّ الشروط عدا `best_users`.
 */
class AudienceRulesTest extends GrowthTestCase
{
    private function audience(string $rule, int $ttlDays = 30): AdAudience
    {
        return AdAudience::create([
            'name' => 'شريحة '.$rule,
            'kind' => $rule === 'best_users' ? 'lookalike_source' : 'retargeting',
            'rule' => ['key' => $rule],
            'ttl_days' => $ttlDays,
            'refresh_hours' => 24,
            'is_active' => true,
        ]);
    }

    private function consented(string $name = 'موافق'): User
    {
        return $this->trainee(['name' => $name, 'tracking_consent' => Consent::ACCEPTED]);
    }

    private function currency(): Currency
    {
        return Currency::query()->firstOrFail();
    }

    /** الرفض يُخرِج المستخدم من كلّ شريحة فعليًّا (21.3-د) */
    public function test_users_without_ads_consent_are_never_in_any_audience(): void
    {
        $rejected = $this->trainee(['tracking_consent' => Consent::REJECTED]);
        $silent = $this->trainee();

        $ids = app(AudienceResolver::class)->resolve($this->audience('best_users'))->pluck('id');

        $this->assertNotContains($rejected->id, $ids);
        $this->assertNotContains($silent->id, $ids);
    }

    public function test_viewed_course_but_never_enrolled(): void
    {
        $matching = $this->consented('شاف وما سجّلش');
        $enrolled = $this->consented('سجّل');

        foreach ([$matching, $enrolled] as $user) {
            TrackingEvent::create([
                'user_id' => $user->id,
                'event' => 'course_page_view',
                'event_uid' => (string) Str::uuid(),
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ]);
        }

        Enrollment::create([
            'user_id' => $enrolled->id,
            'course_id' => $this->makeCourse()->id,
        ]);

        $ids = app(AudienceResolver::class)->resolve($this->audience('viewed_course_not_registered'))->pluck('id');

        $this->assertContains($matching->id, $ids);
        $this->assertNotContains($enrolled->id, $ids);
    }

    public function test_registered_but_no_first_lesson(): void
    {
        $course = $this->makeCourse();
        $idle = $this->consented('ما بدأش');
        $started = $this->consented('بدأ');

        foreach ([$idle, $started] as $user) {
            Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id]);
        }

        LessonCompletion::create([
            'user_id' => $started->id,
            'lesson_id' => $course->id ? Lesson::query()->firstOrFail()->id : 0,
            'completed_at' => now(),
        ]);

        $ids = app(AudienceResolver::class)->resolve($this->audience('registered_no_first_lesson'))->pluck('id');

        $this->assertContains($idle->id, $ids);
        $this->assertNotContains($started->id, $ids);
    }

    public function test_opened_checkout_but_never_paid(): void
    {
        $abandoned = $this->consented('سابها');
        $bought = $this->consented('اشترى');

        foreach ([$abandoned, $bought] as $user) {
            TrackingEvent::create([
                'user_id' => $user->id,
                'event' => 'checkout_opened',
                'event_uid' => (string) Str::uuid(),
            ]);
        }

        Order::create([
            'number' => 'ORD-'.Str::upper(Str::random(6)),
            'user_id' => $bought->id,
            'currency_id' => $this->currency()->id,
            'total' => 100,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $ids = app(AudienceResolver::class)->resolve($this->audience('checkout_not_completed'))->pluck('id');

        $this->assertContains($abandoned->id, $ids);
        $this->assertNotContains($bought->id, $ids);
    }

    public function test_completed_a_course_but_did_not_buy_the_next(): void
    {
        $course = $this->makeCourse();
        $stalled = $this->consented('خلّص وما اشتراش');
        $continued = $this->consented('خلّص واشترى');

        foreach ([$stalled, $continued] as $user) {
            Enrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => 'completed',
            ]);
        }

        Order::create([
            'number' => 'ORD-'.Str::upper(Str::random(6)),
            'user_id' => $continued->id,
            'currency_id' => $this->currency()->id,
            'total' => 50,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $ids = app(AudienceResolver::class)->resolve($this->audience('completed_no_next_purchase'))->pluck('id');

        $this->assertContains($stalled->id, $ids);
        $this->assertNotContains($continued->id, $ids);
    }

    /** ⭐ قاعدة الجودة: المكمّلون والمشترون لا كلّ المسجّلين (21.3-ج) */
    public function test_best_users_requires_completion_purchase_and_return(): void
    {
        $course = $this->makeCourse();

        $best = $this->consented('الأفضل');
        $best->forceFill(['activated_at' => now()->subDays(30), 'last_seen_at' => now()])->saveQuietly();

        $onlyRegistered = $this->consented('مجرّد مسجّل');

        Enrollment::create(['user_id' => $best->id, 'course_id' => $course->id, 'status' => 'completed']);
        Order::create([
            'number' => 'ORD-'.Str::upper(Str::random(6)),
            'user_id' => $best->id,
            'currency_id' => $this->currency()->id,
            'total' => 30,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $ids = app(AudienceResolver::class)->resolve($this->audience('best_users'))->pluck('id');

        $this->assertContains($best->id, $ids);
        $this->assertNotContains($onlyRegistered->id, $ids);
    }

    public function test_unknown_rule_returns_nobody(): void
    {
        $this->consented();

        $this->assertTrue(app(AudienceResolver::class)->resolve($this->audience('made_up_rule'))->isEmpty());
    }
}
