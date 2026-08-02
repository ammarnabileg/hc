<?php

namespace App\Services\Gamification;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\User;
use App\Services\Gamification\Exceptions\InsufficientBalanceException;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * محرّك التحديات/الحروب (15).
 *
 * القواعد المطبَّقة:
 *  - **قفل ذرّيّ** فور الدخول: مشاركةٌ جاريةٌ واحدة لكلّ تحدٍّ، فالدخول **يخصم مرّة واحدة** (15.2-1).
 *  - **تحقّق Server-side**: التصحيح هنا وحده، والإجابات الصحيحة **لا تُرسَل للمتصفح** (15.2-3).
 *  - **Autosave**: كلّ إجابة تُحفَظ لحظيًّا، والانقطاع لا يعاقِب — «تقدّمك محفوظ» ويُستأنف (15.2-2/7).
 *  - **انتهاء الوقت ⟵ تسليم تلقائيّ** يُفرَض على السيرفر لا على المتصفّح.
 */
class ChallengeService
{
    public function __construct(
        private readonly WalletGateway $wallet,
        private readonly CelebrationService $celebrations,
        private readonly BadgeService $badges,
        private readonly StreakService $streaks,
    ) {}

    // ------------------------------------------------------------------ الدخول

    /**
     * دخول التحدّي: خصم التكلفة مرّةً واحدة وفتح مشاركة جارية.
     *
     * @throws InsufficientBalanceException
     */
    public function enter(User $user, Challenge $challenge): ChallengeParticipation
    {
        return DB::transaction(function () use ($user, $challenge) {
            // قفل ذرّيّ: مَن له مشاركة جارية يعود إليها بلا خصمٍ ثانٍ
            $running = ChallengeParticipation::query()
                ->where('challenge_id', $challenge->id)
                ->where('user_id', $user->id)
                ->where('status', 'running')
                ->lockForUpdate()
                ->first();

            if ($running) {
                return $running;
            }

            $cost = (float) $challenge->entry_cost;
            $code = $this->currencyCode($challenge);

            if ($cost > 0) {
                $available = $this->wallet->balance($user, $code);

                if ($available < $cost) {
                    throw new InsufficientBalanceException($cost, $available, $this->wallet->label($code));
                }

                $this->wallet->debit($user, $code, $cost, 'دخول تحدّي: '.$challenge->name_ar, $challenge);
            }

            $participation = ChallengeParticipation::create([
                'challenge_id' => $challenge->id,
                'user_id' => $user->id,
                'started_at' => now(),
                'status' => 'running',
                'score' => 0,
                'progress' => ['answers' => [], 'index' => 0, 'paid' => $cost, 'currency' => $code],
            ]);

            // الإعدادات تُقفَل أثناء حرب نشطة
            if (! $challenge->settings_locked) {
                $challenge->forceFill(['settings_locked' => true])->save();
            }

            return $participation;
        });
    }

    /** الرصيد قبل/بعد لعرضه في بوب-أب الدخول */
    public function entryPreview(User $user, Challenge $challenge): array
    {
        $code = $this->currencyCode($challenge);
        $before = $this->wallet->balance($user, $code);
        $cost = (float) $challenge->entry_cost;

        return [
            'currency' => $code,
            'currency_label' => $this->wallet->label($code),
            'cost' => $cost,
            'before' => $before,
            'after' => max(0, $before - $cost),
            'affordable' => $before >= $cost,
        ];
    }

    // ------------------------------------------------------------------ اللعب

    /** بنود التحدّي بلا إجاباتها الصحيحة — ما يُرسَل للمتصفح فقط */
    public function publicItems(Challenge $challenge): array
    {
        $public = [];

        foreach ($this->items($challenge) as $i => $item) {
            $public[] = [
                'i' => $i,
                'kind' => $item['kind'] ?? 'mcq',
                'text' => $item['text'] ?? '',
                'options' => $item['options'] ?? [],
                'unit' => $item['unit'] ?? null,
            ];
        }

        return $public;
    }

    public function items(Challenge $challenge): array
    {
        return array_values($challenge->question_source['items'] ?? []);
    }

    /**
     * حفظ إجابة لحظيًّا (Autosave) — والتصحيح لا يُعاد للمتصفح.
     *
     * @return array{saved:bool,answered:int,total:int,status:string}
     */
    public function answer(ChallengeParticipation $participation, int $index, mixed $value): array
    {
        $this->enforceDeadline($participation);

        $total = count($this->items($participation->challenge));

        if ($participation->status !== 'running' || $index < 0 || $index >= $total) {
            return [
                'saved' => false,
                'answered' => count($participation->progress['answers'] ?? []),
                'total' => $total,
                'status' => $participation->status,
            ];
        }

        $progress = $participation->progress ?? [];
        $answers = $progress['answers'] ?? [];
        $answers[(string) $index] = $value;

        $progress['answers'] = $answers;
        $progress['index'] = min($index + 1, max(0, $total - 1));
        $progress['saved_at'] = now()->toIso8601String();

        $participation->forceFill([
            'progress' => $progress,
            'score' => $this->score($participation->challenge, $answers),
        ])->save();

        return ['saved' => true, 'answered' => count($answers), 'total' => $total, 'status' => 'running'];
    }

    /** التسليم — يدويّ أو تلقائيّ عند انتهاء الوقت */
    public function finish(ChallengeParticipation $participation, bool $auto = false): ChallengeParticipation
    {
        if ($participation->status !== 'running') {
            return $participation;
        }

        $challenge = $participation->challenge;
        $answers = $participation->progress['answers'] ?? [];
        $total = count($this->items($challenge));
        $score = $this->score($challenge, $answers);

        $passPercent = (float) setting('challenges.pass.percent', 60);
        $percent = $total > 0 ? ($score / $total) * 100 : 0;

        $result = match (true) {
            $total === 0 => 'draw',
            $percent >= $passPercent => 'win',
            default => 'lose',
        };

        $progress = $participation->progress ?? [];
        $progress['auto_submitted'] = $auto;
        $progress['answered'] = count($answers);

        $participation->forceFill([
            'finished_at' => now(),
            'status' => 'finished',
            'score' => $score,
            'result' => $result,
            'progress' => $progress,
        ])->save();

        $this->payout($participation, $result);
        $this->unlockSettings($challenge);

        $user = $participation->user;

        // يومٌ نشط + فحص الشارات بعد كلّ تحدٍّ منتهٍ
        $this->streaks->record($user);
        $this->badges->evaluate($user);

        return $participation->refresh();
    }

    /**
     * فرض انتهاء الوقت على السيرفر: أيّ لمسة للمشاركة بعد الموعد ⟵ تسليم تلقائيّ.
     * لماذا على السيرفر: المتصفّح قد يُغلَق أو ينقطع، والعدالة لا تُترَك للعميل.
     */
    public function enforceDeadline(ChallengeParticipation $participation): bool
    {
        if ($participation->status !== 'running') {
            return false;
        }

        $deadline = $this->deadline($participation);

        if ($deadline && now()->greaterThanOrEqualTo($deadline)) {
            $this->finish($participation, auto: true);

            return true;
        }

        return false;
    }

    public function deadline(ChallengeParticipation $participation): ?CarbonInterface
    {
        $minutes = (int) ($participation->challenge->duration_minutes ?? 0);

        if ($minutes <= 0) {
            return null;
        }

        return Carbon::parse($participation->started_at)->addMinutes($minutes);
    }

    public function secondsLeft(ChallengeParticipation $participation): ?int
    {
        $deadline = $this->deadline($participation);

        return $deadline ? max(0, now()->diffInSeconds($deadline, false)) : null;
    }

    // ------------------------------------------------------------------ النتيجة

    /** احتفال شاشة النتيجة بمستواه — مرّة واحدة لكلّ مشاركة */
    public function celebrationFor(ChallengeParticipation $participation): ?array
    {
        $user = $participation->user;

        if ($participation->result !== 'win') {
            return $this->celebrations->fire($user, 'challenge.finished', $participation);
        }

        $isFirstWin = ChallengeParticipation::query()
            ->where('user_id', $user->id)
            ->where('result', 'win')
            ->count() === 1;

        return $this->celebrations->highest([
            $this->celebrations->fire($user, $isFirstWin ? 'challenge.first_win' : 'challenge.won', $participation),
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    /** التصحيح Server-side وحده */
    private function score(Challenge $challenge, array $answers): float
    {
        $score = 0;

        foreach ($this->items($challenge) as $i => $item) {
            $given = $answers[(string) $i] ?? $answers[$i] ?? null;

            if ($given === null) {
                continue;
            }

            $score += match ($item['kind'] ?? 'mcq') {
                // تقديرٌ رقميّ: الأقرب ضمن السماحيّة يأخذ النقطة (15.6)
                'number' => abs((float) $given - (float) ($item['answer'] ?? 0)) <= (float) ($item['tolerance'] ?? 0) ? 1 : 0,
                // مهمّة تركيز: العدّ مبني على الأمانة (15.3)
                'task' => $given ? 1 : 0,
                default => (string) $given === (string) ($item['answer'] ?? '') ? 1 : 0,
            };
        }

        return (float) $score;
    }

    /** المكافأة من `challenges.rewards` — والخسارة بلا عقوبة إضافيّة هنا */
    private function payout(ChallengeParticipation $participation, string $result): void
    {
        if ($result !== 'win') {
            return;
        }

        $rewards = $participation->challenge->rewards ?? [];
        $user = $participation->user;
        $reason = 'مكافأة تحدّي: '.$participation->challenge->name_ar;

        $xp = (int) ($rewards['xp'] ?? 0);

        if ($xp > 0) {
            $this->wallet->credit($user, 'xp', $xp, $reason, $participation);
            // عمود users.xp هو مصدر الترتيب في الليدر بورد (7.3) فيُحدَّث معه
            $user->increment('xp', $xp);
        }

        foreach (['tickets', 'coins'] as $code) {
            $amount = (float) ($rewards[$code] ?? 0);

            if ($amount > 0) {
                $this->wallet->credit($user, $code, $amount, $reason, $participation);
            }
        }
    }

    /** فكّ قفل الإعدادات حين لا تبقى حربٌ نشطة على هذا التحدّي */
    private function unlockSettings(Challenge $challenge): void
    {
        $stillRunning = ChallengeParticipation::query()
            ->where('challenge_id', $challenge->id)
            ->where('status', 'running')
            ->exists();

        if (! $stillRunning && $challenge->settings_locked) {
            $challenge->forceFill(['settings_locked' => false])->save();
        }
    }

    private function currencyCode(Challenge $challenge): string
    {
        return $challenge->entry_currency?->code
            ?? (string) setting('challenges.entry.default_currency', 'tickets');
    }
}
