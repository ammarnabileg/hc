<?php

namespace App\Services\Volunteer\Tasks;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * جسر آمن نحو خدمتَي المحفظة والإشعارات.
 *
 * لماذا جسر؟ لأنّ `App\Services\Wallet\LedgerService` و`Notifier` يبنيهما مجالان
 * آخران — فنستعملهما إن وُجدا، وإلّا كتبنا المعاملة في الجدول الموحّد مباشرةً
 * حتى لا يضيع أثرٌ ولا ينكسر بناءٌ لأنّ مجالًا آخر لم يصل بعد.
 */
class LedgerBridge
{
    /**
     * تسجيل حركة VXP أو Rep.
     *
     * @param  string  $currency  رمز العملة: vxp · rep
     * @param  string  $source    مصدر الحركة في الجدول الموحّد: task · meeting · academy…
     */
    public function record(
        User $user,
        string $currency,
        float $amount,
        string $source,
        string $reason,
        ?Model $reference = null,
        ?int $entityId = null,
    ): ?Transaction {
        if (abs($amount) < 0.0001) {
            return null;
        }

        $ledger = 'App\Services\Wallet\LedgerService';

        if (class_exists($ledger)) {
            $service = app($ledger);

            if (method_exists($service, 'record')) {
                return $service->record($user, $currency, $amount, $source, $reason, $reference, $entityId);
            }
        }

        return $this->fallback($user, $currency, $amount, $source, $reason, $reference, $entityId);
    }

    /** إشعار عبر البوّابة الموحّدة إن وُجدت (2.8) */
    public function notify(
        User $user,
        string $category,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?Model $reference = null,
        bool $requiresAction = false,
    ): void {
        $notifier = 'App\Services\Notifications\Notifier';

        if (! class_exists($notifier)) {
            return;
        }

        $notification = $notifier::send($user, $category, $title, $body, $url, 'volunteer', null, $requiresAction);

        if ($reference && method_exists($notifier, 'about')) {
            $notifier::about($notification, $reference);
        }
    }

    /**
     * كتابة مباشرة في الجدول الموحّد — بنفس أعمدته المعتمدة:
     * الطبقة «تطوّع»، والسبب والمرجع، وباب الاعتراض مفتوح بمدّته من الإعدادات (13.4-ط).
     */
    private function fallback(
        User $user,
        string $currency,
        float $amount,
        string $source,
        string $reason,
        ?Model $reference,
        ?int $entityId,
    ): ?Transaction {
        $currencyId = Currency::query()->where('code', $currency)->value('id');

        if (! $currencyId) {
            return null;
        }

        return Transaction::create([
            'user_id' => $user->id,
            'currency_id' => $currencyId,
            'amount' => $amount,
            'layer' => 'volunteer',
            'source' => $source,
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'entity_id' => $entityId,
            'objection_deadline_at' => now()->addDays((int) setting('rep.objection.window_days', 5)),
        ]);
    }
}
