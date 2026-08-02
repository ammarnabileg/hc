<?php

namespace App\Services\Admin\Volunteer;

use App\Models\BehaviorTransaction;
use App\Models\BehaviorViolation;
use App\Models\Membership;
use App\Models\User;
use App\Support\Access\AccessEngine;
use RuntimeException;

/**
 * معاملة السلوك اليدويّة (13.4-ن-هـ) — المنفذ الوحيد لتقدير بشريّ على Rep،
 * ولذلك مُقيَّد: نوع مخالفة من قائمة مكوَّدة + مبرّر مكتوب إلزاميّ + سقف شهريّ
 * للمانح + معاينة الأثر قبل الحفظ + المخالفة الجسيمة تنتظر موافقة أعلى.
 */
class BehaviorLedger
{
    public const REP = 'rep';

    /** معاينة الأثر قبل الحفظ: «Rep ينزل من كذا إلى كذا» */
    public static function preview(User $target, float $value): array
    {
        $before = Integrations::balance($target, self::REP);
        $min = (float) setting('rep.display.min', -10);
        $max = (float) setting('rep.display.max', 10);
        $after = max($min, min($max, round($before + $value, 2)));

        return [
            'before' => round($before, 2),
            'value' => round($value, 2),
            'after' => $after,
            'clamped' => abs(($before + $value) - $after) > 0.0001,
        ];
    }

    /** كم معاملة منحها هذا المانح في الشهر الجاري — أساس السقف الشهريّ */
    public static function grantedThisMonth(User $granter): int
    {
        return BehaviorTransaction::query()
            ->where('granted_by', $granter->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->whereIn('status', ['applied', 'pending_approval'])
            ->count();
    }

    public static function monthlyCap(): int
    {
        return (int) setting('rep.behavior.monthly_cap_per_granter', 5);
    }

    /** الأدمن ومشرف عام التطوّع بلا سقف (13.4-ن-هـ) */
    public static function isUncapped(User $granter): bool
    {
        return app(AccessEngine::class)->allows($granter, 'rep_manual.approve');
    }

    public static function remainingQuota(User $granter): ?int
    {
        if (self::isUncapped($granter)) {
            return null; // بلا سقف
        }

        return max(0, self::monthlyCap() - self::grantedThisMonth($granter));
    }

    /**
     * تسجيل معاملة سلوك.
     *
     * @throws RuntimeException عند غياب المبرّر أو تجاوز السقف الشهريّ
     */
    public static function record(
        User $granter,
        User $target,
        BehaviorViolation $violation,
        string $justification,
        ?string $attachmentPath = null,
        ?Membership $membership = null,
    ): BehaviorTransaction {
        $minChars = (int) setting('rep.behavior.justification_min_chars', 10);

        if (mb_strlen(trim($justification)) < $minChars) {
            throw new RuntimeException('المبرّر إلزاميّ — اكتب سببًا واضحًا لا يقلّ عن '.$minChars.' حرفًا.');
        }

        $remaining = self::remainingQuota($granter);

        if ($remaining !== null && $remaining <= 0) {
            throw new RuntimeException('وصلت للسقف الشهريّ ('.self::monthlyCap().' معاملات). تقدر تكمّل الشهر الجاي أو ترفع الأمر لمستوى أعلى.');
        }

        // القيمتان لا غيرهما: تنبيه (−0.5) وجسيمة (−1) — تُقرآن من جدول Rep
        $value = (float) $violation->default_value;
        $severe = (bool) $violation->requires_higher_approval;

        $status = $severe ? 'pending_approval' : 'applied';

        $record = BehaviorTransaction::create([
            'user_id' => $target->id,
            'membership_id' => $membership?->id,
            'granted_by' => $granter->id,
            'behavior_violation_id' => $violation->id,
            'value' => $value,
            'justification' => trim($justification),
            'attachment_path' => $attachmentPath,
            'status' => $status,
        ]);

        // التنبيه يُنفَّذ فورًا، والجسيمة تنتظر موافقة المستوى الأعلى (نافذة 24 ساعة)
        if (! $severe) {
            $transaction = Integrations::post(
                $target, self::REP, $value, 'behavior',
                $violation->label_ar.' — '.$record->justification,
                $granter, $record, 'volunteer',
            );

            if ($transaction) {
                $record->transaction_id = $transaction->id;
                $record->save();
            }

            // إشعار فوريّ للعضو بالنوع والمبرّر — لا مفاجآت (13.4-ن-هـ)
            Integrations::notify(
                $target, 'objection', 'معاملة سلوك على درجة الالتزام',
                $violation->label_ar.' — '.$record->justification, null, 'volunteer',
            );
        }

        AuditTrail::log($granter, 'behavior.record', $record, [], [
            'target' => $target->id, 'violation' => $violation->code, 'value' => $value, 'status' => $status,
        ]);

        return $record;
    }

    /** اعتماد المخالفة الجسيمة من مستوى أعلى ⟵ تُطبَّق على Rep */
    public static function approve(User $approver, BehaviorTransaction $record): void
    {
        if ($record->status !== 'pending_approval') {
            return;
        }

        $target = $record->user()->firstOrFail();

        $transaction = Integrations::post(
            $target, self::REP, (float) $record->value, 'behavior',
            $record->justification, $approver, $record, 'volunteer',
        );

        $record->forceFill([
            'status' => 'applied',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'transaction_id' => $transaction?->id,
        ])->save();

        Integrations::notify($target, 'objection', 'اعتُمدت معاملة سلوك على درجة الالتزام', $record->justification, null, 'volunteer');

        AuditTrail::log($approver, 'behavior.approve', $record, [], ['id' => $record->id]);
    }
}
