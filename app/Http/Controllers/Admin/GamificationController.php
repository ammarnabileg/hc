<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\CelebrationEvent;
use App\Models\Challenge;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\Level;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Services\Admin\Volunteer\WarSettingsService;
use App\Services\Gamification\GamesAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        'games' => 'الألعاب',
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
            'name_en' => ['nullable', 'string', 'max:120'],
            'condition_text_ar' => ['required', 'string', 'max:255'],
            'condition_key' => ['nullable', 'string', 'max:64'],
            'condition_value' => ['nullable', 'integer', 'min:0'],
            'icon_path' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $badge = isset($data['id']) ? Badge::findOrFail($data['id']) : new Badge;
        $old = $badge->exists ? $badge->only(['name_ar', 'condition_text_ar']) : [];

        $badge->fill([
            'key' => $data['key'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'condition_text_ar' => $data['condition_text_ar'],
            'condition_key' => $data['condition_key'] ?? null,
            'condition_value' => $data['condition_value'] ?? null,
            'icon_path' => $data['icon_path'] ?? null,
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

    // ------------------------------------------------------------ الألعاب (24.2)

    /** إعدادات قسم الألعاب — كتالوج المجال لا أرقام محروقة (2.13) */
    public function saveGameSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);

        GamesAdminService::saveSettings($data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }

    public function resetGameSettings(Request $request): RedirectResponse
    {
        $count = GamesAdminService::resetSettings($request->user());

        return back()->with('status', 'رجعت '.$count.' قيمة للافتراضيّ ✓');
    }

    public function saveGame(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:games,id'],
            'key' => ['required', 'string', 'max:48'],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon_svg' => ['nullable', 'string', 'max:8000'],
            'ticket_cost' => ['nullable', 'numeric', 'min:0'],
            'xp_mode' => ['nullable', 'string'],
            'xp_reward' => ['nullable', 'integer', 'min:0'],
            'daily_limit' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string'],
            'soon_text' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        GamesAdminService::saveGame(
            isset($data['id']) ? Game::findOrFail($data['id']) : null,
            $data,
            $request->user(),
        );

        return back()->with('status', 'اتحفظ ✓');
    }

    public function reverseGameSession(Request $request, GameSession $gameSession): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:240']]);

        GamesAdminService::reverseSession($gameSession, $data['reason'], $request->user());

        return back()->with('status', 'اتعكست الجلسة ✓');
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
            ],
            'badges' => [
                'settings' => SettingsWriter::groupRows('gamification_badges'),
                'badges' => Badge::query()->orderBy('id')->get(),
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
            'games' => [
                'settings' => GamesAdminService::rows(),
                'games' => Game::query()->orderBy('sort_order')->orderBy('id')->get()
                    ->each(fn (Game $g) => $g->setAttribute(
                        'sessions_count',
                        GameSession::query()->where('game_id', $g->id)->count(),
                    )),
                'statuses' => GamesAdminService::STATUSES,
                'sessions' => GameSession::query()
                    ->with(['game:id,name_ar', 'user:id,name,code'])
                    ->latest('id')
                    ->limit((int) setting('ux.lists.per_page', 25))
                    ->get(),
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
