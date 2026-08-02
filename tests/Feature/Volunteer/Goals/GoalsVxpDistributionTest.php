<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Setting;
use App\Services\Volunteer\Goals\Integrations;
use App\Services\Volunteer\Goals\VxpDistributionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * قيدا توزيع VXP الآليّان (الدستور 23 — 3.9-٥):
 *  (أ) مجموع الأبناء ≤ وعاء المهمّة.
 *  (ب) شريحة الأب المحفوظة ≥ النسبة التي يحدّدها الأدمن.
 * والتجاوز يُرفَض عند الحفظ إلّا بموافقة صريحة على الخصم من الرصيد الشخصيّ.
 */
class GoalsVxpDistributionTest extends GoalsTestCase
{
    private function service(): VxpDistributionService
    {
        return app(VxpDistributionService::class);
    }

    /** القيد (ب): لا يوزّع 100% ويشتغل ببلاش — الشريحة المحفوظة تُفرَض عند الحفظ */
    public function test_parent_reserved_share_is_enforced(): void
    {
        $tree = $this->makeTree($this->makeEntity());
        $owner = $this->makeUser();

        $parent = $this->makeTask($tree['item'], 'in_progress', $owner, 100);
        $childA = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 0, $parent);
        $childB = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 0, $parent);

        // 10% محفوظة ⟵ أقصى ما يُوزَّع 90
        $this->assertSame(90.0, $this->service()->maxDistributable($parent));

        $this->expectException(ValidationException::class);

        $this->service()->distribute($parent, [$childA->id => 50, $childB->id => 50], payer: $owner);
    }

    /** القيد (أ): التجاوز فوق الوعاء نفسه مرفوض بلا موافقة صريحة */
    public function test_sum_above_pool_is_rejected(): void
    {
        $tree = $this->makeTree($this->makeEntity());
        $owner = $this->makeUser();

        $parent = $this->makeTask($tree['item'], 'in_progress', $owner, 100);
        $child = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 0, $parent);

        $check = $this->service()->check($parent, [$child->id => 150], payer: $owner);

        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('وعاء المهمّة', $check['errors'][0]);
    }

    /** التوزيع داخل الحدّين يُحفَظ، والمنصرف من وعاء البند يتحدّث معه */
    public function test_valid_distribution_is_saved(): void
    {
        $tree = $this->makeTree($this->makeEntity());
        $owner = $this->makeUser();

        $parent = $this->makeTask($tree['item'], 'in_progress', $owner, 100);
        $childA = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 0, $parent);
        $childB = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 0, $parent);

        $this->service()->distribute($parent, [$childA->id => 40, $childB->id => 50], payer: $owner);

        $this->assertSame(40.0, (float) $childA->fresh()->vxp_value);
        $this->assertSame(50.0, (float) $childB->fresh()->vxp_value);
        $this->assertSame(190.0, (float) $tree['item']->fresh()->vxp_spent);
    }

    /** الزيادة فوق الوعاء لا تأتي إلّا من الرصيد الشخصيّ بموافقة صريحة */
    public function test_overflow_requires_consent_and_sufficient_personal_balance(): void
    {
        $tree = $this->makeTree($this->makeEntity());
        $owner = $this->makeUser();

        $parent = $this->makeTask($tree['item'], 'in_progress', $owner, 100);
        $child = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 0, $parent);

        // بموافقة لكن بلا رصيد ⟵ يُرفَض كذلك
        $withoutBalance = $this->service()->check($parent, [$child->id => 120], consentPersonal: true, payer: $owner);
        $this->assertFalse($withoutBalance['ok']);

        Integrations::credit($owner, VxpDistributionService::CURRENCY, 500, 'admin', null, 'رصيد اختبار');

        $result = $this->service()->distribute($parent, [$child->id => 120], consentPersonal: true, payer: $owner);

        $this->assertSame(30.0, $result['overflow']); // 120 − 90 (أقصى ما يُوزَّع)
        $this->assertSame(120.0, (float) $child->fresh()->vxp_value);
        $this->assertSame(30.0, (float) $parent->fresh()->personal_vxp_top_up);
        $this->assertSame(470.0, Integrations::balance($owner, VxpDistributionService::CURRENCY));
    }

    /** نسبة الشريحة إعداد لا رقم محروق — تغييرها يغيّر الحدّ فورًا */
    public function test_min_share_percent_comes_from_settings(): void
    {
        $tree = $this->makeTree($this->makeEntity());
        $parent = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 200);

        $this->assertSame(180.0, $this->service()->maxDistributable($parent));

        Setting::create([
            'key' => 'workflow.vxp.parent_min_share_percent',
            'group' => 'workflow',
            'label_ar' => 'أدنى شريحة محفوظة للأب (%)',
            'type' => 'number',
            'default_value' => '25',
            'value' => '25',
        ]);
        Cache::forget('settings');

        $this->assertSame(150.0, $this->service()->maxDistributable($parent));
    }
}
