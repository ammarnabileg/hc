<?php

namespace App\Services\Volunteer\Tasks;

/**
 * حالات المهمّة (الدستور 23-3.3):
 * قيد التنفيذ ⟵ (متعثّرة) ⟵ مُسلَّمة/قيد المراجعة ⟵ معتمدة أو مُرجَعة ⟵ (عدم تسليم) ⟵ مُغلَقة.
 *
 * ولكلّ حالة لون بمعنًى واحد ورمز معه دائمًا (2.16) — واللون وحده لا يحمل المعنى.
 */
class TaskStatus
{
    public const IN_PROGRESS = 'in_progress';

    public const BLOCKED = 'blocked';

    public const DELIVERED = 'delivered';

    public const RETURNED = 'returned';

    public const APPROVED = 'approved';

    public const NO_DELIVERY = 'no_delivery';

    public const CLOSED = 'closed';

    /** الحالات التي تشغل مكانًا في سقف الانشغال (23-3.1: «غير مكتملة في نفس الوقت») */
    public const OPEN = [self::IN_PROGRESS, self::BLOCKED, self::DELIVERED, self::RETURNED];

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::IN_PROGRESS => setting('workflow.task_status.labels_1', 'قيد التنفيذ'),
            self::BLOCKED => setting('workflow.task_status.labels_2', 'متعثّرة'),
            self::DELIVERED => setting('workflow.task_status.labels_3', 'مُسلَّمة / قيد المراجعة'),
            self::RETURNED => setting('workflow.task_status.labels_4', 'مُرجَعة'),
            self::APPROVED => setting('workflow.task_status.labels_5', 'معتمدة'),
            self::NO_DELIVERY => setting('workflow.task_status.labels_6', 'عدم تسليم'),
            self::CLOSED => setting('workflow.task_status.labels_7', 'مُغلَقة'),
        ];
    }

    public static function label(?string $status): string
    {
        return self::labels()[$status] ?? setting('workflow.task_status.label_1', 'غير معروفة');
    }

    /** حالة العرض في قاموس الألوان (2.16) */
    public static function state(?string $status): string
    {
        return match ($status) {
            self::APPROVED => 'ok',
            self::DELIVERED, self::BLOCKED => 'warn',
            self::RETURNED, self::NO_DELIVERY => 'danger',
            self::CLOSED => 'idle',
            default => 'ok',
        };
    }

    /** أعمدة الكانبان بترتيب دورة العمل */
    public static function boardColumns(): array
    {
        return [
            self::IN_PROGRESS, self::BLOCKED, self::DELIVERED,
            self::RETURNED, self::APPROVED, self::NO_DELIVERY, self::CLOSED,
        ];
    }
}
