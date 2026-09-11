<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\CelebrationEvent;
use App\Models\Challenge;
use App\Models\Currency;
use App\Models\RewardQuestion;
use App\Models\Role;
use App\Models\Setting;
use App\Models\WarQuestion;
use App\Services\Admin\Volunteer\SettingsCatalog;
use App\Support\Access\PermissionExpander;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * بيانات مجال التحديات وإنجازاتي التجريبيّة + إعداداته وصلاحيّاته.
 * لا يُسجَّل في DatabaseSeeder (قاعدة البناء 7).
 */
class ChallengeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->streakSettings();
        $this->achievementsScreenTextSettings();
        $this->screenTextSettings();
        $this->rewardQuestions();
        $this->celebrations();
        $this->badges();
        $this->challenges();
        $this->questionBank();
        $this->traineePermissions();

        // الإعدادات تُقرأ من كاش دائم — نُبطله بعد الكتابة (2.13)
        Cache::forget('settings');
    }

    /**
     * **نصوص شاشات التحديات** (2.13-أ: «النصوص الظاهرة للمستخدم») — كلّ جملةٍ
     * يقرؤها المتدرّب على `resources/views/challenges/**` لها مفتاحها هنا،
     * والوحدة **جملةٌ كاملة** كما تُقرَأ لا كلمةً مقتطعة. و`:n` وأخواتها
     * مواضع استبدال لا نصًّا.
     *
     * ⚠️ «التحديات» و«الرئيسيّة» عنوانان **منصوصان حرفيًّا في الدستور**
     * (24.5 · 12.0 — سايد بار المتدرّب)، و«لوحة الأبطال» منصوصة في وصف شاشة
     * التحديات (24.5) — فافتراضيُّها هو النصّ المنصوص وتغييرُه يخالف الخريطة.
     */
    public function screenTextSettings(): void
    {
        $rows = [
            ['celebrations.screen.close_aria', 'gamification_celebrations', '«شاشة الاحتفال» — زرّ الإغلاق لقارئ الشاشة', 'إغلاق'],
            ['celebrations.screen.dismiss_action', 'gamification_celebrations', '«شاشة الاحتفال» — زرّ إغلاق الاحتفال', 'تمام'],
            ['celebrations.screen.icon_label', 'gamification_celebrations', '«شاشة الاحتفال» — وصف الأيقونة لقارئ الشاشة', 'إنجاز'],
            ['celebrations.screen.share_action', 'gamification_celebrations', '«شاشة الاحتفال» — زرّ لقطة الإنجاز', 'لقطة إنجاز'],
            // «زرّ مشاركة» شاشة الذروة (2.14-أ · 3) حيث لا لقطة إنجاز — يُشارَك رابط الدعوة
            ['celebrations.screen.share_link_action', 'gamification_celebrations', '«شاشة الاحتفال» — زرّ مشاركة رابط الدعوة', 'شارك الخبر'],
            ['celebrations.screen.share_copied', 'gamification_celebrations', '«شاشة الاحتفال» — ردّ نسخ رابط المشاركة', 'اتنسخ ✓'],
            ['celebrations.screen.share_failed', 'gamification_celebrations', '«شاشة الاحتفال» — ردّ تعذُّر النسخ', 'انسخ الرابط من المتصفّح'],
            ['challenges.arena.all_arenas', 'challenges', '«ساحة الحرب» — زرّ كلّ الساحات', 'كلّ الساحات'],
            ['challenges.arena.bank_not_ready', 'challenges', '«ساحة الحرب» — سطر بنك الأسئلة غير الجاهز', 'بنك أسئلة الساحة لسّه مش جاهز — جرّب ساحة تانية دلوقتي.'],
            ['challenges.arena.duel_action', 'challenges', '«ساحة الحرب» — زرّ التحدّي', 'تحدّاه'],
            ['challenges.arena.fighter_record', 'challenges', '«ساحة الحرب» — سجلّ المحارب (:wins · :losses)', 'الفوز: :wins · الخسارة: :losses'],
            ['challenges.arena.fighters_empty', 'challenges', '«ساحة الحرب» — الحالة الفارغة لقائمة المحاربين', 'يبدو أنك قضيت على كل خصومك! 🔥 أنت وحدك في ساحة الحرب..'],
            ['challenges.arena.fighters_title', 'challenges', '«ساحة الحرب» — عنوان قائمة المحاربين', 'المحاربون الجاهزون'],
            ['challenges.arena.gate_line', 'challenges', '«ساحة الحرب» — سطر شرط الدخول (:gate · :balance)', 'شرط الدخول: رصيدك ≥ :gate تذكرة · رصيدك دلوقتي :balance'],
            ['challenges.arena.loss_line', 'challenges', '«ساحة الحرب» — سطر خسارة المواجهة (:n)', 'الخسارة: −:n تذكرة'],
            ['challenges.arena.paused_badge', 'challenges', '«ساحة الحرب» — شارة الساحة الموقوفة', 'الساحة موقوفة مؤقّتًا'],
            ['challenges.arena.ready_action', 'challenges', '«ساحة الحرب» — زرّ الاستعداد', 'استعداد'],
            ['challenges.arena.ready_badge', 'challenges', '«ساحة الحرب» — شارة الاستعداد', 'إنت مستعدّ — استنّى محارب أو اتحدّى واحدًا'],
            ['challenges.arena.ready_elsewhere', 'challenges', '«ساحة الحرب» — تنبيه الاستعداد في ساحة أخرى (:arena)', 'إنت مستعدّ لـ«:arena» — ألغِ استعدادك من الشريط فوق الأوّل.'],
            ['challenges.arena.resume_match', 'challenges', '«ساحة الحرب» — زرّ العودة للمواجهة', 'ارجع لمواجهتك'],
            ['challenges.arena.win_line', 'challenges', '«ساحة الحرب» — سطر مكسب الفوز (:n)', 'الفوز: +:n تذكرة'],
            ['challenges.arena.withdraw_line', 'challenges', '«ساحة الحرب» — سطر عقوبة الانسحاب (:n)', 'الانسحاب: −:n تذاكر'],
            ['challenges.champion_row.points_word', 'challenges', '«صفّ لوحة الأبطال» — كلمة النقطة', 'نقطة'],
            ['challenges.champion_row.record', 'challenges', '«صفّ لوحة الأبطال» — سجلّ البطل (:wins · :played)', '· :wins فوز من :played'],
            ['challenges.champion_row.you_label', 'challenges', '«صفّ لوحة الأبطال» — وسم صفّك أنت', '— ده إنت'],
            ['challenges.champions.empty_action', 'challenges', '«لوحة الأبطال» — زرّ الحالة الفارغة', 'التحدّيات المتاحة'],
            ['challenges.champions.empty_message', 'challenges', '«لوحة الأبطال» — نصّ الحالة الفارغة', 'لسّه بدري على أوّل ترتيب — ادخل أوّل حرب وابدأ.'],
            ['challenges.champions.filter_apply', 'challenges', '«لوحة الأبطال» — زرّ تطبيق الفلاتر', 'طبّق'],
            ['challenges.champions.filter_challenge', 'challenges', '«لوحة الأبطال» — عنوان فلتر الحرب', 'التحدّي'],
            ['challenges.champions.filter_challenge_all', 'challenges', '«لوحة الأبطال» — خيار كلّ الحروب', 'كلّ الحروب'],
            ['challenges.champions.filter_period', 'challenges', '«لوحة الأبطال» — عنوان فلتر الفترة', 'الفترة'],
            ['challenges.champions.filter_search', 'challenges', '«لوحة الأبطال» — عنوان خانة البحث', 'بحث بالاسم'],
            ['challenges.champions.filter_search_placeholder', 'challenges', '«لوحة الأبطال» — تلميح خانة البحث', 'اسم البطل…'],
            ['challenges.champions.no_rank_note', 'challenges', '«لوحة الأبطال» — سطر مَن بلا ترتيب', 'لسّه مالكش ترتيب في الفترة دي — أوّل تحدّي هيحطّك على اللوحة.'],
            ['challenges.champions.period_30', 'challenges', '«لوحة الأبطال» — خيار فترة 30 يوم', 'آخر 30 يوم'],
            ['challenges.champions.period_7', 'challenges', '«لوحة الأبطال» — خيار فترة 7 أيّام', 'آخر 7 أيّام'],
            ['challenges.champions.period_90', 'challenges', '«لوحة الأبطال» — خيار فترة 90 يوم', 'آخر 90 يوم'],
            ['challenges.champions.range_label', 'challenges', '«لوحة الأبطال» — اسم الفترة في بطاقة الاستخراج (:days)', 'آخر :days يوم'],
            ['challenges.champions.subtitle', 'challenges', '«لوحة الأبطال» — السطر تحت العنوان', 'ترتيب المتحدّين خلال آخر :days يوم.'],
            ['challenges.champions.title', 'challenges', '«لوحة الأبطال» — العنوان', 'لوحة الأبطال'],
            ['challenges.focus.badge_group', 'challenges', '«حرب التركيز» — شارة التحدّي الجماعيّ', 'جماعيّ'],
            ['challenges.focus.badge_solo', 'challenges', '«حرب التركيز» — شارة التحدّي الفرديّ', 'فرديّ'],
            ['challenges.focus.cancel_action', 'challenges', '«حرب التركيز» — زرّ إلغاء التحدّي', 'ألغِ التحدّي'],
            ['challenges.focus.card_duration', 'challenges', '«حرب التركيز» — مدّة التحدّي في الكارت (:minutes)', ':minutes دقيقة تركيز'],
            ['challenges.focus.card_owner', 'challenges', '«حرب التركيز» — صاحب التحدّي في الكارت (:owner)', '· صاحبه: :owner'],
            ['challenges.focus.duration_legend', 'challenges', '«حرب التركيز» — عنوان اختيار المدّة', 'المدّة'],
            ['challenges.focus.duration_option', 'challenges', '«حرب التركيز» — خيار المدّة (:minutes)', ':minutes دقيقة'],
            ['challenges.focus.economy_note', 'challenges', '«حرب التركيز» — شرح اقتصاد التحدّي (:create · :join)', 'الإنشاء بـ:create تذاكر وغير قابلة للاسترجاع، وكلّ منضمّ بيدّيك :join تذكرة — يعني تحدّي حلو الناس تحبّه = مكسب.'],
            ['challenges.focus.empty', 'challenges', '«حرب التركيز» — الحالة الفارغة', 'مفيش تحدّيات تركيز نشطة — ابدأ إنت أوّل واحد.'],
            ['challenges.focus.group_toggle', 'challenges', '«حرب التركيز» — خيار التحدّي الجماعيّ', 'خلّيه تحدّيًا جماعيًّا — الناس تقدر تنضمّ بتذكرة تروح لك.'],
            ['challenges.focus.icon_label', 'challenges', '«حرب التركيز» — وصف الأيقونة لقارئ الشاشة', 'حرب تركيز'],
            ['challenges.focus.intention_label', 'challenges', '«حرب التركيز» — عنوان خانة النيّة', 'نيّتك (اختياريّ)'],
            ['challenges.focus.intention_placeholder', 'challenges', '«حرب التركيز» — تلميح خانة النيّة', 'أقرأ كتاب كذا · أخلّص مهمّة كذا'],
            ['challenges.focus.join_action', 'challenges', '«حرب التركيز» — زرّ الانضمام (:cost)', 'انضمّ بـ:cost تذكرة'],
            ['challenges.focus.joined_note', 'challenges', '«حرب التركيز» — سطر المنضمّ', 'إنت منضمّ — ركّز'],
            ['challenges.focus.joiners_label', 'challenges', '«حرب التركيز» — وسم المنضمّين', 'منضمّين معاه'],
            ['challenges.focus.kpi_active', 'challenges', '«حرب التركيز» — كارت التحدّيات النشطة', 'تحدّياتي النشطة'],
            ['challenges.focus.live_done', 'challenges', '«حرب التركيز» — سطر انتهاء الجلسة', 'خلصت المدّة — دقائق تركيزك اتسجّلت ✓'],
            ['challenges.focus.live_intention', 'challenges', '«حرب التركيز» — نيّة الجلسة الجارية (:intention)', 'نيّتك: :intention'],
            ['challenges.focus.live_note', 'challenges', '«حرب التركيز» — سطر طمأنة العدّاد', 'العدّاد ماشي على ساعة السيرفر — بيكمل حتى لو قفلت الشاشة أو خرجت من التبويب.'],
            ['challenges.focus.live_of_total', 'challenges', '«حرب التركيز» — وحدة العدّاد الحيّ (:total)', 'دقيقة من :total'],
            ['challenges.focus.live_progress_label', 'challenges', '«حرب التركيز» — وصف شريط التقدّم لقارئ الشاشة', 'تقدّم جلسة التركيز'],
            ['challenges.focus.live_title', 'challenges', '«حرب التركيز» — عنوان الجلسة الجارية', 'جلسة تركيز جارية'],
            ['challenges.focus.kpi_create_cost', 'challenges', '«حرب التركيز» — كارت تكلفة الإنشاء', 'تكلفة الإنشاء'],
            ['challenges.focus.kpi_minutes', 'challenges', '«حرب التركيز» — كارت دقائق التركيز', 'دقائق تركيزي'],
            ['challenges.focus.kpi_tickets', 'challenges', '«حرب التركيز» — كارت التذاكر', 'تذاكري'],
            ['challenges.focus.modal_cancel', 'challenges', '«حرب التركيز» — زرّ إغلاق البوب-أب', 'مش دلوقتي'],
            ['challenges.focus.modal_submit', 'challenges', '«حرب التركيز» — زرّ بدء التحدّي', 'ابدأ التحدّي'],
            ['challenges.focus.modal_title', 'challenges', '«حرب التركيز» — عنوان بوب-أب التحدّي الجديد', 'تحدّي تركيز جديد'],
            ['challenges.focus.new_action', 'challenges', '«حرب التركيز» — زرّ تحدّي جديد', 'تحدّي جديد'],
            ['challenges.focus.no_intention', 'challenges', '«حرب التركيز» — الكارت بلا نيّة مكتوبة', 'بلا نيّة مكتوبة'],
            ['challenges.focus.subtitle', 'challenges', '«حرب التركيز» — السطر تحت العنوان', 'عمل عميق بلا مقاطعة — والمكافأة دقائق تركيز مش تذاكر.'],
            ['challenges.focus.title', 'challenges', '«حرب التركيز» — العنوان', 'حرب التركيز'],
            ['challenges.focus_layout.default_title', 'challenges', '«شاشة التركيز» — عنوان شاشة التركيز الافتراضيّ', 'التحدّي'],
            ['challenges.index.badge_bank_not_ready', 'challenges', '«التحديات» — شارة بنك الأسئلة غير الجاهز', 'البنك مش جاهز'],
            ['challenges.index.badge_paused', 'challenges', '«التحديات» — شارة الساحة الموقوفة', 'موقوفة مؤقّتًا'],
            ['challenges.index.badge_ready_here', 'challenges', '«التحديات» — شارة الاستعداد في هذه الساحة', 'إنت مستعدّ هنا'],
            ['challenges.index.breadcrumb_home', 'challenges', '«التحديات» — جذر مسار التنقّل (منصوص في 24.5)', 'الرئيسيّة'],
            ['challenges.index.empty', 'challenges', '«التحديات» — الحالة الفارغة', 'مفيش ساحات متاحة دلوقتي — تعالى بكرة، الساحة بتتجدّد.'],
            ['challenges.index.enter_arena', 'challenges', '«التحديات» — زرّ دخول الساحة', 'ادخل الساحة'],
            ['challenges.index.enter_focus', 'challenges', '«التحديات» — زرّ دخول ساحة التركيز', 'ادخل ساحة التركيز'],
            ['challenges.index.filter_apply', 'challenges', '«التحديات» — زرّ تطبيق الفلاتر', 'طبّق'],
            ['challenges.index.filter_search', 'challenges', '«التحديات» — عنوان خانة البحث', 'بحث'],
            ['challenges.index.filter_search_placeholder', 'challenges', '«التحديات» — تلميح خانة البحث', 'اسم الحرب…'],
            ['challenges.index.filter_state', 'challenges', '«التحديات» — عنوان فلتر الحالة', 'الحالة'],
            ['challenges.index.filter_state_all', 'challenges', '«التحديات» — خيار كلّ الحالات', 'الكلّ'],
            ['challenges.index.filter_state_open', 'challenges', '«التحديات» — خيار الحالة المفتوحة', 'مفتوحة'],
            ['challenges.index.filter_state_paused', 'challenges', '«التحديات» — خيار الحالة الموقوفة', 'موقوفة'],
            ['challenges.index.filter_type', 'challenges', '«التحديات» — عنوان فلتر النوع', 'النوع'],
            ['challenges.index.filter_type_all', 'challenges', '«التحديات» — خيار كلّ الأنواع', 'كلّ الأنواع'],
            ['challenges.index.kpi_gate', 'challenges', '«التحديات» — كارت شرط الاستعداد', 'شرط الاستعداد'],
            ['challenges.index.kpi_losses', 'challenges', '«التحديات» — كارت الخسارة', 'خسارتي'],
            ['challenges.index.kpi_tickets', 'challenges', '«التحديات» — كارت التذاكر', 'تذاكري'],
            ['challenges.index.kpi_wins', 'challenges', '«التحديات» — كارت الفوز', 'فوزي'],
            ['challenges.index.mine_action', 'challenges', '«التحديات» — زرّ تحدّياتي', 'تحدّياتي'],
            ['challenges.index.reopen_soon', 'challenges', '«التحديات» — سطر الساحة الموقوفة', 'هترجع تفتح قريب.'],
            ['challenges.index.resume_match', 'challenges', '«التحديات» — زرّ العودة للمواجهة', 'ارجع للمواجهة'],
            ['challenges.index.running_note', 'challenges', '«التحديات» — سطر المواجهة الجارية', 'عندك مواجهة شغّالة دلوقتي.'],
            ['challenges.index.stat_gate_value', 'challenges', '«التحديات» — قيمة شرط الاستعداد (:n)', '≥ :n تذكرة'],
            ['challenges.index.stat_loss', 'challenges', '«التحديات» — عنوان خسارة المواجهة', 'الخسارة'],
            ['challenges.index.stat_loss_value', 'challenges', '«التحديات» — قيمة خسارة المواجهة (:n)', '−:n تذكرة'],
            ['challenges.index.stat_win', 'challenges', '«التحديات» — عنوان مكسب الفوز', 'الفوز'],
            ['challenges.index.stat_win_value', 'challenges', '«التحديات» — قيمة مكسب الفوز (:n)', '+:n تذكرة'],
            ['challenges.index.stat_withdraw', 'challenges', '«التحديات» — عنوان عقوبة الانسحاب', 'الانسحاب'],
            ['challenges.index.stat_withdraw_value', 'challenges', '«التحديات» — قيمة عقوبة الانسحاب (:n)', '−:n تذاكر'],
            ['challenges.index.subtitle', 'challenges', '«التحديات» — السطر تحت العنوان', 'حروب بين المحاربين — الرابح ياخد من الخاسر، ومحدّش بيكسب من العدم.'],
            ['challenges.index.title', 'challenges', '«التحديات» — العنوان', 'التحديات'],
            ['challenges.mine.arenas_action', 'challenges', '«تحدّياتي» — زرّ ساحات الحرب', 'ساحات الحرب'],
            ['challenges.mine.badge_done', 'challenges', '«تحدّياتي» — شارة المواجهة المكتملة', 'مكتملة'],
            ['challenges.mine.badge_running', 'challenges', '«تحدّياتي» — شارة المواجهة الجارية', 'شغّالة دلوقتي'],
            ['challenges.mine.continue_action', 'challenges', '«تحدّياتي» — زرّ إكمال المواجهة', 'كمّل المواجهة'],
            ['challenges.mine.empty_done', 'challenges', '«تحدّياتي» — الحالة الفارغة للمنتهية', 'لسّه مخلّصتش مواجهة — أوّل واحدة هتبان هنا.'],
            ['challenges.mine.empty_running', 'challenges', '«تحدّياتي» — الحالة الفارغة للجارية', 'مفيش مواجهة شغّالة دلوقتي — الساحة مستنّياك.'],
            ['challenges.mine.kpi_draws', 'challenges', '«تحدّياتي» — كارت التعادل', 'تعادلي'],
            ['challenges.mine.kpi_focus_minutes', 'challenges', '«تحدّياتي» — كارت دقائق التركيز', 'دقائق تركيزي'],
            ['challenges.mine.kpi_losses', 'challenges', '«تحدّياتي» — كارت الخسارة', 'خسارتي'],
            ['challenges.mine.kpi_wins', 'challenges', '«تحدّياتي» — كارت الفوز', 'فوزي'],
            ['challenges.mine.loss_streak_note', 'challenges', '«تحدّياتي» — تنبيه سلسلة الخسائر (:n)', 'عندك :n خسارة ورا بعض — لو وصلت للحدّ هتختفي من قائمة الجاهزين (حماية ليك)، وتفضل قادر تتحدّى الناس لحدّ ما تكسر السلسلة بفوز.'],
            ['challenges.mine.my_score', 'challenges', '«تحدّياتي» — عنوان نقاطي', 'نقاطي'],
            ['challenges.mine.result_action', 'challenges', '«تحدّياتي» — زرّ عرض النتيجة', 'شوف النتيجة'],
            ['challenges.mine.result_draw', 'challenges', '«تحدّياتي» — وسم نتيجة التعادل', 'تعادل'],
            ['challenges.mine.result_lose', 'challenges', '«تحدّياتي» — وسم نتيجة الخسارة', 'خسارة'],
            ['challenges.mine.result_win', 'challenges', '«تحدّياتي» — وسم نتيجة الفوز', 'فوز'],
            ['challenges.mine.settlement', 'challenges', '«تحدّياتي» — عنوان المحصّلة', 'المحصّلة'],
            ['challenges.mine.subtitle', 'challenges', '«تحدّياتي» — السطر تحت العنوان', 'اللي شغّال دلوقتي واللي خلص — كلّه في مكان واحد.'],
            ['challenges.mine.tab_done', 'challenges', '«تحدّياتي» — تاب المواجهات المنتهية', 'منتهية'],
            ['challenges.mine.tab_running', 'challenges', '«تحدّياتي» — تاب المواجهات الجارية', 'جارية'],
            ['challenges.mine.title', 'challenges', '«تحدّياتي» — العنوان', 'تحدّياتي'],
            ['challenges.mine.withdrew_note', 'challenges', '«تحدّياتي» — سطر الانسحاب', 'انسحبت من المواجهة دي.'],
            ['challenges.play.answer_queued', 'challenges', '«المواجهة» — ردّ حفظ الإجابة بلا شبكة', 'تقدّمك محفوظ — هنبعته أوّل ما النت يرجع'],
            ['challenges.play.answer_saved', 'challenges', '«المواجهة» — ردّ حفظ الإجابة', 'اتحفظ ✓'],
            ['challenges.play.answered_of', 'challenges', '«المواجهة» — عدد الإجابات (:count · :total)', '· :count من :total'],
            ['challenges.play.decision_note', 'challenges', '«المواجهة» — تنبيه عدّاد الحسم (:seconds)', 'خصمك خلّص — باقي :seconds ثانية وتُقفَل المواجهة.'],
            ['challenges.play.no_questions', 'challenges', '«المواجهة» — الحالة الفارغة للمواجهة', 'المواجهة دي بلا أسئلة — سلّم وارجع بعدين.'],
            ['challenges.play.no_questions_action', 'challenges', '«المواجهة» — زرّ الحالة الفارغة', 'سلّم'],
            ['challenges.play.number_answer_label', 'challenges', '«المواجهة» — عنوان خانة التقدير الرقميّ', 'تقديرك بالرقم'],
            ['challenges.play.offline_note', 'challenges', '«المواجهة» — تطمين انقطاع الشبكة', 'النت فصل — بس تقدّمك محفوظ، وأوّل ما يرجع هنكمّل من نفس المكان.'],
            ['challenges.play.question_of', 'challenges', '«المواجهة» — موضع السؤال (:index · :total)', '· سؤال :index من :total'],
            ['challenges.play.rival_line', 'challenges', '«المواجهة» — سطر الخصم (:rival)', 'خصمك: :rival'],
            ['challenges.play.seconds_per_question', 'challenges', '«المواجهة» — وسم مؤقّت السؤال', 'ثانية للسؤال'],
            ['challenges.play.submit_action', 'challenges', '«المواجهة» — زرّ التسليم', 'خلّصت — سلّم'],
            ['challenges.play.unknown_rival', 'challenges', '«المواجهة» — اسم الخصم المجهول', 'محارب'],
            ['challenges.play.withdraw_action', 'challenges', '«المواجهة» — زرّ الانسحاب', 'انسحاب'],
            ['challenges.play.withdraw_cancel', 'challenges', '«المواجهة» — زرّ إكمال المواجهة في البوب-أب', 'أكمّل المواجهة'],
            ['challenges.play.withdraw_confirm', 'challenges', '«المواجهة» — زرّ تأكيد الانسحاب', 'أنسحب'],
            ['challenges.play.withdraw_loss_word', 'challenges', '«المواجهة» — كلمة الخسارة في شرح الانسحاب', 'خسارة'],
            ['challenges.play.withdraw_modal_body', 'challenges', '«المواجهة» — شرح كلفة الانسحاب (:loss · :penalty)', 'الانسحاب بيحسب عليك :loss وكمان :penalty — والخصم بيكسب المواجهة. لو النت بيقطع منك، مفيش داعي تنسحب: تقدّمك محفوظ وهيتحسب لوحده.'],
            ['challenges.play.withdraw_modal_title', 'challenges', '«المواجهة» — عنوان بوب-أب الانسحاب', 'متأكّد إنك عايز تنسحب؟'],
            ['challenges.play.withdraw_penalty_word', 'challenges', '«المواجهة» — كلمة العقوبة في شرح الانسحاب', 'عقوبة انسحاب'],
            ['challenges.result.back_to_arena', 'challenges', '«نتيجة المواجهة» — زرّ العودة للساحة', 'ارجع الساحة'],
            ['challenges.result.badge_done', 'challenges', '«نتيجة المواجهة» — شارة المواجهة المكتملة', 'مكتمل'],
            ['challenges.result.badge_draw', 'challenges', '«نتيجة المواجهة» — شارة التعادل', 'تعادل'],
            ['challenges.result.badge_lose', 'challenges', '«نتيجة المواجهة» — شارة الخسارة', 'خسارة'],
            ['challenges.result.badge_pending', 'challenges', '«نتيجة المواجهة» — شارة انتظار الحسم', 'في انتظار الحسم'],
            ['challenges.result.badge_win', 'challenges', '«نتيجة المواجهة» — شارة الفوز', 'فوز'],
            ['challenges.result.breadcrumb_self', 'challenges', '«نتيجة المواجهة» — آخر مسار التنقّل', 'النتيجة'],
            ['challenges.result.delta_draw', 'challenges', '«نتيجة المواجهة» — محصّلة التعادل', 'لا خصم ولا إضافة'],
            ['challenges.result.delta_lose', 'challenges', '«نتيجة المواجهة» — محصّلة الخسارة (:n)', '−:n تذكرة'],
            ['challenges.result.delta_win', 'challenges', '«نتيجة المواجهة» — محصّلة الفوز (:n)', '+:n تذكرة'],
            ['challenges.result.draw_note', 'challenges', '«نتيجة المواجهة» — سطر التعادل (:unit)', 'تعادل بعدد :unit — فمحدّش خسر ومحدّش كسب.'],
            ['challenges.result.draw_unit_answers', 'challenges', '«نتيجة المواجهة» — وحدة التعادل في باقي الحروب', 'الإجابات'],
            ['challenges.result.draw_unit_survival', 'challenges', '«نتيجة المواجهة» — وحدة التعادل في حرب البقاء', 'الأسئلة اللي نجوتوا فيها'],
            ['challenges.result.headline_draw', 'challenges', '«نتيجة المواجهة» — عنوان التعادل', 'تعادل'],
            ['challenges.result.headline_lose', 'challenges', '«نتيجة المواجهة» — عنوان الخسارة', 'خسرت المواجهة'],
            ['challenges.result.headline_waiting', 'challenges', '«نتيجة المواجهة» — عنوان انتظار الخصم', 'مستنّيين خصمك يخلّص…'],
            ['challenges.result.headline_win', 'challenges', '«نتيجة المواجهة» — عنوان الفوز', 'كسبت المواجهة'],
            ['challenges.result.headline_withdrew', 'challenges', '«نتيجة المواجهة» — عنوان الانسحاب', 'انسحبت من المواجهة'],
            ['challenges.result.kpi_my_score', 'challenges', '«نتيجة المواجهة» — كارت نقاطي', 'نقاطي'],
            ['challenges.result.kpi_questions', 'challenges', '«نتيجة المواجهة» — كارت عدد الأسئلة', 'عدد الأسئلة'],
            ['challenges.result.kpi_rival_score', 'challenges', '«نتيجة المواجهة» — كارت نقاط الخصم', 'نقاط خصمي'],
            ['challenges.result.kpi_survived', 'challenges', '«نتيجة المواجهة» — كارت آخر سؤال نجوت فيه', 'نجوت لسؤال'],
            ['challenges.result.kpi_tickets', 'challenges', '«نتيجة المواجهة» — كارت التذاكر', 'تذاكري دلوقتي'],
            ['challenges.result.lose_note', 'challenges', '«نتيجة المواجهة» — سطر تشجيع بعد الخسارة', 'مجهودك مش رايح — كلّ مواجهة بتقرّبك. جهّز نفسك وارجع الساحة.'],
            ['challenges.result.page_title', 'challenges', '«نتيجة المواجهة» — عنوان التبويب (:challenge)', 'نتيجة: :challenge'],
            ['challenges.result.penalty_note', 'challenges', '«نتيجة المواجهة» — شرح عقوبة الانسحاب (:n)', 'وعقوبة الانسحاب (:n تذاكر) بتتشال من الاقتصاد ومبتروحش لحدّ.'],
            ['challenges.result.settlement_title', 'challenges', '«نتيجة المواجهة» — عنوان بلوك المحصّلة', 'محصّلة المواجهة'],
            ['challenges.result.share_action', 'challenges', '«نتيجة المواجهة» — زرّ لقطة الإنجاز', 'لقطة إنجاز قابلة للمشاركة'],
            ['challenges.result.waiting_note', 'challenges', '«نتيجة المواجهة» — تنبيه انتظار الحسم (:seconds)', 'سلّمت وخلّصت — باقي :seconds ثانية وتُقفَل المواجهة وتظهر النتيجة.'],
            ['challenges.result.zero_sum_note', 'challenges', '«نتيجة المواجهة» — شرح الاقتصاد المنغلق', 'اللي بيكسبه الفائز هو بعينه اللي بيخسره الخاسر — مفيش تذكرة بتتولد من العدم.'],
            ['challenges.war_icon.default_aria', 'challenges', '«أيقونة الحرب» — الوصف الافتراضيّ لقارئ الشاشة', 'أيقونة الحرب'],
        ];

        foreach ($rows as [$key, $group, $label, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => 'string',
                'default_value' => $default,
                'value' => $default,
            ]);
        }

        Cache::forget('settings');
    }

    /** كلّ رقم ونصّ في المجال من الإعدادات — ممنوع الحرق (2.13) */
    public function settings(): void
    {
        $rows = [
            // ---------------- الحروب (15)
            // عدد أسئلة الجولة لكلّ نوع (15.1 · 15.5 · 15.6)
            // عناوين سكشنز إعدادات الحرب (WarSettingsService::sections — 2.13-ب)
            ['wars.section.costs', 'gamification_wars', 'عنوان سكشن: التكاليف', 'string', 'التكاليف'],
            ['wars.section.rewards', 'gamification_wars', 'عنوان سكشن: المكافآت', 'string', 'المكافآت'],
            ['wars.section.timers', 'gamification_wars', 'عنوان سكشن: المؤقّتات', 'string', 'المؤقّتات'],
            ['wars.section.question_source', 'gamification_wars', 'عنوان سكشن: مصدر الأسئلة', 'string', 'مصدر الأسئلة'],
            ['wars.section.limits', 'gamification_wars', 'عنوان سكشن: الحدود', 'string', 'الحدود'],
            ['wars.section.texts', 'gamification_wars', 'عنوان سكشن: النصوص والهويّة', 'string', 'النصوص والهويّة'],
            ['wars.count.knowledge', 'gamification_wars', 'عدد أسئلة حرب المعلومات', 'number', '20'],
            ['wars.count.survival', 'gamification_wars', 'عدد أسئلة حرب البقاء', 'number', '12'],
            ['wars.count.estimation', 'gamification_wars', 'عدد أسئلة حرب التقدير', 'number', '7'],
            // منع تكرار السؤال لنفس المستخدم + نافذة التكرار (24.2)
            ['wars.bank.prevent_repeat', 'gamification_wars', 'منع تكرار السؤال لنفس المستخدم', 'bool', '1'],
            ['wars.bank.repeat_window_matches', 'gamification_wars', 'نافذة التكرار (آخر كم مواجهة)', 'number', '5'],
            ['wars.focus.honesty_message', 'gamification_wars', 'رسالة الأمانة في حرب التركيز', 'string',
                'هذا التحدي أمانة بينك وبين نفسك. لو سجّلت إنجازًا ما عملتوش، إنت ما غششتش المنصة — غششت نفسك، '
                .'وعوّدتها تاخد مكسب مش من حقها؛ وده أخطر من إنك ما تعملش حاجة أصلًا. '
                .'كن صادقًا مع نفسك… الجائزة الحقيقية مش النقاط، الجائزة هي إنت وإنت بتكبر.'],
            ['challenges.types', 'challenges', 'أنواع الحروب المتاحة', 'json', json_encode([
                'knowledge' => 'حرب المعلومات',
                'focus' => 'حرب التركيز',
                'survival' => 'حرب البقاء',
                'estimation' => 'حرب التقدير',
            ], JSON_UNESCAPED_UNICODE)],

            // ---------------- الاحتفالات (2.14)
            ['celebrations.auto_dismiss_seconds', 'gamification_celebrations', 'ثوانٍ قبل إغلاق الاحتفال تلقائيًّا', 'number', '6'],
            ['celebrations.default_message', 'gamification_celebrations', 'صيغة التهنئة الافتراضيّة', 'string', 'مبروك يا :name — :label 🎉'],

            // ---------------- الليدر بورد (7.3)
            ['leaderboard.rows_per_page', 'gamification_leaderboard', 'عدد الصفوف المعروضة', 'number', '50'],
            // سقف «الفترة التي يحدّدها المستخدم بنفسه» — فلا مدى بلا معنى
            ['leaderboard.max_range_days', 'gamification_leaderboard', 'أقصى عدد أيّام للفترة المخصّصة', 'number', '365'],
            // الحدّ الأدنى لإظهار «أفضل من X%» (2.9-7) — تحته نعرض الترتيب وحده
            ['leaderboard.percentile_min_peers', 'gamification_leaderboard', 'حدّ إظهار «أفضل من X%»', 'number', '20'],
            ['leaderboard.title', 'gamification_leaderboard', 'عنوان اللوحة', 'string', 'الليدر بورد'],
            ['leaderboard.breadcrumb', 'gamification_leaderboard', 'مسار التنقّل', 'string', 'إنجازاتي'],
            ['leaderboard.xp_label', 'gamification_leaderboard', 'اسم وحدة النقاط على الشاشة', 'string', 'XP'],
            ['leaderboard.lifetime_label', 'gamification_leaderboard', 'وصف الرصيد التراكميّ', 'string', 'الرصيد الكلّيّ'],
            ['leaderboard.range_label', 'gamification_leaderboard', 'صيغة اسم الفترة', 'string', 'آخر :days يومًا'],
            ['leaderboard.range.custom', 'gamification_leaderboard', 'خيار الفترة المخصّصة', 'string', 'فترة أحدّدها'],
            ['leaderboard.range.custom_label', 'gamification_leaderboard', 'عنوان خانة الأيّام', 'string', 'عدد الأيّام'],
            ['leaderboard.rank_prefix', 'gamification_leaderboard', 'بادئة سطر الترتيب', 'string', 'ترتيبك دلوقتي'],
            ['leaderboard.rank_of', 'gamification_leaderboard', 'كلمة «من» في سطر الترتيب', 'string', 'من'],
            ['leaderboard.empty_hint', 'gamification_leaderboard', 'الحالة الفارغة', 'string', 'ابدأ أوّل تدريب وهتظهر هنا.'],
            ['leaderboard.you_label', 'gamification_leaderboard', 'وسم صفّك أنت', 'string', 'ده إنت'],
            ['leaderboard.podium.title', 'gamification_leaderboard', 'عنوان منصّة التتويج', 'string', 'منصّة التتويج'],
            ['leaderboard.me_card.title', 'gamification_leaderboard', 'عنوان كارت ترتيبك', 'string', 'ترتيبك'],
            ['leaderboard.me_card.total_prefix', 'gamification_leaderboard', 'بادئة إجماليّ المتنافسين', 'string', 'من'],
            ['leaderboard.me_card.better_than', 'gamification_leaderboard', 'نصّ «أفضل من»', 'string', 'أفضل من'],
            ['leaderboard.me_card.lifetime', 'gamification_leaderboard', 'نصّ الرصيد الكلّيّ في الكارت', 'string', 'رصيدك الكلّيّ'],
            ['leaderboard.filter.scope', 'gamification_leaderboard', 'عنوان فلتر النطاق', 'string', 'النطاق'],
            ['leaderboard.filter.period', 'gamification_leaderboard', 'عنوان فلتر الفترة', 'string', 'الفترة'],
            ['leaderboard.filter.country', 'gamification_leaderboard', 'عنوان فلتر الدولة', 'string', 'الدولة'],
            ['leaderboard.filter.governorate', 'gamification_leaderboard', 'عنوان فلتر المحافظة', 'string', 'المحافظة'],
            ['leaderboard.filter.any', 'gamification_leaderboard', 'خيار «كلّ الدول»', 'string', 'كلّ الدول'],
            ['leaderboard.filter.any_governorate', 'gamification_leaderboard', 'خيار «كلّ المحافظات»', 'string', 'كلّ المحافظات'],
            ['leaderboard.filter.search', 'gamification_leaderboard', 'عنوان خانة البحث', 'string', 'بحث بالاسم'],
            ['leaderboard.filter.search_placeholder', 'gamification_leaderboard', 'تلميح خانة البحث', 'string', 'اسم زميلك…'],
            ['leaderboard.filter.apply', 'gamification_leaderboard', 'زرّ تطبيق الفلاتر', 'string', 'طبّق'],
            ['leaderboard.scope.all', 'gamification_leaderboard', 'خيار النطاق: الكلّ', 'string', 'الكلّ'],
            ['leaderboard.scope.country', 'gamification_leaderboard', 'خيار النطاق: دولتي', 'string', 'دولتي'],
            ['leaderboard.scope.governorate', 'gamification_leaderboard', 'خيار النطاق: محافظتي', 'string', 'محافظتي'],

            // ---------------- الستريك ونادي الخامسة (7.2)
            // مفاتيحه كلّها تُزرَع من كتالوج الإعدادات نفسه (انظر streakSettings)
            // فلا تختلف القيمة الافتراضيّة بين السيدر وزرّ الـReset (2.13).

        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }
    }

    /**
     * **نصوص شاشتَي «الشارات» و«الستريك ونادي الخامسة»** (2.13-أ: «النصوص
     * الظاهرة للمستخدم») — كلّ جملةٍ يقرؤها المتدرّب على
     * `resources/views/achievements/**` لها مفتاحها هنا، والوحدة **جملةٌ
     * كاملة** كما تُقرَأ لا كلمةً مقتطعة. و`:days` وأخواتها مواضع استبدال.
     *
     * ⚠️ عناوينٌ **منصوصة حرفيًّا في الدستور** (24.5 · 12.0): «إنجازاتي»
     * و«الشارات» و«الستريك ونادي الخامسة» — افتراضيُّها هو النصّ المنصوص،
     * وتغييرُه من اللوحة يخالف خريطة السايد بار.
     */
    public function achievementsScreenTextSettings(): void
    {
        $rows = [
            // ---------------- شاشة الشارات (7.4 · 24.5)
            ['badges.screen.title', 'gamification_badges', 'عنوان شاشة الشارات (منصوص في 24.5)', 'الشارات'],
            ['badges.screen.subtitle', 'gamification_badges', 'سطر تحت العنوان (:unlocked · :total)', ':unlocked مفتوحة من :total'],
            ['badges.screen.breadcrumb_root', 'gamification_badges', 'جذر مسار التنقّل (منصوص في 24.5)', 'إنجازاتي'],
            ['badges.screen.export_title', 'gamification_badges', 'عنوان بطاقة الاستخراج كصورة', 'شاراتي'],
            ['badges.screen.export_subtitle', 'gamification_badges', 'سطر بطاقة الاستخراج (:unlocked · :total)', ':unlocked من :total شارة'],
            ['badges.screen.progress_label', 'gamification_badges', 'عنوان بار الإنجاز', 'إنجازي في الشارات'],
            ['badges.screen.filter_state', 'gamification_badges', 'عنوان فلتر الحالة', 'الحالة'],
            ['badges.screen.filter_state_all', 'gamification_badges', 'خيار الحالة: الكلّ', 'الكلّ'],
            ['badges.screen.filter_state_unlocked', 'gamification_badges', 'خيار الحالة: المفتوحة', 'مفتوحة'],
            ['badges.screen.filter_state_locked', 'gamification_badges', 'خيار الحالة: المقفولة', 'مقفولة'],
            ['badges.screen.filter_search', 'gamification_badges', 'عنوان خانة البحث', 'بحث'],
            ['badges.screen.filter_search_placeholder', 'gamification_badges', 'تلميح خانة البحث', 'اسم الشارة أو شرطها…'],
            ['badges.screen.filter_apply', 'gamification_badges', 'زرّ تطبيق الفلاتر', 'طبّق'],
            ['badges.screen.empty', 'gamification_badges', 'الحالة الفارغة بعد الفلترة', 'مفيش شارات بالوصف ده — جرّب بحثًا أوسع.'],
            ['badges.screen.unlocked_at', 'gamification_badges', 'تاريخ فتح الشارة (:date)', 'اتفتحت :date'],
            ['badges.screen.progress_of_condition', 'gamification_badges', 'تقدّم الشارة المقفولة (:percent)', ':percent% من الشرط'],
            ['badges.icon.aria_unlocked', 'gamification_badges', 'وصف أيقونة الشارة المفتوحة لقارئ الشاشة', 'شارة مفتوحة'],
            ['badges.icon.aria_locked', 'gamification_badges', 'وصف أيقونة الشارة المقفولة لقارئ الشاشة', 'شارة مقفولة'],

            // ---------------- شريط نادي الخامسة العلويّ (7.2)
            ['streaks.club_bar.message', 'gamification_streaks', 'نصّ شريط النادي العلويّ', 'نادي الخامسة مفتوح دلوقتي — سجّل حضورك وخُد'],
            ['streaks.club_bar.checkin_action', 'gamification_streaks', 'زرّ التسجيل في الشريط العلويّ', 'سجّل حضوري'],

            // ---------------- الخريطة الحراريّة (7.2)
            ['streaks.heatmap.aria_label', 'gamification_streaks', 'وصف الخريطة لقارئ الشاشة', 'خريطة أيّامي النشطة'],
            ['streaks.heatmap.tooltip_club', 'gamification_streaks', 'تلميح يوم النادي', 'نادي الخامسة ★'],
            ['streaks.heatmap.tooltip_freeze', 'gamification_streaks', 'تلميح اليوم المحميّ بدرع', 'يوم محميّ بدرع ▲'],
            ['streaks.heatmap.tooltip_active', 'gamification_streaks', 'تلميح اليوم النشط', 'يوم نشط ●'],
            ['streaks.heatmap.tooltip_idle', 'gamification_streaks', 'تلميح اليوم بلا نشاط', 'بلا نشاط ○'],
            ['streaks.heatmap.legend_idle', 'gamification_streaks', 'مفتاح الخريطة: بلا نشاط', 'بلا نشاط'],
            ['streaks.heatmap.legend_active', 'gamification_streaks', 'مفتاح الخريطة: يوم نشط', 'يوم نشط'],
            ['streaks.heatmap.legend_club', 'gamification_streaks', 'مفتاح الخريطة: نادي الخامسة', 'نادي الخامسة'],
            ['streaks.heatmap.legend_freeze', 'gamification_streaks', 'مفتاح الخريطة: يوم محميّ بدرع', 'يوم محميّ بدرع'],

            // ---------------- شاشة الستريك ونادي الخامسة (7.2 · 24.5)
            ['streaks.screen.title', 'gamification_streaks', 'عنوان الشاشة (منصوص في 24.5)', 'الستريك ونادي الخامسة'],
            ['streaks.screen.subtitle', 'gamification_streaks', 'سطر تحت العنوان', 'استمراريّتك اليوميّة — يوم ورا يوم، والعادة بتتبني.'],
            ['streaks.screen.breadcrumb_root', 'gamification_streaks', 'جذر مسار التنقّل (منصوص في 24.5)', 'إنجازاتي'],
            ['streaks.screen.breadcrumb_self', 'gamification_streaks', 'آخر مسار التنقّل', 'الستريك'],
            ['streaks.screen.export_subtitle', 'gamification_streaks', 'سطر بطاقة الاستخراج (:date)', 'حتى :date'],
            ['streaks.screen.export_days', 'gamification_streaks', 'صيغة عدد الأيّام في البطاقة (:days)', ':days يوم'],
            ['streaks.screen.export_best', 'gamification_streaks', 'سطر أطول ستريك في البطاقة', 'أطول ستريك'],
            ['streaks.screen.export_club', 'gamification_streaks', 'سطر نادي الخامسة في البطاقة', 'نادي الخامسة'],
            ['streaks.screen.checkin_action', 'gamification_streaks', 'زرّ تسجيل حضور اليوم', 'سجّل حضور النهارده'],
            ['streaks.screen.checked_in_today', 'gamification_streaks', 'إشعار أنّ اليوم سُجِّل', 'النهارده اتسجّل'],
            ['streaks.screen.kpi_current', 'gamification_streaks', 'كارت الستريك الحاليّ — العنوان', 'ستريكي الحاليّ'],
            ['streaks.screen.kpi_current_hint', 'gamification_streaks', 'كارت الستريك الحاليّ — التلميح', 'أيّام متواصلة'],
            ['streaks.screen.kpi_best', 'gamification_streaks', 'كارت أطول ستريك — العنوان', 'أطول ستريك (Best)'],
            ['streaks.screen.kpi_club_days', 'gamification_streaks', 'كارت أيّام النادي — العنوان', 'أيّام نادي الخامسة'],
            ['streaks.screen.kpi_last_active', 'gamification_streaks', 'كارت آخر يوم نشط — العنوان', 'آخر يوم نشط'],
            ['streaks.screen.reward_ready_badge', 'gamification_streaks', 'شارة جاهزيّة المكافأة', 'مكافأة جاهزة'],
            ['streaks.screen.reward_ready_message', 'gamification_streaks', 'رسالة جاهزيّة المكافأة (:days)', 'كمّلت :days يوم متواصل — تذكرة الهدية في انتظارك'],
            ['streaks.screen.broken_title', 'gamification_streaks', 'عنوان الستريك المنقطع', 'ابدأ من جديد النهارده'],
            ['streaks.screen.broken_message', 'gamification_streaks', 'رسالة الستريك المنقطع (:best)', 'الستريك اتقطع، وده بيحصل. أطول ستريك عملته (:best يوم) لسّه محفوظ ليك — وأوّل يوم في السلسلة الجديدة بيبدأ بضغطة.'],
            ['streaks.screen.days_title', 'gamification_streaks', 'عنوان بلوك الخريطة', 'أيّامي'],
            ['streaks.screen.range_1_month', 'gamification_streaks', 'خيار المدى: شهر', 'آخر شهر'],
            ['streaks.screen.range_3_months', 'gamification_streaks', 'خيار المدى: 3 شهور', 'آخر 3 شهور'],
            ['streaks.screen.range_6_months', 'gamification_streaks', 'خيار المدى: 6 شهور', 'آخر 6 شهور'],
            ['streaks.screen.club_title', 'gamification_streaks', 'عنوان بلوك النادي', 'نادي الخامسة صباحًا'],
            ['streaks.screen.window_open', 'gamification_streaks', 'شارة النافذة المفتوحة', 'النافذة مفتوحة'],
            ['streaks.screen.window_closed', 'gamification_streaks', 'شارة النافذة المقفولة', 'النافذة مقفولة'],
            ['streaks.screen.club_window_hint', 'gamification_streaks', 'شرح نافذة النادي (:start · :end · :timezone)', 'سجّل حضورك بين :start و:end بتوقيتك المحلّيّ (:timezone)، فتُحسَب لك يوم في النادي وتاخد XP الحضور.'],
            ['streaks.screen.next_checkin_gives', 'gamification_streaks', 'عنوان XP الحضور القادم', 'حضورك القادم يمنحك'],
            ['streaks.screen.next_ladder_step', 'gamification_streaks', 'الدرجة التالية في سلّم XP (:days · :xp)', 'باقي :days يوم حضور توصل لدرجة :xp لكلّ يوم.'],
            ['streaks.screen.rule_not_consecutive', 'gamification_streaks', 'قاعدة: الأيّام غير متتابعة', 'الأيّام مش لازم متتابعة — الهدف بناء العادة.'],
            ['streaks.screen.rule_reward_cycle', 'gamification_streaks', 'قاعدة: دورة المكافأة (:days)', 'كلّ :days أيّام متواصلة = مكافأة تذكرة هدية'],
            ['streaks.screen.rule_freeze', 'gamification_streaks', 'قاعدة: درع التجميد', 'درع التجميد بيحمي يوم فايت من كسر السلسلة.'],
            ['streaks.screen.freeze_title', 'gamification_streaks', 'عنوان بلوك درع التجميد', 'درع التجميد'],
            ['streaks.screen.freezable_day', 'gamification_streaks', 'اليوم القابل للحماية (:day · :cost)', 'يوم :day فايت — احميه بـ:cost تذكرة قبل ما السلسلة تنكسر.'],
            ['streaks.screen.freezes_used', 'gamification_streaks', 'عدّاد الدروع المستعملة (:used · :cap)', 'استعملت :used من :cap دروع الشهر ده.'],
            ['streaks.screen.recent_club_days', 'gamification_streaks', 'عنوان آخر أيّام النادي', 'آخر أيّامي في النادي'],
        ];

        foreach ($rows as [$key, $group, $label, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => 'string',
                'default_value' => $default,
                'value' => $default,
            ]);
        }
    }

    /**
     * إعدادات الستريكس ونادي الخامسة (7.2) — من **كتالوج الإعدادات نفسه**،
     * فمصدر الافتراضيّ واحد: ما يزرعه السيدر هو ما يرجّعه زرّ الـReset بالضبط.
     */
    public function streakSettings(): void
    {
        foreach (SettingsCatalog::group('gamification_streaks') as $key => [$group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }
    }

    /**
     * أسئلة المكافأة (12.10-أ): إعداداتها من الكتالوج نفسه + سؤالان تجريبيّان
     * (واحد نشط بعدّاد وواحد مغلق) ليظهر الفرق بين الحالتين في الشاشة.
     */
    private function rewardQuestions(): void
    {
        foreach (SettingsCatalog::group('gamification_reward_questions') as $key => [$group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }

        $rows = [
            [
                'token' => 'rqdemoactive',
                'prompt' => 'كام تذكرة بتاخدها لو خلّصت الدرس قبل نصف مهلة التدريب؟',
                'type' => 'choice',
                'options' => ['تذكرة واحدة', 'تذكرتان', 'ثلاث تذاكر'],
                'correct_answer' => 'تذكرتان',
                'reward_xp' => 50,
                'reward_tickets' => 1,
                'active_minutes' => 120,
                'opens_at' => now()->subMinutes(10),
                'closes_at' => now()->addMinutes(110),
                'status' => 'published',
            ],
            [
                'token' => 'rqdemoclosed',
                'prompt' => 'درجة النجاح في الامتحان النهائيّ للتدريب كام بالمئة؟',
                'type' => 'number',
                'options' => null,
                'correct_answer' => '70',
                'reward_xp' => 30,
                'reward_tickets' => 0,
                'active_minutes' => 30,
                'opens_at' => now()->subDay(),
                'closes_at' => now()->subDay()->addMinutes(30),
                'status' => 'published',
            ],
        ];

        foreach ($rows as $row) {
            RewardQuestion::updateOrCreate(['token' => $row['token']], $row);
        }
    }

    /** أحداث احتفال المجال — ولكلّ حدث مستواه (2.14-أ) */
    private function celebrations(): void
    {
        $events = [
            ['challenge.finished', 'إتمام تحدّي', 1, 'خلّصت التحدّي يا :name — تمام كده.'],
            ['challenge.won', 'الفوز بتحدٍّ', 2, 'كسبت يا :name! 🎉'],
            ['challenge.first_win', 'أوّل فوز في التحديات', 3, 'مبروك يا :name — أوّل فوز ليك في ساحة التحدّي!'],
            ['badge.unlocked', 'فتح شارة جديدة', 2, 'شارة جديدة يا :name 🥇'],
        ];

        foreach ($events as [$key, $label, $tier, $message]) {
            CelebrationEvent::updateOrCreate(['key' => $key], [
                'label_ar' => $label,
                'tier' => $tier,
                'message_ar' => $message,
            ]);
        }
    }

    /** الشارات — وشرط الفتح مكتوب صراحةً لا لغزًا (7.4 · 24.5) */
    private function badges(): void
    {
        $rows = [
            ['first_step', 'الخطوة الأولى', 'خلّص أوّل تحدّي واحد.', 'challenges.finished', 1],
            ['warrior', 'محارب', 'خلّص 10 تحدّيات.', 'challenges.finished', 10],
            ['champion', 'بطل الساحة', 'اكسب 5 تحدّيات.', 'challenges.wins', 5],
            // سلّم شارات التركيز (15.3): ساعة · ستّ ساعات · 24 ساعة تراكميّة
            ['focus_1h', 'ساعة تركيز', 'اجمع 60 دقيقة تركيز.', 'focus.minutes', 60],
            ['focus_6h', 'ستّ ساعات تركيز', 'اجمع 360 دقيقة تركيز.', 'focus.minutes', 360],
            ['focus_24h', 'يوم كامل تركيز', 'اجمع 24 ساعة تركيز تراكميّة.', 'focus.minutes', 1440],
            ['week_streak', 'أسبوع كامل', 'حافظ على ستريك 7 أيّام متواصلة.', 'streak.best_days', 7],
            ['month_streak', 'شهر بلا انقطاع', 'حافظ على ستريك 30 يوم متواصلة.', 'streak.best_days', 30],
            ['club_5am_10', 'صاحب الفجر', 'سجّل حضورك 10 أيّام في نادي الخامسة صباحًا.', 'club_5am.days', 10],
            ['xp_1000', 'ألف نقطة', 'اجمع 1000 XP.', 'xp.total', 1000],
            ['xp_10000', 'عشرة آلاف', 'اجمع 10000 XP.', 'xp.total', 10000],
        ];

        foreach ($rows as [$key, $name, $condition, $conditionKey, $value]) {
            Badge::updateOrCreate(['key' => $key], [
                'name_ar' => $name,
                'condition_text_ar' => $condition,
                'condition_key' => $conditionKey,
                'condition_value' => $value,
                'is_active' => true,
            ]);
        }
    }

    /**
     * أربع حروب معتمَدة في الدستور (15.1 · 15.3 · 15.5 · 15.6).
     *
     * ⭐ لا `question_source.items`: الأسئلة تُسحَب من **القمع الموحّد** (15.0)،
     * و`rewards` تبقى فارغة لأنّ الاقتصاد **محصّلة صفريّة** لا مكافأة مسكوكة
     * (15.2-6) — أيّ قيمة هنا تعني سكّ تذاكر من العدم.
     */
    private function challenges(): void
    {
        $tickets = Currency::query()->where('code', 'tickets')->value('id');

        $rows = [
            ['knowledge_war', 'حرب المعلومات', 'اختبر مهاراتك الذهنية والسرعة، وواجه خصمك وجهًا لوجه!', 'knowledge', '#00d4b8'],
            ['focus_war', 'حرب التركيز', 'عمل عميق بلا مقاطعة — والعدّ مبنيّ على أمانتك.', 'focus', '#45ecd7'],
            ['survival_war', 'حرب البقاء', 'جاوب صح وابقى… أول غلطة تخرجك!', 'survival', '#eab308'],
            ['estimation_war', 'حرب التقدير', 'قدّر الرقم الأقرب للصح واكسب!', 'estimation', '#d4af37'],
        ];

        foreach ($rows as [$key, $name, $description, $type, $color]) {
            Challenge::updateOrCreate(['key' => $key], [
                'name_ar' => $name,
                'description' => $description,
                'color' => $color,
                // NULL = اتبع تكلفة الانضمام العامّة من «أوجه الصرف» (2.13)
                'entry_cost' => null,
                'entry_currency_id' => $tickets,
                'rewards' => null,
                'duration_minutes' => null,
                'question_source' => null,
                // نوع الحرب داخل limits ليُقرَأ منه بلا تعديل المخطّط
                'limits' => ['type' => $type],
                'texts' => null,
                'timers' => null,
                'costs' => null,
                'is_active' => true,
                'settings_locked' => false,
            ]);
        }
    }

    /**
     * بنك أسئلة الحروب (12.10-ب) — القمع الموحّد يسحب منه **70%**،
     * و**الأسئلة الرقميّة وحدها** تدخل حرب التقدير (15.6).
     *
     * ولأنّ نصّ 15.6 يشترط **«30% تدريبات رقميّة + 70% قمع»**، فالقسم الرقميّ
     * من `training` **لازمٌ لا زائد**: بدونه يجد القمع تدريباتٍ فارغة فيسدّ
     * العجز كلّه من الساحة، فتخرج المواجهة 100% ساحة — والنسبة تسقط عمليًّا
     * وإن كان حسابها في `WarQuestionFunnel` سليمًا.
     */
    private function questionBank(): void
    {
        foreach ($this->numericQuestions() as [$text, $answer, $tolerance, $unit, $difficulty, $source]) {
            WarQuestion::updateOrCreate(['text' => $text], [
                'answer' => (string) $answer,
                'options' => null,
                'is_numeric' => true,
                'tolerance' => $tolerance,
                'unit' => $unit,
                'difficulty' => $difficulty,
                'source' => $source,
                'status' => 'active',
            ]);
        }

        foreach ($this->choiceQuestions() as [$text, $options, $answer, $difficulty, $source]) {
            WarQuestion::updateOrCreate(['text' => $text], [
                'answer' => (string) $answer,
                'options' => $options,
                'is_numeric' => false,
                'tolerance' => null,
                'unit' => null,
                'difficulty' => $difficulty,
                'source' => $source,
                'status' => 'active',
            ]);
        }
    }

    /**
     * الأسئلة الرقميّة — وحدها تدخل حرب التقدير (15.6).
     *
     * والعمود الأخير **مصدر السؤال** كما في `choiceQuestions()`: قسم `arena`
     * هو الـ70%، وقسم `training` هو الـ30% «تدريبات رقميّة» التي يشترطها
     * النصّ — ولا يكفي فيها ما تحمله أسئلة الدروس المعلَّمة «عامّة»، لأنّ
     * أغلبها اختيارٌ من متعدّد لا رقمٌ كامل، ولأنّ مجال الحروب يُختبَر ويُعرَض
     * بلا زرع بيانات التعلّم أصلًا.
     *
     * @return list<array{0:string,1:int,2:float,3:string,4:string,5:string}>
     */
    private function numericQuestions(): array
    {
        return [
            ['كم دقيقة في اليوم الواحد؟', 1440, 30, 'دقيقة', 'easy', 'arena'],
            ['كم يوم في السنة الميلاديّة العاديّة؟', 365, 2, 'يوم', 'easy', 'arena'],
            ['كم ثانية في الساعة الواحدة؟', 3600, 60, 'ثانية', 'easy', 'arena'],
            ['كم أسبوعًا في السنة تقريبًا؟', 52, 1, 'أسبوع', 'easy', 'arena'],
            ['كم ساعة في الأسبوع؟', 168, 4, 'ساعة', 'easy', 'arena'],
            ['قدّر عدد ساعات النوم المفضَّلة أسبوعيًّا لشخص بالغ.', 56, 7, 'ساعة', 'medium', 'arena'],
            ['كم حرفًا في الأبجديّة العربيّة؟', 28, 1, 'حرف', 'easy', 'arena'],
            ['كم دولة عضو في جامعة الدول العربيّة؟', 22, 1, 'دولة', 'medium', 'arena'],
            ['كم قارّة على سطح الأرض؟', 7, 0, 'قارّة', 'easy', 'arena'],
            ['كم لونًا في قوس قزح؟', 7, 0, 'لون', 'easy', 'arena'],
            ['كم دقيقة في ربع ساعة؟', 15, 0, 'دقيقة', 'easy', 'arena'],
            ['كم شهرًا في ثلاث سنوات؟', 36, 1, 'شهر', 'easy', 'arena'],
            ['قدّر عدد ضربات قلب البالغ في الدقيقة أثناء الراحة.', 72, 12, 'ضربة', 'medium', 'arena'],
            ['كم سنًّا في فم الإنسان البالغ عادةً؟', 32, 2, 'سنّ', 'medium', 'arena'],
            ['كم عظمة في جسم الإنسان البالغ؟', 206, 10, 'عظمة', 'hard', 'arena'],
            ['قدّر درجة غليان الماء بالمئويّة عند مستوى سطح البحر.', 100, 2, 'درجة', 'easy', 'arena'],
            ['كم يومًا في فبراير في السنة الكبيسة؟', 29, 0, 'يوم', 'easy', 'arena'],
            ['كم ركعة في صلوات الفرض اليوميّة مجتمعةً؟', 17, 1, 'ركعة', 'medium', 'arena'],
            ['قدّر عدد صفحات كتاب متوسّط الحجم.', 250, 60, 'صفحة', 'medium', 'arena'],
            ['كم دقيقة في جلسة تركيز مقدارها ساعة ونصف؟', 90, 5, 'دقيقة', 'easy', 'arena'],
            ['قدّر عدد الكلمات التي يقرؤها شخص متوسّط في الدقيقة.', 240, 60, 'كلمة', 'hard', 'arena'],
            ['كم يومًا في الربع الأوّل من السنة العاديّة؟', 90, 3, 'يوم', 'medium', 'arena'],
            ['قدّر عدد اللترات التي يُنصَح بشربها يوميًّا للبالغ.', 2, 1, 'لتر', 'easy', 'arena'],
            ['كم ساعة عمل في أسبوع دوام كامل نموذجيّ؟', 40, 5, 'ساعة', 'easy', 'arena'],

            // ---- الـ30% الرقميّة من التدريبات (15.6) — أرقامها من متن الدروس نفسها
            ['كم دقيقة تستغرق جلسة التركيز الواحدة في نمط بومودورو؟', 25, 5, 'دقيقة', 'easy', 'training'],
            ['كم دقيقة راحة قصيرة بين جلستَي تركيز متتاليتين؟', 5, 2, 'دقيقة', 'easy', 'training'],
            ['كم مهمّة أساسيّة يوصي درس الأولويّات بحصر يومك فيها؟', 3, 1, 'مهمّة', 'easy', 'training'],
            ['قاعدة الدقيقتين: كم دقيقة حدُّ المهمّة التي تُنجَز فورًا؟', 2, 0, 'دقيقة', 'easy', 'training'],
            ['كم سطرًا يكفي لتلخيص الاجتماع كما في درس التواصل؟', 5, 1, 'سطر', 'easy', 'training'],
            ['كم طبقة في بنية رسالة العمل الواضحة؟', 3, 1, 'طبقة', 'medium', 'training'],
            ['قدّر حدّ الكلمات في الجملة الواحدة كما يوصي درس الكتابة.', 20, 5, 'كلمة', 'medium', 'training'],
            ['قدّر عدد دقائق المراجعة الأسبوعيّة لخطّتك.', 30, 10, 'دقيقة', 'medium', 'training'],
            ['قدّر عدد مرّات التكرار المتباعد لتثبيت معلومة في الذاكرة.', 5, 2, 'مرّة', 'hard', 'training'],
            ['كم دقيقة يكفي أن تلخّص فيها درسًا مدّته ساعة؟', 10, 5, 'دقيقة', 'medium', 'training'],
            ['قدّر عدد أسئلة اختبار درسٍ قصير.', 5, 2, 'سؤال', 'easy', 'training'],
            ['قدّر عدد ساعات التعلّم العميق الممكنة في يوم عمل واحد.', 4, 2, 'ساعة', 'hard', 'training'],
            ['كم دقيقة تكفي لمراجعة ملاحظات اليوم قبل النوم؟', 10, 5, 'دقيقة', 'easy', 'training'],
            ['قدّر عدد أيّام الالتزام المتّصل حتى تستقرّ العادة.', 21, 7, 'يوم', 'medium', 'training'],
        ];
    }

    /** @return list<array{0:string,1:list<string>,2:int,3:string,4:string}> */
    private function choiceQuestions(): array
    {
        return [
            ['إيه أهمّ خطوة قبل ما تبدأ أيّ مهمّة؟', ['تحدّد المطلوب بالظبط', 'تبدأ على طول', 'تستنّى حدّ يفكّرك'], 0, 'easy', 'arena'],
            ['التغذية الراجعة المفيدة بتبقى…', ['عامّة ومختصرة', 'محدّدة وقابلة للتنفيذ', 'متأخّرة'], 1, 'easy', 'arena'],
            ['أفضل طريقة تثبّت بيها معلومة اتعلّمتها؟', ['تقراها تاني', 'تشرحها لحدّ', 'تحفظها'], 1, 'medium', 'arena'],
            ['لو الديدلاين قرب والشغل مش خالص، أوّل حاجة تعملها؟', ['تسكت وتكمّل', 'تبلّغ بدري وتقترح خطّة', 'تطلب تأجيل بعد الميعاد'], 1, 'medium', 'arena'],
            ['الهدف الذكيّ (SMART) لازم يكون…', ['طموح ومبهم', 'محدّد وقابل للقياس', 'طويل ومفصّل'], 1, 'easy', 'arena'],
            ['أوّل علامة إنّك مش مركّز؟', ['بتفتح التليفون كلّ شويّة', 'بتخلّص بدري', 'بتاخد نفس عميق'], 0, 'easy', 'arena'],
            ['المهمّة المتعثّرة الصحّ فيها إنّك…', ['تسيبها', 'ترفع علم التعثّر بدري', 'تعيد كتابتها'], 1, 'medium', 'arena'],
            ['أحسن وقت تراجع فيه شغلك؟', ['قبل التسليم بشويّة', 'بعد راحة قصيرة', 'وانت تعبان'], 1, 'medium', 'arena'],
            ['الاعتذار المقبول بيبقى…', ['بعد الميعاد', 'قبل الميعاد وبسبب واضح', 'بلا سبب'], 1, 'easy', 'arena'],
            ['تقسيم المهمّة الكبيرة لخطوات صغيرة بيساعد لأنّه…', ['بيزوّد الشغل', 'بيقلّل التسويف ويوضّح البداية', 'بيأخّر التسليم'], 1, 'medium', 'arena'],
            ['أفضل ردّ على نقد مكتوب بأسلوب حادّ؟', ['ترد بنفس الأسلوب', 'تاخد وقت وترد على المضمون', 'تتجاهل خالص'], 1, 'hard', 'arena'],
            ['قاعدة الدقيقتين في إدارة الوقت معناها…', ['أجّل كلّ حاجة دقيقتين', 'لو المهمّة أقلّ من دقيقتين اعملها فورًا', 'اشتغل دقيقتين بس'], 1, 'medium', 'arena'],
            ['أهمّ حاجة في الاجتماع الفعّال؟', ['عدد الحضور', 'أجندة واضحة ومخرجات', 'طول الوقت'], 1, 'easy', 'arena'],
            ['المراجعة الأسبوعيّة فايدتها الأساسيّة…', ['ملء الوقت', 'تصحيح المسار قبل ما يبعد', 'إظهار المجهود'], 1, 'medium', 'arena'],
            ['التوثيق الجيّد للعمل بيخدم…', ['صاحبه بس', 'مين يجي بعده وصاحبه', 'محدّش'], 1, 'easy', 'arena'],
            ['الأولويّة بتتحدّد بـ…', ['اللي وصل الأوّل', 'الأثر والاستعجال', 'اللي أسهل'], 1, 'medium', 'arena'],
            ['التعلّم بالتكرار المتباعد بيفيد لأنّه…', ['بيقلّل وقت المذاكرة', 'بيقوّي التذكّر بعيد المدى', 'بيخلّي المذاكرة أسهل'], 1, 'hard', 'training'],
            ['قبل ما تسأل سؤالًا الأفضل إنّك…', ['تسأل على طول', 'تحاول تلاقي الإجابة وتكتب اللي جرّبته', 'تستنّى حدّ يشرح'], 1, 'easy', 'training'],
            ['علامة إنّك فهمت المفهوم فعلًا؟', ['حفظته', 'قدرت تشرحه ببساطة وتطبّقه', 'قريته مرّتين'], 1, 'medium', 'training'],
            ['الملاحظات المفيدة أثناء التعلّم بتبقى…', ['نسخة من الشرح', 'بكلماتك إنت مع أمثلة', 'نقاط بلا سياق'], 1, 'medium', 'training'],
            ['الراحة القصيرة بين جلسات التركيز بتساعد على…', ['تشتيت الانتباه', 'استعادة الطاقة والانتباه', 'إطالة اليوم'], 1, 'easy', 'training'],
            ['أفضل طريقة تتعامل بيها مع خطأ ارتكبته؟', ['تخبّيه', 'تعترف بيه بسرعة وتصلّحه', 'تلوم غيرك'], 1, 'easy', 'training'],
        ];
    }

    /**
     * صلاحيّات المتدرّب في شاشات هذا المجال — بنطاق SELF (12.2.1).
     * تُتخطّى بهدوء إن لم تكن مصفوفة الصلاحيّات مزروعة بعد.
     */
    private function traineePermissions(): void
    {
        $role = Role::query()->where('key', 'trainee')->first();

        if (! $role) {
            return;
        }

        $expander = app(PermissionExpander::class);

        foreach ([
            'war_participation.view',
            'war_participation.create',
            'war_participation.delete',
            'wars_matches.view',
            'wars_matches.create',
            'wars_matches.edit',
            'wars_matches.reject',
            'leaderboards.view',
            'achievements.view',
            'badges.view',
            'streaks.view',
            'streaks.create',
        ] as $permission) {
            $expander->attachToRole($role, $permission, 'SELF');
        }
    }
}
