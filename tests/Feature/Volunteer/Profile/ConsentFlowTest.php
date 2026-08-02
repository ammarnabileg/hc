<?php

namespace Tests\Feature\Volunteer\Profile;

use App\Models\ConsentRequest;
use App\Models\UserPrivacySetting;
use App\Services\Volunteer\Profile\ConsentFlow;

/**
 * الفجوة 2: مسار موافقة إظهار التواصل كاملًا (13.4-م-2) —
 * طلب · إشعار · **موافقة تكتب `granted` فعلًا** · **رفض صامت** ·
 * **تبريد يبدأ بعد الانتهاء أو الرفض** · واستثناء إعداد الخصوصيّة.
 */
class ConsentFlowTest extends ProfileTestCase
{
    public function test_request_notifies_the_owner_and_carries_the_optional_reason(): void
    {
        $requester = $this->actorWithRole('VOL-C1', 'coordinator');
        $owner = $this->userByCode('VOL-C4');

        $this->actingAs($requester)
            ->post(route('volunteer.profile.consent.request', ['code' => $owner->code]), [
                'field' => 'phone',
                'reason' => 'محتاج أنسّق معاه تسليم السكربت.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('consent_requests', [
            'requester_id' => $requester->id,
            'owner_id' => $owner->id,
            'field' => 'phone',
            'status' => 'pending',
            'reason' => 'محتاج أنسّق معاه تسليم السكربت.',
        ]);

        // ⭐ الإشعار لصاحب البروفايل — بلا إشعار كان الطلب يبقى pending أبدًا
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $owner->id,
            'category' => 'consent',
        ]);
    }

    /** الطلب على **البريد أيضًا** لا على الهاتف فقط */
    public function test_email_can_be_requested_too(): void
    {
        $requester = $this->actorWithRole('VOL-C1', 'coordinator');
        $owner = $this->userByCode('VOL-C4');

        $this->actingAs($requester)
            ->post(route('volunteer.profile.consent.request', ['code' => $owner->code]), ['field' => 'email'])
            ->assertRedirect();

        $this->assertDatabaseHas('consent_requests', [
            'owner_id' => $owner->id,
            'field' => 'email',
            'status' => 'pending',
        ]);
    }

    /** ⭐ الموافقة تكتب `granted` فعلًا وتفتح الحقل لمدّة محدّدة */
    public function test_approval_actually_writes_granted_and_opens_the_field(): void
    {
        $requester = $this->actorWithRole('VOL-C1', 'coordinator');
        $owner = $this->actorWithRole('VOL-C4', 'coordinator');

        $consent = app(ConsentFlow::class)->request($requester, $owner, 'phone', 'تنسيق مهمّة.');

        $this->actingAs($owner)
            ->post(route('volunteer.profile.consent.approve', $consent))
            ->assertRedirect();

        $consent->refresh();

        $this->assertSame('granted', $consent->status);
        $this->assertNotNull($consent->granted_at);
        $this->assertNotNull($consent->consent_expires_at);
        $this->assertTrue($consent->consent_expires_at->isFuture());

        // والحقل بقى ظاهرًا فعلًا للطالب
        $this->assertTrue(app(ConsentFlow::class)->hasLiveGrant($requester, $owner, 'phone'));

        // والإشعار اتشال من صندوق صاحب البروفايل بلا أثر
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $owner->id,
            'reference_id' => $consent->id,
            'reference_type' => $consent->getMorphClass(),
        ]);
    }

    /** الرفض **صامت تمامًا**: بلا إشعار للطالب وبلا كلمة «رفض» */
    public function test_denial_is_silent_for_the_requester(): void
    {
        $requester = $this->actorWithRole('VOL-C1', 'coordinator');
        $owner = $this->actorWithRole('VOL-C4', 'coordinator');

        $consent = app(ConsentFlow::class)->request($requester, $owner, 'phone');

        $this->actingAs($owner)
            ->post(route('volunteer.profile.consent.deny', $consent))
            ->assertRedirect();

        $this->assertSame('denied', $consent->refresh()->status);

        // ولا إشعار واحد وصل للطالب
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $requester->id, 'category' => 'consent']);

        // وما يراه الطالب نصٌّ محايد لا كلمة رفض
        $this->assertSame('غير متاح / انتهت المدّة', ConsentFlow::neutralLabel());
    }

    /** ⭐ التبريد يبدأ **بعد** الرفض لا لحظة الإنشاء */
    public function test_cooldown_starts_after_the_denial_not_at_creation(): void
    {
        $requester = $this->actorWithRole('VOL-C1', 'coordinator');
        $owner = $this->actorWithRole('VOL-C4', 'coordinator');

        $consent = app(ConsentFlow::class)->request($requester, $owner, 'phone');

        $this->assertNull($consent->cooldown_until, 'التبريد ما يتضبطش وقت الإنشاء');

        app(ConsentFlow::class)->deny($consent, $owner);

        $this->assertNotNull($consent->refresh()->cooldown_until);
        $this->assertTrue($consent->cooldown_until->isFuture());
    }

    /** الانتهاء **في صمت**: منتهٍ لا مرفوض، والإشعار يُشال بلا أثر، والتبريد يبدأ منه */
    public function test_expiry_is_silent_and_starts_the_cooldown(): void
    {
        $requester = $this->actorWithRole('VOL-C1', 'coordinator');
        $owner = $this->userByCode('VOL-C4');

        $consent = app(ConsentFlow::class)->request($requester, $owner, 'phone');
        $consent->forceFill(['request_expires_at' => now()->subMinute()])->save();

        app(ConsentFlow::class)->sweepExpired($owner);
        $consent->refresh();

        $this->assertSame('expired', $consent->status);
        $this->assertNotNull($consent->cooldown_until);
        $this->assertNull($consent->responded_at, 'الانتهاء ليس ردًّا');

        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $owner->id,
            'reference_id' => $consent->id,
            'reference_type' => $consent->getMorphClass(),
        ]);

        // وتبريد سارٍ ⇒ لا طلب جديد قبل انقضائه
        $this->assertTrue(app(ConsentFlow::class)->isBlocked($requester, $owner, 'phone'));
    }

    /** ⭐ استثناء إعداد الخصوصيّة: الحقل المفتوح «لكلّ المتطوّعين» يُرى بلا طلب */
    public function test_privacy_setting_opens_the_field_without_any_request(): void
    {
        $peer = $this->actorWithRole('VOL-C1', 'coordinator');
        $owner = $this->userByCode('VOL-C4');

        // مقفول على المشرفين ⇒ مقنّع للزميل
        UserPrivacySetting::updateOrCreate(
            ['user_id' => $owner->id, 'field' => 'phone'],
            ['visibility' => 'supervisors'],
        );

        $this->actingAs($peer)->get('/u/'.$owner->code.'?tab=volunteer_contact')
            ->assertOk()
            ->assertSee('اطلب إظهار');

        // فتحه صاحبه لكلّ المتطوّعين ⇒ يظهر مباشرةً بلا طلب
        UserPrivacySetting::updateOrCreate(
            ['user_id' => $owner->id, 'field' => 'phone'],
            ['visibility' => 'all_volunteers'],
        );

        $this->actingAs($peer)->get('/u/'.$owner->code.'?tab=volunteer_contact')
            ->assertOk()
            ->assertSee($owner->phone);
    }

    /** الأبلاين يرى البيانات بلا موافقة — ولا يظهر في «مَن يرى بياناتي» */
    public function test_upline_sees_contact_without_consent_and_is_not_listed(): void
    {
        $upline = $this->actorWithRole('VOL-TL1', 'team_leader');
        $owner = $this->userByCode('VOL-C1');

        $this->actingAs($upline)->get('/u/'.$owner->code.'?tab=volunteer_contact')
            ->assertOk()
            ->assertSee($owner->phone);

        // ولا صفّ موافقة اتسجّل له أصلًا — حقّه نظاميّ لا موافقة تُسحَب
        $this->assertDatabaseMissing('consent_requests', [
            'requester_id' => $upline->id,
            'owner_id' => $owner->id,
        ]);
    }

    /** جهة الطوارئ **ظاهرة دائمًا للأبلاينز** */
    public function test_emergency_contact_is_always_visible_to_the_upline(): void
    {
        $upline = $this->actorWithRole('VOL-TL1', 'team_leader');
        $peer = $this->actorWithRole('VOL-C3', 'coordinator');

        $this->actingAs($upline)->get('/u/VOL-C1?tab=volunteer_contact')
            ->assertOk()
            ->assertSee('خالد عبد العزيز');

        $this->actingAs($peer)->get('/u/VOL-C1?tab=volunteer_contact')
            ->assertOk()
            ->assertDontSee('خالد عبد العزيز');
    }

    /** سجلّ الطلبات ومؤشّرات الثقة في شاشة الإدارة المركزيّة (13.4-ك) */
    public function test_trust_metrics_screen_lists_requests_and_rates(): void
    {
        $admin = $this->actorWithRole('VOL-DIR', 'super_admin');

        $this->actingAs($admin)->get(route('volunteer.profile.consent.insights'))
            ->assertOk()
            ->assertSee('معدّل القبول')
            ->assertSee('متوسّط زمن الردّ')
            ->assertSee('المرفوضة');

        $metrics = app(ConsentFlow::class)->trustMetrics(90);

        $this->assertGreaterThan(0, $metrics['total']);
        $this->assertNotNull($metrics['acceptance_rate']);
    }

    /** الحارس: لا أحد يحسم طلبًا ليس له */
    public function test_only_the_owner_can_answer_a_request(): void
    {
        $requester = $this->actorWithRole('VOL-C1', 'coordinator');
        $owner = $this->userByCode('VOL-C4');
        $stranger = $this->actorWithRole('VOL-C3', 'coordinator');

        $consent = app(ConsentFlow::class)->request($requester, $owner, 'phone');

        $this->actingAs($stranger)
            ->post(route('volunteer.profile.consent.approve', $consent))
            ->assertRedirect();

        $this->assertSame('pending', ConsentRequest::find($consent->id)->status);
    }
}
