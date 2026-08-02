<?php

namespace App\Services\Admin\Volunteer;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * شهادات التطوّع (13.4-ع).
 *
 * شرطا الاستحقاق (مانع التضخّم):
 *  1) `setting('volunteer_cert.min_days_in_position')` يومًا على الأقلّ في البوزشن.
 *  2) Rep **غير سالب** وقت الإصدار.
 * وشهادة واحدة لكلّ (بوزشن × كيان) — والترقية تُصدر الأعلى لا نسخة مكرّرة.
 */
class CertificateEligibility
{
    /** الأنواع الأربعة لا خامس لها */
    public const TYPES = [
        'volunteer_position' => 'شهادة بوزشن',
        'volunteer_experience' => 'شهادة خبرة تطوّع',
        'volunteer_case_file' => 'شهادة مشاركة في ملفّ',
        'volunteer_appreciation' => 'شهادة تقدير استثنائيّة',
    ];

    /** الحدّ الأدنى للمدّة — عامّ، ويجوز تخصيصه لكلّ بوزشن */
    public static function minDays(?string $positionKey = null): int
    {
        $perPosition = setting('volunteer_cert.min_days_by_position', []);

        if ($positionKey && is_array($perPosition) && isset($perPosition[$positionKey])) {
            return (int) $perPosition[$positionKey];
        }

        return (int) setting('volunteer_cert.min_days_in_position', 30);
    }

    /** فحص استحقاق عضويّة لشهادة بوزشن — يعيد السبب حين لا تستحقّ */
    public static function check(Membership $membership): array
    {
        $position = $membership->position;
        $minDays = self::minDays($position?->key);

        $start = $membership->started_at ?? $membership->created_at;
        $end = $membership->ended_at ?? now();
        $days = $start ? (int) $start->diffInDays($end) : 0;

        if ($days < $minDays) {
            return [
                'eligible' => false,
                'days' => $days,
                'reason' => 'المدّة في البوزشن '.$days.' يومًا، والمطلوب '.$minDays.' يومًا على الأقلّ.',
            ];
        }

        if ((bool) setting('volunteer_cert.require_non_negative_rep', true)) {
            $user = $membership->user;
            $rep = $user ? Integrations::balance($user, BehaviorLedger::REP) : 0.0;

            if ($rep < 0) {
                return [
                    'eligible' => false,
                    'days' => $days,
                    'reason' => 'درجة الالتزام سالبة الآن ('.number_format($rep, 2).') — الشهادة تتطلّب رقمًا غير سالب.',
                ];
            }
        }

        if (self::alreadyIssued($membership)) {
            return [
                'eligible' => false,
                'days' => $days,
                'reason' => 'صدرت شهادة لهذا البوزشن في هذا الكيان من قبل — والترقية تُصدر الأعلى لا نسخة مكرّرة.',
            ];
        }

        return ['eligible' => true, 'days' => $days, 'reason' => null];
    }

    /** شهادة واحدة لكلّ (بوزشن × كيان) */
    public static function alreadyIssued(Membership $membership): bool
    {
        if (! (bool) setting('volunteer_cert.one_per_position_entity', true)) {
            return false;
        }

        $typeId = CertificateType::query()->where('key', 'volunteer_position')->value('id');

        if (! $typeId) {
            return false;
        }

        return Certificate::query()
            ->where('user_id', $membership->user_id)
            ->where('certificate_type_id', $typeId)
            ->where('status', '!=', 'revoked')
            ->where('data_snapshot->position_id', $membership->position_id)
            ->where('data_snapshot->entity_id', $membership->entity_id)
            ->exists();
    }

    /**
     * إصدار شهادة بوزشن — يحترم الشرطين، وبلا أيّ أرقام داخليّة على الشهادة
     * (لا Rep ولا VXP — 13.4-ع-ب)، مع احتفال ذروة وإشعار عند التفعيل.
     *
     * @return array{issued:bool,reason:?string,certificate:?Certificate}
     */
    public static function issueForMembership(Membership $membership, ?User $actor = null, bool $force = false): array
    {
        $check = self::check($membership);

        if (! $check['eligible'] && ! $force) {
            return ['issued' => false, 'reason' => $check['reason'], 'certificate' => null];
        }

        $type = CertificateType::query()->where('key', 'volunteer_position')->first();

        if (! $type) {
            return ['issued' => false, 'reason' => 'نوع شهادة البوزشن غير مُعرَّف.', 'certificate' => null];
        }

        $start = $membership->started_at ?? $membership->created_at;
        $end = $membership->ended_at ?? now();

        /*
         | بيانات الشهادة — وبلا أيّ أرقام داخليّة (لا Rep ولا VXP)، فهي لغة
         | داخليّة لا معنى لها خارجًا وقد تضرّ صاحبها (13.4-ع-ب).
         */
        $snapshot = [
            'position_id' => $membership->position_id,
            'position' => $membership->position?->name_ar,
            'entity_id' => $membership->entity_id,
            'entity' => $membership->entity?->name_ar,
            'from' => $start?->toDateString(),
            'to' => $end?->toDateString(),
            'days' => $check['days'],
            'team_size' => $membership->id
                ? Membership::query()->where('upline_id', $membership->id)->where('status', 'active')->count()
                : 0,
        ];

        // مصدرٌ واحد للإصدار: نمرّر لمُصدِر الشهادات القائم متى وُجد (كود + Hash
        // + تجميد نسخة القالب + لحظة الذروة)، وإلّا نكتب البديل الآمن بنفس الأثر.
        $certificate = self::issueViaIssuer($membership, $type, $snapshot, $actor)
            ?? self::issueDirectly($membership, $type, $snapshot, $actor);

        if ((bool) setting('volunteer_cert.notify_on_issue', true)) {
            $user = $membership->user;

            if ($user) {
                Integrations::notify(
                    $user, 'certificate', 'صدرت شهادة تطوّعك 🎖️',
                    'شهادة '.($membership->position?->name_ar ?? '').' — '.($membership->entity?->name_ar ?? ''),
                    null, 'volunteer',
                );
            }
        }

        AuditTrail::log($actor, 'volunteer_certificate.issue', $certificate, [], [
            'membership_id' => $membership->id,
            'celebration_tier' => (int) setting('volunteer_cert.celebration_tier', 3),
        ]);

        return ['issued' => true, 'reason' => null, 'certificate' => $certificate];
    }

    /** ⭐ الإلغاء للتزوير المثبَت وحده — والإقصاء لا يُلغي شهادة عن عمل حقيقيّ */
    public static function revoke(Certificate $certificate, string $reason, ?User $actor = null): bool
    {
        if (! (bool) setting('volunteer_cert.revoke_only_on_fraud', true)) {
            return false;
        }

        $certificate->forceFill([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_reason' => $reason,
        ])->save();

        AuditTrail::log($actor, 'volunteer_certificate.revoke', $certificate, [], ['reason' => $reason]);

        return true;
    }

    /** المستحقّون الذين لم تُصدَر لهم بعد — مادّة تاب «مستحقّ ولم تُصدَر» */
    public static function pending(int $limit = 50): Collection
    {
        return Membership::query()
            ->with(['user:id,name,code', 'entity:id,name_ar', 'position'])
            ->where('status', 'active')
            ->limit(200)
            ->get()
            ->map(fn (Membership $m) => ['membership' => $m] + self::check($m))
            ->filter(fn ($row) => $row['eligible'])
            ->take($limit)
            ->values();
    }

    /** أنواع الشهادات المفعّلة من الإعدادات */
    public static function enabledTypes(): array
    {
        $flags = setting('volunteer_cert.types', []);
        $flags = is_array($flags) ? $flags : [];

        $out = [];

        foreach (self::TYPES as $key => $label) {
            $out[$key] = ['label' => $label, 'enabled' => (bool) ($flags[$key] ?? true)];
        }

        return $out;
    }
}
