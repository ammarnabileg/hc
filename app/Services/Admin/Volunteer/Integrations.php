<?php

namespace App\Services\Admin\Volunteer;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Volunteer\Retention\RepLadder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * نقاط التكامل: دفتر الأستاذ والإشعارات.
 *
 * لماذا غلاف؟ لأنّ `LedgerService` و`Notifier` يملكهما مجالان آخران؛
 * فنستدعيهما متى وُجدا (`class_exists`)، وإن غابا نكتب بديلًا آمنًا بنفس
 * الأثر داخل معاملة ذرّيّة — فلا تتعطّل شاشة المكافآت ولا يتكرّر الخصم
 * حين يصل الدفتر لاحقًا.
 */
class Integrations
{
    private const LEDGER = 'App\Services\Wallet\LedgerService';

    private const NOTIFIER = 'App\Services\Notifications\Notifier';

    public static function hasLedger(): bool
    {
        return class_exists(self::LEDGER);
    }

    public static function hasNotifier(): bool
    {
        return class_exists(self::NOTIFIER);
    }

    /** رصيد المستخدم من عملة بكودها */
    public static function balance(User $user, string $currencyCode): float
    {
        if (self::hasLedger()) {
            try {
                return (float) app(self::LEDGER)->balance($user, $currencyCode);
            } catch (Throwable) {
                // نكمل بالقراءة المباشرة أدناه
            }
        }

        return (float) WalletBalance::query()
            ->where('user_id', $user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', $currencyCode))
            ->value('balance');
    }

    /**
     * حركة موقّعة على الرصيد (+ منح · − خصم).
     *
     * ⭐ الخصم يُسمَح له بالنزول تحت الصفر (12.9) — والقيد الوحيد هو حدود
     * العملة نفسها في دفتر الأستاذ (سقف Rep مثلًا)، لا قاعدة شاشة المكافآت.
     */
    public static function post(
        User $user,
        string $currencyCode,
        float $signedAmount,
        string $source,
        string $reason,
        ?User $actor = null,
        ?Model $reference = null,
        string $layer = 'training',
    ): ?Transaction {
        $transaction = null;
        $posted = false;

        if (self::hasLedger()) {
            try {
                $ledger = app(self::LEDGER);
                $method = $signedAmount >= 0 ? 'credit' : 'debit';

                $transaction = $ledger->{$method}(
                    $user, $currencyCode, abs($signedAmount), $source,
                    $reference, $layer, $reason, $actor?->id,
                );
                $posted = true;
            } catch (Throwable) {
                // البديل الآمن أدناه
            }
        }

        if (! $posted) {
            $transaction = self::postDirectly($user, $currencyCode, $signedAmount, $source, $reason, $actor, $reference, $layer);
        }

        // ⭐ ختمُ الكيان **قبل** السلّم لا بعده — انظر `stampEntity()`
        self::stampEntity($transaction, $reference);

        // ⭐ سلّم عتبات الهبوط الثلاث يقع **فورًا** (23-0.2) — ومعاملات السلوك
        // (13.4-ن-هـ) تمرّ من هنا، وهي أكثر ما يُنزِل درجة الالتزام.
        RepLadder::afterRepMovement($user, $currencyCode, $signedAmount, $transaction);

        return $transaction;
    }

    /**
     * ⭐ **ختمُ كيان الحركة على صفّها** — وهو ما يجعل «أبلاين **العضويّة التي
     * وقعت فيها المعاملة الكاسرة**» (23-0.2-1) جملةً قابلةً للتنفيذ لا وصفًا.
     *
     * والوسم منصوصٌ أصلًا في قاعدة العضويّات المتعدّدة (23-0.2-عضويّات-2):
     * «وكلّ حدث **موسوم بكيانه** في سجلّ المعاملات». وكان `MeetingLedger` وحده
     * يفعلها، فحركات السلوك — وهي **أكثر ما يُنزِل الدرجة** — تصل بلا كيان،
     * فيقع التزام الـ48 ساعة على أبلاين العضويّة الأساسيّة أيًّا كان الكيان
     * الذي وقعت فيه المخالفة فعلًا.
     *
     * ولا يُشتقّ الكيان إلّا ممّا يحمله المرجع بنفسه: `entity_id` مباشرةً، أو
     * `membership_id` (معاملة السلوك تحمله) ⟵ كيان تلك العضويّة. وما لا مرجع
     * له يبقى بلا كيان — ولا نخترع له واحدًا.
     */
    private static function stampEntity(?Transaction $transaction, ?Model $reference): void
    {
        if (! $transaction || $transaction->entity_id || ! $reference) {
            return;
        }

        $entityId = $reference->getAttribute('entity_id');

        if (! $entityId && $reference->getAttribute('membership_id')) {
            $entityId = DB::table('memberships')
                ->where('id', $reference->getAttribute('membership_id'))
                ->value('entity_id');
        }

        if ($entityId) {
            $transaction->forceFill(['entity_id' => (int) $entityId])->save();
        }
    }

    /** إشعار المستخدم — يمرّ للـNotifier إن وُجد وإلّا يُتجاهَل بأمان */
    public static function notify(User $user, string $category, string $title, ?string $body = null, ?string $url = null, string $layer = 'platform'): void
    {
        if (! self::hasNotifier()) {
            return;
        }

        try {
            (self::NOTIFIER)::send($user, $category, $title, $body, $url, $layer);
        } catch (Throwable) {
            // الإشعار مساعِد لا شرط لنجاح العمليّة — فلا يُسقِطها
        }
    }

    // ------------------------------------------------------------------ داخليّ

    private static function postDirectly(
        User $user,
        string $currencyCode,
        float $signedAmount,
        string $source,
        string $reason,
        ?User $actor,
        ?Model $reference,
        string $layer,
    ): ?Transaction {
        $currency = Currency::query()->where('code', $currencyCode)->first();

        if (! $currency || $signedAmount == 0.0) {
            return null;
        }

        return DB::transaction(function () use ($user, $currency, $signedAmount, $source, $reason, $actor, $reference, $layer) {
            $wallet = WalletBalance::query()->firstOrCreate(
                ['user_id' => $user->id, 'currency_id' => $currency->id],
                ['balance' => 0, 'lifetime_earned' => 0, 'lifetime_spent' => 0],
            );

            /*
             | ⭐ نفس حارس LedgerService::documentedHumanDeduction() (13.4-ن ·
             | §23-5: لا خصم آليّ على VXP/XP إطلاقًا). هذا المسار احتياطيّ نائم
             | لا يعمل إلّا إذا تعطّل الدفتر الأصليّ، ولا يجوز أن يفتح بابًا خلفيًّا
             | للخصم الآليّ كان الدفتر الأصليّ يقفله — السطر يبقى شاهدًا على
             | المحاولة بقيمتها، لكنّها لا تُطبَّق على الرصيد.
             */
            $blocked = $signedAmount < 0 && $currency->is_cumulative && ! $currency->is_spendable
                && ! self::documentedHumanDeduction($user, $actor, $source);

            $effective = $blocked ? 0.0 : $signedAmount;

            // بلا قصٍّ عند الصفر — النزول تحت الصفر مسموح صراحةً (12.9)
            $after = round((float) $wallet->balance + $effective, 2);

            if ($currency->min_value !== null) {
                $after = max($after, (float) $currency->min_value);
            }

            if ($currency->max_value !== null) {
                $after = min($after, (float) $currency->max_value);
            }

            $applied = round($after - (float) $wallet->balance, 2);

            $wallet->forceFill([
                'balance' => $after,
                // المكتسَب التراكميّ من **المطلوب كاملًا** لا من المسقوف (13.4-ن-و)
                'lifetime_earned' => round((float) $wallet->lifetime_earned + max(round($effective, 2), 0), 2),
                'lifetime_spent' => round((float) $wallet->lifetime_spent + abs(min(round($effective, 2), 0)), 2),
            ])->save();

            return Transaction::create([
                'user_id' => $user->id,
                'currency_id' => $currency->id,
                'amount' => round($signedAmount, 2),
                'applied_amount' => $applied,
                'balance_after' => $after,
                'layer' => $layer,
                'source' => $source,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'created_by' => $actor?->id,
                'objection_deadline_at' => now()->addDays((int) setting('rep.objection.window_days', 5)),
            ]);
        });
    }

    /** نظير LedgerService::documentedHumanDeduction() — بلا مسار «تصحيح» هنا لأنّ `post()` لا يمرّره أصلًا */
    private static function documentedHumanDeduction(User $user, ?User $actor, string $source): bool
    {
        if ($actor === null) {
            return false;
        }

        if ((int) $actor->id !== (int) $user->id) {
            return true;
        }

        return in_array($source, self::selfSpendSources(), true);
    }

    /** @return array<int,string> */
    private static function selfSpendSources(): array
    {
        $raw = setting('wallet.cumulative.self_spend_sources', "contribution.hold\ntask");
        $lines = is_array($raw) ? $raw : (preg_split('/[\r\n,]+/', (string) $raw) ?: []);

        return array_values(array_filter(array_map('trim', $lines), static fn ($v) => $v !== ''));
    }
}
