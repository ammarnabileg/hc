<?php

namespace Tests\Feature\Growth;

use App\Models\Currency;
use App\Models\Order;
use App\Models\TrackingEvent;
use App\Models\Transaction;
use App\Services\Ads\Consent;
use Illuminate\Support\Str;

/**
 * ⭐ حدثا **الشراء والشحن عند لحظتهما الحقيقيّة** (21.3-أ) — لا بالمصالحة عند
 *    أوّل طلبٍ لاحق للمستخدم، فالمنصّة الإعلانيّة تنسب التحويل بوقته.
 *
 * ⛔ ومع ذلك: **لا حدث بلا موافقة صريحة** (21.3-د) — لا من المتصفّح ولا من الخادم.
 *    والمصالحة تبقى شبكة أمان لِما وقع قبل الموافقة.
 */
class ConversionSignalsTest extends GrowthTestCase
{
    private function enableTracking(): void
    {
        $this->setSetting('ads.tracking.enabled', '1', 'bool');
        $this->setSetting('ads.pixel.meta_id', '123456789');
    }

    /** ⭐ الطلب يُنشأ `pending` ثمّ يصير `paid` — والحدث يقع عند لحظة الاعتماد */
    public function test_purchase_event_fires_the_moment_the_order_is_marked_paid(): void
    {
        $this->enableTracking();
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        $order = $this->makeOrder($user, 'pending');

        // لسّه ما اتدفعش ⟵ لا حدث
        $this->assertSame(0, $this->countFor($user, 'purchase_completed'));

        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        // ⭐ بلا أيّ زيارة صفحة بعدها — الحدث وصل بلحظته لا بمصالحة لاحقة
        $this->assertSame(1, $this->countFor($user, 'purchase_completed'));

        $event = TrackingEvent::query()->where('event', 'purchase_completed')->firstOrFail();
        $this->assertSame($order->id, (int) $event->reference_id);
        $this->assertSame(Order::class, $event->reference_type);
    }

    /** ولا يتكرّر: حفظٌ ثانٍ للطلب أو مصالحةٌ لاحقة لا تنتجان حدثًا ثانيًا */
    public function test_purchase_event_is_not_duplicated_by_later_saves_or_reconciliation(): void
    {
        $this->enableTracking();
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        $order = $this->makeOrder($user, 'paid');
        $order->forceFill(['total' => 130])->save();

        $this->actingAs($user)->get(route('growth.articles.index'))->assertOk();

        $this->assertSame(1, $this->countFor($user, 'purchase_completed'));
    }

    /** ⭐ حدث الشحن عند اعتماد سطر الشحن في دفتر الأستاذ — يدويًّا كان أو من بوّابة */
    public function test_topup_event_fires_the_moment_the_ledger_row_is_approved(): void
    {
        $this->enableTracking();
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        $topup = $this->makeTopup($user, 250);

        $this->assertSame(1, $this->countFor($user, 'wallet_topup'));

        $event = TrackingEvent::query()->where('event', 'wallet_topup')->firstOrFail();
        $this->assertSame($topup->id, (int) $event->reference_id);
    }

    /** السالب والتصحيحيّ ليسا شحنًا — ولا يولّدان حدثًا (19.4) */
    public function test_negative_and_correction_rows_are_not_topups(): void
    {
        $this->enableTracking();
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        $this->makeTopup($user, -80);
        $this->makeTopup($user, 90, ['is_correction' => true]);
        // ومصدرٌ آخر (خصم شراء) لا يُحسَب شحنًا
        $this->makeTopup($user, 60, ['source' => 'purchase']);

        $this->assertSame(0, $this->countFor($user, 'wallet_topup'));
    }

    /** ⛔ بلا موافقة لا حدث — ولا حتّى من الخادم عند لحظة الشراء نفسها (21.3-د) */
    public function test_no_server_side_event_without_explicit_consent(): void
    {
        $this->enableTracking();
        $silent = $this->trainee();                                  // ما اختارش — والصمت ليس موافقة
        $refuser = $this->trainee(['tracking_consent' => Consent::REJECTED]);

        $this->makeOrder($silent, 'paid');
        $this->makeTopup($refuser, 100);

        $this->assertSame(0, TrackingEvent::query()->count());
    }

    /** ⭐ مفتاح إيقاف التتبّع يقف فوق كلّ شيء — واللحظة الحقيقيّة ليست استثناءً */
    public function test_master_switch_off_stops_the_moment_events_too(): void
    {
        $this->setSetting('ads.tracking.enabled', '0', 'bool');
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        $this->makeOrder($user, 'paid');
        $this->makeTopup($user, 100);

        $this->assertSame(0, TrackingEvent::query()->count());
    }

    /**
     * شبكة الأمان باقية: مَن اشترى قبل أن يوافق ثمّ وافق — تلتقطه المصالحة.
     * فالموافقة تُطبَّق حرفيًّا، والحدث لا يضيع بعد أن تُعطى.
     */
    public function test_reconciliation_still_covers_purchases_made_before_consent(): void
    {
        $this->enableTracking();
        $user = $this->trainee();

        $this->makeOrder($user, 'paid');
        $this->assertSame(0, $this->countFor($user, 'purchase_completed'));

        $user->forceFill(['tracking_consent' => Consent::ACCEPTED])->saveQuietly();

        $this->actingAs($user->fresh())->get(route('growth.articles.index'))->assertOk();

        $this->assertSame(1, $this->countFor($user->fresh(), 'purchase_completed'));
    }

    // ------------------------------------------------------------------ أدوات

    private function countFor($user, string $event): int
    {
        return TrackingEvent::query()->where('user_id', $user->id)->where('event', $event)->count();
    }

    private function makeOrder($user, string $status): Order
    {
        return Order::create([
            'number' => 'ORD-'.Str::upper(Str::random(6)),
            'user_id' => $user->id,
            'currency_id' => Currency::query()->firstOrFail()->id,
            'total' => 120,
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    private function makeTopup($user, float $amount, array $attributes = []): Transaction
    {
        return Transaction::create($attributes + [
            'user_id' => $user->id,
            'currency_id' => Currency::query()->where('code', 'coins')->value('id') ?: Currency::query()->firstOrFail()->id,
            'amount' => $amount,
            'layer' => 'training',
            'source' => 'topup',
            'reason' => 'شحن محفظة',
        ]);
    }
}
