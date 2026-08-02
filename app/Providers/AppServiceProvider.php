<?php

namespace App\Providers;

use App\Models\Transaction;
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
    }
}
