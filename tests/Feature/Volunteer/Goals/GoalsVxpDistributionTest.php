<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Currency;
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

    /** النسبة إعداد لا رقم محروق (2.13) — والأدمن وحده يحرّكها */
    private function setMinSharePercent(float $percent): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'workflow.vxp.parent_min_share_percent'],
            [
                'group' => 'workflow',
                'label_ar' => 'أدنى شريحة محفوظة للأب (%)',
                'type' => 'number',
                'default_value' => '10',
                'value' => (string) $percent,
            ],
        );

        Cache::forget('settings');
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

    /**
     * الزيادة فوق الوعاء لا تأتي إلّا من الرصيد الشخصيّ بموافقة صريحة —
     * **والزيادة تُقاس على الوعاء (100) لا على السقف (90)**؛ فالطريق تنفتح حين
     * تسمح الأرضيّة (الأدمن ضبطها صفرًا)، ولا تنفتح أبدًا بكسرها.
     */
    public function test_overflow_requires_consent_and_sufficient_personal_balance(): void
    {
        $this->setMinSharePercent(0);

        $tree = $this->makeTree($this->makeEntity());
        $owner = $this->makeUser();

        $parent = $this->makeTask($tree['item'], 'in_progress', $owner, 100);
        $child = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 0, $parent);

        // بموافقة لكن بلا رصيد ⟵ يُرفَض كذلك
        $withoutBalance = $this->service()->check($parent, [$child->id => 120], consentPersonal: true, payer: $owner);
        $this->assertFalse($withoutBalance['ok']);

        Integrations::credit($owner, VxpDistributionService::CURRENCY, 500, 'admin', null, 'رصيد اختبار');

        $result = $this->service()->distribute($parent, [$child->id => 120], consentPersonal: true, payer: $owner);

        $this->assertSame(20.0, $result['overflow']); // 120 − 100 (الوعاء) لا 120 − 90
        $this->assertSame(120.0, (float) $child->fresh()->vxp_value);
        $this->assertSame(20.0, (float) $parent->fresh()->personal_vxp_top_up);
        $this->assertSame(480.0, Integrations::balance($owner, VxpDistributionService::CURRENCY));
    }

    /**
     * ⛔ أرضيّة الشريحة **لا تُشترى بموافقة**: 95 داخل الوعاء تمامًا (فلا زيادة
     * أصلًا) لكنّها تكسر الأرضيّة ⟵ مرفوضة، ولا فلس يُخصَم — «خصمٌ وهميّ» ممنوع.
     */
    public function test_reserved_share_cannot_be_bought_with_consent(): void
    {
        $tree = $this->makeTree($this->makeEntity());
        $owner = $this->makeUser();

        $parent = $this->makeTask($tree['item'], 'in_progress', $owner, 100);
        $childA = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 0, $parent);
        $childB = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 0, $parent);

        Integrations::credit($owner, VxpDistributionService::CURRENCY, 500, 'admin', null, 'رصيد اختبار');

        $check = $this->service()->check($parent, [$childA->id => 50, $childB->id => 45], consentPersonal: true, payer: $owner);

        $this->assertFalse($check['ok']);
        $this->assertSame(0.0, $check['overflow']); // داخل الوعاء ⟵ لا زيادة تُخصَم
        $this->assertStringContainsString('لازم تحتفظ', implode(' ', $check['errors']));

        // وحتى فوق الوعاء بموافقة: الأرضيّة تبقى رفضًا
        $above = $this->service()->check($parent, [$childA->id => 80, $childB->id => 40], consentPersonal: true, payer: $owner);
        $this->assertFalse($above['ok']);
        $this->assertStringContainsString('لازم تحتفظ', implode(' ', $above['errors']));

        $this->assertSame(500.0, Integrations::balance($owner, VxpDistributionService::CURRENCY));
    }

    /**
     * ⭐ «لا نقطة تنزل على ابن إلّا وقد نزلت فعلًا من جيب الأب»:
     * لو حيَّد دفترُ الأستاذ الخصمَ (أو قصَّه على حدّ العملة) فالتوزيع كلّه يسقط —
     * فلا يأخذ الأبناء زيادةً **لم يدفعها أحد**. والقياس على **ما طُبِّق** لا على
     * ما طُلِب.
     */
    public function test_distribution_is_rolled_back_when_the_charge_does_not_actually_apply(): void
    {
        $this->setMinSharePercent(0);

        $tree = $this->makeTree($this->makeEntity());
        $owner = $this->makeUser();

        $parent = $this->makeTask($tree['item'], 'in_progress', $owner, 100);
        $child = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 0, $parent);

        Integrations::credit($owner, VxpDistributionService::CURRENCY, 500, 'admin', null, 'رصيد اختبار');

        // حدّ أدنى للعملة يقصّ الخصم: المطلوب 20 والمطبَّق 5 فقط
        Currency::query()->where('code', VxpDistributionService::CURRENCY)->update(['min_value' => 495]);

        try {
            $this->service()->distribute($parent, [$child->id => 120], consentPersonal: true, payer: $owner);
            $this->fail('التوزيع مرّ رغم أنّ الخصم لم يُطبَّق كاملًا.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('لم تُخصَم فعلًا', implode(' ', $e->errors()['shares']));
        }

        // ولا شيء بقي: لا قيمة على الابن ولا top-up
        $this->assertSame(0.0, (float) $child->fresh()->vxp_value);
        $this->assertSame(0.0, (float) $parent->fresh()->personal_vxp_top_up);
    }

    /** حفظٌ ثانٍ داخل الوعاء يمحو top-up السابق — فلا يبقى دَينٌ وهميّ معلّقًا */
    public function test_top_up_is_cleared_when_a_later_save_stays_inside_the_pool(): void
    {
        $this->setMinSharePercent(0);

        $tree = $this->makeTree($this->makeEntity());
        $owner = $this->makeUser();

        $parent = $this->makeTask($tree['item'], 'in_progress', $owner, 100);
        $child = $this->makeTask($tree['item'], 'in_progress', $this->makeUser(), 0, $parent);

        Integrations::credit($owner, VxpDistributionService::CURRENCY, 500, 'admin', null, 'رصيد اختبار');

        $this->service()->distribute($parent, [$child->id => 120], consentPersonal: true, payer: $owner);
        $this->assertSame(20.0, (float) $parent->fresh()->personal_vxp_top_up);

        $this->service()->distribute($parent, [$child->id => 80], payer: $owner);
        $this->assertSame(0.0, (float) $parent->fresh()->personal_vxp_top_up);
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
