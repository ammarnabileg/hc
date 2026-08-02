<?php

namespace App\Services\Admin\Ops;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * أثر كلّ عمليّة نظام في `audit_logs` (12.7-هـ · 24.3 سجلّ التدقيق).
 *
 * لماذا صنف صغير مستقلّ؟ لأنّ عمليّات النظام (ترحيل · استرجاع · نسخة احتياطيّة)
 * ليست تعديلًا على سجلّ في جدول، فليس لها «Model» تُعلَّق عليه — ومع ذلك
 * **لا يجوز أن تمرّ بلا تسجيل مَن نفّذها ومتى ومن أيّ IP**. فنكتب مباشرةً
 * في نفس الجدول الذي تقرأه صفحة سجلّ التدقيق — مصدر واحد لا سجلّان.
 */
class OpsAudit
{
    /** أفعال هذا المجال — قائمة مقفولة تُقرأ في الفلاتر وفي صفحة التدقيق */
    public const ACTIONS = [
        'ops.onboarding.slide.created' => 'إضافة شريحة ترحيب',
        'ops.onboarding.slide.updated' => 'تعديل شريحة ترحيب',
        'ops.onboarding.slide.deleted' => 'حذف شريحة ترحيب',
        'ops.onboarding.slide.toggled' => 'تفعيل/إيقاف شريحة',
        'ops.onboarding.reordered' => 'إعادة ترتيب الشرائح',
        'ops.onboarding.template.applied' => 'تطبيق قالب جاهز',
        'ops.onboarding.first_time.updated' => 'تعديل شاشات «أوّل مرّة»',
        'ops.updates.dry_run' => 'Dry-run للترحيل',
        'ops.updates.migrated' => 'تنفيذ الترحيل',
        'ops.updates.rolled_back' => 'استرجاع آخر دفعة',
        'ops.updates.version_recorded' => 'تسجيل إصدار',
        'ops.backups.created' => 'نسخة احتياطيّة',
        'ops.backups.deleted' => 'حذف نسخة احتياطيّة',
        'ops.backups.downloaded' => 'تنزيل نسخة احتياطيّة',
        'ops.backups.schedule_updated' => 'تعديل جدولة النسخ',
        'ops.system.health_checked' => 'تشغيل فحص صحّة',
        'ops.system.alert' => 'تنبيه نظام استباقيّ',
    ];

    /** أفعال الترحيل وحدها — صفحة التحديثات تعرض سجلّها فقط */
    public const UPDATE_ACTIONS = [
        'ops.updates.dry_run',
        'ops.updates.migrated',
        'ops.updates.rolled_back',
        'ops.updates.version_recorded',
    ];

    /** أفعال النظام والنسخ — صفحة النسخ وصحّة النظام تعرض سجلّها فقط */
    public const SYSTEM_ACTIONS = [
        'ops.backups.created',
        'ops.backups.deleted',
        'ops.backups.downloaded',
        'ops.backups.schedule_updated',
        'ops.system.health_checked',
        'ops.system.alert',
    ];

    /**
     * تسجيل عمليّة. `subjectType` نصّ حرّ لأنّ عمليّات النظام ليست سجلًّا في جدول،
     * و`subjectId` صفر حين لا يوجد سجلّ بعينه.
     */
    public function record(
        ?User $actor,
        string $action,
        array $details = [],
        string $subjectType = 'ops.system',
        int $subjectId = 0,
        array $before = [],
    ): int {
        $request = request();

        return (int) DB::table('audit_logs')->insertGetId([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $subjectType,
            'auditable_id' => $subjectId,
            'old_values' => $before === [] ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            'new_values' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * سجلّ مفلتَر بأفعال هذا المجال — للقراءة فقط، Append-only بلا تعديل ولا حذف.
     *
     * @param  array<int, string>  $actions
     */
    public function feed(array $actions, int $perPage, string $pageName = 'page'): LengthAwarePaginator
    {
        return DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->whereIn('audit_logs.action', $actions)
            ->orderByDesc('audit_logs.id')
            ->select([
                'audit_logs.id',
                'audit_logs.action',
                'audit_logs.new_values',
                'audit_logs.ip',
                'audit_logs.created_at',
                'users.name as actor_name',
                'users.code as actor_code',
            ])
            ->paginate(max(1, $perPage), ['*'], $pageName);
    }

    public static function label(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    /** ملخّص قصير يُعرَض في عمود «التفاصيل» بلا فتح أيّ نافذة */
    public static function summarize(?string $json): string
    {
        $data = json_decode((string) $json, true);

        if (! is_array($data) || $data === []) {
            return '—';
        }

        $parts = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = count($value).' عنصر';
            }

            $parts[] = $key.': '.mb_substr((string) $value, 0, 40);
        }

        return implode(' · ', array_slice($parts, 0, 3));
    }
}
