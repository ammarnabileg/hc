<?php

namespace App\Services\Onboarding;

use App\Models\PlacementTestAnswer;
use App\Models\PlacementTestQuestion;
use App\Models\User;
use App\Services\Gamification\EconomyLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * الاختبار التمهيديّ (2.5-د-2): بنك أسئلته وشاشته ونتيجته وأثرها.
 *
 * ⚠️ ليس تسكين المتطوّعين (`placement_requests` — 13.4). هذا اختبارٌ يدخله
 * **كلّ مُسجَّل جديد** قبل أن يصل ملفُّه للأدمن، وغرضه المنصوص **التصفية بلا مال**.
 *
 * **الأثر** (لأنّ اختبارًا بلا أثر ليس اختبارًا):
 *   · مكافأة كلّ سؤال تُصرَف من **المصدر الموحّد** لا بزيادة عمود يدويّة (7 · 19)،
 *   · النتيجة تُحفَظ على الحساب فيراها الأدمن قبل قرار الاعتماد (2.5-د-3)،
 *   · والإجابات تبقى مفصَّلةً بسطرٍ لكلّ سؤال — فالقرار قابل للمراجعة لا رقمًا مجرَّدًا.
 *
 * والتصحيح **في الخادم حصرًا**: الإجابة الصحيحة لا تخرج للمتصفّح أبدًا.
 */
class PlacementTest
{
    public function __construct(private readonly EconomyLedger $economy) {}

    public function isEnabled(): bool
    {
        return (bool) setting('onboarding.placement.enabled', true) && $this->questions()->isNotEmpty();
    }

    /** @return Collection<int, PlacementTestQuestion> */
    public function questions(): Collection
    {
        return PlacementTestQuestion::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * تسليم الإجابات: تصحيح وصرف وتسجيل — **مرّة واحدة** لكلّ حساب.
     *
     * @param  array<int|string, string|null>  $answers  معرّف السؤال ⟵ الإجابة
     * @return array{score:int, total:int, xp:int, tickets:int}
     */
    public function submit(User $user, array $answers): array
    {
        $questions = $this->questions();
        $score = 0;
        $xp = 0;
        $tickets = 0;

        foreach ($questions as $question) {
            $given = trim((string) ($answers[$question->id] ?? ''));
            $correct = $this->isCorrect($question, $given);

            // القيد الفريد يحمي من التسليم المزدوج، و`updateOrCreate` يمنع الاستثناء
            $already = PlacementTestAnswer::query()
                ->where('placement_test_question_id', $question->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($already) {
                continue;
            }

            $earnedXp = $correct ? (int) $question->reward_xp : 0;
            $earnedTickets = $correct ? (int) $question->reward_tickets : 0;

            $answer = PlacementTestAnswer::create([
                'placement_test_question_id' => $question->id,
                'user_id' => $user->id,
                'answer' => $given === '' ? null : $given,
                'is_correct' => $correct,
                'xp_awarded' => $earnedXp,
                'tickets_awarded' => $earnedTickets,
                'answered_at' => now(),
            ]);

            if ($correct) {
                $score++;
            }

            // المكافأة «XP فقط أو تذاكر فقط أو الاثنين» — كلٌّ من مصدره الموحّد
            if ($earnedXp > 0) {
                $xp += $this->economy->awardXp(
                    user: $user,
                    amount: $earnedXp,
                    source: 'placement',
                    reference: $answer,
                    reason: (string) setting('onboarding.placement.xp_reason', 'إجابة صحيحة في الاختبار التمهيديّ'),
                );
            }

            if ($earnedTickets > 0) {
                $tickets += (int) $this->economy->awardTickets(
                    user: $user,
                    amount: $earnedTickets,
                    source: 'placement',
                    reference: $answer,
                    reason: (string) setting('onboarding.placement.tickets_reason', 'مكافأة سؤال في الاختبار التمهيديّ'),
                );
            }
        }

        $total = $questions->count();

        // النتيجة نسبةً مئويّة: عدد الأسئلة يتغيّر، والنسبة تبقى مفهومة للأدمن
        $user->forceFill([
            'placement_completed_at' => now(),
            'placement_score' => $total > 0 ? (int) round($score / $total * 100) : 0,
        ])->save();

        return ['score' => $score, 'total' => $total, 'xp' => $xp, 'tickets' => $tickets];
    }

    /** إجابات المستخدم مفهرسةً بالسؤال — لعرض النتيجة والمراجعة */
    public function answersOf(User $user): Collection
    {
        return PlacementTestAnswer::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('placement_test_question_id');
    }

    /** ملخّص لصفحة الأدمن قبل قرار الاعتماد (2.5-د-3) */
    public function summaryOf(User $user): array
    {
        $rows = DB::table('placement_test_answers')
            ->where('user_id', $user->id)
            ->selectRaw('count(*) as total, sum(case when is_correct = 1 then 1 else 0 end) as correct')
            ->first();

        return [
            'answered' => (int) ($rows->total ?? 0),
            'correct' => (int) ($rows->correct ?? 0),
            'score' => $user->placement_score,
            'completed_at' => $user->placement_completed_at,
        ];
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * التصحيح: مقارنةٌ متسامحة مع المسافات وحالة الأحرف — فالمستخدم لا يُعاقَب
     * على مسافةٍ زائدة. والسؤال بلا إجابة صحيحة سؤالُ استطلاع: أيّ إجابة تُقبَل.
     */
    private function isCorrect(PlacementTestQuestion $question, string $given): bool
    {
        $expected = trim((string) $question->correct_answer);

        if ($expected === '') {
            return $given !== '';
        }

        return mb_strtolower($this->normalize($given)) === mb_strtolower($this->normalize($expected));
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
