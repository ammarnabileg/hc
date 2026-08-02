<?php

namespace App\Services\Gamification\Wars;

use App\Models\Challenge;
use App\Services\Admin\Volunteer\WarSettingsService;

/**
 * قواعد الحروب (15.0 · 12.10-ج).
 *
 * مصدر واحد لكلّ عتبة: **القاعدة العامّة من `setting()`** ثمّ **Override
 * الحرب** إن وُجد. ولا رقم محروق في أيّ مكان آخر — الواجهة والخدمات كلّها
 * تسأل هذه الطبقة، فلو غيّر الأدمن قيمةً تغيّرت في الشاشة والمنطق معًا (2.13).
 */
class WarRules
{
    /** أنواع الحروب المعتمَدة (15.1 · 15.3 · 15.5 · 15.6) */
    public const TYPES = ['knowledge', 'focus', 'survival', 'estimation'];

    // ------------------------------------------------------------ الاقتصاد

    /** بوّابة الدخول: لا استعداد برصيدٍ أقلّ (15.2-4) */
    public function readyTickets(?Challenge $challenge = null): int
    {
        return (int) $this->value($challenge, 'costs', 'ready_tickets', 'ready_tickets');
    }

    /** ما يكسبه الفائز — وهو **نفسه** ما يخسره الخاسر (محصّلة صفريّة 15.2-6) */
    public function winAmount(?Challenge $challenge = null): float
    {
        return abs((float) $this->value($challenge, 'rewards', 'win', 'win'));
    }

    public function lossAmount(?Challenge $challenge = null): float
    {
        return abs((float) $this->value($challenge, 'rewards', 'loss', 'loss'));
    }

    /** عقوبة الانسحاب المتعمَّد (15.0) — تُحرَق ولا تُمنَح لأحد */
    public function withdrawPenalty(?Challenge $challenge = null): float
    {
        return abs((float) $this->value($challenge, 'rewards', 'withdraw', 'withdraw'));
    }

    /**
     * تكلفة إنشاء حرب تركيز (15.3) — والمصدر واحد لا اثنان (2.13):
     * Override السكشن للحرب، ثمّ العامّ الحاكم من «أوجه الصرف» (`war.focus.create`).
     */
    public function focusCreateCost(?Challenge $challenge = null): float
    {
        return abs((float) $this->value($challenge, 'costs', 'create_focus_tickets', 'create_focus_tickets'));
    }

    /**
     * تكلفة الانضمام لحرب (15.3): Override السكشن، ثمّ **عمود `entry_cost`**
     * الخاصّ بالحرب — وكان عمودًا يُحرَّر بلا أثر — ثمّ الصفّ الحاكم `war.join`.
     */
    public function focusJoinCost(?Challenge $challenge = null): float
    {
        $override = $this->override($challenge, 'costs', 'join_tickets')
            ?? $challenge?->getAttribute('entry_cost');

        return abs((float) ($override ?? WarSettingsService::sharedDefaults()['join_tickets']));
    }

    // ------------------------------------------------------------ المؤقّتات

    /** عدّاد الحسم بعد انتهاء أوّل طرف (15.1) */
    public function decisionSeconds(?Challenge $challenge = null): int
    {
        return (int) $this->value($challenge, 'timers', 'decision_seconds', 'decision_seconds');
    }

    /** مؤقّت السؤال في وضع البقاء — انتهاؤه = إجابة خاطئة (15.5) */
    public function questionSeconds(?Challenge $challenge = null): int
    {
        return (int) $this->value($challenge, 'timers', 'question_seconds', 'question_seconds');
    }

    /** @return list<int> مدد التركيز المتاحة (15.3) */
    public function focusDurations(?Challenge $challenge = null): array
    {
        $raw = $this->value($challenge, 'timers', 'focus_durations', 'focus_durations');

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        $values = array_values(array_filter(array_map('intval', (array) $raw), fn ($m) => $m > 0));

        return $values ?: [5, 15, 25, 50];
    }

    // ------------------------------------------------------------ الحدود

    public function maxVisibleFighters(?Challenge $challenge = null): int
    {
        return (int) $this->value($challenge, 'limits', 'max_visible_fighters', 'max_visible_fighters');
    }

    public function maxActiveFocus(?Challenge $challenge = null): int
    {
        return (int) $this->value($challenge, 'limits', 'max_active_focus', 'max_active_focus');
    }

    /** قاعدة الخسارات المتتالية: عندها يختفي من قائمة الجاهزين (15.1) */
    public function lossStreakLimit(?Challenge $challenge = null): int
    {
        return (int) $this->value($challenge, 'limits', 'loss_rule_count', 'loss_rule_count');
    }

    // ------------------------------------------------------------ الأسئلة

    public function arenaRatio(?Challenge $challenge = null): int
    {
        return (int) $this->value($challenge, 'question_source', 'arena_ratio', 'arena_ratio');
    }

    public function trainingRatio(?Challenge $challenge = null): int
    {
        return (int) $this->value($challenge, 'question_source', 'training_ratio', 'training_ratio');
    }

    /** حدّ أدنى للأسئلة المفعّلة قبل تشغيل حرب (24.2) */
    public function minActiveQuestions(): int
    {
        return (int) setting('wars.shared.min_active_questions', 20);
    }

    /** عدد أسئلة الجولة حسب نوع الحرب — والعدد نفسه إعداد لا رقم محروق */
    public function questionCount(string $type): int
    {
        return max(1, (int) setting('wars.count.'.$type, match ($type) {
            'survival' => 12,
            'estimation' => 7,
            default => 20,
        }));
    }

    // ------------------------------------------------------------ النصوص

    /** نصوص شاشة الساحة قابلة للتعديل من لوحة الإدارة (12.10-ج) */
    public function text(Challenge $challenge, string $key, string $fallback): string
    {
        $texts = WarSettingsService::overrideOf($challenge, 'texts');

        $value = $texts[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    public function typeOf(Challenge $challenge): string
    {
        $limits = WarSettingsService::overrideOf($challenge, 'limits');
        $type = (string) ($limits['type'] ?? 'knowledge');

        return in_array($type, self::TYPES, true) ? $type : 'knowledge';
    }

    // ------------------------------------------------------------ داخليّ

    /** Override الحرب أوّلًا، وإلّا القاعدة العامّة المشتركة */
    private function value(?Challenge $challenge, string $section, string $key, string $sharedKey): mixed
    {
        return $this->override($challenge, $section, $key) ?? WarSettingsService::sharedDefaults()[$sharedKey];
    }

    /** قيمة السكشن المحفوظة للحرب — أو null فتُقرَأ من العامّ */
    private function override(?Challenge $challenge, string $section, string $key): mixed
    {
        if (! $challenge) {
            return null;
        }

        $override = WarSettingsService::overrideOf($challenge, $section);

        if (array_key_exists($key, $override) && $override[$key] !== null && $override[$key] !== '') {
            return $override[$key];
        }

        return null;
    }
}
