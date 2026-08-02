<?php

namespace App\Services\Account;

use App\Models\User;
use App\Services\Dashboard\DashboardService;
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
 */
class AchievementTracks
{
    public function __construct(private readonly DashboardService $dashboard) {}

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
            'account' => $this->dashboard->xp($user),
            'club_5am' => (int) ($user->streak?->club_5am_count ?? 0),
            // «عدد الدعوات **الناجحة**» — والصفّ بلا مدعوٍّ ليس دعوةً ناجحة
            'referrals' => $this->successfulReferrals($user),
            // «**إجماليّ** التذاكر المكتسبة» — فالإنفاق لا يُنزِل المستوى
            'tickets' => $this->ticketsEarned($user),
            'learning' => $this->lessonsCompleted($user),
        ];
    }

    public function base(string $key): int
    {
        return max(1, (int) setting("dashboard.achievements.{$key}.base", 1));
    }

    public function step(string $key): int
    {
        return max(1, (int) setting("dashboard.achievements.{$key}.step", 1));
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
     * العتبة التراكميّة (10.1): الزيادة للوصول للمستوى N = base + (N−2) × step،
     * والرقم التراكميّ = مجموع الزيادات. **والمستويات مفتوحة بلا سقف** —
     * فلا حارسَ يوقف العدّ عند رقمٍ ما.
     *
     * @return array{level:int, current_at:int, next_at:int, fraction:float, percent:int}
     */
    public function progress(int $value, int $base, int $step): array
    {
        $base = max(1, $base);
        $step = max(1, $step);

        $level = 1;
        $cumulative = 0;

        // الزيادة موجبة دائمًا (base ≥ 1)، فالمجموع يتخطّى أيّ قيمة منتهية — والحلقة تنتهي حتمًا
        while (true) {
            $needed = $cumulative + $base + ($level - 1) * $step;

            if ($value < $needed) {
                $span = $needed - $cumulative;
                $fraction = $span > 0 ? ($value - $cumulative) / $span : 0.0;

                return [
                    'level' => $level,
                    'current_at' => $cumulative,
                    'next_at' => $needed,
                    'fraction' => max(0.0, min(1.0, $fraction)),
                    'percent' => (int) max(0, min(100, round($fraction * 100))),
                ];
            }

            $cumulative = $needed;
            $level++;
        }
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

    /** «إجماليّ التذاكر المكتسبة» — من عدّاد المحفظة التراكميّ لا من الرصيد (10) */
    private function ticketsEarned(User $user): int
    {
        $code = (string) setting('wallet.currency.tickets_code', 'tickets');

        return (int) $user->balances()
            ->whereHas('currency', fn ($q) => $q->where('code', $code))
            ->value('lifetime_earned');
    }

    private function lessonsCompleted(User $user): int
    {
        return DB::table('lesson_completions')->where('user_id', $user->id)->count();
    }
}
