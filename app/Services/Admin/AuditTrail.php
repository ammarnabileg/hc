<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * سجلّ التدقيق (12.2.1-ز-4 · 2.13-هـ).
 *
 * لماذا خدمة واحدة؟ لأنّ قاعدة الدستور «Audit على **كلّ** تغيير صلاحيّة أو إسناد»
 * لا تُنفَّذ لو كتب كلّ كنترولر سطره بطريقته — فتختلف الأفعال ولا يُقرأ السجلّ.
 * والعرض في الواجهة **آخر تغيير فقط** بالـHover مع رابط لبروفايل المحرّر.
 */
class AuditTrail
{
    /** أفعال هذا المجال — قائمة مقفولة تُقرأ في الفلاتر */
    public const ACTIONS = [
        'role.created' => 'إنشاء دور',
        'role.deleted' => 'حذف دور',
        'role.permissions.updated' => 'تعديل صلاحيّات دور',
        'role.assigned' => 'إسناد دور',
        'role.unassigned' => 'سحب دور',
        'permission.user.updated' => 'استثناء صلاحيّة لمستخدم',
        'user.approved' => 'اعتماد حساب',
        'user.rejected' => 'رفض حساب',
        'segment.created' => 'إنشاء شريحة',
        'segment.deleted' => 'حذف شريحة',
    ];

    public function record(?User $actor, string $action, Model $subject, array $old = [], array $new = []): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    /** آخر تغيير على هذا السجلّ — وهو وحده ما يُعرَض بالـHover (لا سجلّ كامل) */
    public function lastChange(Model $subject, ?array $actions = null): ?AuditLog
    {
        return AuditLog::query()
            ->with('user')
            ->where('auditable_type', $subject->getMorphClass())
            ->where('auditable_id', $subject->getKey())
            ->when($actions, fn ($q) => $q->whereIn('action', $actions))
            ->latest('id')
            ->first();
    }

    /** آخر تغيير لمجموعة سجلّات دفعةً واحدة — منعًا لاستعلام لكلّ صفّ */
    public function lastChangeFor(string $morphClass, array $ids, ?array $actions = null): array
    {
        if ($ids === []) {
            return [];
        }

        return AuditLog::query()
            ->with('user')
            ->where('auditable_type', $morphClass)
            ->whereIn('auditable_id', $ids)
            ->when($actions, fn ($q) => $q->whereIn('action', $actions))
            ->orderByDesc('id')
            ->get()
            ->unique('auditable_id')
            ->keyBy('auditable_id')
            ->all();
    }

    /**
     * سجلّ النشاطات مفلتَرًا بالموظّف والنوع **والفترة** (12.3-20).
     *
     * والفترة اختياريّة حتى يبقى النداء القديم (ثلاثة معاملات) سليمًا،
     * ويستهلكها التصدير والشاشة معًا فيخرج الملفّ بنفس ما تراه العين.
     */
    public function feed(?int $userId = null, ?string $action = null, int $limit = 10, mixed $from = null, mixed $to = null)
    {
        return AuditLog::query()
            ->with('user')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($from && $to, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public static function label(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }
}
