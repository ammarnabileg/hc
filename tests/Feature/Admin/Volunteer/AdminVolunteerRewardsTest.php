<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Transaction;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Admin\Volunteer\RewardGrantService;

/**
 * إدارة المكافآت (12.9): ⭐ **الخصم يقدر ينزل تحت الصفر**،
 * والأكواد الخاطئة تُستبعَد بتنبيه لا بفشلٍ صامت.
 */
class AdminVolunteerRewardsTest extends AdminVolunteerTestCase
{
    /** ⭐ الخصم ينزل تحت الصفر — مسموح صراحةً ولا يُقَصّ عند الصفر. */
    public function test_debit_is_allowed_to_go_below_zero(): void
    {
        $admin = $this->grant($this->makeUser(), 'manual_rewards.list', 'manual_rewards.create');
        $target = $this->makeUser('متدرّب');

        $this->assertSame(0.0, Integrations::balance($target, 'coins'));

        $this->actingAs($admin)
            ->post(route('admin.rewards.grant'), [
                'codes' => $target->code,
                'currency' => 'coins',
                'direction' => 'debit',
                'amount' => 250,
                'reason' => 'bonus',
                'confirm' => 1,
            ])
            ->assertOk();

        $this->assertSame(-250.0, Integrations::balance($target->fresh(), 'coins'));
        $this->assertDatabaseHas('transactions', ['user_id' => $target->id, 'source' => 'admin', 'amount' => -250]);
    }

    /** المعاينة تُظهر الرصيد قبل/بعد وتستبعد الأكواد الخاطئة والمكرّرة. */
    public function test_preview_separates_valid_invalid_and_duplicate_codes(): void
    {
        $one = $this->makeUser('أوّل');
        $two = $this->makeUser('ثانٍ');

        $preview = RewardGrantService::preview(
            $one->code.' '.$two->code.' '.$one->code.' NOPE1',
            'xp', 100, 'credit',
        );

        $this->assertCount(2, $preview['rows']);
        $this->assertSame(['NOPE1'], $preview['invalid']);
        $this->assertSame([mb_strtoupper($one->code)], $preview['duplicates']);
        $this->assertSame(200.0, $preview['total']);
    }

    /** المنح لأكثر من كود دفعةً واحدة يكتب معاملةً لكلّ مستلِم. */
    public function test_batch_grant_writes_a_transaction_per_recipient(): void
    {
        $admin = $this->grant($this->makeUser(), 'manual_rewards.list', 'manual_rewards.create');
        $a = $this->makeUser('أ');
        $b = $this->makeUser('ب');

        $this->actingAs($admin)
            ->post(route('admin.rewards.grant'), [
                'codes' => $a->code."\n".$b->code,
                'currency' => 'tickets',
                'direction' => 'credit',
                'amount' => 3,
                'reason' => 'bonus',
                'notes_key' => 'rewards',
                'confirm' => 1,
            ])
            ->assertOk();

        $this->assertSame(2, Transaction::where('source', 'admin')->count());
        $this->assertSame(3.0, Integrations::balance($a->fresh(), 'tickets'));
        $this->assertSame(3.0, Integrations::balance($b->fresh(), 'tickets'));
    }

    /** «تصحيح خطأ تقنيّ» لا يمرّ بلا مرجع المعاملة الأصليّة. */
    public function test_technical_fix_reason_requires_a_reference(): void
    {
        $admin = $this->grant($this->makeUser(), 'manual_rewards.list', 'manual_rewards.create');
        $target = $this->makeUser('متدرّب');

        $this->actingAs($admin)
            ->post(route('admin.rewards.grant'), [
                'codes' => $target->code,
                'currency' => 'coins',
                'direction' => 'credit',
                'amount' => 50,
                'reason' => 'tech_fix',
                'confirm' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(0.0, Integrations::balance($target->fresh(), 'coins'));
    }

    /** بلا صلاحيّة: الصفحة ممنوعة (12.2.1). */
    public function test_rewards_page_is_forbidden_without_permission(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('admin.rewards.index'))
            ->assertForbidden();
    }
}
