<?php

namespace App\Services\Admin\Volunteer;

use App\Models\RepRule;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ كلّ قيم `rep_rules` تُعدَّل من شاشة ضبط Rep (13.4-ك · 13.4-ن).
 *
 * الكاش يُمسَح مع كلّ كتابة، فما تكتبه الشاشة يقرأه `rep_rule()` فورًا —
 * فلا تبقى المنصّة تعمل بقيمةٍ قديمة بينما الأدمن يرى الجديدة.
 */
class RepRuleWriter
{
    /** الافتراضيّات المعتمَدة في الدستور — مرجع زرّ «Reset لكلّ قيمة» */
    public const DEFAULTS = [
        'task.early' => 0.25,
        'task.late_under_24h' => -0.25,
        'task.no_delivery' => -0.75,
        'task.apology_accepted' => -0.5,
        'task.breakdown_delay_per_day' => -0.2,
        'task.breakdown_delay_cap' => -1.0,
        'task.contribution_no_delivery' => -0.2,
        'task.checkpoint_missed' => -0.2,
        'task.slowdown' => -0.1,
        // الحالة الثامنة يدويّة (13.4-ن-أ): المدى +0.25 ↔ −0.5 يحكمه
        // `workflow.repeated_return.rep_min/max`، وهذه قيمة البداية وحدها.
        'task.state8_manual' => 0.25,
        'task.committee_chance' => 1.0,
        'meeting.within_3h' => 1.0,
        'meeting.within_12h' => 0.5,
        'meeting.excused_absence' => 0.0,
        'meeting.unexcused_absence' => -0.5,
        'meeting.managed' => 1.0,
        'academy.recording_otp' => 0.2,
        'academy.path_complete' => 1.0,
        'leadership.ge_9' => 0.3,
        'leadership.8_to_8_9' => 0.15,
        'leadership.6_to_7_9' => 0.0,
        'leadership.4_to_5_9' => -0.15,
        'leadership.lt_4' => -0.3,
        'behavior.warning' => -0.5,
        'behavior.severe' => -1.0,
        'inactivity.weekly' => -0.5,
        'limit.daily_loss' => -2.0,
        'limit.red_indicator' => -8.0,
        'limit.warning_threshold' => -5.0,
        'limit.optional_cut' => -9.5,
        'limit.suspension' => -10.0,
        'limit.cumulative_90d' => -15.0,
        'limit.club_threshold' => 9.5,
    ];

    /** عناوين المجموعات كما تظهر في التاب */
    public const GROUPS = [
        'tasks' => 'المهامّ',
        'meetings' => 'الاجتماعات',
        'academy' => 'الأكاديمية',
        'leadership' => 'مؤشّر القيادة',
        'behavior' => 'السلوك',
        'limits' => 'الحدود والعتبات',
    ];

    /** تعديل قيمة واحدة — ينعكس فورًا على `rep_rule()` */
    public static function put(string $key, float $value, ?User $actor = null): ?RepRule
    {
        $rule = RepRule::query()->where('key', $key)->first();

        if (! $rule) {
            return null;
        }

        $old = (float) $rule->value;
        $rule->value = $value;
        $rule->save();

        self::flush();

        AuditTrail::log($actor, 'rep_rule.update', $rule, ['value' => $old], ['key' => $key, 'value' => $value]);

        return $rule;
    }

    public static function toggle(string $key, bool $active, ?User $actor = null): void
    {
        $rule = RepRule::query()->where('key', $key)->first();

        if (! $rule) {
            return;
        }

        $rule->is_active = $active;
        $rule->save();

        self::flush();

        AuditTrail::log($actor, 'rep_rule.toggle', $rule, [], ['key' => $key, 'is_active' => $active]);
    }

    /** ↺ Reset لقيمة واحدة إلى افتراضيّها المنصوص */
    public static function reset(string $key, ?User $actor = null): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            return;
        }

        self::put($key, (float) self::DEFAULTS[$key], $actor);
    }

    /** ↺ Reset لكلّ مجموعة */
    public static function resetGroup(string $group, ?User $actor = null): int
    {
        $count = 0;

        foreach (RepRule::query()->where('group', $group)->pluck('key') as $key) {
            if (array_key_exists($key, self::DEFAULTS)) {
                self::put($key, (float) self::DEFAULTS[$key], $actor);
                $count++;
            }
        }

        return $count;
    }

    public static function isModified(RepRule $rule): bool
    {
        $default = self::DEFAULTS[$rule->key] ?? null;

        return $default !== null && abs((float) $rule->value - (float) $default) > 0.0001;
    }

    public static function flush(): void
    {
        Cache::forget('rep_rules');
    }
}
