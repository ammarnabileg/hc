<?php

namespace App\Services\Store;

use App\Models\TopupOffer;
use Illuminate\Support\Facades\Route;

/**
 * «أقرب عرض يكفّيك» (19.5-ب-2): إن نقص المستخدمَ مبلغٌ لإتمام شراء
 * يظهر داخل **بوب-أب الشراء** أقربُ عرضِ شحنٍ يغطّي العجز.
 *
 * ⭐ حدود هذه الخدمة: **عرضٌ فقط** — لا تنشئ طلب شحن ولا تمسّ المراجعة ولا الرصيد.
 *    وقيمة العرض تُقرأ كما سجّلها الأدمن (19.5-أ: القيم تُحسَب في الخادم دائمًا).
 *
 * وبلا Dark Patterns (2.9): نعرض **قيمة العرض الحقيقيّة** والنسبة الإضافيّة كما هي،
 * ولا نقترح أكبر عرضٍ حين يكفي الأصغر — «أقرب» تعني الأقلّ الذي يكفّي.
 */
class NearestTopupOffer
{
    /**
     * @return array{
     *     needed:float,
     *     offer:?array{id:int,label:string,pay:float,credit:float,bonus_percent:float,covers:bool,url:?string}
     * }
     */
    public function forDeficit(float $deficit, ?string $currencyCode = null): array
    {
        $needed = round(max($deficit, 0), 2);

        if ($needed <= 0 || ! setting('store.topup.suggest_offer', true)) {
            return ['needed' => $needed, 'offer' => null];
        }

        /*
         | ⭐ الشحن يزيد **رصيد الكوينز** وحده (19.5-أ)، فاقتراح عرض شحنٍ على عجزٍ
         | بالتذاكر أو الـXP وعدٌ كاذب. ومَن نقصته تذاكر مساره «تحويل العملة» (19.3).
         */
        if (($currencyCode ?: Coins::defaultCode()) !== Coins::defaultCode()) {
            return ['needed' => $needed, 'offer' => null];
        }

        $offers = TopupOffer::query()
            ->where('is_active', true)
            ->whereIn('method', (array) setting('store.topup.suggest_methods', ['manual', 'gateway']))
            ->orderBy('credit_amount')
            ->get();

        if ($offers->isEmpty()) {
            return ['needed' => $needed, 'offer' => null];
        }

        // الأقلّ الذي يكفّي؛ وإن لم يكفِ شيءٌ منها فالأكبر مع وسم صريح بأنّه لا يغطّي
        $covering = $offers->first(fn (TopupOffer $o) => (float) $o->credit_amount + 0.0001 >= $needed);
        $offer = $covering ?? $offers->last();

        return [
            'needed' => $needed,
            'offer' => [
                'id' => (int) $offer->id,
                'label' => (string) $offer->label_ar,
                'pay' => round((float) $offer->pay_amount, 2),
                'credit' => round((float) $offer->credit_amount, 2),
                'bonus_percent' => round((float) $offer->bonus_percent, 2),
                'covers' => $covering !== null,
                'url' => $this->topupUrl($offer),
            ],
        ];
    }

    /** الجملة المعروضة — من الإعدادات لا محروقة (2.13) */
    public function sentence(array $suggestion): ?string
    {
        $offer = $suggestion['offer'] ?? null;

        if (! $offer) {
            return null;
        }

        return str_replace(
            ['{pay}', '{credit}', '{bonus}', '{needed}', '{label}'],
            [
                Coins::fmt($offer['pay']),
                Coins::fmt($offer['credit']),
                (string) (float) $offer['bonus_percent'],
                Coins::fmt($suggestion['needed']),
                $offer['label'],
            ],
            (string) setting($offer['covers'] ? 'store.topup.nearest_offer_text' : 'store.topup.largest_offer_text'),
        );
    }

    /** رابط صفحة الشحن مع العرض مختارًا — والصفحة نفسها ملكُ مجال الشحن، لا نمسّها */
    private function topupUrl(TopupOffer $offer): ?string
    {
        if (! Route::has('wallet.topup')) {
            return Route::has('wallet.index') ? route('wallet.index') : null;
        }

        return route('wallet.topup', ['offer' => $offer->id]);
    }
}
