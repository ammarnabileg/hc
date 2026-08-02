<?php

namespace App\Services\Gamification\Wars;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\User;
use App\Models\WarMatch;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\CelebrationService;
use App\Services\Gamification\StreakService;
use App\Services\Gamification\WalletGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * محرّك المواجهة (15.1 · 15.2 · 15.5 · 15.6).
 *
 * ثلاث قواعد لا تُكسَر:
 *  1. **كلّ القرارات في الخادم** — التصحيح والوقت والنتيجة، والمتصفح يعرض فقط.
 *  2. **الإجابات الصحيحة لا تُرسَل للمتصفح** (15.2-3).
 *  3. **محصّلة صفريّة** — ما يكسبه الفائز هو **بعينه** ما يخسره الخاسر،
 *     فلا تُسَكّ تذكرة واحدة من العدم (15.2-6). مجموع تذاكر النظام بعد أيّ
 *     مواجهة = مجموعها قبلها.
 */
class WarMatchService
{
    public function __construct(
        private readonly WarRules $rules,
        private readonly WarQuestionFunnel $funnel,
        private readonly WalletGateway $wallet,
        private readonly WarStats $stats,
        private readonly CelebrationService $celebrations,
        private readonly BadgeService $badges,
        private readonly StreakService $streaks,
    ) {}

    // ------------------------------------------------------------------ عرض

    public function sideOf(WarMatch $match, User $user): ChallengeParticipation
    {
        return ChallengeParticipation::query()
            ->where('war_match_id', $match->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public function rivalSide(WarMatch $match, User $user): ChallengeParticipation
    {
        return ChallengeParticipation::query()
            ->where('war_match_id', $match->id)
            ->where('user_id', '!=', $user->id)
            ->firstOrFail();
    }

    /** بنود الجولة بلا إجابات — ما يراه المتصفح (15.2-3) */
    public function publicItems(WarMatch $match): array
    {
        return $this->funnel->publicItems((array) $match->questions);
    }

    /** ثوانٍ باقية على السؤال الحاليّ في وضع البقاء (15.5) */
    public function questionSecondsLeft(WarMatch $match, ChallengeParticipation $side): ?int
    {
        if ($match->war_type !== 'survival' || $side->status !== 'running') {
            return null;
        }

        $startedAt = $side->progress['q_started_at'] ?? null;
        $limit = $this->rules->questionSeconds($match->challenge);
        $from = $startedAt ? Carbon::parse($startedAt) : Carbon::parse($side->started_at);

        return max(0, $limit - (int) $from->diffInSeconds(now()));
    }

    /** ثوانٍ باقية على عدّاد الحسم — يظهر للطرفين (15.1) */
    public function decisionSecondsLeft(WarMatch $match): ?int
    {
        if (! $match->decision_deadline_at || $match->status !== 'running') {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($match->decision_deadline_at, false));
    }

    // ------------------------------------------------------------------ اللعب

    /**
     * حفظ إجابة **وتصحيحها في الخادم لحظيًّا** (Autosave — 15.1).
     *
     * @return array{saved:bool,answered:int,total:int,status:string,eliminated:bool}
     */
    public function answer(WarMatch $match, User $user, int $index, mixed $value): array
    {
        $this->enforceTimers($match);
        $match->refresh();

        $side = $this->sideOf($match, $user);
        $questions = (array) $match->questions;
        $total = count($questions);

        $state = fn (bool $saved) => [
            'saved' => $saved,
            'answered' => count($side->progress['answers'] ?? []),
            'total' => $total,
            'status' => $side->status,
            'eliminated' => (bool) ($side->progress['eliminated'] ?? false),
        ];

        if ($side->status !== 'running' || $index < 0 || $index >= $total) {
            return $state(false);
        }

        $progress = $side->progress ?? [];
        $answers = $progress['answers'] ?? [];

        // في وضع البقاء لا رجوع: السؤال الحاليّ وحده يُجاب عليه (15.5)
        if ($match->war_type === 'survival' && $index !== (int) ($progress['index'] ?? 0)) {
            return $state(false);
        }

        $answers[(string) $index] = $value;
        $progress['answers'] = $answers;
        $progress['saved_at'] = now()->toIso8601String();

        if ($match->war_type === 'survival') {
            $correct = $this->isCorrect($questions[$index], $value);

            if (! $correct) {
                // أوّل إجابة غلط = خروج (15.5)
                $progress['eliminated'] = true;
                $side->forceFill(['progress' => $progress])->save();
                $this->finishSide($match, $side);

                return $state(false) + ['eliminated' => true];
            }

            $progress['index'] = min($index + 1, $total);
            $progress['q_started_at'] = now()->toIso8601String();
            $side->forceFill([
                'progress' => $progress,
                'reached_index' => $index + 1,
                'score' => $index + 1,
            ])->save();

            if ($progress['index'] >= $total) {
                $this->finishSide($match, $side->refresh());
            }

            return $state(true);
        }

        $progress['index'] = min($index + 1, max(0, $total - 1));

        $side->forceFill([
            'progress' => $progress,
            'score' => $this->correctCount($questions, $answers),
            'reached_index' => count($answers),
        ])->save();

        return $state(true);
    }

    /** «سلّمت» — أوّل مَن يخلّص يشغّل عدّاد الحسم للطرفين (15.1) */
    public function finishSide(WarMatch $match, ChallengeParticipation $side): void
    {
        if ($side->status !== 'running') {
            return;
        }

        $side->forceFill([
            'status' => 'finished',
            'finished_at' => now(),
        ])->save();

        $match->refresh();

        $othersRunning = ChallengeParticipation::query()
            ->where('war_match_id', $match->id)
            ->where('status', 'running')
            ->exists();

        if (! $othersRunning) {
            $this->settle($match);

            return;
        }

        if (! $match->first_finished_at) {
            $seconds = $this->rules->decisionSeconds($match->challenge);

            $match->forceFill([
                'first_finished_at' => now(),
                'decision_deadline_at' => now()->addSeconds($seconds),
            ])->save();
        }
    }

    /**
     * فرض المؤقّتات على الخادم — لا على المتصفح.
     * لماذا: المتصفّح قد يُغلَق أو يُتلاعَب به، والعدالة لا تُترَك للعميل.
     */
    public function enforceTimers(WarMatch $match): bool
    {
        if ($match->status !== 'running') {
            return false;
        }

        $acted = false;

        // مؤقّت السؤال في وضع البقاء: انتهاؤه = إجابة خاطئة ⟵ خروج (15.5)
        if ($match->war_type === 'survival') {
            $limit = $this->rules->questionSeconds($match->challenge);

            foreach ($this->runningSides($match) as $side) {
                $startedAt = $side->progress['q_started_at'] ?? $side->started_at;
                $from = $startedAt instanceof Carbon ? $startedAt : Carbon::parse((string) $startedAt);

                if ($from->addSeconds($limit)->isPast()) {
                    $progress = $side->progress ?? [];
                    $progress['eliminated'] = true;
                    $progress['timed_out'] = true;
                    $side->forceFill(['progress' => $progress])->save();
                    $this->finishSide($match, $side);
                    $acted = true;
                }
            }

            $match->refresh();
        }

        // عدّاد الحسم: انتهاؤه يقفل المواجهة بما هو محفوظ (15.1)
        if ($match->status === 'running' && $match->decision_deadline_at && $match->decision_deadline_at->isPast()) {
            foreach ($this->runningSides($match) as $side) {
                $side->forceFill(['status' => 'finished', 'finished_at' => now()])->save();
            }

            $this->settle($match->refresh());
            $acted = true;
        }

        return $acted;
    }

    // ------------------------------------------------------------------ التسوية

    /** الانسحاب المتعمَّد: خسارة عاديّة + عقوبة الانسحاب، والخصم يفوز (15.0) */
    public function withdraw(WarMatch $match, User $user): WarMatch
    {
        if ($match->status !== 'running') {
            return $match;
        }

        $side = $this->sideOf($match, $user);
        $rival = $this->rivalSide($match, $user);

        $side->forceFill(['status' => 'finished', 'finished_at' => now(), 'withdrew' => true])->save();
        $rival->forceFill(['status' => 'finished', 'finished_at' => now()])->save();

        return $this->settle($match->refresh(), withdrawnBy: $user->id);
    }

    /**
     * حسم المواجهة — **الخادم وحده** يقرّر.
     *
     * ⭐ محصّلة صفريّة: يُخصَم من الخاسر ويُضاف للفائز **نفس المبلغ بالضبط**؛
     * ولا تُسَكّ تذكرة ولا XP من العدم (15.2-6).
     */
    public function settle(WarMatch $match, ?int $withdrawnBy = null): WarMatch
    {
        if ($match->status !== 'running') {
            return $match;
        }

        return DB::transaction(function () use ($match, $withdrawnBy) {
            $fresh = WarMatch::query()->whereKey($match->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->status !== 'running') {
                return $match->refresh();
            }

            $sides = ChallengeParticipation::query()
                ->where('war_match_id', $fresh->id)
                ->orderBy('id')
                ->get()
                ->keyBy('user_id');

            $challengerSide = $sides[$fresh->challenger_id];
            $opponentSide = $sides[$fresh->opponent_id];

            $this->scoreSides($fresh, $challengerSide, $opponentSide);

            [$winnerId, $loserId] = $this->decide($fresh, $challengerSide, $opponentSide, $withdrawnBy);

            $settlement = ['moved' => 0.0, 'penalty' => 0.0, 'currency' => 'tickets'];

            if ($winnerId === null) {
                // تعادل: لا خصم ولا إضافة + شاشة «تعادل» (15.2-5)
                $challengerSide->forceFill(['result' => 'draw'])->save();
                $opponentSide->forceFill(['result' => 'draw'])->save();

                $this->stats->recordDraw($challengerSide->user);
                $this->stats->recordDraw($opponentSide->user);
            } else {
                $winner = $sides[$winnerId]->user;
                $loser = $sides[$loserId]->user;

                $amount = $this->rules->winAmount($fresh->challenge);
                $reason = 'مواجهة حرب #'.$fresh->id;

                // التحويل بعينه — لا سكّ ولا حرق في الحالة العاديّة
                $settlement['moved'] = $this->transfer($loser, $winner, $amount, $reason, $fresh);

                if ($withdrawnBy !== null) {
                    // عقوبة الانسحاب تُحرَق ولا تذهب لأحد — رادع لا مصدر دخل
                    $settlement['penalty'] = $this->burn(
                        $loser,
                        $this->rules->withdrawPenalty($fresh->challenge),
                        'عقوبة انسحاب من مواجهة #'.$fresh->id,
                        $fresh,
                    );
                }

                $sides[$winnerId]->forceFill(['result' => 'win'])->save();
                $sides[$loserId]->forceFill(['result' => 'lose'])->save();

                $this->stats->recordWin($winner);
                $this->stats->recordLoss($loser, withdrawal: $withdrawnBy !== null);
            }

            $fresh->forceFill([
                'status' => 'finished',
                'ended_at' => now(),
                'winner_id' => $winnerId,
                'outcome' => $winnerId === null ? 'draw' : ($withdrawnBy !== null ? 'withdraw' : 'win'),
                'settlement' => $settlement,
            ])->save();

            $this->unlockSettings($fresh->challenge);

            /*
             | ⭐ بعد التسوية نحدّث **لقطة الستريك** فقط ونقيّم الشارات — ولا نسجّل
             | حضورًا (7.2). كان هنا `record()` وهو يمرّ بـ`checkIn()`، فتسويةُ
             | مواجهةٍ داخل نافذة الفجر كانت تمنح الطرفين XP النادي ويوم حضور
             | بلا حضور: سكٌّ لعملةٍ من مسار حرب يخرق المحصّلة الصفريّة (15.2-6)
             | ويشوّه الليدر بورد (7.3). الحضور من مساره وحده: `streak.checkin`.
             */
            foreach ($sides as $side) {
                $this->streaks->touchActivity($side->user);
                $this->badges->evaluate($side->user);
            }

            return $fresh;
        });
    }

    /** احتفال شاشة النتيجة — مرّة واحدة لكلّ مشاركة (2.14-ب) */
    public function celebrationFor(ChallengeParticipation $side): ?array
    {
        $user = $side->user;

        if ($side->result !== 'win') {
            return $this->celebrations->fire($user, 'challenge.finished', $side);
        }

        $isFirstWin = ChallengeParticipation::query()
            ->where('user_id', $user->id)
            ->where('result', 'win')
            ->count() === 1;

        return $this->celebrations->highest([
            $this->celebrations->fire($user, $isFirstWin ? 'challenge.first_win' : 'challenge.won', $side),
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return Collection<int, ChallengeParticipation> */
    private function runningSides(WarMatch $match)
    {
        return ChallengeParticipation::query()
            ->where('war_match_id', $match->id)
            ->where('status', 'running')
            ->get();
    }

    /**
     * التقييم النهائيّ لكلّ طرف.
     * التقدير خاصّة: **الأقرب للرقم الصحيح يأخذ نقطة** — وهو تقييمٌ مقارِن
     * لا يُحسَم إلّا بوجود التقديرين معًا (15.6).
     */
    private function scoreSides(WarMatch $match, ChallengeParticipation $a, ChallengeParticipation $b): void
    {
        $questions = (array) $match->questions;

        if ($match->war_type === 'survival') {
            return; // reached_index محسوب لحظيًّا مع كلّ إجابة
        }

        if ($match->war_type !== 'estimation') {
            $a->forceFill(['score' => $this->correctCount($questions, $a->progress['answers'] ?? [])])->save();
            $b->forceFill(['score' => $this->correctCount($questions, $b->progress['answers'] ?? [])])->save();

            return;
        }

        $pointsA = 0;
        $pointsB = 0;

        foreach ($questions as $i => $question) {
            $target = (float) ($question['answer'] ?? 0);
            $guessA = $a->progress['answers'][(string) $i] ?? null;
            $guessB = $b->progress['answers'][(string) $i] ?? null;

            $distA = $guessA === null || $guessA === '' ? null : abs((float) $guessA - $target);
            $distB = $guessB === null || $guessB === '' ? null : abs((float) $guessB - $target);

            if ($distA === null && $distB === null) {
                continue;
            }

            if ($distB === null || ($distA !== null && $distA < $distB)) {
                $pointsA++;
            } elseif ($distA === null || $distB < $distA) {
                $pointsB++;
            }
        }

        $a->forceFill(['score' => $pointsA])->save();
        $b->forceFill(['score' => $pointsB])->save();
    }

    /** @return array{0:?int,1:?int} [الفائز, الخاسر] — و`null` تعني تعادلًا */
    private function decide(WarMatch $match, ChallengeParticipation $a, ChallengeParticipation $b, ?int $withdrawnBy): array
    {
        if ($withdrawnBy !== null) {
            return [$match->rivalIdOf($withdrawnBy), $withdrawnBy];
        }

        [$valueA, $valueB] = $match->war_type === 'survival'
            ? [(int) $a->reached_index, (int) $b->reached_index]
            : [(float) $a->score, (float) $b->score];

        if ($valueA === $valueB || abs($valueA - $valueB) < 0.0001) {
            return [null, null];
        }

        return $valueA > $valueB
            ? [(int) $a->user_id, (int) $b->user_id]
            : [(int) $b->user_id, (int) $a->user_id];
    }

    /**
     * تحويل تذاكر بين مستخدمين — يُخصَم **بالضبط** ما يُضاف.
     * نقرأ الرصيد قبل الخصم فلا نضيف للفائز أكثر ممّا خرج من الخاسر،
     * وهذا هو ضمان المحصّلة الصفريّة عمليًّا (15.2-6).
     */
    private function transfer(User $from, User $to, float $amount, string $reason, WarMatch $ref): float
    {
        $available = $this->wallet->balance($from, 'tickets');
        $moved = min($amount, max(0.0, $available));

        if ($moved <= 0) {
            return 0.0;
        }

        $this->wallet->debit($from, 'tickets', $moved, $reason.' — خسارة', $ref);
        $this->wallet->credit($to, 'tickets', $moved, $reason.' — فوز', $ref);

        return $moved;
    }

    /** حرق عقوبة (لا يستلمها أحد) — سحبٌ من الاقتصاد لا ضخّ فيه */
    private function burn(User $from, float $amount, string $reason, WarMatch $ref): float
    {
        $available = $this->wallet->balance($from, 'tickets');
        $burned = min($amount, max(0.0, $available));

        if ($burned > 0) {
            $this->wallet->debit($from, 'tickets', $burned, $reason, $ref);
        }

        return $burned;
    }

    private function correctCount(array $questions, array $answers): float
    {
        $score = 0;

        foreach ($questions as $i => $question) {
            $given = $answers[(string) $i] ?? null;

            if ($given === null || $given === '') {
                continue;
            }

            $score += $this->isCorrect($question, $given) ? 1 : 0;
        }

        return (float) $score;
    }

    /** التصحيح Server-side وحده (15.2-3) */
    private function isCorrect(array $question, mixed $given): bool
    {
        if (($question['kind'] ?? 'mcq') === 'number') {
            $tolerance = (float) ($question['tolerance'] ?? 0);

            return abs((float) $given - (float) ($question['answer'] ?? 0)) <= $tolerance;
        }

        return (string) $given === (string) ($question['answer'] ?? '');
    }

    private function unlockSettings(Challenge $challenge): void
    {
        $stillRunning = WarMatch::query()
            ->where('challenge_id', $challenge->id)
            ->where('status', 'running')
            ->exists();

        if (! $stillRunning && $challenge->settings_locked) {
            $challenge->forceFill(['settings_locked' => false])->save();
        }
    }
}
