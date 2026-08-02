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
            'content_admin' => ['paths', 'courses', 'sections', 'lessons', 'lesson_questions', 'media', 'exams', 'exam_questions', 'enrollments'],
            'certificates_admin' => ['certificates', 'certificate_types', 'certificate_templates', 'accreditations', 'certificate_verification'],
            'support_admin' => ['users', 'accounts', 'complaints', 'help_articles', 'announcements'],
            'marketing_admin' => ['products', 'bundles', 'coupons', 'articles', 'image_templates', 'events', 'referrals', 'acquisition_sources'],
            'finance_admin' => ['orders', 'invoices', 'topup_requests', 'wallets', 'transactions'],
            'gamification_admin' => ['badges', 'levels', 'streaks', 'leaderboard', 'challenges', 'tickets', 'xp'],
            'tech_admin' => ['settings', 'maintenance', 'backups', 'system_health', 'updates', 'audit_logs', 'feature_flags'],
        ];

        foreach ($map as $roleKey => $resources) {
            $this->grantResources($roleKey, $resources, 'ALL', $expander);
        }

        // ---------------- المدقّق: قراءة فقط على كلّ غير الحسّاس
        $this->grantReadOnly('auditor');

        // ---------------- أدوار التطوّع: نفس الموارد بنطاقات متدرّجة
        $volunteerResources = [
            'tasks', 'subtasks', 'contributions', 'checkpoints', 'submissions', 'reviews',
            'meetings', 'attendance', 'objections', 'escalations', 'arbitrations',
            'goals', 'milestones', 'work_packages', 'work_items', 'projects',
            'memberships', 'entities', 'positions', 'internal_library', 'kudos',
            'rep', 'vxp', 'behavior', 'evaluations', 'volunteers', 'volunteer_cards',
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

        $this->grantResources('recruiter', ['candidates', 'interviews', 'scorecards', 'placements', 'recruitment'], 'ALL', $expander);
        $this->grantResources('academy_manager', ['academy', 'recordings', 'paths', 'courses'], 'TRACK', $expander);

        // ---------------- المتدرّب: ما يخصّه هو فقط
        $this->grantResources('trainee', ['enrollments', 'lessons', 'exams', 'certificates', 'wallets', 'transactions', 'orders', 'library', 'cv', 'complaints', 'events', 'challenges'], 'SELF', $expander);

        // ---------------- تحت المراجعة: قراءة محدودة جدًّا
        $this->grantResources('pending_review', ['help_articles', 'announcements'], 'SELF', $expander);

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
