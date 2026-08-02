<?php

namespace App\Services\Admin\Volunteer;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use Illuminate\Support\Collection;

/**
 * سعة الأقسام (13.4-ف) — **مؤشّرات لا موانع**: لا تُوقِف تسكينًا ولا ترقيةً.
 * الغاية توازن الأحمال ودعم القرار، فالمتجاوز يظهر ولا يُعطَّل.
 */
class CapacityReport
{
    /** لون الإشغال بعتباته المضبوطة من الإعدادات (لا رقم محروق) */
    public static function occupancyState(float $percent): string
    {
        $warn = (float) setting('volunteer.org.occupancy_warn_percent', 80);
        $danger = (float) setting('volunteer.org.occupancy_danger_percent', 100);

        return match (true) {
            $percent >= $danger => 'danger',
            $percent >= $warn => 'warn',
            default => 'ok',
        };
    }

    /** سقف أعضاء الكيان — يُحسَب تلقائيًّا من الهيكل ويظلّ قابلًا للتعديل يدويًّا */
    public static function computedCap(Entity $entity): int
    {
        if ($entity->member_cap) {
            return (int) $entity->member_cap;
        }

        $factor = max(1, (int) setting('volunteer.org.member_cap_per_span_factor', 2));

        $sum = (int) Position::query()
            ->whereNotNull('span_default')
            ->sum('span_default');

        return max(1, $sum * $factor);
    }

    /** صفّ تقرير السعة لكلّ كيان: الأعضاء · السقف · الإشغال · الشواغر */
    public static function rows(?int $trackId = null): Collection
    {
        $counts = Membership::query()
            ->where('status', 'active')
            ->selectRaw('entity_id, COUNT(*) as members')
            ->groupBy('entity_id')
            ->pluck('members', 'entity_id');

        return Entity::query()
            ->with('track')
            ->when($trackId, fn ($q) => $q->where('track_id', $trackId))
            ->where('status', 'active')
            ->orderBy('name_ar')
            ->get()
            ->map(function (Entity $entity) use ($counts) {
                $members = (int) ($counts[$entity->id] ?? 0);
                $cap = self::computedCap($entity);
                $percent = $cap > 0 ? round($members / $cap * 100, 1) : 0.0;

                return [
                    'entity' => $entity,
                    'members' => $members,
                    'cap' => $cap,
                    'percent' => $percent,
                    'vacancies' => max(0, $cap - $members),
                    'state' => self::occupancyState($percent),
                ];
            });
    }

    /**
     * نطاق الإشراف الفعليّ لكلّ عضويّة مقابل مدى بوزشنه.
     * التجاوز **تنبيه** يظهر عند الأبلاين الأعلى — بلا منع ولا مبرّر إلزاميّ.
     */
    public static function spanRows(): Collection
    {
        $downlines = Membership::query()
            ->where('status', 'active')
            ->whereNotNull('upline_id')
            ->selectRaw('upline_id, COUNT(*) as c')
            ->groupBy('upline_id')
            ->pluck('c', 'upline_id');

        return Membership::query()
            ->with(['user:id,name,code', 'entity:id,name_ar', 'position'])
            ->where('status', 'active')
            ->get()
            ->map(function (Membership $m) use ($downlines) {
                $count = (int) ($downlines[$m->id] ?? 0);
                $min = $m->position?->span_min;
                $max = $m->position?->span_max;

                $state = 'ok';

                if ($max !== null && $count > $max) {
                    $state = 'danger';   // تجاوز نطاق الإشراف
                } elseif ($min !== null && $count > 0 && $count < $min) {
                    $state = 'warn';     // كيان غير صحّيّ — اقتراح دمج أو إلغاء طبقة
                }

                return [
                    'membership' => $m,
                    'count' => $count,
                    'min' => $min,
                    'default' => $m->position?->span_default,
                    'max' => $max,
                    'state' => $state,
                ];
            })
            ->filter(fn ($row) => $row['membership']->position && ! $row['membership']->position->is_honorary)
            ->values();
    }

    /** الكيانات غير الصحّيّة: تحت الحدّ الأدنى أو بلا فرعيّات */
    public static function unhealthy(): Collection
    {
        return self::spanRows()->filter(fn ($row) => $row['state'] === 'warn')->values();
    }

    /** التجاوزات: فوق الأقصى — تظهر ولا تمنع */
    public static function overflows(): Collection
    {
        return self::spanRows()->filter(fn ($row) => $row['state'] === 'danger')->values();
    }
}
