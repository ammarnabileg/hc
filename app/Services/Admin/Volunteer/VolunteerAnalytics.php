<?php

namespace App\Services\Admin\Volunteer;

use App\Models\BehaviorTransaction;
use App\Models\Membership;
use App\Models\Offboarding;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * تحليلات التطوّع — **مادّة قرار لا أرقام زينة**:
 * تسرّب · أحمال · صحّة الكيانات · تقرير سعة.
 */
class VolunteerAnalytics
{
    public static function rangeDays(?int $days = null): int
    {
        return $days ?: (int) setting('volunteer.analytics.default_range_days', 30);
    }

    /** كروت الأربعة الأعلى — والحدّ الأقصى أربعة في الشاشة (2.15-أ-3) */
    public static function kpis(int $days): array
    {
        $active = Membership::query()->where('status', 'active')->distinct('user_id')->count('user_id');

        return [
            'active' => $active,
            'vacancies' => CapacityReport::rows()->sum('vacancies'),
            'overflows' => CapacityReport::overflows()->count(),
            'exits' => Offboarding::query()->where('created_at', '>=', now()->subDays($days))->count(),
        ];
    }

    /** أسباب التسرّب من مقابلات الخروج ومن نوع الخروج نفسه */
    public static function attrition(int $days): Collection
    {
        return Offboarding::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('type, COUNT(*) as c')
            ->groupBy('type')
            ->pluck('c', 'type')
            ->map(fn ($count, $type) => [
                'label' => OffboardingService::types()[$type] ?? $type,
                'count' => $count,
            ])
            ->values();
    }

    /**
     * أحمال الفرق: عدد المهامّ المفتوحة لكلّ عضويّة مقابل سقف انشغال بوزشنه.
     * سقف الانشغال = عدد مهامّ (23-3.1) — مستقلّ تمامًا عن السعة (عدد أشخاص).
     */
    public static function loads(): Collection
    {
        $open = class_exists(Task::class)
            ? Task::query()
                ->whereNotIn('status', ['approved', 'closed', 'cancelled'])
                ->selectRaw('owner_id, COUNT(*) as c')
                ->groupBy('owner_id')
                ->pluck('c', 'owner_id')
            : collect();

        return Membership::query()
            ->with(['user:id,name,code', 'entity:id,name_ar', 'position'])
            ->where('status', 'active')
            ->get()
            ->map(function (Membership $m) use ($open) {
                $count = (int) ($open[$m->user_id] ?? 0);
                $cap = $m->position?->task_load_cap;
                $alert = (int) setting('volunteer.analytics.load_alert_tasks', 10);

                return [
                    'membership' => $m,
                    'open' => $count,
                    'cap' => $cap,
                    'state' => match (true) {
                        $cap !== null && $count > $cap => 'danger',
                        $count >= $alert => 'warn',
                        default => 'ok',
                    },
                ];
            })
            ->sortByDesc('open')
            ->take((int) setting('volunteer.analytics.capacity_rows', 20))
            ->values();
    }

    /** رقابة المانحين: عدد معاملات السلوك لكلّ مشرف — المفرِط مكشوف تلقائيًّا */
    public static function granterWatch(int $days): Collection
    {
        return BehaviorTransaction::query()
            ->with('granted_by:id,name,code')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('granted_by, COUNT(*) as c, SUM(CASE WHEN status = \'reversed\' THEN 1 ELSE 0 END) as reversed')
            ->groupBy('granted_by')
            ->orderByDesc('c')
            ->limit((int) setting('volunteer.analytics.top_rows', 10))
            ->get();
    }

    /** صحّة الكيانات: غير الصحّيّ (تحت الأدنى) والمتجاوز (فوق الأقصى) */
    public static function entityHealth(): array
    {
        return [
            'unhealthy' => CapacityReport::unhealthy(),
            'overflows' => CapacityReport::overflows(),
        ];
    }
}
