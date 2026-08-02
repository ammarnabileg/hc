<?php

namespace Tests\Feature\Growth;

use App\Http\Controllers\Onboarding\OnboardingController;
use App\Models\User;
use App\Services\Referral\ReferralService;

/**
 * نجاة الدعوة (7.6 · 7.6.2).
 *
 * 7.6 يشترط حفظ الداعي في **السيشن**. وكانت البوّابة تحفظ الوجهة وحدها وبشرط أن
 * يكون نوعها مدعومًا، ثمّ تمرّر الكود في الـQuery — فأيّ تشتّت (تبويب جديد ·
 * رجوع للخلف · تحقّق OTP في نافذة أخرى · فتح `/register` مباشرةً) يُسقِط الدعوة،
 * فيخسر **الطرفان** التذكرة، ويخسر الداعي **عمولة مدى الحياة**.
 */
class ReferralSessionTest extends GrowthTestCase
{
    private function referrer(): User
    {
        return $this->trainee(['name' => 'الداعي عمرو', 'code' => 'REFCODE1']);
    }

    public function test_the_invitation_survives_going_to_register_directly(): void
    {
        $referrer = $this->referrer();

        $this->get(route('referral.join', ['ref' => $referrer->code]))
            ->assertRedirect(route('register'));

        // زيارةٌ مستقلّة تمامًا: بلا كودٍ في الرابط وبلا رِفِرَر — كما لو فتح تبويبًا جديدًا
        $this->get(route('register'))
            ->assertOk()
            ->assertSee($referrer->code, false);

        $this->assertSame($referrer->code, session(OnboardingController::SESSION_CODE));
    }

    /** ومن دخل برابط دعوة لا تُسأل عنه شاشة «هل دعاك أحد؟» مرّةً أخرى (2.5-أ) */
    public function test_a_guest_who_came_by_an_invite_link_skips_the_referral_question(): void
    {
        $referrer = $this->referrer();

        $this->get(route('referral.join', ['ref' => $referrer->code]));

        $this->assertTrue((bool) session(OnboardingController::SESSION_ANSWERED));
        $this->get(route('register'))->assertOk();
    }

    /** الصيغة القديمة `?offer=` تبقى مقبولةً — فلا تموت روابط بين يدي الناس (7.6) */
    public function test_the_legacy_offer_parameter_is_still_accepted(): void
    {
        $referrer = $this->referrer();

        $this->get(route('referral.join', ['offer' => $referrer->code]))
            ->assertRedirect(route('register'));

        $this->assertSame($referrer->code, session(OnboardingController::SESSION_CODE));
    }

    /**
     * ⭐ بوّابة الرابط العميق تحفظ الداعي **حتى لو كانت الوجهة غير مدعومة** —
     * فالوجهة تفصيل، أمّا الداعي فهو الأصل.
     */
    public function test_the_deep_link_gate_keeps_the_referrer_even_when_the_destination_is_unknown(): void
    {
        $referrer = $this->referrer();

        $this->get(route('referral.invite', ['code' => $referrer->code]).'?type=nothing&id=0')
            ->assertRedirect();

        $this->assertSame($referrer->code, session(OnboardingController::SESSION_CODE));

        $this->get(route('register'))->assertOk()->assertSee($referrer->code, false);
    }

    /** أوّل مَن دعا يفوز: رابطٌ لاحق لا يسرق الدعوة من صاحبها (7.6) */
    public function test_a_later_link_does_not_steal_the_first_referrer(): void
    {
        $first = $this->referrer();
        $second = $this->trainee(['name' => 'الداعية سلمى', 'code' => 'REFCODE2']);

        $this->get(route('referral.join', ['ref' => $first->code]));
        $this->get(route('referral.join', ['ref' => $second->code]));

        $this->assertSame($first->code, session(OnboardingController::SESSION_CODE));
    }

    /** ورابط الدعوة المولَّد اليوم على الصيغة المعتمَدة `?ref=CODE` (7.6.2) */
    public function test_the_generated_link_uses_the_approved_ref_format(): void
    {
        $referrer = $this->referrer();
        $link = app(ReferralService::class)->link($referrer);

        $this->assertStringContainsString('ref='.$referrer->code, $link);
        $this->assertStringNotContainsString('offer='.$referrer->id, $link);
    }
}
