<?php

namespace App\Services\Admin\Volunteer;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\User;
use App\Services\Gamification\EconomyRules;
use RuntimeException;

/**
 * إعدادات الحروب (12.10-ج).
 *
 * المبدأ: **عامّ افتراضيّ + Override عند الحاجة** — والقيمة العامّة تظهر
 * Placeholder في كلّ حقل، فالحقل الفارغ يعني «اتبع العامّ» لا «صفر».
 *
 * ⭐ الأمان: **قفل الإعدادات أثناء حرب نشطة** — التعديل لا يُطبَّق على جولة
 * جارية، وإلّا تغيّرت قواعد اللعبة على لاعبٍ في منتصفها.
 */
class WarSettingsService
{
    /** حقول الحرب مجمّعة في سكشنز مطويّة — نفس ترتيب الدستور */
    public const SECTIONS = [
        'costs' => 'التكاليف',
        'rewards' => 'المكافآت',
        'timers' => 'المؤقّتات',
        'question_source' => 'مصدر الأسئلة',
        'limits' => 'الحدود',
        'texts' => 'النصوص والهويّة',
    ];

    /** هل للحرب جولة جارية الآن؟ */
    public static function isActive(Challenge $challenge): bool
    {
        if ($challenge->settings_locked) {
            return true;
        }

        if (! class_exists(ChallengeParticipation::class)) {
            return false;
        }

        return ChallengeParticipation::query()
            ->where('challenge_id', $challenge->id)
            ->whereIn('status', ['in_progress', 'active', 'running'])
            ->exists();
    }

    /** القفل مفعَّل + الحرب نشطة ⟵ لا حفظ */
    public static function isLocked(Challenge $challenge): bool
    {
        return (bool) setting('wars.lock_while_active', true) && self::isActive($challenge);
    }

    public static function lockMessage(): string
    {
        return (string) setting('wars.lock_message', 'تعذّر الحفظ — حرب نشطة الآن، حاول بعد انتهائها.');
    }

    /** القيم العامّة التي تظهر Placeholder في حقول الحرب */
    public static function sharedDefaults(): array
    {
        /*
         | ⭐ تكلفتا الإنشاء والانضمام مصدرهما الحاكم جدول «أوجه الصرف» (12.10)
         | لا إعداد الحروب وحده — كان للقيمة الواحدة مصدران متنازعان، فيغيّر
         | المالك الجدول ولا يظهر له أثر (2.13). وإعداد `wars.shared.*` بقي
         | الافتراضيّ الذي يُستعمَل حين لا يكون للصفّ وجود أصلًا.
         */
        $economy = app(EconomyRules::class);

        return [
            'ready_tickets' => (int) setting('wars.shared.ready_tickets', 12),
            'create_focus_tickets' => (int) $economy->spendCost('war.focus.create', (float) setting('wars.shared.create_focus_tickets', 5)),
            'join_tickets' => (int) $economy->spendCost('war.join', (float) setting('wars.shared.join_tickets', 1)),
            'win' => (float) setting('wars.shared.win', 2),
            'loss' => (float) setting('wars.shared.loss', -2),
            'withdraw' => (float) setting('wars.shared.withdraw', -10),
            'question_seconds' => (int) setting('wars.shared.question_seconds', 15),
            'decision_seconds' => (int) setting('wars.shared.decision_seconds', 20),
            'focus_durations' => setting('wars.shared.focus_durations', [5, 15, 25, 50]),
            'arena_ratio' => (int) setting('wars.shared.arena_ratio', 70),
            'training_ratio' => (int) setting('wars.shared.training_ratio', 30),
            'max_visible_fighters' => (int) setting('wars.shared.max_visible_fighters', 10),
            'max_active_focus' => (int) setting('wars.shared.max_active_focus', 5),
            'loss_rule_count' => (int) setting('wars.shared.loss_rule_count', 3),
        ];
    }

    /** قيمة الحرب المخزّنة (Override) — أو null فتُقرَأ من العامّ */
    public static function overrideOf(Challenge $challenge, string $section): array
    {
        $raw = $challenge->getAttribute($section);

        if (is_array($raw)) {
            return $raw;
        }

        return is_string($raw) ? (json_decode($raw, true) ?? []) : [];
    }

    /**
     * حفظ تفاصيل حرب.
     *
     * @throws RuntimeException حين تكون الحرب نشطة والقفل مفعَّل
     */
    public static function save(Challenge $challenge, array $payload, ?User $actor = null): Challenge
    {
        if (self::isLocked($challenge)) {
            throw new RuntimeException(self::lockMessage());
        }

        $old = $challenge->only(['entry_cost', 'duration_minutes', 'is_active', 'color']);

        $challenge->fill(array_filter([
            'name_ar' => $payload['name_ar'] ?? null,
            'description' => $payload['description'] ?? null,
            'color' => $payload['color'] ?? null,
            'icon_path' => $payload['icon_path'] ?? null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('is_active', $payload)) {
            $challenge->is_active = (bool) $payload['is_active'];
        }

        // الحقل الفارغ = «اتبع العامّ» لا صفر — والعمود Override صريح وحده (2.13)
        if (array_key_exists('entry_cost', $payload)) {
            $challenge->entry_cost = ($payload['entry_cost'] ?? '') !== '' ? (float) $payload['entry_cost'] : null;
        }

        if (array_key_exists('duration_minutes', $payload) && $payload['duration_minutes'] !== null && $payload['duration_minutes'] !== '') {
            $challenge->duration_minutes = (int) $payload['duration_minutes'];
        }

        // السكشنز المطويّة: الحقل الفارغ يعني «اتبع العامّ» فيُحذَف من الـOverride
        foreach (['costs', 'rewards', 'timers', 'question_source', 'limits', 'texts'] as $section) {
            if (! array_key_exists($section, $payload)) {
                continue;
            }

            $values = array_filter(
                (array) $payload[$section],
                fn ($v) => $v !== null && $v !== '',
            );

            $challenge->setAttribute($section, $values ? json_encode($values, JSON_UNESCAPED_UNICODE) : null);
        }

        $challenge->save();

        AuditTrail::log($actor, 'war_settings.update', $challenge, $old, $payload);

        return $challenge;
    }

    /** ↺ إعادة الضبط للافتراضيّ لكلّ حرب — تُمسَح كلّ الـOverrides */
    public static function reset(Challenge $challenge, ?User $actor = null): void
    {
        if (self::isLocked($challenge)) {
            throw new RuntimeException(self::lockMessage());
        }

        foreach (['costs', 'rewards', 'timers', 'question_source', 'limits', 'texts'] as $section) {
            $challenge->setAttribute($section, null);
        }

        $challenge->save();

        AuditTrail::log($actor, 'war_settings.reset', $challenge);
    }
}
