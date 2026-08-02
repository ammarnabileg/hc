<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Access\PermissionExpander;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ربط الأدوار العشرين بصلاحيّاتها (12.2.3).
 * القاعدة: الدور = تجميعة قدرات، و«manage» تُفرَد ظاهرةً عند الحفظ.
 */
class RolePermissionSeeder extends Seeder
{
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
            'gamification_admin' => ['badges', 'streaks', 'five_am_club', 'streak_freeze', 'leaderboards', 'public_leaderboard', 'achievements', 'games', 'tickets_xp', 'xp_rules', 'tickets_rules', 'wars_settings', 'war_types', 'wars_bank', 'wars_matches', 'reward_questions', 'celebrations', 'positive_messages'],
            'tech_admin' => ['settings_general', 'settings_audit', 'maintenance', 'backups', 'system_health', 'updates', 'error_logs', 'scheduled_jobs', 'storage_files', 'audit_logs', 'feature_toggles', 'integrations', 'email_templates', 'rate_limits', 'localization', 'countries_data', 'password_policy', 'version_history', 'setup_installer'],
        ];

        foreach ($map as $roleKey => $resources) {
            $this->grantResources($roleKey, $resources, 'ALL', $expander);
        }

        // ---------------- المدقّق: قراءة فقط على كلّ غير الحسّاس
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

        foreach ($scaleByRole as $roleKey => $scope) {
            $this->grantResources($roleKey, $volunteerResources, $scope, $expander);
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
        $this->grantResources('academy_manager', ['academy_paths', 'academy_recordings', 'otp_verification', 'paths', 'courses'], 'TRACK', $expander);

        // ---------------- المتدرّب: ما يخصّه هو فقط
        $this->grantResources('trainee', [
            'user_dashboard', 'user_profile', 'privacy_settings', 'user_sessions', 'data_export', 'emergency_contact',
            'enrollments', 'progress', 'lessons', 'lesson_quiz', 'lesson_attachments', 'course_notes', 'course_exam',
            'video_comments',
            'courses', 'paths', 'certificates', 'certificate_verification', 'my_library', 'flip_reader',
            'wallet', 'purchases', 'orders', 'invoices', 'topup', 'transfer', 'cart', 'store_products', 'bundles',
            'badges', 'streaks', 'five_am_club', 'achievements', 'games', 'leaderboards', 'public_leaderboard',
            'war_participation', 'events', 'event_registrations', 'event_attendance',
            'referrals', 'friend_invite', 'invitations_page', 'user_cv', 'cv_templates', 'user_attestation',
            'complaints', 'user_guide', 'announcements', 'announcement_ack', 'notifications',
            'user_search', 'contact_consent', 'calendar', 'personal_reports', 'share_links', 'volunteer_page',
            'war_participation', 'wars_matches', 'invitations_page', 'friend_invite', 'streak_freeze',
        ], 'SELF', $expander);

        /*
         | موارد عامّة بطبيعتها (كتالوج · ليدر بورد · تحقّق) لا تُعرَّف إلّا بنطاق ALL،
         | فمنحُها للمتدرّب بنطاق SELF كان يُسقِطها ويُعطيه 403 على صفحات عامّة.
         */
        $this->grantResources('trainee', [
            'events', 'games', 'courses', 'paths', 'store_products', 'bundles',
            'product_categories', 'leaderboards', 'public_leaderboard', 'badges',
            'achievements', 'certificate_verification', 'user_guide', 'public_pages',
        ], 'ALL', $expander);

        // ---------------- تحت المراجعة: قراءة محدودة جدًّا
        $this->grantResources('pending_review', ['user_guide', 'announcements', 'notifications', 'user_profile'], 'SELF', $expander);

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

    private function grantResources(string $roleKey, array $resources, string $scope, PermissionExpander $expander): void
    {
        $role = Role::where('key', $roleKey)->first();

        if (! $role) {
            return;
        }

        $permissions = Permission::query()
            ->whereIn('resource', $resources)
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

    /** أوسع نطاق مسموح لا يتجاوز المطلوب */
    private function resolveScope(string $requested, array $allowed): ?string
    {
        $order = config('access.scopes');

        if ($allowed === []) {
            return $requested;
        }

        $max = array_search($requested, $order, true);
        $candidates = array_filter($allowed, fn ($s) => array_search($s, $order, true) !== false
            && array_search($s, $order, true) <= $max);

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => array_search($b, $order, true) <=> array_search($a, $order, true));

        return $candidates[0];
    }

    private function insertRows(int $roleId, array $permissionIds, string $scope): void
    {
        foreach (array_chunk($permissionIds, 400) as $chunk) {
            $payload = array_map(fn ($id) => [
                'role_id' => $roleId,
                'permission_id' => $id,
                'scope' => $scope,
                'effect' => 'allow',
                'conditions' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ], $chunk);

            DB::table('permission_role')->upsert($payload, ['role_id', 'permission_id', 'scope'], ['effect', 'updated_at']);
        }
    }
}
