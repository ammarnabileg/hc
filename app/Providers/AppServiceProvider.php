<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Transaction;
use App\Services\Ads\PurchaseSignalObserver;
use App\Services\Ads\TopupSignalObserver;
use App\Services\Wallet\TopupCommissionObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         | عمولة الريفيرال 7% تُلتقَط من لحظة نجاح الشحن في دفتر الأستاذ (19.3)،
         | فتسري على الشحن اليدويّ والبوّابة معًا بلا لمس منطق أيٍّ منهما (19.5).
         */
        Transaction::observe(TopupCommissionObserver::class);

        /*
         | ⭐ حدثا **الشراء والشحن** عند لحظتهما الحقيقيّة لا بالمصالحة المتأخّرة (21.3-أ):
         | نقطة النجاح وحدها، بعد تثبيت المعاملة، وبلا لمس منطق 19.5 — تمامًا كما
         | فُعِل بعمولة الريفيرال. ⛔ ولا حدث بلا موافقة صريحة (21.3-د).
         */
        Order::observe(PurchaseSignalObserver::class);
        Transaction::observe(TopupSignalObserver::class);
    }
}
