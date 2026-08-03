<?php

namespace App\Services\Account;

use App\Models\User;
use App\Services\Gamification\LevelResolver;
use App\Services\Gamification\TicketsAccount;
use Illuminate\Support\Facades\DB;

/**
 * مسارات الإنجازات الخمسة وعتباتها (الدستور 10 · 10.1) — **المصدر الواحد**.
 *
 * لماذا صنفٌ مستقلّ: كانت الصيغة مكتوبةً مرّتين (بروفايل + رادار اللوحة)
 * وبقيمٍ مختلفة، فكان إنفاق تذكرةٍ واحدة يُنزِل مستواك في البروفايل ولا يُنزِله
 * في اللوحة. المستوى واحد في المنصّة كلّها، فالحساب واحد.
 *
 * والقيم بنصّ الدستور حرفيًّا: التذاكر = **إجماليّ المكتسب** (لا الرصيد الحاليّ)،
 * والدعوات = **الناجحة** (لا كلّ الصفوف)، وXP من مصدر الـXP الموحّد.
 *
 * ⭐ **وبعد ن-2:** لا صيغةَ هنا ولا قراءةَ مخزنٍ هنا — الصيغة كلّها في
 * `LevelResolver::progress()` (نصّ 10.1)، وXP في `LevelResolver::xpFor()`،
 * والتذاكر في `TicketsAccount::earned()`. فالرادار وكارت الـKPI وهيدر البروفايل
 * يقرؤون **من المصدر نفسه**، ولا يبقى موضعٌ يُعيد الحساب فيختلف.
 */
class AchievementTracks
{
    public function __construct(
        private readonly LevelResolver $levels,
        private readonly TicketsAccount $tickets,
    ) {}

    /**
     * مسارات الرادار وترتيبها — إعدادٌ واحد يخدم البروفايل واللوحة معًا (2.13).
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        $keys = setting('dashboard.achievements.tracks');

        return is_array($keys) && $keys !== []
            ? array_values(array_filter($keys, 'is_string'))
            : ['account', 'club_5am', 'referrals', 'tickets', 'learning'];
    }

    /**
     * قيمة كلّ مسار كما ينصّ عليها الدستور 10 — لا كما يسهل حسابه.
     *
     * @return array<string, int>
     */
    public function values(User $user): array
    {
        return [
            // XP من **المصدر الواحد** — نفسه الذي يبني كارت الـKPI وبار السايد بار
            'account' => $this->levels->xpFor($user),
            'club_5am' => (int) ($user->streak?->club_5am_count ?? 0),
            // «عدد الدعوات **الناجحة**» — والصفّ بلا مدعوٍّ ليس دعوةً ناجحة
            'referrals' => $this->successfulReferrals($user),
            // «**إجماليّ** التذاكر المكتسبة» (10) — فالإنفاق لا يُنزِل المستوى
            'tickets' => $this->tickets->earned($user),
            'learning' => $this->lessonsCompleted($user),
        ];
    }

    /**
     * عتبة المسار. ومسار **الحساب** يُسأل عنه `LevelResolver` وحده حتّى لو غاب
     * الإعداد: افتراضٌ مختلف هنا (1) وهناك (500) يُنتِج مستويين للرقم نفسه —
     * وهو بالضبط العطل الذي جئنا نُغلقه.
     */
    public function base(string $key): int
    {
        return $key === LevelResolver::TRACK
            ? $this->levels->base()
            : max(1, (int) setting("dashboard.achievements.{$key}.base", 1));
    }

    public function step(string $key): int
    {
        return $key === LevelResolver::TRACK
            ? $this->levels->step()
            : max(1, (int) setting("dashboard.achievements.{$key}.step", 1));
    }

    public function label(string $key): string
    {
        return (string) setting("dashboard.achievements.{$key}.label", $key);
    }

    public function unit(string $key): string
    {
        return (string) setting("dashboard.achievements.{$key}.unit", '');
    }

    /**
     * العتبة التراكميّة (10.1) — **واجهةٌ رفيعة فوق `LevelResolver::progress()`**.
     * الصيغة تُكتَب مرّةً واحدةً في المنصّة كلّها، وهنا نداؤها فقط.
     *
     * @return array{level:int, current_at:int, next_at:int, fraction:float, percent:int}
     */
    public function progress(int $value, int $base, int $step): array
    {
        return $this->levels->progress($value, $base, $step);
    }

    /**
     * كلّ المسارات جاهزةً للعرض — يستعملها البروفايل والرادار معًا.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        $values = $this->values($user);
        $tracks = [];

        foreach ($this->keys() as $key) {
            $base = $this->base($key);
            $step = $this->step($key);
            $value = (int) ($values[$key] ?? 0);
            $progress = $this->progress($value, $base, $step);

            $tracks[] = [
                'key' => $key,
                'label' => $this->label($key),
                'unit' => $this->unit($key),
                'value' => $value,
                'base' => $base,
                'step' => $step,
                'level' => $progress['level'],
                'current_threshold' => $progress['current_at'],
                'next_threshold' => $progress['next_at'],
                'fraction' => $progress['fraction'],
                'percent' => $progress['percent'],
            ];
        }

        return $tracks;
    }

    /** «الدعوات الناجحة» — الصفّ الذي التحق به مدعوٌّ فعلًا (10) */
    private function successfulReferrals(User $user): int
    {
        return DB::table('referrals')
            ->where('referrer_id', $user->id)
            ->whereNotNull('referred_id')
            ->count();
    }

    private function lessonsCompleted(User $user): int
    {
        return DB::table('lesson_completions')->where('user_id', $user->id)->count();
    }
}
