<?php

namespace App\Services\Admin\Volunteer;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * سجلّ التدقيق لكلّ تغيير حسّاس في لوحة إدارة التطوّع (2.13-و).
 *
 * لماذا خدمة مستقلّة؟ لأنّ كلّ شاشة هنا تُغيّر قواعد المنصّة نفسها (قيم Rep ·
 * إعدادات الحروب · منح الأرصدة)، فلا بدّ من أثرٍ موحّد الشكل يُقرأ لاحقًا
 * بلا اجتهاد كلّ شاشة على حدة.
 */
class AuditTrail
{
    public static function log(?User $actor, string $action, ?Model $subject = null, array $old = [], array $new = []): void
    {
        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $subject ? $subject->getMorphClass() : 'settings',
            'auditable_id' => $subject?->getKey() ?? 0,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255) ?: null,
        ]);
    }

    /** آخر حركات التدقيق على مورد — تُعرَض كشارة «آخر تعديل: مَن/متى» */
    public static function latest(string $action, int $limit = 10)
    {
        return AuditLog::query()
            ->with('user:id,name,code')
            ->where('action', 'like', $action.'%')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
