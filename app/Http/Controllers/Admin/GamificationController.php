<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\CelebrationEvent;
use App\Models\Challenge;
use App\Models\Level;
use App\Models\RewardQuestion;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Services\Admin\Volunteer\WarSettingsService;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\EconomyRules;
use App\Services\Gamification\RewardQuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * التلعيب والتحديات (12.10 موسّع · 2.14 · 7 · 7.1 · 7.2 · 7.3 · 7.4).
 *
 * شاشة واحدة بتابات داخليّة تُحمَّل كسولًا (2.15-د): XP · التذاكر · الشارات ·
 * الستريكس ونادي الخامسة · الليدر بورد · المستويات · الحروب · الاحتفالات.
 */
class GamificationController extends Controller
{
    public const TABS = [
        'xp' => 'XP والتذاكر',
        'badges' => 'الشارات',
        'streaks' => 'الستريكس ونادي الخامسة',
        'leaderboard' => 'الليدر بورد',
        'levels' => 'المستويات',
        'wars' => 'الحروب والتحديات',
        'reward_questions' => 'أسئلة المكافآت',
        'celebrations' => 'الاحتفالات',
    ];

    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString() ?: 'xp';

        abort_unless(array_key_exists($tab, self::TABS), 404);

        return view('admin.gamification.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            // تحميل كسول: التاب المفتوح وحده يجهّز بياناته (2.15-د · 2.7)
            'data' => $this->dataFor($tab, $request),
        ]);
    }

    // ------------------------------------------------------------ XP والتذاكر

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }

    public function resetGroup(Request $request): RedirectResponse
    {
        $data = $request->validate(['group' => ['required', 'string']]);

        abort_unless(str_starts_with($data['group'], 'gamification_'), 404);

        $count = SettingsWriter::resetGroup($data['group'], $request->user());

        return back()->with('status', 'رجعت '.$count.' قيمة للافتراضيّ ✓');
    }

    /** صفوف الكسب/الصرف تُحرَّر كجدول (12.10 — XP والتذاكر) */
    public function saveXpRows(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'in:xp_rules.earn,xp_rules.spend'],
            'rows' => ['nullable', 'array'],
        ]);

        SettingsWriter::put($data['key'], array_values($data['rows'] ?? []), $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }

    // ------------------------------------------------------------ الشارات

    /** الشارة: هويّة + **شرط الفتح مكتوب صراحةً** + الأيقونة (7.4) */
    public function saveBadge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:badges,id'],
            'key' => ['required', 'string', 'max:64'],
            'name_ar' => ['required', 'string', 'max:120'],
            // ⭐ التسمية ثنائيّة اللغة قاعدة عامّة تشمل الشارات نصًّا (القسم 3)
            'name_en' => ['required', 'string', 'max:120'],
            'description_ar' => ['nullable', 'string', 'max:500'],
            'description_en' => ['nullable', 'string', 'max:500'],
            'condition_text_ar' => ['required', 'string', 'max:255'],
            'condition_text_en' => ['nullable', 'string', 'max:255'],
            /*
             | ⭐ المفتاح من **القائمة المقفولة** لا نصًّا حرًّا (7.4).
             | الحقل الحرّ كان مصنع الشارات الميتة: مفتاحٌ لا يقابله مقياس =
             | شارةٌ لا تُمنَح أبدًا، والمتدرّب يرى «0% من الشرط» وهو مستوفيه.
             */
            'condition_key' => ['nullable', 'string', Rule::in(array_keys(BadgeService::CONDITIONS))],
            'condition_value' => ['nullable', 'integer', 'min:0'],
            'icon' => ['nullable', 'image', 'max:'.(int) setting('badges.icon.max_kb', 512)],
            'icon_path' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name_en' => 'الاسم بالإنجليزيّة',
            'condition_key' => 'مقياس الشرط',
            'icon' => 'صورة الشارة',
        ]);

        $badge = isset($data['id']) ? Badge::findOrFail($data['id']) : new Badge;
        $old = $badge->exists ? $badge->only(['name_ar', 'condition_text_ar']) : [];

        // رفع الصورة (7.4: «اسم + وصف + **صورة**») — والمسار النصّيّ يبقى للقوالب الجاهزة
        $iconPath = $request->hasFile('icon')
            ? $request->file('icon')->store((string) setting('badges.icon.directory', 'badges'), 'public')
            : ($data['icon_path'] ?? $badge->icon_path);

        $badge->fill([
            'key' => $data['key'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'condition_text_ar' => $data['condition_text_ar'],
            'condition_text_en' => $data['condition_text_en'] ?? null,
            'condition_key' => $data['condition_key'] ?? null,
            'condition_value' => $data['condition_value'] ?? null,
            'icon_path' => $iconPath,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        AuditTrail::log($request->user(), 'badge.save', $badge, $old, $badge->only(['key', 'name_ar', 'condition_text_ar']));

        return back()->with('status', 'اتحفظ ✓');
    }

    public function deleteBadge(Request $request, Badge $badge): RedirectResponse
    {
        AuditTrail::log($request->user(), 'badge.delete', $badge, $badge->only(['key', 'name_ar']), []);
        $badge->delete();

        return back()->with('status', 'اتحذفت الشارة ✓');
    }

    // ------------------------------------------------------------ المستويات

    public function saveLevel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:levels,id'],
            'level' => ['required', 'integer', 'min:1'],
            'name_ar' => ['required', 'string', 'max:80'],
            'min_xp' => ['required', 'integer', 'min:0'],
        ]);

        $level = isset($data['id']) ? Level::findOrFail($data['id']) : new Level;
        $level->fill(['level' => $data['level'], 'name_ar' => $data['name_ar'], 'min_xp' => $data['min_xp']])->save();

        AuditTrail::log($request->user(), 'level.save', $level, [], $level->only(['level', 'min_xp']));

        return back()->with('status', 'اتحفظ ✓');
    }

    public function deleteLevel(Request $request, Level $level): RedirectResponse
    {
        AuditTrail::log($request->user(), 'level.delete', $level, $level->only(['level', 'min_xp']), []);
        $level->delete();

        return back()->with('status', 'اتحذف المستوى ✓');
    }

    // ------------------------------------------------------------ الحروب

    public function saveWar(Request $request, Challenge $challenge): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:16'],
            'icon_path' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'entry_cost' => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'costs' => ['nullable', 'array'],
            'rewards' => ['nullable', 'array'],
            'timers' => ['nullable', 'array'],
            'question_source' => ['nullable', 'array'],
            'limits' => ['nullable', 'array'],
            'texts' => ['nullable', 'array'],
        ]);

        try {
            // ⭐ قفل الإعدادات أثناء حرب نشطة — لا تتغيّر القواعد على لاعبٍ في منتصفها
            WarSettingsService::save($challenge, $data, $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('status', $e->getMessage());
        }

        return back()->with('status', 'اتحفظ ✓');
    }

    public function resetWar(Request $request, Challenge $challenge): RedirectResponse
    {
        try {
            WarSettingsService::reset($challenge, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', 'رجعت الحرب للافتراضيّ ✓');
    }

    // ------------------------------------------------------------ أسئلة المكافآت (12.10-أ)

    /**
     * إنشاء/تعديل سؤال مكافأة.
     * **الإجابة الصحيحة تُحفَظ ولا تُعرَض في أيّ صفحة يراها المتدرّب** — التصحيح
     * في الخادم وحده، وإلّا فمن يفتح مصدر الصفحة يوزّع الإجابة على الجروب كلّه.
     */
    public function saveRewardQuestion(Request $request, RewardQuestionService $service): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:reward_questions,id'],
            'prompt' => ['required', 'string', 'max:2000'],
            'type' => ['required', 'string', 'in:choice,text,number'],
            'options' => ['nullable', 'string', 'max:2000'],
            'correct_answer' => ['required', 'string', 'max:500'],
            'reward_xp' => ['nullable', 'integer', 'min:0'],
            'reward_tickets' => ['nullable', 'integer', 'min:0'],
            'active_minutes' => ['nullable', 'integer', 'min:1'],
            'opens_at' => ['nullable', 'date'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
        ]);

        $question = isset($data['id']) ? RewardQuestion::findOrFail($data['id']) : null;
        $old = $question?->only(['status', 'closes_at']) ?? [];

        $saved = $service->save($data, $question, $request->user());

        AuditTrail::log($request->user(), 'reward_question.save', $saved, $old, $saved->only(['status', 'opens_at', 'closes_at']));

        return back()->with('status', 'اتحفظ ✓');
    }

    /**
     * استيراد دفعة أسئلة من CSV (12.10-أ) — والصفوف تدخل **مسودّات**،
     * فلا ينشر ملفٌّ سؤالًا قبل أن يراه إنسان.
     */
    public function importRewardQuestions(Request $request, RewardQuestionService $service): RedirectResponse
    {
        abort_unless((bool) setting('reward_questions.csv_import_enabled', true), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel'],
        ]);

        $result = $service->importCsv(
            (string) file_get_contents($request->file('file')->getRealPath()),
            $request->user(),
        );

        AuditTrail::log($request->user(), 'reward_question.import', null, [], ['imported' => $result['imported']]);

        // ماذا حدث + ماذا تفعل (2.17-ج): العدد المستورَد وأسطر الخطأ إن وُجدت
        $message = 'اتستوردت '.$result['imported'].' أسئلة كمسودّات ✓';

        if ($result['errors'] !== []) {
            $message .= ' — تخطّينا: '.implode(' · ', array_slice($result['errors'], 0, 3));
        }

        return back()->with('status', $message);
    }

    /** إغلاق فوريّ: الرابط يقفل الآن ويظهر «انتهى وقت الإجابة» (12.10-أ) */
    public function closeRewardQuestion(Request $request, RewardQuestion $rewardQuestion, RewardQuestionService $service): RedirectResponse
    {
        $service->closeNow($rewardQuestion);

        AuditTrail::log($request->user(), 'reward_question.close', $rewardQuestion, [], ['closes_at' => $rewardQuestion->closes_at]);

        return back()->with('status', 'اتقفل السؤال ✓');
    }

    /** نتائج بعد الإغلاق: كم حلّه · نسبة الصحّ · أسرع مجيب (12.10-أ) */
    public function rewardQuestionResults(RewardQuestion $rewardQuestion, RewardQuestionService $service): View
    {
        return view('admin.gamification.reward-questions.results', [
            'question' => $rewardQuestion,
            'results' => $service->results($rewardQuestion),
            'state' => $service->liveState($rewardQuestion),
            'link' => $service->url($rewardQuestion),
        ]);
    }

    // ------------------------------------------------------------ الاحتفالات

    /**
     * ربط كلّ حدث بمستواه + الصوت + نصّ التهنئة.
     * ⭐ الأنيميشن دائم بلا توجل — الصوت وحده له توجل (2.14-ب).
     */
    public function saveCelebration(Request $request, CelebrationEvent $celebrationEvent): RedirectResponse
    {
        $data = $request->validate([
            'tier' => ['required', 'integer', 'in:1,2,3'],
            'sound_path' => ['nullable', 'string', 'max:255'],
            'message_ar' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $old = $celebrationEvent->only(['tier', 'is_active']);

        $celebrationEvent->fill([
            'tier' => $data['tier'],
            'sound_path' => $data['sound_path'] ?? null,
            'message_ar' => $data['message_ar'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        AuditTrail::log($request->user(), 'celebration.save', $celebrationEvent, $old, $celebrationEvent->only(['tier', 'is_active']));

        return back()->with('status', 'اتحفظ ✓');
    }

    // ------------------------------------------------------------ داخليّ

    private function dataFor(string $tab, Request $request): array
    {
        return match ($tab) {
            'xp' => [
                'settings' => SettingsWriter::groupRows('gamification_xp'),
                'earn' => (array) setting('xp_rules.earn', []),
                'spend' => (array) setting('xp_rules.spend', []),
                // ⭐ أيّ صفٍّ لا يقرؤه الكود يُعلَّم في الشاشة — لا إعداد بلا أثر (2.13)
                'earnConsumed' => EconomyRules::CONSUMED_EARN,
            ],
            'badges' => [
                'settings' => SettingsWriter::groupRows('gamification_badges'),
                'badges' => Badge::query()->orderBy('id')->get(),
                // ⭐ القائمة المقفولة للمقاييس — الأدمن يختار ولا يكتب (7.4)
                'conditions' => BadgeService::CONDITIONS,
            ],
            'streaks' => [
                'settings' => SettingsWriter::groupRows('gamification_streaks'),
                'ladder' => (array) setting('streaks.xp_ladder', []),
            ],
            'leaderboard' => [
                'settings' => SettingsWriter::groupRows('gamification_leaderboard'),
            ],
            'levels' => [
                'levels' => Level::query()->orderBy('level')->get(),
                'settings' => SettingsWriter::groupRows('gamification_xp'),
            ],
            'wars' => [
                'settings' => SettingsWriter::groupRows('gamification_wars'),
                'shared' => WarSettingsService::sharedDefaults(),
                'sections' => WarSettingsService::SECTIONS,
                'challenges' => Challenge::query()->orderBy('id')->get(),
                'selected' => $this->selectedWar($request),
            ],
            // أسئلة المكافآت (12.10-أ): الحالة حيّة بعدّاد، والإجابة مخفيّة افتراضيًّا
            'reward_questions' => [
                'settings' => SettingsWriter::groupRows('gamification_reward_questions'),
                'questions' => RewardQuestion::query()->latest('id')
                    ->limit((int) setting('ux.lists.per_page', 25))->get(),
                'service' => app(RewardQuestionService::class),
            ],
            'celebrations' => [
                'settings' => SettingsWriter::groupRows('gamification_celebrations'),
                'events' => CelebrationEvent::query()->orderBy('tier')->orderBy('key')->get(),
                'tiers' => [1 => 'خفيف', 2 => 'متوسّط', 3 => 'ذروة'],
            ],
            default => [],
        };
    }

    private function selectedWar(Request $request): ?Challenge
    {
        $id = (int) $request->integer('war');

        return $id
            ? Challenge::find($id)
            : Challenge::query()->orderBy('id')->first();
    }
}
