<?php

namespace App\Services\Admin\Volunteer;

use App\Models\Membership;
use App\Models\Offboarding;
use App\Models\Reentry;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * الأوفبوردنج (13.4-س) — ثلاثة أنواع لا رابع لها:
 *  استقالة طوعيّة · انتهاء كيان مؤقّت · **إقصاء حصرًا عبر سلّم العتبات**.
 *
 * ⛔ «إنهاء إداريّ» و«خمول» ليسا نوعَي خروج — الخمول مدخل للسلّم لا مسار خروج،
 * فلا يُفتَح بابٌ موازٍ للطرد بمزاج الأبلاين.
 */
class OffboardingService
{
    public const TYPES = [
        'resignation' => 'استقالة طوعيّة',
        'entity_ended' => 'انتهاء كيان مؤقّت (ملفّ)',
        'exclusion' => 'إقصاء (عبر سلّم العتبات وحده)',
    ];

    /** بنود التصفية الإلزاميّة — لا إنهاء قبل اكتمالها */
    public static function clearanceItems(): array
    {
        $items = setting('volunteer.offboarding.clearance_items', []);

        return is_array($items) ? $items : [];
    }

    /** هل بلغ المتطوّع عتبة الإقصاء؟ الإقصاء لا يُفتَح إلّا بها */
    public static function reachedExclusionThreshold(User $user): bool
    {
        $rep = Integrations::balance($user, BehaviorLedger::REP);

        return $rep <= rep_rule('limit.suspension', -10);
    }

    /** مدّة التبريد المحسوبة حسب نوع الخروج */
    public static function cooldownUntil(string $type): ?Carbon
    {
        return match ($type) {
            'resignation' => now()->addDays((int) setting('volunteer.offboarding.cooldown_days.resignation', 30)),
            'entity_ended' => now()->addDays((int) setting('volunteer.offboarding.cooldown_days.entity_ended', 30)),
            // الإقصاء = لا عودة إلّا بقرار مشرف عام التطوّع
            'exclusion' => (bool) setting('volunteer.offboarding.exclusion_allows_return', false)
                ? now()->addDays((int) setting('volunteer.offboarding.cooldown_days.thresholds', 90))
                : null,
            default => null,
        };
    }

    /**
     * فتح ملفّ إنهاء عضويّة.
     *
     * @param  array<int,bool>  $checklist  حالة بنود التصفية
     *
     * @throws RuntimeException عند نوعٍ غير مسموح أو إقصاءٍ بلا عتبة
     */
    public static function open(User $target, string $type, ?string $reason, User $actor, array $checklist = []): Offboarding
    {
        if (! array_key_exists($type, self::TYPES)) {
            throw new RuntimeException('نوع الخروج غير معروف — الأنواع ثلاثة لا رابع لها.');
        }

        // ⭐ الإقصاء حصرًا عبر سلّم العتبات — لا فصل بقرار فرديّ
        if ($type === 'exclusion' && ! self::reachedExclusionThreshold($target)) {
            throw new RuntimeException('الإقصاء لا يكون إلّا عبر سلّم العتبات — درجة الالتزام لم تبلغ عتبة التعليق بعد.');
        }

        $noticeDays = (int) setting('volunteer.offboarding.notice_days', 7);

        $record = Offboarding::create([
            'user_id' => $target->id,
            'type' => $type,
            // ⭐ السبب لا يُنشَر للفريق — يبقى في الملاحظات الإداريّة وحدها
            'reason' => $reason,
            'initiated_by' => $actor->id,
            // مهلة الإشعار تُصفَّر في الإقصاء
            'notice_until' => $type === 'exclusion' ? now() : now()->addDays($noticeDays),
            'clearance_checklist' => self::normalizeChecklist($checklist),
            'cooldown_until' => self::cooldownUntil($type),
        ]);

        AuditTrail::log($actor, 'offboarding.open', $record, [], ['type' => $type, 'user_id' => $target->id]);

        return $record;
    }

    /** هل اكتملت التصفية؟ */
    public static function clearanceComplete(Offboarding $record): bool
    {
        $checklist = $record->clearance_checklist ?? [];

        if (! $checklist) {
            return false;
        }

        foreach ($checklist as $row) {
            if (empty($row['done'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * إتمام الإنهاء: يقفل العضويّات ويُصدر شهادة الخروج المشرَّف عند استحقاقها.
     *
     * @throws RuntimeException إن لم تكتمل التصفية الإلزاميّة
     */
    public static function complete(Offboarding $record, User $actor): Offboarding
    {
        if (! self::clearanceComplete($record)) {
            throw new RuntimeException('التصفية الإلزاميّة لم تكتمل — كمّل بنود التشيك-ليست قبل الإنهاء.');
        }

        Membership::query()
            ->where('user_id', $record->user_id)
            ->where('status', 'active')
            ->update(['status' => 'ended', 'ended_at' => now(), 'end_reason' => $record->type]);

        // خروج مُشرَّف: شهادة خبرة في الاستقالة وانتهاء الملفّ فقط — لا في الإقصاء
        $honorable = $record->type !== 'exclusion'
            && (bool) setting('volunteer.offboarding.honorable_certificate_enabled', true);

        $record->forceFill([
            'approved_by' => $actor->id,
            'completed_at' => now(),
            'honorable_certificate_issued' => $honorable,
        ])->save();

        $user = $record->user;

        if ($user) {
            // الرسالة للفريق بلا سبب — «انتهت عضويّة فلان» فقط
            Integrations::notify(
                $user, 'account', 'انتهت عضويّتك التطوّعيّة',
                str_replace('{name}', $user->name, (string) setting('volunteer.offboarding.team_message', '')),
                null, 'platform',
            );
        }

        AuditTrail::log($actor, 'offboarding.complete', $record, [], ['honorable' => $honorable]);

        return $record;
    }

    /** حالة العودة: متاحة؟ ومتى؟ (13.4-ق) */
    public static function reentryState(Offboarding $record): array
    {
        if ($record->type === 'exclusion' && ! (bool) setting('volunteer.offboarding.exclusion_allows_return', false)) {
            return [
                'state' => 'danger',
                'label' => 'لا عودة إلّا بقرار مشرف عام التطوّع',
                'copy' => (string) setting('volunteer.offboarding.excluded_copy', ''),
            ];
        }

        $until = $record->cooldown_until;

        if ($until && $until->isFuture()) {
            return [
                'state' => 'warn',
                'label' => 'داخل التبريد حتى '.$until->format('Y-m-d'),
                'copy' => str_replace('{date}', $until->format('Y-m-d'), (string) setting('volunteer.offboarding.cooldown_copy', '')),
            ];
        }

        return ['state' => 'ok', 'label' => 'العودة متاحة', 'copy' => ''];
    }

    /** فتح ملفّ عودة — الامتحان إجباريّ والشهادة القديمة تصير «منتهية» */
    public static function openReentry(User $user, ?Offboarding $record, User $actor): Reentry
    {
        $reentry = Reentry::create([
            'user_id' => $user->id,
            'offboarding_id' => $record?->id,
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        AuditTrail::log($actor, 'reentry.open', $reentry, [], [
            'exam_required' => (bool) setting('volunteer.offboarding.reentry_exam_required', true),
            'starts_position' => (string) setting('volunteer.offboarding.reentry_starts_position', 'coordinator'),
        ]);

        return $reentry;
    }

    private static function normalizeChecklist(array $checked): array
    {
        $out = [];

        foreach (self::clearanceItems() as $index => $label) {
            $out[] = ['label' => $label, 'done' => (bool) ($checked[$index] ?? false)];
        }

        return $out;
    }
}
