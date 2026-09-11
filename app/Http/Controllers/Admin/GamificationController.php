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
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * التلعيب والتحديات (12.10 موسّع · 2.14 · 7 · 7.1 · 7.2 · 7.3 · 7.4).
 *
 * ⭐ **12.10 لم تعد ثلاثة تبويبات.** نصُّها الأوّل (12.10 الأصليّة · 2026-07-23)
 * كان «دروب-داون بـ3 تبويبات: أسئلة المكافآت / بنك أسئلة الحروب / إعدادات
 * الحروب»، ثمّ **وسّعها الدستور نفسه** في «🎮 ثانيًا — التلعيب والتحديات (12.10
 * موسّع)»: «**12.10 توسّعت من 3 تبويبات إلى 11 صفحة** فاستوعبت `xp_rules` ·
 * `streaks` · `five_am_club` · `badges` · `referrals` · `positive_messages` ·
 * `celebrations` التي كانت صلاحيّات بلا شاشات» — والحادية عشرة (الألعاب) سقطت
 * بإلغاء 7.5، فبقيت **عشر وجهات** هي بعينها بنود مجموعة «🎮 التلعيب والتحديات»
 * في خريطة سايد بار الإدارة المعتمَدة (12.0).
 *
 * فالوجهات العشر مصدرُها **`menu()` وحده** يقرأ منه سايد بار الإدارة وشريط
 * الشاشة معًا — ولو نُسِخت في الاثنين لشاخت إحدى النسختين عند أوّل وجهةٍ جديدة،
 * وهو بالضبط ما كان: شريط الشاشة يعرض ثمانية تابات مسطّحة **لا تذكر** بنك أسئلة
 * الحروب ولا الريفيرال ولا الرسائل الإيجابيّة (وهي وجهات 12.0 نفسها بشاشاتها
 * المستقلّة)، ويعرض تابّ «المستويات» وهو **ليس بندًا في 12.0** ولا في السايد بار
 * — فكان يُبلَغ بالعنوان وحده. وقد صار قسمًا داخل صفحة «XP والتذاكر» التي هي
 * موضعُه الطبيعيّ (عتبات XP = اقتصاد XP)، فلا قدرة ضاعت ولا وجهة يتيمة بقيت.
 *
 * والتابات تُحمَّل كسولًا: التاب المفتوح وحده يجهّز بياناته (2.15-د).
 */
class GamificationController extends Controller
{
    /**
     * مفاتيح التابات — **مفاتيح داخليّة** لا نصوصًا، فلا تُنقَل.
     *
     * سبعةٌ منها وجهاتٌ في `menu()`، و`levels` **ليس وجهة** (قسمٌ داخل صفحة XP
     * كما شُرح أعلاه) لكنّه يبقى مفتاحًا صالحًا فلا ينكسر رابطٌ قديم محفوظ.
     */
    public const TAB_KEYS = ['xp', 'badges', 'streaks', 'leaderboard', 'levels', 'wars', 'reward_questions', 'celebrations'];

    /**
     * عناوين التابات كما يقرؤها المسؤول — ميثودٌ لا `const`، لأنّ الثابت لا
     * يقبل `setting()` فيبقى نصُّه محروقًا (2.13-أ).
     *
     * @return array<string, string>
     */
    public static function tabs(): array
    {
        return [
            'xp' => (string) setting('gamification.admin.tab_xp', 'XP والتذاكر'),
            'badges' => (string) setting('gamification.admin.tab_badges', 'الشارات'),
            'streaks' => (string) setting('gamification.admin.tab_streaks', 'الستريكس ونادي الخامسة'),
            'leaderboard' => (string) setting('gamification.admin.tab_leaderboard', 'الليدر بورد'),
            'levels' => (string) setting('gamification.admin.tab_levels', 'المستويات'),
            'wars' => (string) setting('gamification.admin.tab_wars', 'الحروب والتحديات'),
            'reward_questions' => (string) setting('gamification.admin.tab_reward_questions', 'أسئلة المكافآت'),
            'celebrations' => (string) setting('gamification.admin.tab_celebrations', 'الاحتفالات'),
        ];
    }

    /**
     * ⭐⭐ **مفاتيح باب الشاشة — بسعة تاباتها** (12.2.1-أ · 12.2.3-أ-8).
     *
     * كان الباب `xp_rules.view · badges.view · wars_settings.view ·
     * celebrations.view · reward_questions.view` — خمسةٌ لثمانية تابات. فسقط
     * منه تابّا **الستريكس ونادي الخامسة** و**الليدر بورد** و**المستويات**،
     * ومَن مُنِح إدارتها **وحدها** يُردّ عن شاشتها الوحيدة. وقالب 12.2.3-أ-8
     * يغطّي بالنصّ: «`wars_settings · wars_bank · war_types · wars_matches ·
     * reward_questions · badges · **achievements · leaderboards · streaks** ·
     * five_am_club · positive_messages · celebrations`».
     *
     * ⚠️ والمفاتيح **زوجان لكلّ تابّ** لا مفتاحًا واحدًا، والفرق مقصود في
     * 12.2.2: `streaks.view` نطاقُها **SELF** ووصفُها «**ستريكي** الحاليّ
     * وأيّامه المتبقّية» — مفتاح متدرّبٍ لا مفتاح إدارة؛ والمفتاح الإداريّ هو
     * `streaks.list` («**متابعة الستريكات النشطة (صحّة التلعيب)**» — ALL).
     * وكذلك `leaderboards.view` (SELF…ALL) مقابل `leaderboards.export` (ENTITY ·
     * TRACK · ALL)، و`achievements.view` (SELF · ALL) مقابل `achievements.list`
     * («قائمة المسارات وعتباتها الحاليّة» — ALL).
     *
     * فيُقبَل الوجهان معًا: الإداريّ يفتح فعلًا، والشخصيّ مذكورٌ لأنّه ما تحرس
     * به الشاشةُ تابَّها (`@can('leaderboards.view')`) — ولا يفتح بابًا وحده
     * لأنّ `EnsureAdminPanel` لا يعدّ مفاتيح قالب المستخدم النهائيّ سلطةً.
     *
     * @var list<string>
     */
    public const GATE_KEYS = [
        'xp_rules.view',
        'badges.view',
        'streaks.view', 'streaks.list',
        'leaderboards.view', 'leaderboards.export',
        'achievements.view', 'achievements.list',
        'wars_settings.view',
        'celebrations.view',
        'reward_questions.view',
    ];

    /**
     * ⭐⭐ **وجهات مجموعة «التلعيب والتحديات» العشر — مصدرٌ واحد** (12.0 · 12.10
     * موسّع).
     *
     * سبعٌ منها تابٌّ في هذه الشاشة، وثلاثٌ **شاشاتٌ مستقلّة بمسارها الخاصّ**:
     * بنك أسئلة الحروب (12.10-ب) · الريفيرال والسفراء (24.2) · الرسائل
     * الإيجابيّة (2.6-ب). والبنك بالذات شاشةٌ قائمة بذاتها كما تنصّ 12.10-ب
     * (بنك · استيراد · تصدير · كشف مؤقّت بـAudit) لا مرساةً داخل صفحةٍ عملاقة.
     *
     * وصلاحيّة كلّ بند **نسخةٌ حرفيّةٌ ممّا تحرسه المِدل-وير على مساره**: القائمة
     * تُقرَأ «كلّها لازمة»، وكلّ بندٍ فيها «أيٌّ من» مفصولةً بـ`|`. فبند التاب له
     * صلاحيّتان: صلاحيّة التاب نفسه، و**باب الصفحة** الذي تحرسه المِدل-وير —
     * ولولا الثانية لظهر بندٌ يفتح 403، وهو أسوأ من إخفائه (2.15-أ-7).
     *
     * @return list<array{label: string, route: string, params: array<string, string>, permission: string|list<string>}>
     */
    public static function menu(): array
    {
        // باب الشاشة كما تحرسه المِدل-وير — يُقرَأ من مصدره لا يُنسَخ بالحرف
        $door = implode('|', self::GATE_KEYS);

        return [
            ['label' => (string) setting('nav.admin.item_gamification_xp', 'XP والتذاكر'), 'route' => 'admin.gamification.index', 'params' => ['tab' => 'xp'], 'permission' => 'xp_rules.view'],
            // المفتاح الإداريّ أو الشخصيّ — 12.2.2 تفرّق بينهما (`streaks.list` ALL · `streaks.view` SELF)
            ['label' => (string) setting('nav.admin.item_gamification_streaks', 'الستريك ونادي الخامسة'), 'route' => 'admin.gamification.index', 'params' => ['tab' => 'streaks'], 'permission' => ['streaks.view|streaks.list', $door]],
            ['label' => (string) setting('nav.admin.item_gamification_leaderboard', 'الليدر بورد'), 'route' => 'admin.gamification.index', 'params' => ['tab' => 'leaderboard'], 'permission' => ['leaderboards.view|leaderboards.export', $door]],
            ['label' => (string) setting('nav.admin.item_gamification_badges', 'الشارات والإنجازات'), 'route' => 'admin.gamification.index', 'params' => ['tab' => 'badges'], 'permission' => 'badges.view'],
            /*
             | ⛔ «الألعاب» ملغاة بقرار المالك (الدستور v5.3 — 7.5)، فسقط بندها من
             | خريطة 12.0. ولا مدخل لها هنا، ولا تابّ `?tab=games`.
             */
            // الطرف الإداريّ للدعوات والألقاب (24.2) — شاشةٌ مستقلّة
            ['label' => (string) setting('nav.admin.item_gamification_referrals', 'الريفيرال والسفراء'), 'route' => 'admin.referrals.index', 'params' => [], 'permission' => 'referrals.list'],
            // الرسائل الإيجابيّة لأيقونة المفاجأة (2.6-ب · 12.0) — شاشةٌ مستقلّة
            ['label' => (string) setting('nav.admin.item_gamification_positive', 'الرسائل الإيجابيّة'), 'route' => 'admin.positive.index', 'params' => [], 'permission' => 'positive_messages.list'],
            ['label' => (string) setting('nav.admin.item_gamification_celebrations', 'الاحتفالات'), 'route' => 'admin.gamification.index', 'params' => ['tab' => 'celebrations'], 'permission' => 'celebrations.view'],
            ['label' => (string) setting('nav.admin.item_gamification_reward_questions', 'أسئلة المكافآت'), 'route' => 'admin.gamification.index', 'params' => ['tab' => 'reward_questions'], 'permission' => 'reward_questions.view'],
            // بنك أسئلة الحروب — بند صريح في 12.0 (12.10-ب) بشاشته المستقلّة
            ['label' => (string) setting('nav.admin.item_gamification_wars_bank', 'بنك أسئلة الحروب'), 'route' => 'admin.wars.bank.index', 'params' => [], 'permission' => 'wars_bank.list'],
            ['label' => (string) setting('nav.admin.item_gamification_wars_settings', 'إعدادات الحروب'), 'route' => 'admin.gamification.index', 'params' => ['tab' => 'wars'], 'permission' => 'wars_settings.view'],
        ];
    }

    /**
     * مفاتيح تابات هذه الشاشة **التي هي وجهةٌ في 12.0** — وما عداها (`levels`)
     * قسمٌ داخل صفحةٍ منها، يُفتَح برابطٍ قديم ولا يُعلَن بندًا.
     *
     * @return list<string>
     */
    public static function menuTabs(): array
    {
        return array_values(array_filter(array_map(
            fn (array $item) => $item['route'] === 'admin.gamification.index' ? ($item['params']['tab'] ?? null) : null,
            self::menu(),
        )));
    }

    /**
     * وجهات 12.10 التي يراها **هذا المستخدم** — والمحظور يُخفى ولا يُعطَّل
     * (2.15-أ-7)، والمسار غير الموجود لا يُعرَض أصلًا فلا رابط ميّت.
     *
     * @return list<array{label: string, route: string, params: array<string, string>, permission: string|list<string>, href: string}>
     */
    public static function menuFor(?Authenticatable $user): array
    {
        $allows = function ($permission) use ($user): bool {
            foreach ((array) $permission as $clause) {
                $any = false;

                foreach (explode('|', $clause) as $key) {
                    if (Gate::forUser($user)->allows($key)) {
                        $any = true;
                        break;
                    }
                }

                if (! $any) {
                    return false;
                }
            }

            return true;
        };

        $items = [];

        foreach (self::menu() as $item) {
            if (! Route::has($item['route']) || ! $allows($item['permission'])) {
                continue;
            }

            $item['href'] = route($item['route'], $item['params']);
            $items[] = $item;
        }

        return $items;
    }

    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString() ?: 'xp';

        abort_unless(in_array($tab, self::TAB_KEYS, true), 404);

        return view('admin.gamification.index', [
            'tab' => $tab,
            'tabs' => self::tabs(),
            // وجهات 12.10 العشر — نفس مصدر السايد بار، فلا يفترق الشريط عنه
            'menu' => self::menuFor($request->user()),
            'menuTabs' => self::menuTabs(),
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

        return back()->with('status', (string) setting('gamification.admin.save_settings_ok', 'اتحفظ ✓'));
    }

    public function resetGroup(Request $request): RedirectResponse
    {
        $data = $request->validate(['group' => ['required', 'string']]);

        abort_unless(str_starts_with($data['group'], 'gamification_'), 404);

        $count = SettingsWriter::resetGroup($data['group'], $request->user());

        return back()->with('status', strtr((string) setting('gamification.admin.reset_group_ok', 'رجعت :a1 قيمة للافتراضيّ ✓'), [':a1' => (string) ($count)]));
    }

    /** صفوف الكسب/الصرف تُحرَّر كجدول (12.10 — XP والتذاكر) */
    public function saveXpRows(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'in:xp_rules.earn,xp_rules.spend'],
            'rows' => ['nullable', 'array'],
        ]);

        SettingsWriter::put($data['key'], array_values($data['rows'] ?? []), $request->user());

        return back()->with('status', (string) setting('gamification.admin.save_xp_rows_ok', 'اتحفظ ✓'));
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
            'condition_key' => ['nullable', 'string', Rule::in(BadgeService::CONDITION_KEYS)],
            'condition_value' => ['nullable', 'integer', 'min:0'],
            'icon' => ['nullable', 'image', 'max:'.(int) setting('badges.icon.max_kb', 512)],
            'icon_path' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name_en' => (string) setting('gamification.admin.save_badge_msg', 'الاسم بالإنجليزيّة'),
            'condition_key' => (string) setting('gamification.admin.save_badge_msg_2', 'مقياس الشرط'),
            'icon' => (string) setting('gamification.admin.save_badge_msg_3', 'صورة الشارة'),
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

        return back()->with('status', (string) setting('gamification.admin.save_badge_ok', 'اتحفظ ✓'));
    }

    public function deleteBadge(Request $request, Badge $badge): RedirectResponse
    {
        AuditTrail::log($request->user(), 'badge.delete', $badge, $badge->only(['key', 'name_ar']), []);
        $badge->delete();

        return back()->with('status', (string) setting('gamification.admin.delete_badge_ok', 'اتحذفت الشارة ✓'));
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

        return back()->with('status', (string) setting('gamification.admin.save_level_ok', 'اتحفظ ✓'));
    }

    public function deleteLevel(Request $request, Level $level): RedirectResponse
    {
        AuditTrail::log($request->user(), 'level.delete', $level, $level->only(['level', 'min_xp']), []);
        $level->delete();

        return back()->with('status', (string) setting('gamification.admin.delete_level_ok', 'اتحذف المستوى ✓'));
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

        return back()->with('status', (string) setting('gamification.admin.save_war_ok', 'اتحفظ ✓'));
    }

    public function resetWar(Request $request, Challenge $challenge): RedirectResponse
    {
        try {
            WarSettingsService::reset($challenge, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', (string) setting('gamification.admin.reset_war_ok', 'رجعت الحرب للافتراضيّ ✓'));
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

        return back()->with('status', (string) setting('gamification.admin.save_reward_question_ok', 'اتحفظ ✓'));
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
        $message = strtr((string) setting('gamification.admin.import_reward_questions_ok', 'اتستوردت :a1 أسئلة كمسودّات ✓'), [':a1' => (string) ($result['imported'])]);

        if ($result['errors'] !== []) {
            $message .= strtr((string) setting('gamification.admin.import_reward_questions_msg', ' — تخطّينا: :a1'), [':a1' => (string) (implode(' · ', array_slice($result['errors'], 0, 3)))]);
        }

        return back()->with('status', $message);
    }

    /** إغلاق فوريّ: الرابط يقفل الآن ويظهر «انتهى وقت الإجابة» (12.10-أ) */
    public function closeRewardQuestion(Request $request, RewardQuestion $rewardQuestion, RewardQuestionService $service): RedirectResponse
    {
        $service->closeNow($rewardQuestion);

        AuditTrail::log($request->user(), 'reward_question.close', $rewardQuestion, [], ['closes_at' => $rewardQuestion->closes_at]);

        return back()->with('status', (string) setting('gamification.admin.close_reward_question_ok', 'اتقفل السؤال ✓'));
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

        return back()->with('status', (string) setting('gamification.admin.save_celebration_ok', 'اتحفظ ✓'));
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
                /*
                 | ⭐ المستويات قسمٌ في صفحة «XP والتذاكر» لا وجهةً في 12.0:
                 | عتبة المستوى **رقم XP**، فموضعُها اقتصاد XP. وكانت تابًّا
                 | لا يذكره السايد بار ولا خريطة 12.0 — يُبلَغ بالعنوان وحده.
                 */
                'levels' => Level::query()->orderBy('level')->get(),
            ],
            'badges' => [
                'settings' => SettingsWriter::groupRows('gamification_badges'),
                'badges' => Badge::query()->orderBy('id')->get(),
                // ⭐ القائمة المقفولة للمقاييس — الأدمن يختار ولا يكتب (7.4)
                'conditions' => BadgeService::conditionLabels(),
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
                'sections' => WarSettingsService::sections(),
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
                'tiers' => [1 => (string) setting('gamification.admin.data_for_msg', 'خفيف'), 2 => (string) setting('gamification.admin.data_for_msg_2', 'متوسّط'), 3 => (string) setting('gamification.admin.data_for_msg_3', 'ذروة')],
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
