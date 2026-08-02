<?php

namespace App\Services\Wallet;

use App\Models\Transaction;

/**
 * الجسر بين **نجاح الشحن** وعمولة الريفيرال (19.3).
 *
 * لماذا مراقِبٌ على دفتر الأستاذ بدل نداءٍ داخل خدمتَي الشحن؟
 * لأنّ للشحن مسارَين (يدويّ بعد اعتماد الأدمن · وبوّابة من الويب هوك)
 * ولحظة النجاح فيهما واحدة: **سطر الشحن في الجدول الموحّد**. فالتقاطها من هنا
 * يغطّي المسارين معًا، ويغطّي أيّ مسار شحنٍ يُضاف مستقبلًا، **بلا لمس منطق 19.5**
 * الذي ثبتت سلامته — وأقلّ مساسٍ بالماليّ العامل هو الصواب.
 *
 * والمراقِب يعمل داخل معاملة الشحن نفسها: فلو فشل الشحن وارتدّ، ارتدّت العمولة معه.
 */
class TopupCommissionObserver
{
    public function __construct(private readonly ReferralCommissionService $commissions) {}

    public function created(Transaction $transaction): void
    {
        $this->commissions->recordForTopup($transaction);
    }
}
