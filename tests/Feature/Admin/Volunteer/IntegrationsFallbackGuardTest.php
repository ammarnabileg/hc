<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Currency;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Wallet\LedgerService;
use RuntimeException;

/**
 * الحارس الاحتياطيّ في Integrations::postDirectly() — نظير حارس
 * LedgerService::documentedHumanDeduction() على المسار النائم اللي يعمل
 * فقط لو تعطّل الدفتر الأصليّ. لا خصم آليّ على VXP إطلاقًا (§23-5 · 13.4-ن).
 */
class IntegrationsFallbackGuardTest extends AdminVolunteerTestCase
{
    private function forceLedgerFailure(): void
    {
        $this->mock(LedgerService::class, function ($mock) {
            $mock->shouldReceive('debit')->andThrow(new RuntimeException('ledger down'));
            $mock->shouldReceive('credit')->andThrow(new RuntimeException('ledger down'));
        });
    }

    private function vxpWallet(User $user, float $balance): WalletBalance
    {
        return WalletBalance::create([
            'user_id' => $user->id,
            'currency_id' => Currency::where('code', 'vxp')->value('id'),
            'balance' => $balance,
            'lifetime_earned' => $balance,
            'lifetime_spent' => 0,
        ]);
    }

    public function test_fallback_path_blocks_an_unsigned_automatic_vxp_deduction(): void
    {
        $this->forceLedgerFailure();
        $user = $this->makeUser();
        $this->vxpWallet($user, 10);

        $transaction = Integrations::post($user, 'vxp', -5, 'some.automatic.source', 'محاولة خصم آليّ', null);

        $this->assertNotNull($transaction);
        $this->assertSame('0.00', (string) $transaction->applied_amount);
        $this->assertSame('10.00', (string) WalletBalance::where('user_id', $user->id)->value('balance'));
    }

    public function test_fallback_path_blocks_a_self_signed_deduction_outside_the_allowed_sources(): void
    {
        $this->forceLedgerFailure();
        $user = $this->makeUser();
        $this->vxpWallet($user, 10);

        $transaction = Integrations::post($user, 'vxp', -5, 'not.an.allowed.self.source', 'محاولة توقيع ذاتيّ', $user);

        $this->assertSame('0.00', (string) $transaction->applied_amount);
        $this->assertSame('10.00', (string) WalletBalance::where('user_id', $user->id)->value('balance'));
    }

    public function test_fallback_path_allows_a_deduction_signed_by_a_different_human(): void
    {
        $this->forceLedgerFailure();
        $user = $this->makeUser();
        $admin = $this->makeUser('أدمن');
        $this->vxpWallet($user, 10);

        $transaction = Integrations::post($user, 'vxp', -5, 'admin', 'خصم يدويّ من الأدمن', $admin);

        $this->assertSame('-5.00', (string) $transaction->applied_amount);
        $this->assertSame('5.00', (string) WalletBalance::where('user_id', $user->id)->value('balance'));
    }

    public function test_fallback_path_allows_a_self_signed_deduction_from_the_declared_allowlist(): void
    {
        $this->forceLedgerFailure();
        $user = $this->makeUser();
        $this->vxpWallet($user, 10);

        $transaction = Integrations::post($user, 'vxp', -5, 'contribution.hold', 'رصيد معلَّق موافَق عليه', $user);

        $this->assertSame('-5.00', (string) $transaction->applied_amount);
        $this->assertSame('5.00', (string) WalletBalance::where('user_id', $user->id)->value('balance'));
    }
}
