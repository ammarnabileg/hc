<?php

namespace App\Services\Volunteer\People;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * جسر آمن نحو خدمات المجالات الأخرى (دفتر الأستاذ · الإشعارات · الاحتفالات).
 *
 * لماذا جسر؟ لأنّ هذا المجال لا يملك تلك الخدمات، وقد يُبنى قبلها أو بعدها.
 * فالقاعدة هنا: **إن وُجدت نستعملها، وإن غابت لا ننكسر** — والمنطق التطوّعيّ
 * (الحدود والأقفال والمرّة الواحدة) محفوظٌ في جداولنا نحن لا في دفتر غيرنا.
 */
class PeopleBridge
{
    private const LEDGER = 'App\Services\Wallet\LedgerService';

    private const NOTIFIER = 'App\Services\Notifications\Notifier';

    private const CELEBRATION = 'App\Services\Gamification\CelebrationService';

    /** إضافة رصيد بعملة تطوّعيّة (vxp / rep) — بلا كسر لو غاب الدفتر */
    public function credit(User $user, string $currency, float $amount, string $source, ?Model $reference = null, ?string $reason = null): void
    {
        if ($amount == 0.0 || ! class_exists(self::LEDGER)) {
            return;
        }

        try {
            app(self::LEDGER)->credit($user, $currency, $amount, $source, $reference, 'volunteer', $reason);
        } catch (\Throwable $e) {
            // الفشل هنا لا يُبطل الفعل نفسه — والمنطق مسجَّل عندنا
            Log::warning('People ledger credit failed: '.$e->getMessage());
        }
    }

    /** إشعار في طبقة التطوّع — يظهر في تاب «التطوّع» بجرس الهيدر (2.8) */
    public function notify(
        User $user,
        string $category,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?Carbon $deadlineAt = null,
        bool $requiresAction = false,
    ): void {
        if (! class_exists(self::NOTIFIER)) {
            return;
        }

        try {
            (self::NOTIFIER)::send($user, $category, $title, $body, $url, 'volunteer', $deadlineAt, $requiresAction);
        } catch (\Throwable $e) {
            Log::warning('People notify failed: '.$e->getMessage());
        }
    }

    /**
     * إطلاق احتفال — والمستوى ومرّة-واحدة من نظام الاحتفالات نفسه (2.14).
     *
     * @return array{key:string,tier:int,label:string,message:string,sound:bool}|null
     */
    public function celebrate(User $user, string $eventKey, ?Model $reference = null): ?array
    {
        if (! class_exists(self::CELEBRATION)) {
            return null;
        }

        try {
            return app(self::CELEBRATION)->fire($user, $eventKey, $reference);
        } catch (\Throwable $e) {
            Log::warning('People celebration failed: '.$e->getMessage());

            return null;
        }
    }
}
