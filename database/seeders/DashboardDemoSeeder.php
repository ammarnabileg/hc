<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\CourseCompletion;
use App\Models\Currency;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Role;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Streak;
use App\Models\StreakDay;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Gamification\LevelResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * بيانات تجريبيّة للوحة المتدرّب الرئيسيّة (14 · 24.5).
 * ⚠️ لا تُسجَّل في DatabaseSeeder — تُشغَّل يدويًّا:
 *    php artisan db:seed --class=DashboardDemoSeeder
 */
class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->screenTextSettings();

        $user = $this->trainee();
        $courses = $this->courses();

        $this->enroll($user, $courses);
        $this->wallet($user);
        $this->streak($user);
        $this->certificate($user, $courses['تصميم واجهات المستخدم']);

        $this->command?->info('لوحة المتدرّب: بيانات تجريبيّة لـ'.$user->name.' (#'.$user->code.').');
    }

    // ------------------------------------------------------------ الإعدادات

    /** كلّ رقم ونصّ في اللوحة يُقرأ من هنا — ولا شيء محروق في الكود (2.13) */
    public function settings(): void
    {
        $rows = [
            ['dashboard.header.greeting', 'dashboard', 'تحيّة رأس اللوحة (:name = اسم المستخدم)', 'string', 'أهلًا :name'],
            ['dashboard.primary_action.label', 'dashboard', 'نصّ الفعل الرئيسيّ', 'string', 'أكمل آخر درس'],
            ['dashboard.empty.message', 'dashboard', 'نصّ الحالة الفارغة', 'string', 'لسّه مابدأتش تدريب'],
            ['dashboard.empty.action', 'dashboard', 'زرّ الحالة الفارغة', 'string', 'تصفّح المتجر'],
            ['dashboard.deadline.none_label', 'dashboard', 'نصّ التدريب بلا موعد نهائيّ', 'string', 'بلا موعد نهائيّ'],
            ['dashboard.deadline.warn_days', 'dashboard', 'أيّام «انتبه» قبل الموعد (أصفر)', 'number', '3'],
            ['dashboard.deadline.danger_hours', 'dashboard', 'ساعات الخطر قبل الموعد (أحمر)', 'number', '24'],
            ['dashboard.deadlines.limit', 'dashboard', 'عدد المواعيد في بلوك «أقرب المواعيد»', 'number', '4'],
            ['dashboard.deadlines.window_days', 'dashboard', 'نافذة «أقرب المواعيد» (أيّام)', 'number', '30'],
            ['dashboard.courses.limit', 'dashboard', 'أقصى كروت تدريبات جارية', 'number', '6'],
            ['dashboard.stats.range_options', 'dashboard', 'خيارات فلتر الفترة في الإحصائيّات', 'json', '[7,30]'],
            ['dashboard.heatmap.weeks', 'dashboard', 'عدد أسابيع خريطة الحضور', 'number', '12'],
            ['dashboard.tickets.daily_max_days', 'dashboard', 'أقصى مدى تُعرَض فيه بارات التذاكر يوميًّا', 'number', '7'],
            ['dashboard.achievements.radar_max_level', 'dashboard', 'سقف الرادار المعروض (مستوى)', 'number', '6'],
            // عتبات مسارات الإنجاز الخمسة (10.1): الزيادة = base + (N−2) × step
            ['dashboard.achievements.account.base', 'dashboard', 'عتبة مستوى الحساب — الأساس (XP)', 'number', '500'],
            ['dashboard.achievements.account.step', 'dashboard', 'عتبة مستوى الحساب — الزيادة (XP)', 'number', '250'],
            ['dashboard.achievements.club_5am.base', 'dashboard', 'عتبة نادي الخامسة — الأساس (يوم)', 'number', '3'],
            ['dashboard.achievements.club_5am.step', 'dashboard', 'عتبة نادي الخامسة — الزيادة (يوم)', 'number', '2'],
            ['dashboard.achievements.referrals.base', 'dashboard', 'عتبة الدعوات — الأساس (دعوة)', 'number', '5'],
            ['dashboard.achievements.referrals.step', 'dashboard', 'عتبة الدعوات — الزيادة (دعوة)', 'number', '2'],
            ['dashboard.achievements.tickets.base', 'dashboard', 'عتبة التذاكر — الأساس (تذكرة)', 'number', '15'],
            ['dashboard.achievements.tickets.step', 'dashboard', 'عتبة التذاكر — الزيادة (تذكرة)', 'number', '10'],
            ['dashboard.achievements.learning.base', 'dashboard', 'عتبة استمراريّة التعلّم — الأساس (درس)', 'number', '5'],
            ['dashboard.achievements.learning.step', 'dashboard', 'عتبة استمراريّة التعلّم — الزيادة (درس)', 'number', '3'],
            // عمر شهادة العرض عند الزرع — رقمٌ في اللوحة لا محروقًا في السيدر (2.13)
            ['dashboard.demo.certificate_age_days', 'dashboard', 'عمر شهادة العرض عند الزرع (أيّام)', 'number', '5'],
            ['wallet.currency.xp_code', 'wallet', 'كود عملة نقاط الخبرة', 'string', 'xp'],
            ['wallet.currency.tickets_code', 'wallet', 'كود عملة التذاكر', 'string', 'tickets'],
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

        Cache::forget('settings');
    }

    /**
     * **نصوص شاشة اللوحة** (2.13-أ: «النصوص الظاهرة للمستخدم») — كلّ جملةٍ
     * يقرؤها المتدرّب على `resources/views/dashboard/**` لها مفتاحها هنا،
     * والوحدة **جملةٌ كاملة** كما تُقرَأ لا كلمةً مقتطعة، حتى تبقى الترجمة
     * والتحرير ممكنَين. و`:name` وأخواتها **مواضع استبدال** لا نصًّا.
     *
     * ⚠️ `dashboard.page.title` عنوانٌ **منصوصٌ حرفيًّا في الدستور** (24.5 —
     * أوّل عناصر سايد بار المتدرّب «الرئيسيّة»)، فافتراضيُّه هو النصّ المنصوص،
     * وتغييرُه من اللوحة يخالف الخريطة.
     */
    public function screenTextSettings(): void
    {
        $rows = [
            // ---------------- رأس الشاشة و«⋯» (24.5)
            ['dashboard.page.title', 'عنوان صفحة اللوحة (منصوص في 24.5)', 'الرئيسيّة'],
            ['dashboard.page.subtitle', 'سطر تحت عنوان اللوحة', 'أين إنت في تدريباتك دلوقتي'],
            ['dashboard.page.more_actions_aria', 'وصف زرّ «⋯» لقارئ الشاشة', 'أفعال أخرى'],
            ['dashboard.page.more_store', 'عنصر «⋯»: المتجر', 'تصفّح المتجر'],
            ['dashboard.page.more_certificates', 'عنصر «⋯»: الشهادات', 'شهاداتي'],

            // ---------------- تاب «نظرة عامّة»
            ['dashboard.overview.active_courses_title', 'عنوان بلوك التدريبات الجارية', 'تدريباتي الجارية'],
            ['dashboard.overview.all_courses_link', 'رابط كلّ التدريبات', 'كلّ تدريباتي'],
            ['dashboard.overview.courses_empty_message', 'الحالة الفارغة للتدريبات الجارية', 'خلّصت كلّ تدريباتك الجارية — تحفة'],
            ['dashboard.overview.courses_empty_action', 'زرّ الحالة الفارغة للتدريبات الجارية', 'تصفّح المتجر'],
            ['dashboard.overview.deadlines_title', 'عنوان بلوك أقرب المواعيد', 'أقرب المواعيد'],
            ['dashboard.overview.deadlines_empty', 'الحالة الفارغة لأقرب المواعيد', 'مفيش موعد قريب — خُد وقتك.'],
            ['dashboard.overview.deadline_percent', 'نسبة إكمال التدريب في سطر الموعد (:percent)', ':percent% مكتمل'],

            // ---------------- كارت التدريب الجاري
            ['dashboard.course_card.all_lessons_done', 'سطر الكارت حين تنتهي كلّ الدروس', 'خلّصت كلّ الدروس'],
            ['dashboard.course_card.lessons_progress', 'عدّاد دروس الكارت (:done · :total)', ':done/:total درس'],
            ['dashboard.course_card.continue_action', 'زرّ متابعة التدريب في الكارت', 'إكمال'],
            ['dashboard.progress_ring.aria_label', 'وصف حلقة التقدّم لقارئ الشاشة (:percent)', 'نسبة الإكمال :percent٪'],

            // ---------------- عدّاد المواعيد الحيّ (صيغ الجمع العربيّة الأربع · :n مكان الرقم)
            ['dashboard.countdown.minutes_one', 'العدّاد: دقيقة واحدة', 'دقيقة'],
            ['dashboard.countdown.minutes_two', 'العدّاد: دقيقتان', 'دقيقتين'],
            ['dashboard.countdown.minutes_few', 'العدّاد: 3–10 دقائق (:n)', ':n دقائق'],
            ['dashboard.countdown.minutes_many', 'العدّاد: أكثر من 10 دقائق (:n)', ':n دقيقة'],
            ['dashboard.countdown.hours_one', 'العدّاد: ساعة واحدة', 'ساعة'],
            ['dashboard.countdown.hours_two', 'العدّاد: ساعتان', 'ساعتين'],
            ['dashboard.countdown.hours_few', 'العدّاد: 3–10 ساعات (:n)', ':n ساعات'],
            ['dashboard.countdown.hours_many', 'العدّاد: أكثر من 10 ساعات (:n)', ':n ساعة'],
            ['dashboard.countdown.days_one', 'العدّاد: يوم واحد', 'يوم'],
            ['dashboard.countdown.days_two', 'العدّاد: يومان', 'يومين'],
            ['dashboard.countdown.days_few', 'العدّاد: 3–10 أيّام (:n)', ':n أيّام'],
            ['dashboard.countdown.days_many', 'العدّاد: أكثر من 10 أيّام (:n)', ':n يومًا'],
            ['dashboard.countdown.late', 'صيغة العدّاد بعد فوات الموعد (:duration)', 'فات الموعد من :duration'],
            ['dashboard.countdown.remaining', 'صيغة العدّاد قبل الموعد (:duration)', 'باقي :duration'],

            // ---------------- تاب «تفاصيل»
            ['dashboard.details.completed_label', 'كارت التدريبات المكتملة — العنوان', 'تدريبات مكتملة'],
            ['dashboard.details.completed_hint', 'كارت التدريبات المكتملة — التلميح', 'خلّصتها بالكامل'],
            ['dashboard.details.active_label', 'كارت التدريبات الجارية — العنوان', 'تدريبات جارية'],
            ['dashboard.details.active_hint', 'كارت التدريبات الجارية — التلميح', 'لسّه شغّال فيها'],
            ['dashboard.details.rank_label', 'كارت الترتيب — العنوان', 'ترتيبك في الليدر بورد'],
            ['dashboard.details.rank_hint', 'كارت الترتيب — التلميح (:peers)', 'من بين :peers متدرّبًا'],
            ['dashboard.details.title', 'عنوان بلوك تفصيل التقدّم', 'تفصيل تقدّمك'],
            ['dashboard.details.lessons_done', 'تفصيل التقدّم: الدروس المكتملة', 'دروس مكتملة'],
            ['dashboard.details.lessons_total', 'تفصيل التقدّم: إجماليّ الدروس', 'إجمالي دروس تدريباتك'],
            ['dashboard.details.xp_from_courses', 'تفصيل التقدّم: XP من التدريبات', 'XP من التدريبات'],

            // ---------------- تاب «إحصائيّاتي» — الفلتر
            ['dashboard.stats.range_label', 'عنوان فلتر الفترة', 'الفترة'],
            ['dashboard.stats.range_option', 'خيار الفترة (:days)', ':days يوم'],

            // ---------------- رسم XP عبر الزمن
            ['dashboard.chart.xp.title', 'عنوان رسم XP عبر الزمن', 'XP عبر الزمن'],
            ['dashboard.chart.xp.range_total', 'مجموع XP في المدى (:total)', 'مجموع المدى: :total XP'],
            ['dashboard.chart.xp.aria_label', 'وصف رسم XP لقارئ الشاشة', 'نقاط الخبرة المكتسبة يوميًّا خلال المدى المختار'],

            // ---------------- رسم التذاكر
            ['dashboard.chart.tickets.title', 'عنوان رسم التذاكر', 'التذاكر: مكتسب ومصروف'],
            ['dashboard.chart.tickets.aria_label', 'وصف رسم التذاكر لقارئ الشاشة', 'التذاكر المكتسبة مقابل المصروفة خلال المدى المختار'],
            ['dashboard.chart.tickets.earned_tooltip', 'تلميح عمود المكتسب (:label · :value)', ':label — مكتسب: :value'],
            ['dashboard.chart.tickets.spent_tooltip', 'تلميح عمود المصروف (:label · :value)', ':label — مصروف: :value'],
            ['dashboard.chart.tickets.legend_earned', 'مفتاح الرسم: المكتسب', 'مكتسب'],
            ['dashboard.chart.tickets.legend_spent', 'مفتاح الرسم: المصروف', 'مصروف'],

            // ---------------- دونات إكمال المسار
            ['dashboard.chart.completion.title', 'عنوان دونات إكمال المسار', 'إكمال المسار'],
            ['dashboard.chart.completion.aria_label', 'وصف الدونات لقارئ الشاشة (:percent)', 'نسبة إكمال تدريباتك :percent٪'],
            ['dashboard.chart.completion.svg_title', 'عنوان الدونات داخل الرسم (:percent)', 'إكمال المسار — :percent٪'],
            ['dashboard.chart.completion.center_caption', 'سطر منتصف الدونات', 'من الدروس'],

            // ---------------- خريطة الحضور
            ['dashboard.chart.heatmap.title', 'عنوان خريطة الحضور', 'خريطة الحضور'],
            ['dashboard.chart.heatmap.summary', 'ملخّص الخريطة (:present · :club)', ':present يوم حضور · ★ :club في النادي'],
            ['dashboard.chart.heatmap.aria_label', 'وصف الخريطة لقارئ الشاشة', 'خريطة حضورك في الأسابيع الماضية'],
            ['dashboard.chart.heatmap.day_sat', 'اسم يوم السبت على محور الخريطة', 'السبت'],
            ['dashboard.chart.heatmap.day_mon', 'اسم يوم الاثنين على محور الخريطة', 'الاثنين'],
            ['dashboard.chart.heatmap.day_wed', 'اسم يوم الأربعاء على محور الخريطة', 'الأربعاء'],
            ['dashboard.chart.heatmap.day_fri', 'اسم يوم الجمعة على محور الخريطة', 'الجمعة'],
            ['dashboard.chart.heatmap.tooltip_club', 'تلميح يوم نادي الخامسة', 'نادي الخامسة ★'],
            ['dashboard.chart.heatmap.tooltip_present', 'تلميح يوم الحضور', 'حضور ●'],
            ['dashboard.chart.heatmap.tooltip_absent', 'تلميح يوم بلا حضور', 'بلا حضور ○'],
            ['dashboard.chart.heatmap.legend_present', 'مفتاح الخريطة: حضور', 'حضور'],
            ['dashboard.chart.heatmap.legend_club', 'مفتاح الخريطة: نادي الخامسة', 'نادي الخامسة'],
            ['dashboard.chart.heatmap.legend_absent', 'مفتاح الخريطة: بلا حضور', 'بلا حضور'],

            // ---------------- رادار الإنجازات
            ['dashboard.chart.radar.title', 'عنوان رادار الإنجازات', 'مسارات الإنجاز الخمسة'],
            ['dashboard.chart.radar.max_level', 'سقف الرادار المعروض (:level)', 'السقف المعروض: مستوى :level'],
            ['dashboard.chart.radar.aria_label', 'وصف الرادار لقارئ الشاشة', 'مستوياتك في مسارات الإنجاز الخمسة'],
            ['dashboard.chart.radar.svg_title', 'عنوان الرادار داخل الرسم', 'رادار الإنجازات'],
            ['dashboard.chart.radar.axis_tooltip', 'تلميح محور الرادار (:label · :level · :value · :unit)', ':label: مستوى :level — :value :unit'],
            ['dashboard.chart.radar.axis_level', 'مستوى المحور تحت اسمه (:level)', 'مستوى :level'],
        ];

        foreach ($rows as [$key, $label, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => 'dashboard',
                'label_ar' => $label,
                'type' => 'string',
                'default_value' => $default,
                'value' => $default,
            ]);
        }

        Cache::forget('settings');
    }

    // ------------------------------------------------------------ المتدرّب

    private function trainee(): User
    {
        $user = User::withTrashed()->updateOrCreate(
            ['email' => 'salma@demo.local'],
            [
                'name' => 'سلمى عبد الرحمن محمود',
                'password' => 'secret-password',
                'code' => 'UDASH001',
                'status' => 'active',
                'activated_at' => now()->subMonths(4),
                'xp' => 3200,
                'level' => 3,
                'last_seen_at' => now(),
            ],
        );

        if (Role::where('key', 'trainee')->exists()) {
            $user->assignRole('trainee');
        }

        return $user;
    }

    // ------------------------------------------------------------ المحتوى

    /** @return array<string, Course> */
    private function courses(): array
    {
        $blueprints = [
            'أساسيّات العمل التطوّعيّ' => [
                'sections' => [
                    'مقدّمة ومفاهيم' => ['ما معنى التطوّع؟', 'أثر المتطوّع في مجتمعه', 'أخلاقيّات العمل التطوّعيّ'],
                    'المهارات الأساسيّة' => ['إدارة الوقت', 'التواصل مع الفريق', 'حلّ المشكلات'],
                ],
                'xp' => 400,
            ],
            'تصميم واجهات المستخدم' => [
                'sections' => [
                    'أساسيّات التصميم' => ['المسافات والشبكة', 'التدرّج البصريّ', 'نظام الألوان'],
                    'التطبيق العمليّ' => ['تصميم شاشة الدخول', 'تصميم لوحة معلومات'],
                ],
                'xp' => 650,
            ],
            'مهارات العرض والإلقاء' => [
                'sections' => [
                    'التحضير' => ['بناء الرسالة', 'ترتيب الأفكار'],
                    'الوقوف أمام الناس' => ['لغة الجسد', 'إدارة التوتّر', 'التعامل مع الأسئلة'],
                ],
                'xp' => 500,
            ],
            'إدارة المشروعات الصغيرة' => [
                'sections' => [
                    'من الفكرة للخطّة' => ['تحديد الهدف', 'تقسيم العمل', 'الجدول الزمنيّ'],
                ],
                'xp' => 300,
            ],
        ];

        $courses = [];

        foreach ($blueprints as $name => $blueprint) {
            $course = Course::updateOrCreate(
                ['slug' => str()->slug(str()->transliterate($name)) ?: md5($name)],
                [
                    'name_ar' => $name,
                    'description_ar' => 'تدريب عمليّ مختصر في «'.$name.'» بأمثلة من واقع الفرق التطوّعيّة.',
                    'is_free' => true,
                    'xp_max' => $blueprint['xp'],
                    'deadline_days' => 45,
                    'status' => 'published',
                    'published_at' => now()->subMonths(6),
                ],
            );

            $sectionOrder = 0;

            foreach ($blueprint['sections'] as $sectionTitle => $lessons) {
                $section = Section::updateOrCreate(
                    ['course_id' => $course->id, 'title_ar' => $sectionTitle],
                    ['sort_order' => $sectionOrder++],
                );

                foreach (array_values($lessons) as $index => $lessonTitle) {
                    Lesson::updateOrCreate(
                        ['section_id' => $section->id, 'title_ar' => $lessonTitle],
                        [
                            'type' => $index % 3 === 2 ? 'document' : 'video',
                            'duration_minutes' => 7 + $index * 3,
                            'sort_order' => $index,
                        ],
                    );
                }
            }

            Exam::updateOrCreate(
                ['examable_type' => Course::class, 'examable_id' => $course->id],
                // درجة النجاح من الإعداد لا رقمًا محروقًا (2.13 · 4.2)
                ['title_ar' => 'امتحان '.$name, 'duration_minutes' => 30, 'pass_score' => (int) setting('exams.pass_score.default', 70)],
            );

            $courses[$name] = $course;
        }

        return $courses;
    }

    // ------------------------------------------------------------ التسجيلات والتقدّم

    /** @param  array<string, Course>  $courses */
    private function enroll(User $user, array $courses): void
    {
        // اسم التدريب => [نسبة الدروس المكتملة, أيّام حتّى الموعد النهائيّ]
        $plan = [
            'تصميم واجهات المستخدم' => [1.0, 12],
            'أساسيّات العمل التطوّعيّ' => [0.66, 2],      // الموعد قرب ⟵ أصفر
            'مهارات العرض والإلقاء' => [0.4, -1],         // فات الموعد ⟵ أحمر
            'إدارة المشروعات الصغيرة' => [0.0, 26],       // لسّه مابدأش ⟵ أخضر
        ];

        foreach ($plan as $name => [$ratio, $daysLeft]) {
            $course = $courses[$name];

            $lessons = Lesson::query()
                ->join('sections', 'sections.id', '=', 'lessons.section_id')
                ->where('sections.course_id', $course->id)
                ->orderBy('sections.sort_order')
                ->orderBy('lessons.sort_order')
                ->get(['lessons.id']);

            $doneCount = (int) round($lessons->count() * $ratio);
            $xpEarned = (int) round($course->xp_max * $ratio);

            Enrollment::updateOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                [
                    'source' => 'academy',
                    'started_at' => now()->subDays(30),
                    'deadline_at' => now()->addDays($daysLeft),
                    'progress_percent' => (int) round($ratio * 100),
                    'xp_earned' => $xpEarned,
                    'status' => $ratio >= 1 ? 'completed' : 'active',
                ],
            );

            foreach ($lessons->take($doneCount) as $index => $lesson) {
                LessonCompletion::updateOrCreate(
                    ['user_id' => $user->id, 'lesson_id' => $lesson->id],
                    ['completed_at' => now()->subDays(max(1, 26 - $index * 2))],
                );
            }

            if ($ratio >= 1) {
                CourseCompletion::updateOrCreate(
                    ['user_id' => $user->id, 'course_id' => $course->id],
                    ['completed_at' => now()->subDays(6), 'xp_awarded' => $xpEarned],
                );

                $examId = Exam::where('examable_type', Course::class)
                    ->where('examable_id', $course->id)
                    ->value('id');

                if ($examId) {
                    ExamAttempt::updateOrCreate(
                        ['user_id' => $user->id, 'exam_id' => $examId],
                        [
                            'started_at' => now()->subDays(6),
                            'submitted_at' => now()->subDays(6),
                            'score' => 88,
                            'passed' => true,
                            'status' => 'submitted',
                        ],
                    );
                }
            }
        }
    }

    // ------------------------------------------------------------ المحفظة

    private function wallet(User $user): void
    {
        $xp = Currency::where('code', 'xp')->first();
        $tickets = Currency::where('code', 'tickets')->first();
        $coins = Currency::where('code', 'coins')->first();

        if (! $xp || ! $tickets) {
            $this->command?->warn('العملات غير مزروعة — شغّل CoreSeeder أوّلًا.');

            return;
        }

        WalletBalance::updateOrCreate(
            ['user_id' => $user->id, 'currency_id' => $xp->id],
            ['balance' => 3200, 'lifetime_earned' => 3200, 'lifetime_spent' => 0],
        );

        // الرصيد = المكتسب − المصروف: ميزانٌ منغلق، فلا يظهر رقمٌ بلا تفسير (7.1 · 19.2)
        WalletBalance::updateOrCreate(
            ['user_id' => $user->id, 'currency_id' => $tickets->id],
            ['balance' => 18, 'lifetime_earned' => 46, 'lifetime_spent' => 28],
        );

        if ($coins) {
            WalletBalance::updateOrCreate(
                ['user_id' => $user->id, 'currency_id' => $coins->id],
                ['balance' => 120, 'lifetime_earned' => 400, 'lifetime_spent' => 280],
            );
        }

        Transaction::where('user_id', $user->id)->whereIn('source', ['academy', 'challenge', 'purchase'])->delete();

        // XP يوميّ متفاوت على مدى شهر — ليظهر الرسم حيًّا لا خطًّا مسطّحًا
        $xpPattern = [40, 0, 65, 90, 0, 30, 120, 55, 0, 0, 80, 45, 150, 60, 0, 35, 95, 110, 0, 40, 70, 0, 130, 85, 25, 0, 60, 145, 90, 50];

        foreach ($xpPattern as $index => $amount) {
            if ($amount === 0) {
                continue;
            }

            $at = Carbon::today()->subDays(29 - $index)->addHours(19);

            Transaction::create([
                'user_id' => $user->id,
                'currency_id' => $xp->id,
                'amount' => $amount,
                'layer' => 'training',
                'source' => 'academy',
                'reason' => 'إكمال درس',
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        // التذاكر: مكتسب من التحديات ومصروف في المتجر
        $ticketFlow = [[27, 5, 'challenge'], [24, -3, 'purchase'], [19, 4, 'challenge'], [14, 6, 'challenge'], [11, -2, 'purchase'], [6, 3, 'challenge'], [3, -4, 'purchase'], [1, 2, 'challenge']];

        foreach ($ticketFlow as [$daysAgo, $amount, $source]) {
            $at = Carbon::today()->subDays($daysAgo)->addHours(12);

            Transaction::create([
                'user_id' => $user->id,
                'currency_id' => $tickets->id,
                'amount' => $amount,
                'layer' => 'training',
                'source' => $source,
                'reason' => $amount > 0 ? 'مكافأة تحدٍّ' : 'شراء من المتجر',
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        /*
         | ⭐ العمود المخبَّأ يُشتقّ من **المصدر الواحد** (10.1) لا من عتبات الجدول
         | ولا من رقمٍ مكتوب بيد — فالمستوى المعروض في السايد بار والـKPI والرادار
         | وهيدر البروفايل رقمٌ واحد منذ لحظة الزرع (ن-2).
         */
        app(LevelResolver::class)->sync($user->refresh());
    }

    // ------------------------------------------------------------ الستريك والحضور

    private function streak(User $user): void
    {
        $clubDays = 0;

        // حضور مبعثر بشكل واقعيّ على 12 أسبوعًا، ونادي الخامسة في أيّام مختارة
        for ($day = 0; $day < 84; $day++) {
            if ($day % 7 === 5) {
                continue; // إجازة أسبوعيّة
            }

            if ($day > 20 && in_array($day % 10, [3, 8], true)) {
                continue; // أيّام انقطاع متفرّقة
            }

            $club = $day % 4 === 1;
            $clubDays += $club ? 1 : 0;

            StreakDay::updateOrCreate(
                ['user_id' => $user->id, 'day' => Carbon::today()->subDays($day)->toDateString()],
                ['club_5am' => $club],
            );
        }

        Streak::updateOrCreate(
            ['user_id' => $user->id],
            ['current_days' => 9, 'best_days' => 23, 'last_active_date' => today(), 'club_5am_count' => $clubDays],
        );
    }

    // ------------------------------------------------------------ الشهادة

    /**
     * ⭐⭐ **شهادة العرض تُصدَر بمحرّك الإصدار — لا ببصمةٍ مخترَعة** (8.1 · 12.5-هـ).
     *
     * كان الصفّ يُكتَب بيده: `'hash' => hash('sha256', $code)` — **بصمةُ محتوًى
     * بلا مفتاح** يقدر أيّ أحدٍ يعرف الكود أن ينتجها، و`template_snapshot` و
     * `data_snapshot` **فارغتان**. والنتيجة أنّ صفحة التحقّق العامّة — وهي التي
     * يَعِد الدستور بأنّها «تتيح للجهات والشركات التحقّق من **صحّة** وصلاحيّة أيّ
     * شهادة» (8.1) — تسم شهادة اللوحة بـ«**التوقيع لا يطابق**». وأوّل شهادةٍ
     * يراها المجرِّب مطعونٌ في صحّتها، فيبدو المحرّك معطوبًا وهو سليم.
     *
     * والعلاج ليس بصمةً «أصحّ» تُكتَب هنا: `CertificateIssuer` هو **المسار
     * المعتمَد الوحيد** — يجمّد لقطة القالب ولقطة البيانات ثمّ يوقّع بـ
     * `CertificateSignature` بمفتاح التطبيق **بعد** اكتمالهما. فمصدر التوقيع
     * واحدٌ للإصدار والتحقّق، ولا نسخةَ ثانية تنحرف عن الأولى.
     *
     * ⏳ والزمن يُثبَّت قبل النداء لا بعده: التوقيع يغطّي `issued_at`، فتعديلُ
     * التاريخ بعد الإصدار يكسر التوقيع الذي وُقِّع للتوّ. فتُزرَع الشهادة
     * **بتاريخها** في لحظةٍ واحدة، ويعود الزمن كما كان.
     */
    private function certificate(User $user, Course $course): void
    {
        $type = CertificateType::where('key', 'course')->first();

        if (! $type || Certificate::where('user_id', $user->id)->where('certificate_type_id', $type->id)->exists()) {
            return;
        }

        $issuedAt = Carbon::now()->subDays((int) setting('dashboard.demo.certificate_age_days', 5));

        // ولا نمسح ساعةً مزوَّرة لغيرنا: نعيدها كما كانت لا إلى «الآن الحقيقيّ»
        $previous = Carbon::getTestNow();

        Carbon::setTestNow($issuedAt);

        try {
            app(CertificateIssuer::class)->issue(
                user: $user,
                typeKey: (string) $type->key,
                subject: $course,
                source: 'auto',
                language: 'ar',
            );
        } finally {
            Carbon::setTestNow($previous);
        }
    }
}
