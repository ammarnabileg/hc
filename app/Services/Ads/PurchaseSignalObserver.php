<?php

namespace App\Services\Ads;

use App\Models\Order;

/**
 * الجسر بين **لحظة اعتماد الطلب مدفوعًا** وحدث «أتمّ الشراء» (21.3-أ).
 *
 * لماذا مراقِبٌ لا نداءٌ داخل خدمة الشراء؟ لنفس السبب الذي جعل عمولة الريفيرال
 * مراقِبًا (19.3): **لا نلمس منطق الماليّات الذي ثبتت سلامته**. المراقِب يلتقط
 * نقطة النجاح وحدها — والطلب الذي لم يُدفَع لا يولّد حدثًا.
 *
 * ⭐ و`$afterCommit` مقصودة: الحدث الإعلانيّ يخرج **بعد** أن تُثبَّت المعاملة،
 *    فلا يبطئ قفل المحفظة ولا يُسقِط شراءً لأنّ منصّةً إعلانيّة تأخّرت. وهذا
 *    عكس عمولة الريفيرال التي **يجب** أن ترتدّ مع الشراء لأنّها مالٌ في دفترنا.
 *
 * ⛔ ولا شيء من هذا بلا موافقة صريحة: الحارس في `AdEvents::record()` نفسه (21.3-د).
 */
class PurchaseSignalObserver
{
    public bool $afterCommit = true;

    public function __construct(private readonly AdSignals $signals) {}

    public function created(Order $order): void
    {
        $this->signals->purchaseCompleted($order);
    }

    /** الطلب يُنشأ `pending` ثمّ يصير `paid` — فاللحظة الحقيقيّة هنا */
    public function updated(Order $order): void
    {
        $this->signals->purchaseCompleted($order);
    }
}
