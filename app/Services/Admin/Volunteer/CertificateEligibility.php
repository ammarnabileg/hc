<?php

namespace App\Services\Admin\Volunteer;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Membership;
use App\Models\User;
use App\Services\Certificates\CertificateRenderer;
use App\Support\Scope\ScopeFilter;
use Illuminate\Support\Collection;
use Throwable;

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

        // ⭐ «أخوكم» لا يُصدَر له شهادة بوزشن تطوّع (13.4-ص-ج) — مكانه شرفيّ لا وظيفيّ
        if ($position?->is_honorary) {
            return [
                'eligible' => false,
                'days' => $days,
                'reason' => 'العنصر الشرفيّ مكانه تقديريّ لا بوزشن تطوّع — ولا تُصدَر له شهادة بوزشن.',
            ];
        }

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

    /**
     * ⭐ **شهادة خبرة التطوّع عند الخروج المشرَّف** (13.4-س-ط · 13.4-ع-أ-2):
     * تجمع **كلّ البوزشنات والمدّة في ورقة واحدة**، وتُصدَر في **الاستقالة
     * وانتهاء الملفّ فقط لا في الإقصاء**.
     *
     * كان `honorable_certificate_issued = 1` يُكتَب على ملفّ الخروج بينما عدد
     * شهادات `volunteer_experience` **صفر** — علَمٌ يقول «صدرت» وسجلٌّ خالٍ.
     *
     * والمدّة **تراكميّة** عبر فترات الخدمة كلّها (13.4-ع-هـ): 8 شهور + 5 = 13.
     *
     * @return array{issued:bool,reason:?string,certificate:?Certificate}
     */
    public static function issueExperience(User $user, ?User $actor = null): array
    {
        $flags = setting('volunteer_cert.types', []);

        if (is_array($flags) && array_key_exists('volunteer_experience', $flags) && ! $flags['volunteer_experience']) {
            return ['issued' => false, 'reason' => 'نوع شهادة الخبرة موقوف من الإعدادات.', 'certificate' => null];
        }

        $type = CertificateType::query()->where('key', 'volunteer_experience')->first();

        if (! $type) {
            return ['issued' => false, 'reason' => 'نوع شهادة الخبرة غير مُعرَّف.', 'certificate' => null];
        }

        $memberships = Membership::query()
            ->with(['position', 'entity'])
            ->where('user_id', $user->id)
            ->orderBy('started_at')
            ->get();

        if ($memberships->isEmpty()) {
            return ['issued' => false, 'reason' => 'لا عضويّات في سجلّه — لا مدّة خدمة تُشهَد.', 'certificate' => null];
        }

        // شهادة خبرة واحدة سارية لكلّ متطوّع — والعودة تُجدّدها بمدّة تراكميّة
        Certificate::query()
            ->where('user_id', $user->id)
            ->where('certificate_type_id', $type->id)
            ->where('status', 'valid')
            ->update(['status' => 'expired', 'expired_at' => now()]);

        $days = 0;
        $positions = [];

        foreach ($memberships as $membership) {
            $start = $membership->started_at ?? $membership->created_at;
            $end = $membership->ended_at ?? now();
            $days += $start ? (int) $start->diffInDays($end) : 0;

            $positions[] = [
                'position' => $membership->position?->name_ar,
                'entity' => $membership->entity?->name_ar,
                'from' => $start?->toDateString(),
                'to' => $end?->toDateString(),
            ];
        }

        // ⛔ بلا أيّ أرقام داخليّة على الشهادة — لا Rep ولا VXP (13.4-ع-ب)
        $snapshot = [
            'positions' => $positions,
            'from' => $positions[0]['from'] ?? null,
            'to' => $positions[count($positions) - 1]['to'] ?? null,
            'total_days' => $days,
            'total_months' => (int) round($days / 30),
        ];

        $last = $memberships->last();

        $certificate = self::issueViaIssuer($last, $type, $snapshot, $actor)
            ?? self::issueDirectly($last, $type, $snapshot, $actor);

        if ((bool) setting('volunteer_cert.notify_on_issue', true)) {
            Integrations::notify(
                $user, 'certificate',
                (string) setting('volunteer_cert.experience.notify_title', 'شهادة خبرة التطوّع بتاعتك صدرت 🎖️'),
                (string) setting('volunteer_cert.experience.notify_body', 'شكرًا على كلّ اللي قدّمته — الشهادة في مكتبتك وبتفضل سارية للأبد.'),
                null, 'volunteer',
            );
        }

        AuditTrail::log($actor, 'volunteer_certificate.experience_issued', $certificate, [], [
            'user_id' => $user->id,
            'total_days' => $days,
        ]);

        return ['issued' => true, 'reason' => null, 'certificate' => $certificate];
    }

    private const ISSUER = 'App\Services\Certificates\CertificateIssuer';

    /** التمرير لمُصدِر الشهادات القائم إن وُجد — بلا نظام موازٍ (13.4-ع) */
    private static function issueViaIssuer(Membership $membership, CertificateType $type, array $snapshot, ?User $actor): ?Certificate
    {
        if (! class_exists(self::ISSUER) || ! $membership->user) {
            return null;
        }

        try {
            return app(self::ISSUER)->issue(
                $membership->user, $type->key, $membership, $snapshot,
                $actor ? 'manual' : 'auto', null, $actor,
            );
        } catch (Throwable) {
            return null;
        }
    }

    private static function issueDirectly(Membership $membership, CertificateType $type, array $snapshot, ?User $actor): Certificate
    {
        $code = ($type->numbering_prefix ?: 'VPS').'-'.str()->upper(str()->random(10));

        return Certificate::create([
            'code' => $code,
            'hash' => hash('sha256', $code.'|'.$membership->user_id.'|'.$membership->position_id.'|'.$membership->entity_id),
            'user_id' => $membership->user_id,
            'certificate_type_id' => $type->id,
            'subject_type' => $membership->getMorphClass(),
            'subject_id' => $membership->id,
            'language' => $type->lang_en_enabled && ! $type->lang_ar_enabled ? 'en' : 'ar',
            'source' => $actor ? 'manual' : 'auto',
            'issued_at' => now(),
            'issued_by' => $actor?->id,
            'status' => 'valid',
            'data_snapshot' => $snapshot,
        ]);
    }

    /**
     * الإصدار التلقائيّ عند الاستيفاء (13.4-ع-د) — يمرّ على المستحقّين
     * ويُصدر لهم، ويعيد عدد ما صدر.
     */
    public static function autoIssue(?User $actor = null, int $limit = 100): int
    {
        if (! (bool) setting('volunteer_cert.auto_issue', true)) {
            return 0;
        }

        $issued = 0;

        foreach (self::pending($limit) as $row) {
            if (self::issueForMembership($row['membership'], $actor)['issued']) {
                $issued++;
            }
        }

        return $issued;
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

        // الصورة المرسومة تحمل حالتها، فالملغاة لا يجوز أن تُقدَّم من كاشٍ «سارية» (8.1)
        app(CertificateRenderer::class)->forget($certificate);

        AuditTrail::log($actor, 'volunteer_certificate.revoke', $certificate, [], ['reason' => $reason]);

        return true;
    }

    /** المستحقّون الذين لم تُصدَر لهم بعد — مادّة تاب «مستحقّ ولم تُصدَر» */
    /**
     * المستحقّون لشهادة بوزشن — ومع `$viewer` تُحصَر القائمة **بنطاقه** (12.2.1-ب)،
     * فلا يرى أحدٌ عضويّاتِ كيانٍ ليس له عليه سلطان.
     */
    public static function pending(int $limit = 50, ?User $viewer = null): Collection
    {
        return Membership::query()
            ->when($viewer !== null, fn ($q) => app(ScopeFilter::class)->apply($q, $viewer, 'volunteer_certificates.view', 'user_id', 'entity_id'))
            ->with(['user:id,name,code', 'entity:id,name_ar', 'position'])
            ->where('status', 'active')
            ->limit((int) setting('volunteer_cert.pending_scan_limit', 200))
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
