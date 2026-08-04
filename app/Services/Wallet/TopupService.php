<?php

namespace App\Services\Wallet;

use App\Models\AppNotification;
use App\Models\TopupOffer;
use App\Models\TopupRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * طلبات الشحن اليدويّة (19.5-ب).
 *
 * الحالات أربعٌ لا تزيد، والقفل «طلب معلَّق واحد» يمنع تكديس الطلبات على المراجع،
 * وبصمة الإيصال تكشف إعادة رفع نفس الصورة قبل أن تصل للأدمن أصلًا.
 */
class TopupService
{
    public const PENDING = 'pending_review';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const DUPLICATE = 'duplicate';

    /** الحالات الأربع المعتمَدة — لا خامسة (19.5-ب-4) */
    public const STATUSES = [self::PENDING, self::COMPLETED, self::CANCELLED, self::DUPLICATE];

    /** هل عند المستخدم طلبٌ معلَّق؟ — قفل: واحدٌ في المرّة (19.5-ب-5) */
    public function pendingRequestFor(User $user): ?TopupRequest
    {
        return TopupRequest::query()
            ->where('user_id', $user->id)
            ->where('status', self::PENDING)
            ->latest('id')
            ->first();
    }

    /**
     * تسجيل طلب تحويل يدويّ.
     * ترجع الطلب بحالته: معلَّق، أو «مكرَّرة» إن كان الإيصال مرفوعًا من قبل.
     */
    public function submit(User $user, array $data, UploadedFile $receipt): TopupRequest
    {
        $hash = hash_file('sha256', $receipt->getRealPath());
        $path = $receipt->store('receipts', 'public');

        $offer = isset($data['topup_offer_id'])
            ? TopupOffer::query()->where('method', 'manual')->where('is_active', true)->find($data['topup_offer_id'])
            : null;

        return DB::transaction(function () use ($user, $data, $hash, $path, $offer) {
            // ⭐ بصمة الإيصال: نفس الملفّ من أيّ مستخدم يوسم الطلب «مكرَّرة» تلقائيًّا
            $isDuplicate = TopupRequest::query()->where('receipt_hash', $hash)->exists();

            $request = TopupRequest::create([
                'number' => $this->nextNumber(),
                'user_id' => $user->id,
                'topup_offer_id' => $offer?->id,
                'transfer_method_id' => $data['transfer_method_id'] ?? null,
                'transferred_amount' => (float) $data['transferred_amount'],
                'paid_at' => $data['paid_at'],
                'receipt_path' => $path,
                'receipt_hash' => $hash,
                'contact_phone' => $data['contact_phone'],
                'status' => $isDuplicate ? self::DUPLICATE : self::PENDING,
            ]);

            $this->notify(
                $user,
                $isDuplicate ? setting('wallet.topup_service.submit_1', 'الإيصال ده مرفوع قبل كده') : setting('wallet.topup_service.submit_2', 'استلمنا طلب الشحن'),
                $isDuplicate
                    ? setting('wallet.topup_service.submit_3', 'الإيصال المرفق مطابق لإيصالٍ سابق، فاتوسم الطلب «مكرَّرة». ارفع إيصال العمليّة الصحيحة وابعت تاني.')
                    : strtr(setting('wallet.topup_service.submit_4', 'طلبك رقم :p1 تحت التحقّق، وهنبلّغك بأيّ تغيير في حالته.'), [':p1' => (string) ($request->number)]),
                $request,
            );

            return $request;
        });
    }

    /** إشعار المستخدم عند كلّ تغيير في حالة الطلب (19.5-ب-6) */
    public function notify(User $user, string $title, string $body, TopupRequest $request): void
    {
        AppNotification::create([
            'user_id' => $user->id,
            'layer' => 'platform',
            'category' => 'topup',
            'title' => $title,
            'body' => $body,
            'url' => route('wallet.topup.requests'),
            'reference_type' => $request->getMorphClass(),
            'reference_id' => $request->getKey(),
        ]);
    }

    /** حذف إيصالٍ رُفِع ثمّ فشل الحفظ — حتى لا تتراكم ملفّات بلا طلب */
    public function forgetReceipt(string $path): void
    {
        Storage::disk('public')->delete($path);
    }

    private function nextNumber(): string
    {
        return 'TR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
