<?php

namespace Tests\Feature\Wallet;

use App\Models\TopupOffer;
use App\Models\TopupRequest;
use App\Models\TransferMethod;
use App\Services\Wallet\TopupService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ManualTopupTest extends WalletTestCase
{
    private TransferMethod $method;

    private TopupOffer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->method = TransferMethod::create([
            'type' => 'instapay',
            'name_ar' => 'إنستا باي',
            'account_number' => 'platform@instapay',
            'beneficiary_name' => 'مؤسّسة المنصّة',
            'is_active' => true,
        ]);

        $this->offer = TopupOffer::create([
            'method' => 'manual',
            'label_ar' => 'باقة المتعلّم',
            'pay_amount' => 500,
            'credit_amount' => 550,
            'bonus_percent' => 10,
            'is_active' => true,
        ]);
    }

    private function submit(UploadedFile $receipt, array $overrides = [])
    {
        return $this->actingAs($this->user)->post(route('wallet.topup.manual'), array_merge([
            'topup_offer_id' => $this->offer->id,
            'transferred_amount' => 500,
            'paid_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'transfer_method_id' => $this->method->id,
            'contact_phone' => $this->user->phone,
            'receipt' => $receipt,
        ], $overrides));
    }

    public function test_manual_request_is_stored_as_pending_review(): void
    {
        $this->submit(UploadedFile::fake()->image('receipt.png'))
            ->assertRedirect(route('wallet.topup.requests'));

        $request = TopupRequest::query()->firstOrFail();

        $this->assertSame(TopupService::PENDING, $request->status);
        $this->assertSame($this->user->id, $request->user_id);
        $this->assertNotEmpty($request->receipt_hash);
        // ولا رصيد ينزل قبل مراجعة الأدمن
        $this->assertSame(0.0, app(\App\Services\Wallet\LedgerService::class)->balance($this->user, 'coins'));
    }

    public function test_receipt_is_mandatory(): void
    {
        $this->actingAs($this->user)->post(route('wallet.topup.manual'), [
            'topup_offer_id' => $this->offer->id,
            'transferred_amount' => 500,
            'paid_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'transfer_method_id' => $this->method->id,
            'contact_phone' => $this->user->phone,
        ])->assertSessionHasErrors('receipt');

        $this->assertSame(0, TopupRequest::query()->count());
    }

    /** ⭐ (4) بصمة الإيصال: المكرَّر يُوسَم duplicate تلقائيًّا */
    public function test_duplicate_receipt_is_flagged_automatically(): void
    {
        $bytes = UploadedFile::fake()->image('receipt.png')->get();

        $this->submit(UploadedFile::fake()->createWithContent('first.png', $bytes));

        // المستخدم الثاني يرفع نفس الملفّ بالضبط — فالبصمة واحدة والوسم تلقائيّ
        $other = $this->makeUser();

        $this->actingAs($other)->post(route('wallet.topup.manual'), [
            'topup_offer_id' => $this->offer->id,
            'transferred_amount' => 500,
            'paid_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'transfer_method_id' => $this->method->id,
            'contact_phone' => $other->phone,
            'receipt' => UploadedFile::fake()->createWithContent('second.png', $bytes),
        ])->assertRedirect(route('wallet.topup.requests'));

        $second = TopupRequest::query()->where('user_id', $other->id)->firstOrFail();

        $this->assertSame(TopupService::DUPLICATE, $second->status);
        $this->assertSame(
            TopupRequest::query()->where('user_id', $this->user->id)->value('receipt_hash'),
            $second->receipt_hash,
        );
    }

    /** ⭐ (5) قفل: طلب معلَّق واحد لكلّ مستخدم */
    public function test_only_one_pending_request_per_user(): void
    {
        $this->submit(UploadedFile::fake()->image('one.png'));
        $this->submit(UploadedFile::fake()->image('two.png'))->assertRedirect(route('wallet.topup.requests'));

        $this->assertSame(1, TopupRequest::query()->where('user_id', $this->user->id)->count());
    }

    public function test_cancelled_request_can_be_edited_and_resent(): void
    {
        $this->submit(UploadedFile::fake()->image('one.png'));

        $request = TopupRequest::query()->firstOrFail();
        $request->update(['status' => TopupService::CANCELLED, 'cancel_reason' => 'الإيصال غير واضح — ابعت صورة أوضح.']);

        // القفل يُرفَع بمجرّد ألّا يبقى طلبٌ معلَّق
        $this->actingAs($this->user)->get(route('wallet.topup', ['resend' => $request->id]))
            ->assertOk()
            ->assertSee('عدّل وأعد الإرسال', false);

        $this->submit(UploadedFile::fake()->image('three.png'));

        $this->assertSame(1, TopupRequest::query()->where('status', TopupService::PENDING)->count());
    }

    public function test_requests_page_shows_status_and_cancel_reason(): void
    {
        $this->submit(UploadedFile::fake()->image('one.png'));

        TopupRequest::query()->firstOrFail()->update([
            'status' => TopupService::CANCELLED,
            'cancel_reason' => 'القيمة المحوَّلة أقلّ من العرض المختار.',
        ]);

        $this->actingAs($this->user)->get(route('wallet.topup.requests'))
            ->assertOk()
            ->assertSee('ملغاة', false)
            ->assertSee('القيمة المحوَّلة أقلّ من العرض المختار.', false);
    }
}
