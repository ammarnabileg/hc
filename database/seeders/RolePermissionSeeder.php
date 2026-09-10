<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Access\PermissionExpander;
use Database\Seeders\Concerns\GrantsWithinMatrixCeiling;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ربط الأدوار العشرين بصلاحيّاتها (12.2.3).
 * القاعدة: الدور = تجميعة قدرات، و«manage» تُفرَد ظاهرةً عند الحفظ.
 */
class RolePermissionSeeder extends Seeder
{
    /*
     | ⭐ نقطة القصّ **مشتركة** الآن مع سيدرات العرض: كانت هنا وحدها فصار مسار
     | الإنتاج صفرًا، وبقيت سيدرات العرض تكتب من بابٍ خلفيّ فوق السقف.
     | والحكم واحدٌ لا حكمان — وإلّا لعاد الخرق مع أوّل `DemoSeeder`.
     */
    use GrantsWithinMatrixCeiling;

    public function run(): void
    {
        $expander = app(PermissionExpander::class);

        // ---------------- مالك المنصّة: كلّ شيء بنطاق ALL (بما فيه الحسّاس)
        $this->grantAll('platform_owner');

        // ---------------- أدمن عامّ: كلّ شيء عدا ما هو لمالك المنصّة وحده
        $this->grantAll('super_admin', excludeOwnerOnly: true);

        // ---------------- أدوار المنصّة المتخصّصة: بمجموعات الموارد
        $map = [
            'content_admin' => ['paths', 'courses', 'sections', 'lessons', 'lesson_quiz', 'lesson_attachments', 'media_library', 'content_categories', 'course_exam', 'question_bank', 'general_questions', 'enrollments', 'progress', 'learning_ux', 'path_page_layout', 'availability', 'deadlines', 'video_comments'],
            'certificates_admin' => ['certificates', 'certificate_templates', 'certificate_ledger', 'certificate_verification', 'accreditations', 'reports_certificates'],
            'support_admin' => ['users', 'user_approvals', 'user_profile', 'admin_user_detail', 'complaints', 'user_guide', 'announcements', 'announcement_ack', 'notifications', 'user_search', 'account_suspension', 'user_sessions'],
            'marketing_admin' => ['store_products', 'product_categories', 'product_protection', 'bundles', 'coupons', 'order_bump', 'pricing', 'paywall', 'landing_pages', 'public_pages', 'share_links', 'referrals', 'ambassadors', 'invitations_page', 'events', 'event_registrations', 'event_recordings'],
            'finance_admin' => ['orders', 'invoices', 'purchases', 'topup', 'transfer', 'wallet', 'currencies', 'exchange_rates', 'refunds', 'reports_sales', 'reports_finance'],
            'gamification_admin' => ['badges', 'streaks', 'five_am_club', 'streak_freeze', 'leaderboards', 'public_leaderboard', 'achievements', 'tickets_xp', 'xp_rules', 'tickets_rules', 'wars_settings', 'war_types', 'wars_bank', 'wars_matches', 'reward_questions', 'celebrations', 'positive_messages'],
            'tech_admin' => ['settings_general', 'settings_audit', 'maintenance', 'backups', 'system_health', 'updates', 'error_logs', 'scheduled_jobs', 'storage_files', 'audit_logs', 'feature_toggles', 'integrations', 'email_templates', 'rate_limits', 'localization', 'countries_data', 'password_policy', 'version_history', 'setup_installer'],
        ];

        foreach ($map as $roleKey => $resources) {
            $this->grantResources($roleKey, $resources, 'ALL', $expander);
        }

        // ---------------- المدقّق: قراءة فقط على كلّ غير المعزول
        $this->grantReadOnly('auditor');

        // ---------------- أدوار التطوّع: نفس الموارد بنطاقات متدرّجة
        $volunteerResources = [
            'tasks', 'subtasks', 'todos', 'task_notes', 'task_comments', 'task_types', 'assignments',
            'contributions', 'checkpoints', 'dependencies', 'public_board', 'recurring_items',
            'meetings', 'meeting_attendance', 'meeting_minutes', 'meeting_posts',
            'objections', 'escalations', 'arbitration', 'delegations',
            'goals', 'milestones', 'work_packages', 'wp_items', 'operational_projects',
            'memberships', 'departments', 'sub_departments', 'positions',
            'vacancies', 'promotion_ladder', 'internal_library', 'library_access_requests',
            'kudos', 'thanks_wall', 'evaluations', 'supervisor_log', 'personal_reports',
            'rep_transactions', 'vxp_transactions', 'volunteer_certificates', 'calendar',
            'public_board', 'personal_reports', 'task_comments', 'progress',
        ];

        $scaleByRole = [
            'volunteer_gm' => 'ALL',
            'track_supervisor' => 'TRACK',
            'director' => 'ENTITY',
            'supervisor' => 'SUBTREE',
            'team_leader' => 'TEAM',
            'coordinator' => 'SELF',
        ];

        /*
         | ⭐⭐ **فتحُ «غائب» محصورٌ بثلاثة بوزشنات — نصًّا** (23 — القسم 6).
         |
         | النصّ الحاكم حرفيًّا: «**مَن يضيفه:** **مشرف عام التطوّع** أو **مشرف
         | المسار** أو **دايركتور الكيان** — لا الشخص نفسه (منعًا للتهرّب)».
         |
         | وكان المورد `delegations` يُمنَح لكلّ أدوار التطوّع بنطاقاتها، فيأخذ
         | **السوبرفايزر** `delegations.create@SUBTREE` و**التيم ليدر**
         | `delegations.create@TEAM` — وكلاهما خارج الثلاثة. وقائمةُ البوزشنات في
         | مسار الإضافة كانت تردّهما فعلًا، لكنّ **صلاحيّةً أوسع من سندها ثغرةٌ
         | تنتظر حارسًا يسقط**: يكفي أن يُضاف مسارٌ ثانٍ للإضافة أو تُنسى القائمة
         | فتنفتح القدرة بلا نصّ.
         |
         | ⚠️ والحصر على **`create` وحدها**: 23-6 يقيّد «مَن **يضيفه**» لا مَن
         | يراه. و`delegations.list` («استعراض حالات «غائب» والبدلاء المفوَّضين»)
         | و`delegations.view` تبقيان لكلّ الأدوار كما تسمح 12.2.2 — فالوسم يظهر
         | «معلَّمًا في الهيكل والبروفايل … فيعرف الجميع لمن يرجعون» (23-6).
         | ولا يُسحَب من الثلاثة شيء: `volunteer_gm@ALL` و`track_supervisor@TRACK`
         | و`director@ENTITY` يأخذون المورد كاملًا.
         */
        $absenceAdders = ['volunteer_gm', 'track_supervisor', 'director'];

        foreach ($scaleByRole as $roleKey => $scope) {
            $this->grantResources(
                $roleKey,
                $volunteerResources,
                $scope,
                $expander,
                except: in_array($roleKey, $absenceAdders, true) ? [] : ['delegations.create'],
            );
        }

        /*
         | ⭐ صاحب البروفايل نفسه لا يُحجَب عن شاشات إعداداته وخصوصيّته: أدوار
         | طبقة التطوّع الثمانية كانت تُمنَح موارد التطوّع وحدها لا موارد الحساب
         | الشخصيّة — فمتطوّعٌ يحمل واحدًا منها فقط (بلا `trainee` معه) كان يصطدم
         | بـ403 على `/settings` و`/settings/privacy`، رغم أنّ 13.4-م يبني كلّ
         | آليّة الموافقة على أنّ صاحب الحقل يضبط خصوصيّته بنفسه — فالحارس هنا
         | كان يقفل الباب على صاحب البيت. المنح بنطاق **SELF** فقط: هو نفسه
         | سقف المصفوفة الذي يأخذه `trainee` أصلًا لهذه الموارد بعينها (12.2.2)،
         | فلا تضييقَ ولا توسيعًا فوق ما تُتيحه — إضافةٌ محضة.
         */
        $selfAccountResources = ['user_profile', 'privacy_settings', 'emergency_contact', 'user_sessions', 'data_export', 'contact_consent'];

        foreach ([...array_keys($scaleByRole), 'recruiter', 'academy_manager'] as $roleKey) {
            $this->grantResources($roleKey, $selfAccountResources, 'SELF', $expander);
        }

        /*
         | الرقابة (صحّة القسم · السعة · الهيكل) لسوبرفايزر فأعلى —
         | فالكوردنيتور والتيم ليدر لا يريان مؤشّرات الفريق ولا مخاطر الفقدان (24.4).
         */
        foreach (['volunteer_gm' => 'ALL', 'track_supervisor' => 'TRACK', 'director' => 'ENTITY', 'supervisor' => 'SUBTREE'] as $roleKey => $scope) {
            $this->grantResources($roleKey, ['org_chart', 'capacity', 'team_health', 'retention_risk'], $scope, $expander);
        }

        // والهيكل وحده (بلا رقابة) يراه التيم ليدر والكوردنيتور
        $this->grantResources('team_leader', ['org_chart'], 'TEAM', $expander);
        $this->grantResources('coordinator', ['org_chart'], 'SELF', $expander);

        $this->grantResources('recruiter', ['candidates', 'interviews', 'scorecards', 'scorecard_criteria', 'shortlists', 'placements', 'placement_test', 'recruitment_analytics', 'vacancies', 'qualifying_path'], 'ALL', $expander);

        // معايير مؤشّر القيادة (13.4-ن-د · 24 — التاب 3): «يحدّدها الأدمن» — نفس مستوى بنود الإدارة المركزيّة
        $this->grantResources('volunteer_gm', ['leadership_criteria'], 'ALL', $expander);

        /*
         | لجنة التحقيق (23-0.2-4): تفعيل الملفّ وتعيين المقعدين والقرار
         | النهائيّ والأرشفة — **حصريّة مشرف عام التطوّع** بنصّ الدستور. أمّا
         | المطالعة وتسجيل قرار الميتينج فلمَن قد يقع عليه مقعد قسم المتطوّعين
         | فعلًا (تيم ليدر فأعلى — عتبة الترشّح المنصوصة)، وإلّا فلن يصل
         | العضوَ المعيَّن آليًّا شاشةٌ يرى فيها ملفّه أصلًا.
         */
        $this->grantResources('volunteer_gm', ['investigations'], 'ALL', $expander);

        // المطالعة وتسجيل القرار — بسقف نطاق دوره (12.2.3-ب)، وأضيق ما تسمح به المصفوفة عند تجاوزه (12.2.2)
        $investigationSeatKeys = ['investigations.view', 'investigations.edit'];

        foreach (['track_supervisor' => 'TRACK', 'director' => 'ENTITY', 'supervisor' => 'SUBTREE', 'team_leader' => 'TEAM'] as $roleKey => $scope) {
            $this->grantKeys($roleKey, $investigationSeatKeys, $scope, matrixFloor: true);
        }

        // 12.2.3-ب-18: «`academy_paths · academy_recordings` **داخل قسمه**» — النطاق ENTITY لا TRACK
        $this->grantResources('academy_manager', ['academy_paths', 'academy_recordings', 'otp_verification', 'paths', 'courses'], 'ENTITY', $expander);

        /*
         | ⭐⭐ **الأكاديمية تصل جمهورها المنصوص** (13.4-ل).
         |
         | النصّ: «**التسجيلات/المسارات تظهر للمتطوّع حسب قسمه**». وكان لا دور من
         | أدوار البوزشنز يحمل `academy_paths.*` ولا `academy_recordings.*` —
         | فالحاملون `super_admin` و`auditor` و`academy_manager` وحدهم، و
         | `/volunteer/academy` **403** للكوردنيتور والمحرّك سليم خلف بابٍ مقفول.
         |
         | والمنح **قراءةً فقط** (`view · list`): 13.4-ل يجعل الإضافة والتعديل
         | لـ«مسؤول القسم بصلاحيّة» — وهو قالب 12.2.3-ب-18 — لا لكلّ متطوّع.
         | والنطاق **أضيق ما تسمح به المصفوفة** (12.2.2) في حدود سقف الدور
         | (12.2.3) — لا أوسع.
         */
        $academyReadKeys = [
            'academy_paths.view', 'academy_paths.list',
            'academy_recordings.view', 'academy_recordings.list',
        ];

        foreach ($scaleByRole as $roleKey => $scope) {
            $this->grantKeys($roleKey, $academyReadKeys, $scope, matrixFloor: true);
        }

        // ---------------- المتدرّب: ما يخصّه هو فقط
        $this->grantResources('trainee', [
            'user_dashboard', 'user_profile', 'privacy_settings', 'user_sessions', 'data_export', 'emergency_contact',
            'enrollments', 'progress', 'lessons', 'lesson_quiz', 'lesson_attachments', 'course_notes', 'course_exam',
            'video_comments',
            'courses', 'paths', 'certificates', 'certificate_verification', 'my_library', 'flip_reader',
            'wallet', 'purchases', 'orders', 'invoices', 'topup', 'transfer', 'cart', 'store_products', 'bundles',
            // أرباحه هو ومسحوباته هو (19.2/19.3) — أمّا الاعتماد والرفض وتقارير
            // الأرباح على مستوى المنصّة فتبقى معزولة لمالك المنصّة وحده.
            'withdraw', 'earnings',
            'badges', 'streaks', 'five_am_club', 'achievements', 'leaderboards', 'public_leaderboard',
            'war_participation', 'events', 'event_registrations', 'event_attendance',
            'referrals', 'friend_invite', 'invitations_page', 'user_cv', 'cv_templates', 'user_attestation',
            'complaints', 'user_guide', 'announcements', 'announcement_ack', 'notifications',
            // ⛔ `user_search` ليست هنا: نطاقها المسموح **ALL** وحده، فطلبها بـSELF
            //    يُسقطها صامتةً — ومكانها الصحيح في منح المفاتيح العامّة أدناه.
            'contact_consent', 'calendar', 'personal_reports', 'share_links', 'volunteer_page',
            'war_participation', 'wars_matches', 'invitations_page', 'friend_invite', 'streak_freeze',
        ], 'SELF', $expander, except: [
            /*
             | ⭐ مفاتيح **تحرس شاشات إدارة** ولا يحتاجها المتدرّب في أيّ صفحةٍ له
             | (صفحاته العامّة غير محروسة بها أصلًا). وحيازته إيّاها — ولو بنطاق
             | SELF — كانت تفتح له تلك الشاشات، لأنّ حارس المسار يفحص «هل يستطيع
             | مبدئيًّا؟» بلا هدف فيمرّ نطاق SELF. أخطرها `certificates.export`:
             | تصدير CSV بأسماء وأكواد كلّ حاملي الشهادات.
             */
            'certificates.export',
            'referrals.list',
            'event_attendance.create',
            'announcements.view', 'announcements.list', 'announcements.export',
            'user_guide.view', 'user_guide.list', 'user_guide.export',
        ]);

        /*
         | ⭐ الصفحات العامّة: **مفاتيح بعينها** لا موارد كاملة.
         |
         | كان السطر يمنح المتدرّب **الموردَ كلّه بنطاق ALL** بحجّة أنّ الكتالوج عامّ،
         | فكان يأخذ معه `courses.create` و`paths.delete` و`badges.delete` و
         | `store_products.edit` و`user_guide.delete` — و**مسارات الإدارة لهذه
         | الموارد محروسةٌ بهذه المفاتيح نفسها**، فصار أيّ متدرّبٍ يفتح
         | `admin/courses/create` و`admin/paths` ويحذف. المنح الآن **مفتاحًا مفتاحًا**
         | وبأفعال القراءة وحدها — وهذا هو معنى «تغطية كاملة» في 12.2: تغطيةٌ
         | بالتحديد لا بالجملة.
         */
        $this->grantKeys('trainee', [
            'achievements.view', 'badges.view', 'events.view',
            'leaderboards.view', 'public_leaderboard.view',
            'store_products.list', 'product_categories.view', 'bundles.view',
            'certificate_verification.view', 'public_pages.view',

            /*
             | ⭐ صفحة البحث الكبيرة (13.1 · 24.5): «مَن يراها: **كلّ مستخدم
             | مفعَّل**»، ونطاقها المسموح في المصفوفة **ALL وحده** (12.2.2).
             | وكان طلبها ضمن موارد المتدرّب بنطاق SELF فتسقط كلّها بلا نطاقٍ
             | مسموح — فيبقى المسار مفتوحًا بلا حارس لأنّ لا أحد يملك مفتاحه.
             */
            'user_search.view', 'user_search.list',
        ], 'ALL');

        // ---------------- تحت المراجعة: قراءة محدودة جدًّا
        $this->grantKeys('pending_review', [
            'notifications.view', 'notifications.list',
            'user_profile.view', 'user_profile.edit',
        ], 'SELF');

        $count = DB::table('permission_role')->count();
        $this->command?->info("أسطر ربط الأدوار بالصلاحيّات: {$count}");
    }

    private function grantAll(string $roleKey, bool $excludeOwnerOnly = false): void
    {
        $role = Role::where('key', $roleKey)->first();

        if (! $role) {
            return;
        }

        $query = Permission::query();

        if ($excludeOwnerOnly) {
            $query->where('is_owner_only', false);
        }

        $this->insertRows($role->id, $query->pluck('id')->all(), 'ALL');
    }

    private function grantReadOnly(string $roleKey): void
    {
        $role = Role::where('key', $roleKey)->first();

        if (! $role) {
            return;
        }

        $ids = Permission::query()
            ->whereIn('action', ['view', 'list', 'export'])
            ->where('is_owner_only', false)
            ->pluck('id')
            ->all();

        $this->insertRows($role->id, $ids, 'ALL');
    }

    /**
     * @param  array<int, string>  $except  مفاتيح تُستثنى بالاسم من منح المورد
     */
    private function grantResources(string $roleKey, array $resources, string $scope, PermissionExpander $expander, array $except = []): void
    {
        $role = Role::where('key', $roleKey)->first();

        if (! $role) {
            return;
        }

        $permissions = Permission::query()
            ->whereIn('resource', $resources)
            ->when($except !== [], fn ($q) => $q->whereNotIn('key', $except))
            ->where('is_owner_only', false)
            ->get();

        $rows = [];

        foreach ($permissions as $permission) {
            $allowed = $permission->allowed_scopes ?: [];
            // النطاق الفعليّ: المطلوب إن كان مسموحًا، وإلّا فأقرب نطاق مسموح أضيق منه
            $effective = $this->resolveScope($scope, $allowed);

            if ($effective === null) {
                continue;
            }

            $rows[] = ['id' => $permission->id, 'scope' => $effective];
        }

        foreach (collect($rows)->groupBy('scope') as $groupScope => $group) {
            $this->insertRows($role->id, collect($group)->pluck('id')->all(), (string) $groupScope);
        }
    }

    /**
     * منح **مفاتيح بعينها** لا موردًا كاملًا — لصفحات المستخدم النهائيّ العامّة.
     *
     * @param  array<int, string>  $keys
     * @param  bool  $matrixFloor  حين يكون **كلّ** ما تسمح به المصفوفة أوسعَ من سقف
     *                             الدور، يُمنَح المفتاح بـ**أضيق** نطاقٍ تسمح به بدل
     *                             أن يسقط. ولا يتناقض هذا مع «لا أوسع»: المصفوفة
     *                             (12.2.2) تحدّد ما **يمكن التعبير عنه** أصلًا لهذا
     *                             المفتاح، فمفتاحٌ لا SELF في نطاقاته لا يُكتَب SELF
     *                             بحال. والبديل — إسقاطه — يخالف 12.2.3 و13.4-ل
     *                             معًا ويترك الشاشة مقفولةً في وجه أصحابها.
     */
    private function grantKeys(string $roleKey, array $keys, string $scope, bool $matrixFloor = false): void
    {
        $role = Role::where('key', $roleKey)->first();

        if (! $role) {
            return;
        }

        $permissions = Permission::query()
            ->whereIn('key', $keys)
            ->where('is_owner_only', false)
            ->get();

        $byScope = [];

        foreach ($permissions as $permission) {
            $allowed = $permission->allowed_scopes ?: [];
            $effective = $this->resolveScope($scope, $allowed) ?? ($matrixFloor ? $this->narrowest($allowed) : null);

            if ($effective === null) {
                $this->command?->warn("مفتاح سقط لتعذّر النطاق: {$permission->key} ({$scope}) — نطاقاته: ".implode(' · ', $allowed));

                continue;
            }

            $byScope[$effective][] = $permission->id;
        }

        foreach ($byScope as $effective => $ids) {
            $this->insertRows($role->id, $ids, (string) $effective);
        }
    }
}
