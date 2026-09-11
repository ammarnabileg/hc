<?php

namespace Tests\Feature\Onboarding;

use App\Models\CelebrationEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;

/**
 * 🎉 شاشة «قبول الحساب» — الحارس على نصّ 2.14-أ حرفيًّا (المستوى الثالث):
 *
 *   «**3 — ذروة (Peak)** | **شاشة احتفال كاملة**: كونفيتي غزير + صوت +
 *   **رسالة تهنئة** + **زرّ مشاركة** | … **قبول الحساب** …»
 *
 * وكان المبنيّ نصفَ بندٍ: `onboarding/partials/celebration.blade.php` يرسم
 * `<x-confetti>` ووسمَ صوتٍ ولا شيء غيرهما — و`$celebration['message']` يصل من
 * `CelebrationService` جاهزًا **ولا يُطبَع حرفًا**، ولا زرّ مشاركة أصلًا. فحدثُ
 * ذروةٍ منصوصٌ بالاسم في الجدول كان يمرّ بلا تهنئةٍ ولا مشاركة.
 *
 * والرسالة ليست نصًّا في القالب: هي **إعدادٌ لكلّ حدث** (2.14-ج «نصّ رسالة
 * التهنئة لكلّ حدث») ينادي صاحبها باسمه (2.17-أ) — فالاختبار يغيّرها من
 * القاعدة ويطلبها على الشاشة، لا يكتفي بوجود أيّ كلام.
 */
class AcceptedCelebrationTest extends OnboardingTestCase
{
    /** مَن يقف عند خطوة «تمّ قبول حسابك» بالضبط (2.5-د-4) */
    private function acceptedUser(): User
    {
        return $this->newcomer([
            'status' => 'active',
            'instructions_agreed_at' => now(),
            'placement_completed_at' => now(),
        ]);
    }

    private function withMessage(string $template): void
    {
        CelebrationEvent::query()->where('key', 'account.approved')->update(['message_ar' => $template]);
        Cache::forget('settings');
    }

    #[Test]
    public function the_acceptance_screen_prints_the_congratulation_message(): void
    {
        $this->withMessage('مبروك يا :name — :label 🎉');

        $user = $this->acceptedUser();

        $html = $this->actingAs($user)->get(route('onboarding.accepted'))->assertOk()->getContent();

        $expected = 'مبروك يا '.$user->shortName(1).' — قبول الحساب 🎉';

        $this->assertStringContainsString($expected, $html,
            'رسالة التهنئة لا تُطبَع على شاشة القبول — و2.14-أ تنصّ عليها في المستوى الثالث.');

        // وفي وسمها الخاصّ لا مطمورةً في أيّ نصّ — فقارئ الشاشة يقرؤها في `role="status"`
        $this->assertStringContainsString('data-onboarding-celebration-message', $html);
        $this->assertStringContainsString('role="status"', $html);
    }

    /** الرسالة **إعدادٌ لكلّ حدث** (2.14-ج) — فتغييرها يغيّر ما يُقرأ على الشاشة */
    #[Test]
    public function the_message_text_follows_the_per_event_setting(): void
    {
        $this->withMessage('أهلًا بيك يا :name في العيلة');

        $user = $this->acceptedUser();

        $this->actingAs($user)->get(route('onboarding.accepted'))
            ->assertOk()
            ->assertSee('أهلًا بيك يا '.$user->shortName(1).' في العيلة', false);
    }

    /** «+ **زرّ مشاركة**» (2.14-أ · 3) — وما يُشارَك رابط الدعوة القائم (7.6 · 21.1) */
    #[Test]
    public function the_acceptance_screen_offers_a_share_control(): void
    {
        $this->withMessage('مبروك يا :name — :label 🎉');

        $user = $this->acceptedUser();

        $html = $this->actingAs($user)->get(route('onboarding.accepted'))->assertOk()->getContent();

        $this->assertStringContainsString('data-onboarding-share=', $html,
            'شاشة الذروة بلا زرّ مشاركة — ونصّ 2.14-أ يذكره صراحةً.');

        // نصّ الزرّ من الإعدادات لا محروقًا (2.13)
        $this->assertStringContainsString(
            (string) setting('celebrations.screen.share_link_action', 'شارك الخبر'),
            $html,
        );

        // والرابط المشارَك هو رابط الدعوة نفسه الذي يقرؤه ويدجت الدعوات — لا رابطٌ ثانٍ
        $link = app(ReferralService::class)->link($user->fresh());

        $this->assertStringContainsString(e($link), $html,
            'زرّ المشاركة لا يحمل رابط الدعوة — وهو المصدر القائم للمشاركة في المنصّة.');

        // وواجهة المشاركة بنمط المنصّة: `navigator.share` أوّلًا والحافظة تراجعًا
        $this->assertStringContainsString('navigator.share', $html);
        $this->assertStringContainsString('navigator.clipboard.writeText', $html);
    }

    /**
     * «حدّ يوميّ لمستوى الذروة» (2.14-ب): ما بعد الحدّ ينزل للمستوى الثاني —
     * و«2 — متوسّط» شكلُه «كونفيتي خفيف + صوت قصير» **بلا زرّ مشاركة**.
     */
    #[Test]
    public function below_peak_the_share_button_does_not_leak_but_the_message_stays(): void
    {
        Setting::query()->where('key', 'celebrations.peak.daily_cap')->update(['value' => '0']);
        Cache::forget('settings');

        $this->withMessage('مبروك يا :name — :label 🎉');

        $user = $this->acceptedUser();

        $html = $this->actingAs($user)->get(route('onboarding.accepted'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-onboarding-share=', $html,
            'زرّ المشاركة تسرّب لما دون الذروة — و2.14-أ تقصره على المستوى الثالث.');

        // والرسالة تبقى: المستويان 1 و2 يحملانها كذلك (Toast / كونفيتي خفيف)
        $this->assertStringContainsString('مبروك يا '.$user->shortName(1), $html);
    }

    /** «مرّة واحدة لكلّ حدث» (2.14-ب) — فلا تهنئة ثانية بإعادة التحميل */
    #[Test]
    public function the_celebration_is_consumed_once_so_a_reload_shows_no_message(): void
    {
        $this->withMessage('مبروك يا :name — :label 🎉');

        $user = $this->acceptedUser();

        $this->actingAs($user)->get(route('onboarding.accepted'))->assertOk()
            ->assertSee('data-onboarding-celebration-message', false);

        $this->actingAs($user->fresh())->get(route('onboarding.accepted'))->assertOk()
            ->assertDontSee('data-onboarding-celebration-message', false);
    }
}
