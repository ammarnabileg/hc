<?php

namespace App\Services\Ui;

use App\Models\Task;
use App\Models\User;
use App\Services\Account\UserSearch;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * فهرس البحث الموحّد (Ctrl+K) — 2.15-د:
 * «حقل واحد يصل إلى أيّ **صفحة** أو **شخص** أو **مهمّة** بالكتابة، بديلًا عن
 * التنقّل في السايد بار».
 *
 * ⭐ والصفحة التي لا يملك المستخدم صلاحيّتها **لا تظهر في النتائج أصلًا** —
 *   لا معطَّلة ولا رماديّة (2.15-أ-7)، فالبحث لا يكشف ما لا يُرى.
 */
class CommandIndex
{
    /**
     * خريطة الصفحات: [اسم المسار => [العنوان, الصلاحيّة|null]].
     * ولماذا في الكود لا في قاعدة البيانات؟ لأنّها **خريطة السايد بار نفسها**
     * (12.0 · 24.5)، وأيّ تفريع لها يخلق مصدرَي حقيقة.
     *
     * @return array<string, array{0:string,1:string|array<int,string>|null,2?:string}>
     */
    public function pages(): array
    {
        return [
            // المتدرّب (24.5)
            'dashboard' => [setting('ux.command_index.pages_1', 'الرئيسيّة'), null],
            'announcements.index' => [setting('ux.command_index.pages_2', 'التعليمات'), null],
            'learning.courses' => [setting('ux.command_index.pages_3', 'تدريباتي'), null],
            'learning.paths' => [setting('ux.command_index.pages_4', 'المسارات'), null],
            'learning.certificates' => [setting('ux.command_index.pages_5', 'شهاداتي'), null],
            'library.index' => [setting('ux.command_index.pages_6', 'مكتبتي'), null],
            'store.index' => [setting('ux.command_index.pages_7', 'المتجر'), null],
            'wallet.index' => [setting('ux.command_index.pages_8', 'المحفظة'), null],
            'wallet.tickets' => [setting('ux.command_index.pages_9', 'التذاكر'), null],
            'challenges.index' => [setting('ux.command_index.pages_10', 'التحديات'), null],
            'achievements.leaderboard' => [setting('ux.command_index.pages_11', 'الليدر بورد'), null],
            'achievements.badges' => [setting('ux.command_index.pages_12', 'الشارات'), null],
            'achievements.streak' => [setting('ux.command_index.pages_13', 'الستريك ونادي الخامسة'), null],
            'events.index' => [setting('ux.command_index.pages_14', 'الفعاليّات'), null],
            'cv.index' => [setting('ux.command_index.pages_15', 'السيرة الذاتيّة'), 'user_cv.view'],
            'attestations.index' => [setting('ux.command_index.pages_16', 'الإفادة'), 'user_attestation.view'],
            'referral.index' => [setting('ux.command_index.pages_17', 'ادعُ أصدقاءك'), null],
            'complaints.index' => [setting('ux.command_index.pages_18', 'الشكاوى والمقترحات'), 'complaints.view'],
            'help.index' => [setting('ux.command_index.pages_19', 'دليل المستخدم'), null],
            'profile.me' => [setting('ux.command_index.pages_20', 'بروفايلي'), 'user_profile.view'],
            'settings.index' => [setting('ux.command_index.pages_21', 'الإعدادات'), 'user_profile.edit'],
            'settings.privacy' => [setting('ux.command_index.pages_22', 'الخصوصيّة والأمان'), 'privacy_settings.view'],

            // التطوّع (24.4): مرآة سايد بار التطوّع نفسه (13.4-ح) — مفاتيحه ونصوصه حرفًا بحرف
            ...$this->volunteerPages(),

            // الإدارة (12.0)
            // باب اللوحة قدرةٌ محسوبة لا صلاحيّة باسم شاشة (12.2.1-أ)
            'admin.dashboard' => [setting('ux.command_index.pages_24', 'لوحة القيادة'), 'admin-panel'],
            'admin.users.index' => [setting('ux.command_index.pages_25', 'قائمة المستخدمين'), 'users.list'],
            'admin.roles.index' => [setting('ux.command_index.pages_26', 'الأدوار والصلاحيّات'), 'roles.list'],
            'admin.paths.index' => [setting('ux.command_index.pages_27', 'المسارات (إدارة)'), 'paths.list'],
            'admin.courses.index' => [setting('ux.command_index.pages_28', 'التدريبات (إدارة)'), 'courses.list'],
            'admin.media.index' => [setting('ux.command_index.pages_29', 'مكتبة الوسائط'), 'media_library.list'],
            'admin.certificates.index' => [setting('ux.command_index.pages_30', 'الشهادات (إدارة)'), 'certificate_ledger.list'],
            'admin.volunteer.index' => [setting('ux.command_index.pages_31', 'إدارة التطوّع'), 'memberships.list'],
            'admin.gamification.index' => [setting('ux.command_index.pages_32', 'التلعيب والتحديات'), 'badges.list'],
            'admin.store.index' => [setting('ux.command_index.pages_33', 'المتجر (إدارة)'), 'store_products.list'],
            'admin.topups.index' => [setting('ux.command_index.pages_34', 'طلبات الشحن'), 'topup_requests.list'],
            'admin.articles.index' => [setting('ux.command_index.pages_35', 'المقالات'), 'articles.list'],
            'admin.ads.index' => [setting('ux.command_index.pages_36', 'الإعلان المدفوع'), 'ad_audiences.view'],
            'admin.studio.index' => [setting('ux.command_index.pages_37', 'استوديو الصور'), 'image_templates.list'],
            'admin.rewards.index' => [setting('ux.command_index.pages_38', 'إدارة المكافآت'), 'manual_rewards.list'],
            'admin.events.index' => [setting('ux.command_index.pages_39', 'الفعاليّات (إدارة)'), 'events.list'],
            'admin.guidance.index' => [setting('ux.command_index.pages_40', 'التوجيه والدعم'), 'announcements.list'],
            'admin.stats.index' => [setting('ux.command_index.pages_41', 'الإحصائيّات'), 'reports_users.list'],
            'admin.finance.index' => [setting('ux.command_index.pages_42', '🔒 الماليّات'), 'finance.view'],
            'admin.settings.index' => [setting('ux.command_index.pages_43', 'الإعدادات والنظام'), 'settings_general.view'],
        ];
    }

    /**
     * صفحات لوحة التطوّع — **نفس أسطر** `partials/sidebar-volunteer` بنصوصها
     * (`nav.volunteer.item_*`) ومفاتيحها، فلا تُفهرَس شاشةٌ لا يصلها صاحبها من
     * السايد بار ولا تغيب شاشةٌ يصلها. والمفتاح مصفوفةٌ حين يقبل المسار أيًّا منها.
     *
     * ⛔ قبلها: البحث الموحّد لم يعرف من التطوّع إلّا «لوحة التطوّع»، فكتابة
     * «مهامي» أو «الأهداف» في Ctrl+K كانت تنتهي إلى «مفيش نتيجة».
     *
     * @return array<string, array{0:string,1:string|array<int,string>|null,2:string}>
     */
    public function volunteerPages(): array
    {
        $rows = [
            [setting('nav.volunteer.group_overview', 'نظرة عامّة'), [
                ['volunteer.overview', setting('nav.volunteer.item_overview_journey', 'رحلتي في التطوّع'), 'personal_reports.view'],
                ['volunteer.report', setting('nav.volunteer.item_overview_report', 'تقريري الأسبوعيّ'), 'personal_reports.view'],
                ['volunteer.calendar', setting('nav.volunteer.item_overview_calendar', 'تقويم نشاطي'), 'calendar.view'],
            ]],
            [setting('nav.volunteer.group_tasks', 'المهام'), [
                ['volunteer.tasks.index', setting('nav.volunteer.item_tasks_mine', 'مهامّي'), 'tasks.list'],
                ['volunteer.tasks.board', setting('nav.volunteer.item_tasks_board', 'لوحة المهام العامّة'), 'public_board.list'],
                ['volunteer.contributions', setting('nav.volunteer.item_tasks_contributions', 'مساهماتي'), 'contributions.list'],
                ['volunteer.reviews', setting('nav.volunteer.item_tasks_reviews', 'بانتظار مراجعتي'), ['tasks.approve', 'contributions.approve']],
            ]],
            [setting('nav.volunteer.group_goals', 'المشاريع والأهداف'), [
                ['volunteer.goals', setting('nav.volunteer.item_goals_index', 'الأهداف والمَعالِم'), ['goals.list', 'goals.view']],
                ['volunteer.goals.build', setting('nav.volunteer.item_goals_build', 'بناء الأهداف'), ['goals.create', 'milestones.create']],
                ['volunteer.goals.launch', setting('nav.volunteer.item_goals_launch', 'إطلاق الهدف'), 'goals.approve'],
                ['volunteer.packages', setting('nav.volunteer.item_goals_packages', 'حزم العمل وبنودها'), ['work_packages.list', 'work_packages.view']],
                ['volunteer.project', setting('nav.volunteer.item_goals_project', 'المشروع التشغيليّ'), 'operational_projects.view'],
                ['volunteer.recurring', setting('nav.volunteer.item_goals_recurring', 'البنود المتكرّرة'), 'recurring_items.view'],
            ]],
            [setting('nav.volunteer.group_performance', 'الأداء'), [
                ['volunteer.performance.vxp', setting('nav.volunteer.item_performance_vxp', 'VXP وترتيبي'), 'leaderboards.view'],
                ['volunteer.performance.rep', setting('nav.volunteer.item_performance_rep', 'درجة الالتزام (Rep)'), 'rep_transactions.view'],
                ['volunteer.performance.champion', setting('nav.volunteer.item_performance_champion', 'مشرف الشهر'), 'leaderboards.view'],
                ['volunteer.performance.evaluations', setting('nav.volunteer.item_performance_evaluations', 'تقييماتي (مؤشّر القيادة)'), 'evaluations.view'],
            ]],
            [setting('nav.volunteer.group_meetings', 'الاجتماعات'), [
                ['volunteer.meetings', setting('nav.volunteer.item_meetings_index', 'القادمة والمنتهية'), ['meetings.list', 'meetings.view']],
                ['volunteer.attendance', setting('nav.volunteer.item_meetings_attendance', 'حضوري والمحاضر'), ['meeting_attendance.view', 'meeting_minutes.view']],
            ]],
            [setting('nav.volunteer.group_transactions', 'المعاملات'), [
                ['volunteer.transactions', setting('nav.volunteer.item_transactions_index', 'معاملاتي (Rep/VXP)'), ['rep_transactions.list', 'rep_transactions.view']],
                ['volunteer.objections', setting('nav.volunteer.item_transactions_objections', 'اعتراضاتي'), ['objections.view', 'objections.list']],
            ]],
            [setting('nav.volunteer.group_department', 'قسمي'), [
                ['volunteer.department', setting('nav.volunteer.item_department_members', 'الأعضاء والبوزشنز'), 'org_chart.view'],
                ['volunteer.org', setting('nav.volunteer.item_department_org', 'الهيكل التنظيميّ'), 'org_chart.view'],
                ['volunteer.health', setting('nav.volunteer.item_department_health', 'صحّة القسم'), 'team_health.view'],
                ['volunteer.capacity', setting('nav.volunteer.item_department_capacity', 'السعة والأحمال'), 'capacity.view'],
            ]],
            [setting('nav.volunteer.group_escalations', 'التصعيدات'), [
                ['volunteer.escalations', setting('nav.volunteer.item_escalations_index', 'يحتاج قرارك'), 'escalations.list'],
                ['volunteer.escalations.objections', setting('nav.volunteer.item_escalations_objections', 'الاعتراضات المصعَّدة'), 'objections.list'],
                ['volunteer.arbitrations', setting('nav.volunteer.item_escalations_arbitrations', 'التحكيمات'), 'arbitration.list'],
            ]],
            [setting('nav.volunteer.group_academy', 'الأكاديمية'), [
                ['volunteer.academy', setting('nav.volunteer.item_academy_paths', 'التدريبات'), ['academy_paths.list', 'academy_paths.view']],
                ['volunteer.academy.recordings', setting('nav.volunteer.item_academy_recordings', 'التسجيلات'), ['academy_recordings.list', 'academy_recordings.view']],
            ]],
            [setting('nav.volunteer.group_recognition', 'التقدير'), [
                ['volunteer.kudos', setting('nav.volunteer.item_recognition_kudos', 'Kudos'), 'kudos.view'],
                ['volunteer.kudos.wall', setting('nav.volunteer.item_recognition_wall', 'حائط الشكر (نادي +9.5)'), 'thanks_wall.view'],
            ]],
            [setting('nav.volunteer.group_recruitment', 'التوظيف'), [
                ['volunteer.recruitment', setting('nav.volunteer.item_recruitment_candidates', 'المرشّحون (كانبان)'), 'candidates.list'],
                ['volunteer.interviews', setting('nav.volunteer.item_recruitment_interviews', 'المقابلات والـScorecards'), ['interviews.list', 'interviews.view']],
                ['volunteer.placement', setting('nav.volunteer.item_recruitment_placement', 'القوائم والتسكين'), 'placements.list'],
            ]],
        ];

        $pages = [];

        foreach ($rows as [$groupLabel, $items]) {
            foreach ($items as [$route, $label, $permission]) {
                $pages[$route] = [$label, $permission, $groupLabel];
            }
        }

        return $pages;
    }

    /**
     * ⭐ تطبيع عربيّ للمطابقة: تُسقَط الحركات والتطويل وتُوحَّد الهمزات والياء
     * والتاء المربوطة، فـ«الاهداف» تجد «الأهداف والمَعالِم» و«مهامي» تجد «مهامّي».
     * الحركات في النصوص المعروضة زينةٌ للقراءة لا شرطٌ على الكاتب.
     */
    public static function normalize(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ئ' => 'ي', 'ؤ' => 'و']);

        return Str::lower(trim($text));
    }

    /**
     * نتائج البحث الموحّد.
     *
     * @return array{pages:array,people:array,tasks:array}
     */
    public function search(User $viewer, string $term): array
    {
        $term = trim($term);
        $limit = max(3, (int) setting('ux.palette.limit_per_group', 5));

        return [
            'pages' => $this->matchPages($viewer, $term, $limit),
            'people' => $term === '' ? [] : $this->matchPeople($viewer, $term, $limit),
            'tasks' => $term === '' ? [] : $this->matchTasks($viewer, $term, $limit),
        ];
    }

    // ------------------------------------------------------------------ داخليّ

    private function matchPages(User $viewer, string $term, int $limit): array
    {
        $results = [];
        $needle = self::normalize($term);
        $gate = Gate::forUser($viewer);

        foreach ($this->pages() as $route => $row) {
            [$label, $permission] = $row;
            $group = $row[2] ?? null;

            if (! Route::has($route)) {
                continue;
            }

            // ما لا يملكه لا يظهر له أصلًا — والمصفوفة تعني: يكفي أيٌّ منها (كالسايد بار)
            if ($permission !== null && ! collect((array) $permission)->contains(fn (string $key) => $gate->allows($key))) {
                continue;
            }

            if ($needle !== '' && ! Str::contains(self::normalize($label.' '.($group ?? '').' '.$route), $needle)) {
                continue;
            }

            $results[] = [
                'label' => $label,
                'url' => route($route),
                'hint' => $group ?? setting('ux.command_index.match_pages_1', 'صفحة'),
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    private function matchPeople(User $viewer, string $term, int $limit): array
    {
        /*
         | ⚠️ كان الشرط `و` لا `أو`: فلا يخرج فارغًا إلّا مَن جمع فقدانَ المفتاح
         | **وعدمَ التفعيل معًا** — أي أنّ غير المفعَّل كان يبحث في الناس من لوحة
         | الأوامر. والشرطان مستقلّان: مفعَّلٌ **و**يملك مفتاح البحث (13.1 · 24.5).
         */
        if (! $viewer->isActive() || ! Gate::forUser($viewer)->allows(UserSearch::PAGE_PERMISSION)) {
            return [];
        }

        // نستعمل محرّك البحث القائم (13.1) — بنطاق الباحث نفسه وبلا بيانات حسّاسة
        return app(UserSearch::class)
            ->results($viewer, $term, ['name', 'code'], 0, $limit)
            ->map(fn (User $user) => [
                'label' => $user->shortName(),
                'url' => route('u.profile', ['code' => $user->code]),
                'hint' => '#'.$user->code,
            ])
            ->values()
            ->all();
    }

    private function matchTasks(User $viewer, string $term, int $limit): array
    {
        if (! Gate::forUser($viewer)->allows('tasks.view') || ! Route::has('volunteer.tasks.show')) {
            return [];
        }

        // مهامّ المستخدم وحده — البحث الموحّد اختصار تنقّل لا نافذة على مهامّ غيره
        return Task::query()
            ->where('title', 'like', '%'.trim($term).'%')
            ->where(fn ($q) => $q->where('owner_id', $viewer->id)->orWhere('created_by', $viewer->id))
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'title'])
            ->map(fn (Task $task) => [
                'label' => $task->title,
                'url' => route('volunteer.tasks.show', ['task' => $task->id]),
                'hint' => setting('ux.command_index.match_tasks_1', 'مهمّة'),
            ])
            ->values()
            ->all();
    }
}
