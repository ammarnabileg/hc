<?php

namespace App\Services\Gamification;

use App\Models\RewardQuestion;
use App\Models\RewardQuestionAnswer;
use App\Models\User;
use App\Services\Certificates\QrCode;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * أسئلة المكافأة (12.10-أ).
 *
 * الفكرة: سؤالٌ **برابط مؤقّت** ينشره الأدمن لجروبات المتدرّبين، وفوقه **تايمر
 * نازل** — ندرةٌ صادقة تحفّز الإجابة الفوريّة — وبعد انتهاء الوقت يقفل الرابط
 * ويظهر «انتهى وقت الإجابة».
 *
 * القواعد المقفولة هنا (لا تُترَك للواجهة):
 *  - **التصحيح Server-side** والإجابة الصحيحة لا تُرسَل للمتصفّح أبدًا.
 *  - **إجابة واحدة لكلّ مستخدم** ومنع تكرار الصرف — بقيد فريد في قاعدة البيانات.
 *  - **المكافأة تُصرَف مرّة واحدة** وتُسجَّل في نفس سطر الإجابة.
 */
class RewardQuestionService
{
    /** مفتاح صفّ الكسب في جدول «مصادر كسب XP» (12.10) */
    public const EARN_RULE = 'reward.question';

    /** دلو المصدر في دفتر الأستاذ */
    private const LEDGER_SOURCE = 'reward_question';

    public function __construct(
        private readonly EconomyLedger $economy,
        private readonly EconomyRules $rules,
    ) {}

    // ------------------------------------------------------------ الحالة الحيّة

    /**
     * حالة السؤال الآن — بلونٍ ورمزٍ معًا (2.16) وعدّادٍ حين يكون نشطًا.
     *
     * @return array{key:string,label:string,state:string,seconds_left:?int,open:bool}
     */
    public function liveState(RewardQuestion $question, ?Carbon $at = null): array
    {
        $at ??= Carbon::now();

        if ($question->status === 'draft') {
            return $this->state('draft', setting('reward_questions.labels.draft', 'مسودّة'), 'idle', null, false);
        }

        if ($question->status === 'archived') {
            return $this->state('archived', setting('reward_questions.labels.archived', 'مؤرشف'), 'idle', null, false);
        }

        if ($question->opens_at && $at->lessThan($question->opens_at)) {
            return $this->state('scheduled', setting('reward_questions.labels.scheduled', 'مجدول'), 'warn', null, false);
        }

        if ($question->closes_at && $at->greaterThanOrEqualTo($question->closes_at)) {
            return $this->state('closed', setting('reward_questions.labels.closed', 'مغلق'), 'danger', 0, false);
        }

        $secondsLeft = $question->closes_at
            ? max(0, (int) $at->diffInSeconds($question->closes_at, false))
            : null;

        return $this->state('active', setting('reward_questions.labels.active', 'نشط'), 'ok', $secondsLeft, true);
    }

    public function isOpen(RewardQuestion $question, ?Carbon $at = null): bool
    {
        return $this->liveState($question, $at)['open'];
    }

    /** رابط السؤال العامّ — يُنسَخ ويُشارَك على واتساب (12.10-أ) */
    public function url(RewardQuestion $question): string
    {
        return route('reward-questions.show', $question->token);
    }

    /**
     * ⭐ QR الرابط **مرسومًا داخل المشروع** (2.16-ج): مصفوفة من مولّدنا تتحوّل
     * إلى مربّعات SVG — بلا أيّ مكتبة أيقونات أو باركود خارجيّة.
     */
    public function qrSvg(RewardQuestion $question, int $side = 132): string
    {
        $matrix = QrCode::matrix($this->url($question));
        $modules = count($matrix);

        if ($modules === 0) {
            return '';
        }

        $rects = '';

        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $on) {
                if ($on) {
                    $rects .= '<rect x="'.$x.'" y="'.$y.'" width="1" height="1" />';
                }
            }
        }

        return '<svg viewBox="0 0 '.$modules.' '.$modules.'" width="'.$side.'" height="'.$side.'" role="img" '
            .'aria-label="QR رابط السؤال" shape-rendering="crispEdges">'
            .'<rect width="'.$modules.'" height="'.$modules.'" fill="#ffffff" />'
            .'<g fill="#000000">'.$rects.'</g></svg>';
    }

    /** نصّ رسالة الواتساب الجاهزة + الرابط */
    public function shareText(RewardQuestion $question): string
    {
        return trim((string) setting('reward_questions.whatsapp_text', 'سؤال المكافأة النهارده — جاوب قبل ما الوقت يخلص:'))
            .' '.$this->url($question);
    }

    // ------------------------------------------------------------ الكتابة

    /**
     * إنشاء/تعديل سؤال — و`closes_at` تُحسَب من **مدّة التفعيل** لا تُكتَب يدويًّا،
     * فلا يختلف ما يراه الأدمن عمّا يحسبه الخادم.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, ?RewardQuestion $question = null, ?User $actor = null): RewardQuestion
    {
        $question ??= new RewardQuestion(['token' => $this->newToken()]);

        $minutes = max(1, (int) ($data['active_minutes'] ?? setting('reward_questions.default_minutes', 60)));
        $status = (string) ($data['status'] ?? $question->status ?? 'draft');

        // بلا جدولة تلقائيّة: السؤال يفتح فور نشره مهما كُتِب في حقل الموعد
        $opensAt = setting('reward_questions.autoschedule_enabled', true)
            ? (! empty($data['opens_at']) ? Carbon::parse($data['opens_at']) : ($question->opens_at ?? Carbon::now()))
            : Carbon::now();

        $wasOpen = $question->exists && $this->isOpen($question);

        $question->fill([
            'prompt' => $data['prompt'],
            'type' => $data['type'] ?? 'choice',
            'options' => $this->cleanOptions($data['options'] ?? null),
            'correct_answer' => (string) $data['correct_answer'],
            'reward_xp' => max(0, (int) ($data['reward_xp'] ?? 0)),
            'reward_tickets' => max(0, (int) ($data['reward_tickets'] ?? 0)),
            'active_minutes' => $minutes,
            'opens_at' => $opensAt,
            // مسودّة بلا موعد إغلاق: العدّاد لا يبدأ إلّا بالنشر
            'closes_at' => $status === 'published' ? $opensAt->copy()->addMinutes($minutes) : null,
            'status' => $status,
        ]);

        if (! $question->exists && $actor) {
            $question->created_by = $actor->id;
        }

        $question->save();

        // Toast/إشعار بفتح سؤال جديد — مرّة واحدة عند أوّل فتحٍ فعليّ لا مع كلّ حفظ
        if (! $wasOpen && $this->isOpen($question)) {
            $this->announce($question);
        }

        return $question;
    }

    /**
     * استيراد دفعة من ملفّ CSV (12.10-أ).
     * الأعمدة: السؤال · النوع · الاختيارات (مفصولة بـ`|`) · الإجابة · XP · تذاكر · الدقائق.
     * والصفوف تُستورَد **مسودّات** دائمًا — فلا ينشر ملفٌّ سؤالًا بلا مراجعة.
     *
     * @return array{imported:int,errors:array<int,string>}
     */
    public function importCsv(string $contents, ?User $actor = null): array
    {
        $imported = 0;
        $errors = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];

        foreach ($lines as $index => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $columns = str_getcsv($line);

            // سطر العناوين يُتخطّى بلا خطأ
            if ($index === 0 && ! empty($columns[0]) && mb_strtolower(trim($columns[0])) === 'prompt') {
                continue;
            }

            $prompt = trim((string) ($columns[0] ?? ''));
            $answer = trim((string) ($columns[3] ?? ''));

            if ($prompt === '' || $answer === '') {
                $errors[] = 'الصفّ رقم '.($index + 1).': السؤال أو الإجابة ناقص.';

                continue;
            }

            $this->save([
                'prompt' => $prompt,
                'type' => in_array(trim((string) ($columns[1] ?? '')), ['choice', 'text', 'number'], true)
                    ? trim((string) $columns[1])
                    : 'choice',
                'options' => array_filter(explode('|', (string) ($columns[2] ?? ''))),
                'correct_answer' => $answer,
                'reward_xp' => (int) ($columns[4] ?? 0),
                'reward_tickets' => (int) ($columns[5] ?? 0),
                'active_minutes' => (int) ($columns[6] ?? setting('reward_questions.default_minutes', 60)),
                'status' => 'draft',
            ], null, $actor);

            $imported++;
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    /** إغلاق فوريّ: الرابط يقفل الآن ويظهر «انتهى وقت الإجابة» */
    public function closeNow(RewardQuestion $question): RewardQuestion
    {
        $question->forceFill(['closes_at' => Carbon::now()])->save();

        return $question;
    }

    /**
     * تسجيل إجابة وصرف المكافأة — **التصحيح هنا وحده**.
     *
     * @return array{ok:bool,correct:bool,already:bool,xp:int,tickets:int,message:string}
     */
    public function answer(User $user, RewardQuestion $question, string $submitted): array
    {
        if (! $this->isOpen($question)) {
            return $this->refuse((string) setting('reward_questions.closed_text', 'انتهى وقت الإجابة'));
        }

        $existing = RewardQuestionAnswer::query()
            ->where('reward_question_id', $question->id)
            ->where('user_id', $user->id)
            ->first();

        // حدّ إجابة واحدة لكلّ مستخدم — ولا صرف ثانٍ مهما تكرّر الإرسال
        if ($existing) {
            return [
                'ok' => true,
                'correct' => (bool) $existing->is_correct,
                'already' => true,
                'xp' => 0,
                'tickets' => 0,
                'message' => (string) setting('reward_questions.already_message', 'جاوبت على السؤال ده قبل كده — مكافأتك اتصرفت مرّة واحدة.'),
            ];
        }

        $isCorrect = $this->matches($question, $submitted);
        $xp = $isCorrect ? $this->rewardXp($question) : 0;
        $tickets = $isCorrect ? (int) $question->reward_tickets : 0;

        $granted = DB::transaction(function () use ($user, $question, $submitted, $isCorrect, $xp, $tickets) {
            $answer = RewardQuestionAnswer::query()->firstOrCreate(
                ['reward_question_id' => $question->id, 'user_id' => $user->id],
                [
                    'answer' => $submitted,
                    'is_correct' => $isCorrect,
                    'xp_awarded' => 0,
                    'tickets_awarded' => 0,
                    'answered_at' => Carbon::now(),
                ],
            );

            // سبقنا نداءٌ آخر إلى السطر ⟵ لا صرف ثانيًا
            if (! $answer->wasRecentlyCreated || ! $isCorrect) {
                return ['xp' => 0, 'tickets' => 0];
            }

            $reason = (string) setting('reward_questions.ledger_reason', 'إجابة صحيحة على سؤال مكافأة');

            $awardedXp = $this->economy->awardXp(
                user: $user,
                amount: $xp,
                source: self::LEDGER_SOURCE,
                reference: $question,
                reason: $reason,
                ruleKey: self::EARN_RULE,
            );

            $awardedTickets = (int) $this->economy->awardTickets(
                user: $user,
                amount: $tickets,
                source: self::LEDGER_SOURCE,
                reference: $question,
                reason: $reason,
            );

            $answer->forceFill(['xp_awarded' => $awardedXp, 'tickets_awarded' => $awardedTickets])->save();

            return ['xp' => $awardedXp, 'tickets' => $awardedTickets];
        });

        return [
            'ok' => true,
            'correct' => $isCorrect,
            'already' => false,
            'xp' => $granted['xp'],
            'tickets' => $granted['tickets'],
            'message' => $isCorrect
                ? (string) setting('reward_questions.correct_message', 'إجابة صحيحة 🎉 — مكافأتك اتضافت لحسابك.')
                : (string) setting('reward_questions.wrong_message', 'مش الإجابة الصحيحة المرّة دي — بس شكرًا إنك جاوبت بسرعة.'),
        ];
    }

    // ------------------------------------------------------------ النتائج

    /**
     * نتائج بعد الإغلاق: كم حلّه · نسبة الصحّ · أسرع مجيب (12.10-أ).
     *
     * @return array{answers:int,correct:int,percent:int,fastest:?RewardQuestionAnswer}
     */
    public function results(RewardQuestion $question): array
    {
        $answers = RewardQuestionAnswer::query()
            ->where('reward_question_id', $question->id)
            ->with('user')
            ->orderBy('answered_at')
            ->get();

        $correct = $answers->where('is_correct', true);

        return [
            'answers' => $answers->count(),
            'correct' => $correct->count(),
            'percent' => $answers->count() > 0 ? (int) round($correct->count() / $answers->count() * 100) : 0,
            'fastest' => $correct->first(),
        ];
    }

    /** إجابة هذا المستخدم إن وُجدت — لعرض حالته في الصفحة */
    public function answerOf(User $user, RewardQuestion $question): ?RewardQuestionAnswer
    {
        return RewardQuestionAnswer::query()
            ->where('reward_question_id', $question->id)
            ->where('user_id', $user->id)
            ->first();
    }

    // ------------------------------------------------------------ داخليّ

    /**
     * إشعار بفتح سؤال جديد (12.10-أ) — لأصحاب الحسابات المفعَّلة وحدهم،
     * ويمرّ من **بوّابة الإشعارات الموحّدة** (2.8) لا بقناة خاصّة بهذا المجال.
     */
    private function announce(RewardQuestion $question): void
    {
        if (! setting('reward_questions.notify_on_open', true)) {
            return;
        }

        $users = User::query()->where('status', 'active')->cursor();

        Notifier::sendMany(
            $users,
            'reward_question',
            (string) setting('reward_questions.page_title', 'سؤال المكافأة'),
            Str::limit($question->prompt, 90),
            $this->url($question),
            'platform',
            $question->closes_at,
        );
    }

    /** قيمة XP: قيمة السؤال أوّلًا، وإلّا فصفّ «سؤال مكافأة» في جدول الكسب (12.10) */
    private function rewardXp(RewardQuestion $question): int
    {
        return (int) $question->reward_xp ?: $this->rules->earnValue(self::EARN_RULE);
    }

    private function newToken(): string
    {
        do {
            $token = Str::lower(Str::random((int) setting('reward_questions.token_length', 12)));
        } while (RewardQuestion::query()->where('token', $token)->exists());

        return $token;
    }

    /** @param  array<int, string>|string|null  $options */
    private function cleanOptions(array|string|null $options): ?array
    {
        if (is_string($options)) {
            $options = preg_split('/\r\n|\r|\n/', $options) ?: [];
        }

        $clean = array_values(array_filter(array_map('trim', (array) $options), fn ($o) => $o !== ''));

        return $clean === [] ? null : $clean;
    }

    /** المقارنة تتحمّل فروق المسافات وأشكال الأرقام — فلا تُرفَض إجابة صحيحة شكلًا */
    private function matches(RewardQuestion $question, string $submitted): bool
    {
        $expected = $this->normalize((string) $question->correct_answer);
        $given = $this->normalize($submitted);

        if ($expected === '' || $given === '') {
            return false;
        }

        if ($question->type === 'number') {
            return is_numeric($given) && is_numeric($expected)
                && abs((float) $given - (float) $expected) < 0.000001;
        }

        return $expected === $given;
    }

    private function normalize(string $value): string
    {
        $value = str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value,
        );

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower(trim($value));
    }

    /** @return array{key:string,label:string,state:string,seconds_left:?int,open:bool} */
    private function state(string $key, mixed $label, string $state, ?int $secondsLeft, bool $open): array
    {
        return [
            'key' => $key,
            'label' => (string) $label,
            'state' => $state,
            'seconds_left' => $secondsLeft,
            'open' => $open,
        ];
    }

    /** @return array{ok:bool,correct:bool,already:bool,xp:int,tickets:int,message:string} */
    private function refuse(string $message): array
    {
        return ['ok' => false, 'correct' => false, 'already' => false, 'xp' => 0, 'tickets' => 0, 'message' => $message];
    }
}
