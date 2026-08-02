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
use App\Models\Level;
use App\Models\Role;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Streak;
use App\Models\StreakDay;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
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
    private function settings(): void
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
            ['dashboard.achievements.radar_max_level', 'dashboard', 'سقف الرادار المعروض (مستوى)', 'number', '10'],
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
                    'xp_before_half' => $blueprint['xp'],
                    'xp_after_half' => (int) round($blueprint['xp'] / 2),
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
                ['title_ar' => 'امتحان '.$name, 'duration_minutes' => 30, 'pass_score' => 60],
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
            $xpEarned = (int) round($course->xp_before_half * $ratio);

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

        if (Level::count() > 0) {
            $level = Level::where('min_xp', '<=', 3200)->orderByDesc('min_xp')->first();
            $user->forceFill(['level' => (int) ($level->level ?? 1)])->save();
        }
    }

    // ------------------------------------------------------------ الستريك والحضور

    private function streak(User $user): void
    {
        Streak::updateOrCreate(
            ['user_id' => $user->id],
            ['current_days' => 9, 'best_days' => 23, 'last_active_date' => today(), 'club_5am_count' => 14],
        );

        // حضور مبعثر بشكل واقعيّ على 12 أسبوعًا، ونادي الخامسة في أيّام مختارة
        for ($day = 0; $day < 84; $day++) {
            $date = Carbon::today()->subDays($day);

            if ($day % 7 === 5) {
                continue; // إجازة أسبوعيّة
            }

            if ($day > 20 && $day % 3 === 0) {
                continue;
            }

            StreakDay::updateOrCreate(
                ['user_id' => $user->id, 'day' => $date->toDateString()],
                ['club_5am' => $day % 6 === 0],
            );
        }
    }

    // ------------------------------------------------------------ الشهادة

    private function certificate(User $user, Course $course): void
    {
        $type = CertificateType::where('key', 'course')->first();

        if (! $type) {
            return;
        }

        Certificate::updateOrCreate(
            ['code' => 'CRS-DASH-0001'],
            [
                'hash' => hash('sha256', 'CRS-DASH-0001'),
                'user_id' => $user->id,
                'certificate_type_id' => $type->id,
                'subject_type' => Course::class,
                'subject_id' => $course->id,
                'language' => 'ar',
                'issued_at' => now()->subDays(5),
                'status' => 'valid',
            ],
        );
    }
}
