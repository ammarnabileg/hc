<?php

namespace Tests\Feature\Admin\System;

use App\Models\Currency;
use App\Models\TopupOffer;
use App\Models\TopupRequest;
use App\Models\TransferMethod;
use App\Services\Admin\System\TopupReviewService;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\TopupService;
use RuntimeException;

/**
 * إدارة طلبات الشحن (19.5-ب-5) — القواعد الحاسمة:
 *  ⛔ لا موافقة سريعة بضغطة · ⭐ القيمة اليدويّة · ⭐ الإيصال المكرَّر يُوسَم ·
 *  ⭐ سبب الإلغاء إلزاميّ · ⛔ ولا مهلة مراجعة معلَنة للمُرسِل.
 */
class AdminSystemTopupReviewTest extends SystemTestCase
{
    private const ADMIN_PERMISSIONS = [
        'topup_requests.list',
        'topup_requests.view',
        'topup_requests.approve',
        'topup_requests.reject',
        'topup.manage',
    ];

    public function test_topup_request_cannot_be_approved_without_reviewing_the_receipt(): void
    {
        $admin = $this->admin(self::ADMIN_PERMISSIONS);
        $request = $this->pendingRequest();
        $offer = TopupOffer::query()->where('method', 'manual')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.topups.approve', $request), [
            'mode' => 'offer',
            'topup_offer_id' => $offer->id,
            'reason' => 'الإيصال مطابق',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(TopupService::PENDING, $request->refresh()->status);
        $this->assertSame(0.0, app(LedgerService::class)->balance($request->user, 'coins'));
    }

    public function test_approval_works_after_the_receipt_is_reviewed(): void
    {
        $admin = $this->admin(self::ADMIN_PERMISSIONS);
        $request = $this->pendingRequest();
        $offer = TopupOffer::query()->where('method', 'manual')->where('credit_amount', 550)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.topups.reviewed', $request))->assertRedirect();

        $this->actingAs($admin)->post(route('admin.topups.approve', $request), [
            'mode' => 'offer',
            'topup_offer_id' => $offer->id,
            'reason' => 'الإيصال مطابق والقيمة اتأكّدت',
        ])->assertRedirect(route('admin.topups.index'));

        $request->refresh();

        $this->assertSame(TopupService::COMPLETED, $request->status);
        // ⭐ الكريدتس تُحسَب في الخادم من العرض لا من المتصفّح
        $this->assertSame(550.0, (float) $request->credited_amount);
        $this->assertSame(550.0, app(LedgerService::class)->balance($request->user, 'coins'));
    }

    public function test_manual_credit_amount_is_applied_with_a_written_reason(): void
    {
        $admin = $this->admin(self::ADMIN_PERMISSIONS);
        $request = $this->pendingRequest();

        $this->actingAs($admin)->post(route('admin.topups.reviewed', $request));

        $this->actingAs($admin)->post(route('admin.topups.approve', $request), [
            'mode' => 'manual',
            'manual_amount' => 275,
            'reason' => 'المبلغ المحوَّل أقلّ من العرض فاتضاف يدويًّا',
        ])->assertRedirect();

        $request->refresh();

        $this->assertSame('manual', $request->credit_mode);
        $this->assertSame(275.0, (float) $request->credited_amount);
        $this->assertSame(275.0, app(LedgerService::class)->balance($request->user, 'coins'));
        $this->assertStringContainsString('يدويًّا', (string) $request->admin_note);
    }

    public function test_duplicate_receipt_hash_is_flagged_and_never_approved(): void
    {
        $admin = $this->admin(self::ADMIN_PERMISSIONS);
        $first = $this->pendingRequest();
        $second = $this->pendingRequest(hash: $first->receipt_hash);

        $this->actingAs($admin)->post(route('admin.topups.reviewed', $second));

        $this->actingAs($admin)->post(route('admin.topups.approve', $second), [
            'mode' => 'manual',
            'manual_amount' => 100,
            'reason' => 'محاولة اعتماد لإيصال مكرَّر',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(TopupService::DUPLICATE, $second->refresh()->status);
        $this->assertSame(0.0, app(LedgerService::class)->balance($second->user, 'coins'));
    }

    public function test_cancellation_requires_a_written_reason_that_reaches_the_user(): void
    {
        $admin = $this->admin(self::ADMIN_PERMISSIONS);
        $request = $this->pendingRequest();

        $this->actingAs($admin)->post(route('admin.topups.cancel', $request), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('admin.topups.cancel', $request), [
            'reason' => 'الإيصال مش واضح — صوّره تاني',
        ])->assertRedirect();

        $request->refresh();

        $this->assertSame(TopupService::CANCELLED, $request->status);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $request->user_id,
            'category' => 'topup',
        ]);
    }

    /** ⛔ العدّاد وتلوين المتأخّر داخليّان: شاشة الأدمن تعرضهما وصفحة المستخدم لا تعرف بهما */
    public function test_review_age_is_an_internal_admin_indicator_only(): void
    {
        $admin = $this->admin(self::ADMIN_PERMISSIONS);
        $request = $this->pendingRequest();

        $this->actingAs($admin)->get(route('admin.topups.show', $request))
            ->assertOk()
            ->assertSee('مؤشّر داخليّ', false)
            ->assertSee('مش معلَن للمُرسِل', false);

        $this->assertIsFloat(app(TopupReviewService::class)->internalAgeHours($request));
    }

    public function test_review_screen_shows_the_receipt_beside_the_form(): void
    {
        $admin = $this->admin(self::ADMIN_PERMISSIONS);
        $request = $this->pendingRequest();

        $this->actingAs($admin)->get(route('admin.topups.show', $request))
            ->assertOk()
            ->assertSee('صورة الإيصال', false)
            ->assertSee('راجعت صورة الإيصال', false);
    }

    public function test_index_shows_the_pending_counter_and_the_four_statuses(): void
    {
        $admin = $this->admin(self::ADMIN_PERMISSIONS);
        $this->pendingRequest();

        $this->actingAs($admin)->get(route('admin.topups.index'))
            ->assertOk()
            ->assertSee('قيد التحقّق', false)
            ->assertSee('مكتملة', false)
            ->assertSee('ملغاة', false)
            ->assertSee('مكرَّرة', false);
    }

    /** القيمة اليدويّة خارج النطاق تُرفَض — الحدود إعدادات لا أرقام محروقة */
    public function test_manual_amount_outside_the_configured_range_is_rejected(): void
    {
        $admin = $this->admin(self::ADMIN_PERMISSIONS);
        $request = $this->pendingRequest();

        app(TopupReviewService::class)->markReceiptReviewed($request, $admin);

        $this->expectException(RuntimeException::class);

        app(TopupReviewService::class)->approve($request, $admin, 'manual', null, 0.0, 'محاولة بقيمة صفر');
    }

    // ---------------------------------------------------------------- أدوات

    private function pendingRequest(?string $hash = null): TopupRequest
    {
        Currency::query()->firstOrCreate(['code' => 'coins'], ['name_ar' => 'كوينز', 'layer' => 'training']);

        $user = $this->makeUser('متدرّب '.fake()->randomNumber(4));
        $method = TransferMethod::query()->firstOrFail();

        return TopupRequest::create([
            'number' => 'TR-'.now()->format('Ymd').'-'.str()->upper(str()->random(6)),
            'user_id' => $user->id,
            'transfer_method_id' => $method->id,
            'transferred_amount' => 500,
            'paid_at' => now()->subHour(),
            'receipt_path' => 'receipts/sample.png',
            'receipt_hash' => $hash ?? hash('sha256', str()->random(20)),
            'contact_phone' => '01000000000',
            'status' => TopupService::PENDING,
        ]);
    }
}
