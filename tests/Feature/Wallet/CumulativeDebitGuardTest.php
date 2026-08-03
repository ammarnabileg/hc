<?php

namespace Tests\Feature\Wallet;

use App\Models\Transaction;
use App\Models\WalletBalance;
use App\Services\Wallet\LedgerService;

/**
 * ⭐ «لا خصم آليّ على VXP إطلاقًا» (13.4-ن · 23 — القسم 5 · 24 تاب VXP).
 *
 * الحارس القديم كان شرطه `createdBy === null` وحده، وكلّ مُنادٍ يمرّر
 * `$createdBy ?? $user->id` — فالتوقيع الذاتيّ كان يُبطِله **دائمًا**، ولم يصدق
 * الشرط ولا مرّةً واحدة في مسارٍ حقيقيّ. هذه الاختبارات تقيس **الفرق الذي
 * يدّعي الحارس قياسه**: توزيعٌ يدويّ مخوَّل ⟵ يمرّ · خصمٌ آليّ ⟵ يُحيَّد.
 */
class CumulativeDebitGuardTest extends WalletTestCase
{
    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    private function seedVxp(float $amount = 100): void
    {
        $this->ledger()->credit($this->user, 'vxp', $amount, 'task', null, 'volunteer', 'إنتاج');
    }

    /**
     * ⭐ الحالة التي **كانت تمرّ وصارت تُرَدّ**: مسارٌ آليّ يوقّع الخصم باسم
     * صاحب الرصيد نفسه (`$createdBy ?? $user->id`) — وهو نمط كلّ المنادين.
     */
    public function test_automatic_deduction_signed_by_the_owner_himself_is_neutralised(): void
    {
        $this->seedVxp(100);

        $transaction = $this->ledger()->debit(
            $this->user, 'vxp', 40, 'inactivity.decay', null, 'volunteer', 'خمول', $this->user->id,
        );

        // السطر يبقى شاهدًا بقيمته الكاملة، لكنّه **لم يُطبَّق**
        $this->assertSame('-40.00', (string) $transaction->amount);
        $this->assertSame('0.00', (string) $transaction->applied_amount);
        $this->assertSame(100.0, $this->ledger()->balance($this->user, 'vxp'));
    }

    /** بلا توقيعٍ أصلًا — آليّ كذلك (السلوك القديم يبقى) */
    public function test_unsigned_deduction_is_neutralised(): void
    {
        $this->seedVxp(100);

        $transaction = $this->ledger()->debit($this->user, 'vxp', 25, 'task', null, 'volunteer', 'خصم آليّ');

        $this->assertSame('0.00', (string) $transaction->applied_amount);
        $this->assertSame(100.0, $this->ledger()->balance($this->user, 'vxp'));
    }

    /**
     * ⭐ الحالة المشروعة الأولى — **قرار محكّم/أدمن**: إنسانٌ **غير صاحب الرصيد**
     * وقّع الخصم، وهو قيد «ليس نفسه» في `vxp_manual.create` بجدول الموارد.
     */
    public function test_deduction_signed_by_another_human_applies_in_full(): void
    {
        $this->seedVxp(100);
        $arbiter = $this->makeUser(['name' => 'محكّم']);

        $transaction = $this->ledger()->debit(
            $this->user, 'vxp', 30, 'arbitration.deduct', null, 'volunteer', 'قرار تحكيم', $arbiter->id,
        );

        $this->assertSame('-30.00', (string) $transaction->applied_amount);
        $this->assertSame(70.0, $this->ledger()->balance($this->user, 'vxp'));
    }

    /**
     * ⭐ الحالة المشروعة الثانية — **الرصيد المعلَّق لبند مساهمة**: المالك يخصم
     * من جيبه بموافقته الصريحة، فالمصدر معلَنٌ في قائمة الصرف الذاتيّ.
     */
    public function test_contribution_hold_by_the_owner_applies_in_full(): void
    {
        $this->seedVxp(100);

        $transaction = $this->ledger()->debit(
            $this->user, 'vxp', 20, 'contribution.hold', null, 'volunteer', 'رصيد معلَّق لبند مساهمة', $this->user->id,
        );

        $this->assertSame('-20.00', (string) $transaction->applied_amount);
        $this->assertSame(80.0, $this->ledger()->balance($this->user, 'vxp'));
    }

    /** ⭐ الحالة المشروعة الثالثة — الزيادة فوق وعاء المهمّة من الرصيد الشخصيّ (23 — 3.9-٥) */
    public function test_personal_top_up_over_the_task_pool_applies_in_full(): void
    {
        $this->seedVxp(100);

        $transaction = $this->ledger()->debit(
            $this->user, 'vxp', 20, 'task', null, 'volunteer', 'زيادة فوق وعاء المهمّة بموافقة صريحة', $this->user->id,
        );

        $this->assertSame('-20.00', (string) $transaction->applied_amount);
        $this->assertSame(80.0, $this->ledger()->balance($this->user, 'vxp'));
    }

    /** المعاملة العكسيّة الموثّقة تمرّ — ولها أصلٌ تشير إليه */
    public function test_documented_correction_applies(): void
    {
        $original = $this->ledger()->credit($this->user, 'vxp', 50, 'task', null, 'volunteer', 'إنتاج');
        $correction = $this->ledger()->reverse($original, 'إلغاء بعد قبول الاعتراض');

        $this->assertTrue((bool) $correction->is_correction);
        $this->assertSame(0.0, $this->ledger()->balance($this->user, 'vxp'));
    }

    /** القفل **معلَن ومن الإعدادات** لا محروق (2.13): أفرغ القائمة فيُقفَل البابان */
    public function test_self_spend_sources_come_from_settings_not_from_code(): void
    {
        $this->seedVxp(100);
        $this->setSetting('wallet.cumulative.self_spend_sources', '');

        $transaction = $this->ledger()->debit(
            $this->user, 'vxp', 20, 'contribution.hold', null, 'volunteer', 'رصيد معلَّق', $this->user->id,
        );

        $this->assertSame('0.00', (string) $transaction->applied_amount);
        $this->assertSame(100.0, $this->ledger()->balance($this->user, 'vxp'));
    }

    /** المكتسَب التراكميّ لا يُلمَس بخصمٍ لم يقع — وإلّا صار الرقم مرآةً لمحاولةٍ فاشلة */
    public function test_neutralised_deduction_does_not_touch_lifetime_columns(): void
    {
        $this->seedVxp(100);

        $this->ledger()->debit(
            $this->user, 'vxp', 40, 'inactivity.decay', null, 'volunteer', 'خمول', $this->user->id,
        );

        $wallet = WalletBalance::query()
            ->where('user_id', $this->user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', 'vxp'))
            ->firstOrFail();

        $this->assertSame('100.00', (string) $wallet->lifetime_earned);
        $this->assertSame('0.00', (string) $wallet->lifetime_spent);
        $this->assertSame(2, Transaction::query()->count());
    }
}
