<?php

namespace App\Services\Ads;

use App\Models\Transaction;

/**
 * الجسر بين **لحظة اعتماد الشحن** وحدث «شحن المحفظة» (21.3-أ).
 *
 * ولحظة النجاح واحدة للمسارين — اليدويّ بعد اعتماد الأدمن، والبوّابة من
 * الويب-هوك — وهي **سطر الشحن في دفتر الأستاذ**. فالتقاطها من هنا يغطّيهما معًا
 * ويغطّي أيّ مسار شحنٍ يُضاف لاحقًا، **بلا لمس منطق 19.5** الذي ثبتت سلامته.
 *
 * ⭐ و`$afterCommit` مقصودة: فشلُ منصّةٍ إعلانيّة لا يجوز أن يُرجِع شحنًا صحيحًا.
 * ⛔ ولا حدث بلا موافقة صريحة — الحارس في `AdEvents::record()` (21.3-د).
 */
class TopupSignalObserver
{
    public bool $afterCommit = true;

    public function __construct(private readonly AdSignals $signals) {}

    public function created(Transaction $transaction): void
    {
        $this->signals->walletToppedUp($transaction);
    }
}
