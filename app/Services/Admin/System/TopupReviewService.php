<?php

namespace App\Services\Admin\System;

use App\Models\AuditLog;
use App\Models\TopupOffer;
use App\Models\TopupRequest;
use App\Models\User;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\TopupService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * مراجعة طلبات الشحن اليدويّة في لوحة الإدارة (19.5-ب-5).
 *
 * ثلاث قواعد لا تُكسَر هنا:
 *  1) ⛔ **لا موافقة سريعة بضغطة** — مراجعة صورة الإيصال إلزاميّة قبل أيّ اعتماد
 *     مهما طابقت القيمةُ العرضَ، لأنّ المطابقة الرقميّة لا تثبت أنّ تحويلًا حصل.
 *  2) ⭐ الكريدتس تُحسَب في الخادم: من العرض أو قيمةً يدويّة — ومعهما سببٌ مكتوب.
 *  3) ⭐ الإيصال المكرَّر (نفس البصمة) يُوسَم «مكرَّرة» ولا يُعتمَد.
 *
 * ⛔ ولا يُعلَن أيّ مهلة مراجعة للمُرسِل — العدّاد وتلوين المتأخّر **داخليّان**.
 */
class TopupReviewService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly TopupService $topups,
    ) {}

    /** تسجيل أنّ الأدمن فتح الإيصال وراجعه فعلًا — بوّابة الاعتماد */
    public function markReceiptReviewed(TopupRequest $request, User $actor): TopupRequest
    {
        $request->update([
            'receipt_reviewed_at' => now(),
            'receipt_reviewed_by' => $actor->id,
        ]);

        $this->audit($request, 'topup.receipt_reviewed', [], ['reviewed_by' => $actor->id]);

        return $request->refresh();
    }

    public function receiptReviewed(TopupRequest $request): bool
    {
        return $request->receipt_reviewed_at !== null;
    }

    /** طلبٌ آخر بنفس بصمة الإيصال — لو وُجد فالطلب مكرَّر بلا نقاش */
    public function duplicateOf(TopupRequest $request): ?TopupRequest
    {
        return TopupRequest::query()
            ->where('receipt_hash', $request->receipt_hash)
            ->whereKeyNot($request->getKey())
            ->orderBy('id')
            ->first();
    }

    /** وسم الطلب «مكرَّرة» وتنبيه صاحبه — تلقائيًّا لا يدويًّا */
    public function markDuplicate(TopupRequest $request, ?User $actor = null): TopupRequest
    {
        $request->update(['status' => TopupService::DUPLICATE]);

        $this->topups->notify(
            $request->user,
            setting('store.topup_review_service.mark_duplicate_1', 'الطلب اتوسم «مكرَّرة»'),
            setting('store.topup_review_service.mark_duplicate_2', 'الإيصال المرفق مطابق لإيصالٍ سابق. لو ده خطأ، ارفع إيصال العمليّة الصحيحة وابعت طلبًا جديدًا.'),
            $request,
        );

        $this->audit($request, 'topup.duplicate', ['status' => TopupService::PENDING], ['status' => TopupService::DUPLICATE], $actor);

        return $request->refresh();
    }

    /**
     * ⭐ معاينة الرصيد قبل/بعد — تُحسب في الخادم وتُعرَض قبل التأكيد.
     *
     * @param  string  $mode  offer · manual
     * @return array{amount:float, before:float, after:float, label:string}
     */
    public function preview(TopupRequest $request, string $mode, ?int $offerId, ?float $manual): array
    {
        $currency = (string) setting('topup.credit_currency', 'coins');
        $before = $this->ledger->balance($request->user, $currency);
        [$amount, $label] = $this->resolveAmount($mode, $offerId, $manual);

        return [
            'amount' => $amount,
            'before' => $before,
            'after' => $before + $amount,
            'label' => $label,
        ];
    }

    /**
     * الاعتماد النهائيّ بعد التحقّق.
     *
     * @throws RuntimeException عند أيّ محاولة اعتماد بلا مراجعة أو بلا سبب أو لمكرَّر
     */
    public function approve(TopupRequest $request, User $actor, string $mode, ?int $offerId, ?float $manual, string $reason): TopupRequest
    {
        if ($request->status !== TopupService::PENDING) {
            throw new RuntimeException(setting('store.topup_review_service.approve_1', 'الطلب ده مش في حالة «قيد التحقّق» — راجع حالته الأوّل.'));
        }

        // ⛔ القاعدة الحاسمة: بلا مراجعة إيصال لا اعتماد — ولو طابقت القيمةُ العرضَ
        if (! $this->receiptReviewed($request)) {
            throw new RuntimeException(setting('store.topup_review_service.approve_2', 'راجع صورة الإيصال الأوّل — الاعتماد مقفول لحدّ ما تراجعها.'));
        }

        if ($this->duplicateOf($request)) {
            $this->markDuplicate($request, $actor);

            throw new RuntimeException(setting('store.topup_review_service.approve_3', 'الإيصال ده مرفوع قبل كده — الطلب اتوسم «مكرَّرة» ومااتعتمدش.'));
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException(setting('store.topup_review_service.approve_4', 'اكتب سبب الاعتماد — بيتسجّل في سجلّ التدقيق ويوصل صاحب الطلب.'));
        }

        return DB::transaction(function () use ($request, $actor, $mode, $offerId, $manual, $reason) {
            $locked = TopupRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== TopupService::PENDING) {
                throw new RuntimeException(setting('store.topup_review_service.approve_5', 'الطلب اتعالج بالفعل.'));
            }

            $currency = (string) setting('topup.credit_currency', 'coins');
            [$amount, $label] = $this->resolveAmount($mode, $offerId, $manual);

            $transaction = $this->ledger->credit(
                user: $locked->user,
                currencyCode: $currency,
                amount: $amount,
                source: 'topup',
                reference: $locked,
                layer: 'training',
                reason: $reason,
                createdBy: $actor->id,
            );

            $locked->update([
                'status' => TopupService::COMPLETED,
                'credited_amount' => $amount,
                'credit_mode' => $mode,
                'admin_note' => $reason,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'transaction_id' => $transaction->id,
            ]);

            $this->topups->notify(
                $locked->user,
                setting('store.topup_review_service.approve_6', 'رصيدك اتشحن ✓'),
                strtr(setting('store.topup_review_service.approve_7', 'اتضاف لمحفظتك :p1 — :p2.'), [':p1' => (string) ($this->money($amount)), ':p2' => (string) ($label)]),
                $locked,
            );

            $this->audit(
                $locked,
                'topup.approve',
                ['status' => TopupService::PENDING],
                ['status' => TopupService::COMPLETED, 'amount' => $amount, 'mode' => $mode, 'reason' => $reason],
                $actor,
            );

            return $locked->refresh();
        });
    }

    /** ⭐ سبب الإلغاء إلزاميّ ويصل للمستخدم بنصٍّ واضح ومعه «عدّل وأعد الإرسال» */
    public function cancel(TopupRequest $request, User $actor, string $reason): TopupRequest
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException(setting('store.topup_review_service.cancel_1', 'سبب الإلغاء إلزاميّ — المستخدم لازم يفهم يعدّل إيه.'));
        }

        $old = $request->status;

        $request->update([
            'status' => TopupService::CANCELLED,
            'cancel_reason' => $reason,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        $this->topups->notify(
            $request->user,
            setting('store.topup_review_service.cancel_2', 'طلب الشحن اتلغى'),
            strtr(setting('store.topup_review_service.cancel_3', ':p1 — تقدر تعدّل وتبعت تاني من صفحة طلبات الشحن.'), [':p1' => (string) ($reason)]),
            $request,
        );

        $this->audit($request, 'topup.cancel', ['status' => $old], ['status' => TopupService::CANCELLED, 'reason' => $reason], $actor);

        return $request->refresh();
    }

    /**
     * ⛔ **داخليّ للأدمن فقط**: عمر الطلب بالساعات وتلوين المتأخّر.
     * ولا يُعرَض للمُرسِل ولا يُعلَن كالتزام بمهلة (19.5-أ).
     */
    public function internalAgeHours(TopupRequest $request): float
    {
        return round((float) $request->created_at->diffInMinutes(now()) / 60, 1);
    }

    public function internalIsLate(TopupRequest $request): bool
    {
        return $this->internalAgeHours($request) > (float) setting('topup.review.internal_late_hours', 24);
    }

    /** عدّاد التاب: أيّ جديد لم يُبتّ فيه */
    public function pendingCount(): int
    {
        return TopupRequest::query()->where('status', TopupService::PENDING)->count();
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * ⭐ القيمة تُحسب في الخادم دائمًا ولا تأتي من المتصفّح (19.5-أ).
     *
     * @return array{0:float,1:string}
     */
    private function resolveAmount(string $mode, ?int $offerId, ?float $manual): array
    {
        if ($mode === 'offer') {
            $offer = TopupOffer::query()->where('is_active', true)->find($offerId);

            if (! $offer) {
                throw new RuntimeException(setting('store.topup_review_service.resolve_amount_1', 'اختار عرضًا مفعَّلًا من القائمة.'));
            }

            return [(float) $offer->credit_amount, $offer->label_ar];
        }

        $min = (float) setting('topup.manual_credit.min', 1);
        $max = (float) setting('topup.manual_credit.max', 1000000);
        $amount = (float) $manual;

        if ($amount < $min || $amount > $max) {
            throw new RuntimeException(strtr(setting('store.topup_review_service.resolve_amount_2', 'القيمة اليدويّة لازم تكون من :p1 إلى :p2.'), [':p1' => (string) ($min), ':p2' => (string) ($max)]));
        }

        return [$amount, setting('store.topup_review_service.body_1', 'قيمة يدويّة')];
    }

    private function money(float $amount): string
    {
        return strtr(setting('store.topup_review_service.money_1', ':p1 كوينز'), [':p1' => (string) (rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.'))]);
    }

    private function audit(TopupRequest $request, string $action, array $old, array $new, ?User $actor = null): void
    {
        AuditLog::create([
            'user_id' => $actor?->id ?? auth()->id(),
            'action' => $action,
            'auditable_type' => $request->getMorphClass(),
            'auditable_id' => $request->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
