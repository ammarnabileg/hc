<?php

namespace App\Services\Volunteer\Meetings;

use App\Models\RepScore;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Wallet\LedgerService;
use Illuminate\Database\Eloquent\Model;

/**
 * غلاف آمن حول دفتر الأستاذ وبوّابة الإشعارات.
 *
 * لماذا غلاف؟ لأنّ المجالات تُبنى بالتوازي: لو لم تكن `LedgerService` أو
 * `Notifier` موجودةً بعد، يظلّ مجال الاجتماعات يعمل بلا كسر — ولا يكتب
 * أحدٌ في سجلّ المعاملات بطريقته الخاصّة فتختلف القواعد من مكان لمكان.
 *
 * وقاعدة حاكمة: **لا قيمة Rep محروقة هنا ولا في نداءات هذه الخدمة** —
 * كلّ القيم تأتي من `rep_rule()` (13.4-ن).
 */
class MeetingLedger
{
    /** طبقة التطوّع — الجدول الموحّد مفلتر لكلّ جانب (13.4-ط) */
    public const LAYER = 'volunteer';

    /**
     * حركة على درجة الالتزام بسببها ومرجعها.
     *
     * @param  float  $value  القيمة بإشارتها كما جاءت من `rep_rule()`
     */
    public function rep(User $user, float $value, string $source, string $reason, ?Model $reference = null, ?int $createdBy = null): ?Transaction
    {
        if (! class_exists(LedgerService::class)) {
            return null;
        }

        /** @var LedgerService $ledger */
        $ledger = app(LedgerService::class);

        $transaction = $value >= 0
            ? $ledger->credit($user, LedgerService::REP, $value, $source, $reference, self::LAYER, $reason, $createdBy)
            : $ledger->debit($user, LedgerService::REP, abs($value), $source, $reference, self::LAYER, $reason, $createdBy);

        $this->stampEntity($transaction, $reference);
        $this->syncRepScore($user);

        return $transaction;
    }

    /** حركة على نقاط الإنتاج التراكميّة */
    public function vxp(User $user, float $value, string $source, string $reason, ?Model $reference = null, ?int $createdBy = null): ?Transaction
    {
        if (! class_exists(LedgerService::class) || $value <= 0) {
            return null;
        }

        return app(LedgerService::class)
            ->credit($user, 'vxp', $value, $source, $reference, self::LAYER, $reason, $createdBy);
    }

    /**
     * ⭐ التصحيح لا يكون بتعديل المعاملة الأصليّة أبدًا، بل بمعاملة عكسيّة
     * موثّقة (`is_correction` + `corrects_transaction_id`) — 13.4-ط.
     */
    public function reverse(Transaction $original, string $reason, ?int $createdBy = null): ?Transaction
    {
        if (! class_exists(LedgerService::class)) {
            return null;
        }

        $correction = app(LedgerService::class)->reverse($original, $reason, $createdBy);

        $this->syncRepScore($original->user()->first());

        return $correction;
    }

    /** الرصيد الحاليّ لعملة — بلا كسر لو لم يوجد الدفتر بعد */
    public function balance(User $user, string $currencyCode): float
    {
        if (class_exists(LedgerService::class)) {
            return app(LedgerService::class)->balance($user, $currencyCode);
        }

        return (float) $user->balance($currencyCode);
    }

    /** إشعار فوريّ بالنوع والمبرّر — لا مفاجآت (13.4-ن-هـ) */
    public function notify(?User $user, string $category, string $title, ?string $body = null, ?string $url = null): void
    {
        if (! $user || ! class_exists(Notifier::class)) {
            return;
        }

        Notifier::send($user, $category, $title, $body, $url, self::LAYER);
    }

    /** ختم كيان المرجع على المعاملة — فتظهر أيقونة الكيان ويعمل فلترها (24.4) */
    private function stampEntity(?Transaction $transaction, ?Model $reference): void
    {
        $entityId = $reference?->getAttribute('entity_id');

        if ($transaction && $entityId) {
            $transaction->forceFill(['entity_id' => $entityId])->save();
        }
    }

    /** مرآة الرقم الظاهر في `rep_scores` — يقرأها الجيج والبروفايل */
    public function syncRepScore(?User $user): void
    {
        if (! $user) {
            return;
        }

        RepScore::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['score' => round($this->balance($user, 'rep'), 2)],
        );
    }
}
