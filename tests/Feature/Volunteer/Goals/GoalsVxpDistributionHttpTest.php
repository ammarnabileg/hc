<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Goals\Integrations;
use App\Services\Volunteer\Goals\VxpDistributionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

/**
 * قيدا توزيع VXP على **مسار الطلب الحقيقيّ** `POST /volunteer/packages/tasks/{task}/vxp`
 * (الدستور 23 — 3.9-٥).
 *
 * النصّ الحاكم حرفيًّا: «**بقيدين آليّين:** (أ) مجموع ما يوزّعه على أبنائه ≤ وعاء
 * مهمّته (فحص عند الحفظ، ورفض التجاوز)، (ب) شريحة محفوظة للأب لا تقلّ عن نسبة
 * يحدّدها الأدمن (افتراضي 10% من الوعاء) — فلا يوزّع 100% ويشتغل ببلاش، ولا يوزّع
 * 5% ويستغلّ فريقه. **والزيادة فوق الوعاء لا تأتي إلا من رصيد الأب الشخصي
 * بموافقته الصريحة**».
 *
 * فالموافقة الصريحة مكتوبة على **«الزيادة فوق الوعاء»** وحدها — ولا نصّ يبيح بها
 * كسر أرضيّة الشريحة. الأرضيّة **رفضٌ مطلق** لا يُشترى، وإلّا صار الأب يوزّع 100%
 * ويشتغل ببلاش **ويدفع** — وهو عين ما ينفيه النصّ.
 */
class GoalsVxpDistributionHttpTest extends GoalsTestCase
{
    /** أب يملك مهمّةً بوعاء 100 وابنين، وله صلاحيّة التوزيع */
    private function scenario(float $pool = 100): array
    {
        $entity = $this->makeEntity();
        $tree = $this->makeTree($entity);
        $owner = $this->makeUser('الأب');
        $membership = $this->makeMembership($owner, $entity, position: 'director');

        $this->grant($owner, 'wp_items.edit', 'ALL', $membership);

        $parent = $this->makeTask($tree['item'], 'in_progress', $owner, $pool);
        $childA = $this->makeTask($tree['item'], 'in_progress', $this->makeUser('ابن أ'), 0, $parent);
        $childB = $this->makeTask($tree['item'], 'in_progress', $this->makeUser('ابن ب'), 0, $parent);

        Integrations::credit($owner, VxpDistributionService::CURRENCY, 500, 'admin', null, 'رصيد اختبار');

        return compact('tree', 'owner', 'parent', 'childA', 'childB');
    }

    private function balance(User $user): float
    {
        return Integrations::balance($user, VxpDistributionService::CURRENCY);
    }

    private function submit(array $s, array $shares, bool $consent): TestResponse
    {
        return $this->actingAs($s['owner'])->post(
            route('volunteer.packages.vxp', $s['parent']),
            ['shares' => $shares, 'consent_personal' => $consent ? '1' : null],
        );
    }

    /**
     * ⛔ 95 من وعاء 100 **بموافقة صريحة**: داخل الوعاء تمامًا فلا زيادة أصلًا،
     * لكنّه يكسر الأرضيّة (90) ⟵ يُرفَض، ولا فلس يُخصَم من جيب الأب.
     */
    public function test_ninety_five_inside_pool_with_consent_is_rejected_and_nothing_is_charged(): void
    {
        $s = $this->scenario();
        $before = $this->balance($s['owner']);

        $response = $this->submit($s, [$s['childA']->id => 50, $s['childB']->id => 45], consent: true);

        $response->assertSessionHasErrors('shares');
        $this->assertStringContainsString(
            'لازم تحتفظ',
            implode(' ', session('errors')->get('shares')),
        );

        // لا خصم، ولا حتى سطر معاملة خصم
        $this->assertSame($before, $this->balance($s['owner']));
        $this->assertSame(0, Transaction::query()->where('user_id', $s['owner']->id)->where('amount', '<', 0)->count());

        // ولا قيمة نزلت على الأبناء ولا top-up كُتِب
        $this->assertSame(0.0, (float) $s['childA']->fresh()->vxp_value);
        $this->assertSame(0.0, (float) $s['childB']->fresh()->vxp_value);
        $this->assertSame(0.0, (float) $s['parent']->fresh()->personal_vxp_top_up);
    }

    /**
     * ⛔ 100 من وعاء 100 **بموافقة صريحة**: «يوزّع 100% ويشتغل ببلاش **ويدفع**»
     * — منفيٌّ بالنصّ ⟵ يُرفَض، ولا خصم.
     */
    public function test_full_pool_with_consent_is_rejected_and_nothing_is_charged(): void
    {
        $s = $this->scenario();
        $before = $this->balance($s['owner']);

        $response = $this->submit($s, [$s['childA']->id => 50, $s['childB']->id => 50], consent: true);

        $response->assertSessionHasErrors('shares');
        $this->assertSame($before, $this->balance($s['owner']));
        $this->assertSame(0, Transaction::query()->where('user_id', $s['owner']->id)->where('amount', '<', 0)->count());
        $this->assertSame(0.0, (float) $s['childA']->fresh()->vxp_value);
        $this->assertSame(0.0, (float) $s['parent']->fresh()->personal_vxp_top_up);
    }

    /** ✅ 85 داخل الوعاء وفوق الأرضيّة ⟵ يمرّ بلا موافقة وبلا خصم — الباب لم يُغلَق */
    public function test_eighty_five_is_accepted_without_any_charge(): void
    {
        $s = $this->scenario();
        $before = $this->balance($s['owner']);

        $this->submit($s, [$s['childA']->id => 45, $s['childB']->id => 40], consent: false)
            ->assertSessionHasNoErrors();

        $this->assertSame(45.0, (float) $s['childA']->fresh()->vxp_value);
        $this->assertSame(40.0, (float) $s['childB']->fresh()->vxp_value);
        $this->assertSame($before, $this->balance($s['owner']));
        $this->assertSame(0.0, (float) $s['parent']->fresh()->personal_vxp_top_up);
    }

    /** ⛔ فوق الوعاء بلا موافقة ⟵ يُرفَض برسالة الوعاء (القيد أ) */
    public function test_above_pool_without_consent_is_rejected(): void
    {
        $s = $this->scenario();

        $response = $this->submit($s, [$s['childA']->id => 80, $s['childB']->id => 40], consent: false);

        $response->assertSessionHasErrors('shares');
        $this->assertSame($this->balance($s['owner']), 500.0);
    }

    /**
     * ⭐ مسار «الزيادة فوق الوعاء» كما نصّ عليه الدستور: يعمل **حين تسمح الأرضيّة**
     * (الأدمن ضبط النسبة صفرًا) — والزيادة تُقاس على **الوعاء** لا على السقف:
     * 120 من وعاء 100 ⟵ الزيادة 20 لا 30.
     */
    public function test_overflow_is_measured_against_the_pool_not_the_ceiling(): void
    {
        Setting::create([
            'key' => 'workflow.vxp.parent_min_share_percent',
            'group' => 'workflow',
            'label_ar' => 'أدنى شريحة محفوظة للأب (%)',
            'type' => 'number',
            'default_value' => '10',
            'value' => '0',
        ]);
        Cache::forget('settings');

        $s = $this->scenario();

        $this->submit($s, [$s['childA']->id => 70, $s['childB']->id => 50], consent: true)
            ->assertSessionHasNoErrors();

        $this->assertSame(20.0, (float) $s['parent']->fresh()->personal_vxp_top_up);
        $this->assertSame(480.0, $this->balance($s['owner'])); // 500 − 20 لا 500 − 30
    }
}
