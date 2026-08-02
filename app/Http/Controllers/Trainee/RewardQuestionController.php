<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\RewardQuestion;
use App\Services\Gamification\RewardQuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحة سؤال المكافأة كما يراها المتدرّب (12.10-أ).
 *
 * يفتح الرابط المنشور في جروبه فيجد **السؤال وفوقه تايمر الديدلاين**؛ وبعد
 * انتهاء الوقت يقفل الرابط ويظهر «انتهى وقت الإجابة».
 *
 * ⭐ **الإجابة الصحيحة لا تُمرَّر للصفحة إطلاقًا** — لا في HTML ولا في JSON:
 * التصحيح في الخادم وحده، وإلّا فأوّل مَن يفتح أدوات المطوّر يوزّعها على الجميع.
 */
class RewardQuestionController extends Controller
{
    public function __construct(private readonly RewardQuestionService $service) {}

    public function show(Request $request, string $token): View
    {
        $question = $this->find($token);
        $state = $this->service->liveState($question);

        return view('reward-questions.show', [
            'question' => $question,
            'state' => $state,
            'mine' => $this->service->answerOf($request->user(), $question),
            // الخيارات وحدها تُعرَض — بلا الإجابة الصحيحة
            'options' => (array) ($question->options ?? []),
        ]);
    }

    public function answer(Request $request, string $token): RedirectResponse
    {
        $question = $this->find($token);

        $data = $request->validate([
            'answer' => ['required', 'string', 'max:500'],
        ]);

        $result = $this->service->answer($request->user(), $question, $data['answer']);

        $message = $result['message'];

        if ($result['xp'] > 0 || $result['tickets'] > 0) {
            $message .= ' (+'.$result['xp'].' XP · +'.$result['tickets'].' تذكرة)';
        }

        return redirect()
            ->route('reward-questions.show', $question->token)
            ->with('status', $message);
    }

    /** السؤال بمفتاح رابطه — والمؤرشف/المسودّة لا يُفتَحان أصلًا */
    private function find(string $token): RewardQuestion
    {
        abort_unless((bool) setting('reward_questions.enabled', true), 404);

        return RewardQuestion::query()
            ->where('token', $token)
            ->whereIn('status', ['published', 'archived'])
            ->firstOrFail();
    }
}
