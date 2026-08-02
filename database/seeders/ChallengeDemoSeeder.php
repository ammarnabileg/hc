<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\CelebrationEvent;
use App\Models\Challenge;
use App\Models\Currency;
use App\Models\Game;
use App\Models\RewardQuestion;
use App\Models\Role;
use App\Models\Setting;
use App\Models\WarQuestion;
use App\Services\Admin\Volunteer\SettingsCatalog;
use App\Services\Gamification\GamesAdminService;
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
        $this->rewardQuestions();
        $this->celebrations();
        $this->badges();
        $this->challenges();
        $this->questionBank();
        $this->games();
        $this->traineePermissions();

        // الإعدادات تُقرأ من كاش دائم — نُبطله بعد الكتابة (2.13)
        Cache::forget('settings');
    }

    /** كلّ رقم ونصّ في المجال من الإعدادات — ممنوع الحرق (2.13) */
    private function settings(): void
    {
        $rows = [
            // ---------------- الحروب (15)
            // عدد أسئلة الجولة لكلّ نوع (15.1 · 15.5 · 15.6)
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

            // ---------------- الستريك ونادي الخامسة (7.2)
            // مفاتيحه كلّها تُزرَع من كتالوج الإعدادات نفسه (انظر streakSettings)
            // فلا تختلف القيمة الافتراضيّة بين السيدر وزرّ الـReset (2.13).

        ];

        // ---------------- الألعاب (7.5 · 24.2) — من كتالوج المجال نفسه،
        // فمصدر الافتراضيّ واحد: ما يزرعه السيدر هو ما يرجّعه زرّ الـReset.
        foreach (GamesAdminService::catalog() as $key => [$group, $label, $type, $default]) {
            $rows[] = [$key, $group, $label, $type, $default];
        }

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
     * إعدادات الستريكس ونادي الخامسة (7.2) — من **كتالوج الإعدادات نفسه**،
     * فمصدر الافتراضيّ واحد: ما يزرعه السيدر هو ما يرجّعه زرّ الـReset بالضبط.
     */
    private function streakSettings(): void
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
                'entry_cost' => 0,
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
     */
    private function questionBank(): void
    {
        foreach ($this->numericQuestions() as [$text, $answer, $tolerance, $unit, $difficulty]) {
            WarQuestion::updateOrCreate(['text' => $text], [
                'answer' => (string) $answer,
                'options' => null,
                'is_numeric' => true,
                'tolerance' => $tolerance,
                'unit' => $unit,
                'difficulty' => $difficulty,
                'source' => 'arena',
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

    /** @return list<array{0:string,1:int,2:float,3:string,4:string}> */
    private function numericQuestions(): array
    {
        return [
            ['كم دقيقة في اليوم الواحد؟', 1440, 30, 'دقيقة', 'easy'],
            ['كم يوم في السنة الميلاديّة العاديّة؟', 365, 2, 'يوم', 'easy'],
            ['كم ثانية في الساعة الواحدة؟', 3600, 60, 'ثانية', 'easy'],
            ['كم أسبوعًا في السنة تقريبًا؟', 52, 1, 'أسبوع', 'easy'],
            ['كم ساعة في الأسبوع؟', 168, 4, 'ساعة', 'easy'],
            ['قدّر عدد ساعات النوم المفضَّلة أسبوعيًّا لشخص بالغ.', 56, 7, 'ساعة', 'medium'],
            ['كم حرفًا في الأبجديّة العربيّة؟', 28, 1, 'حرف', 'easy'],
            ['كم دولة عضو في جامعة الدول العربيّة؟', 22, 1, 'دولة', 'medium'],
            ['كم قارّة على سطح الأرض؟', 7, 0, 'قارّة', 'easy'],
            ['كم لونًا في قوس قزح؟', 7, 0, 'لون', 'easy'],
            ['كم دقيقة في ربع ساعة؟', 15, 0, 'دقيقة', 'easy'],
            ['كم شهرًا في ثلاث سنوات؟', 36, 1, 'شهر', 'easy'],
            ['قدّر عدد ضربات قلب البالغ في الدقيقة أثناء الراحة.', 72, 12, 'ضربة', 'medium'],
            ['كم سنًّا في فم الإنسان البالغ عادةً؟', 32, 2, 'سنّ', 'medium'],
            ['كم عظمة في جسم الإنسان البالغ؟', 206, 10, 'عظمة', 'hard'],
            ['قدّر درجة غليان الماء بالمئويّة عند مستوى سطح البحر.', 100, 2, 'درجة', 'easy'],
            ['كم يومًا في فبراير في السنة الكبيسة؟', 29, 0, 'يوم', 'easy'],
            ['كم ركعة في صلوات الفرض اليوميّة مجتمعةً؟', 17, 1, 'ركعة', 'medium'],
            ['قدّر عدد صفحات كتاب متوسّط الحجم.', 250, 60, 'صفحة', 'medium'],
            ['كم دقيقة في جلسة تركيز مقدارها ساعة ونصف؟', 90, 5, 'دقيقة', 'easy'],
            ['قدّر عدد الكلمات التي يقرؤها شخص متوسّط في الدقيقة.', 240, 60, 'كلمة', 'hard'],
            ['كم يومًا في الربع الأوّل من السنة العاديّة؟', 90, 3, 'يوم', 'medium'],
            ['قدّر عدد اللترات التي يُنصَح بشربها يوميًّا للبالغ.', 2, 1, 'لتر', 'easy'],
            ['كم ساعة عمل في أسبوع دوام كامل نموذجيّ؟', 40, 5, 'ساعة', 'easy'],
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

    /** ألعاب تجريبيّة لتاب الألعاب (24.2 · 7.5) */
    private function games(): void
    {
        $rows = [
            ['memory_match', 'مطابقة الذاكرة', 'قلّب الكروت ولاقِ الأزواج قبل ما الوقت يخلص.', 1, 60, 'active'],
            ['fast_math', 'حساب سريع', 'عمليّات حسابيّة بسيطة في وقت ضيّق.', 1, 80, 'active'],
            ['word_ladder', 'سلّم الكلمات', 'كوّن كلمات جديدة بتغيير حرف واحد.', 2, 120, 'soon'],
        ];

        foreach ($rows as [$key, $name, $description, $cost, $xp, $status]) {
            Game::updateOrCreate(['key' => $key], [
                'name_ar' => $name,
                'description' => $description,
                'ticket_cost' => $cost,
                'xp_mode' => 'fixed',
                'xp_reward' => $xp,
                'status' => $status,
                'soon_text' => $status === 'soon' ? 'اللعبة دي في الطريق — استنّانا قريب.' : null,
            ]);
        }
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
            'games.view',
        ] as $permission) {
            $expander->attachToRole($role, $permission, 'SELF');
        }
    }
}
