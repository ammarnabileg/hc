<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Project;
use App\Models\RepScore;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskTodo;
use App\Models\TaskType;
use App\Models\Track;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use App\Services\Volunteer\Tasks\TaskStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بيانات تجريبيّة لمجال «لوحة التطوّع: النظرة العامّة والمهام» (24.4 · 23).
 *
 * ولا يُسجَّل هذا السيدر في `DatabaseSeeder` — يُجمَّع مع باقي المجالات لاحقًا.
 */
class VolunteerCoreDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->permissionsForVolunteerRoles();

        $entity = $this->entity();
        $item = $this->workItem($entity);
        $types = $this->taskTypes();

        [$leader, $coordinator] = $this->people($entity);

        $this->tasks($entity, $item, $types, $leader, $coordinator);

        $this->command?->info('بيانات لوحة التطوّع التجريبيّة: '.Task::count().' مهمّة.');
    }

    /** إعدادات المجال (2.13) — كلّ رقم في الكود مصدره هنا */
    private function settings(): void
    {
        $rows = [
            ['workflow.task_cap.personal_extra_percent', 'workflow', 'الزيادة على أعلى سقف دور للسقف الشخصيّ الكلّي (%)', 'number', '50'],
            ['workflow.merge_window_hours', 'workflow', 'نافذة الدمج والتسليم للأب (ساعات)', 'number', '24'],
            ['workflow.extension.max_per_task', 'workflow', 'أقصى عدد طلبات تمديد للمهمّة', 'number', '1'],
            ['workflow.blocked.max_retries', 'workflow', 'أقصى مرّات تعثّر للمهمّة', 'number', '2'],
            ['workflow.blocked.second_block_reward_multiplier', 'workflow', 'معامل مكافأة Rep بعد التعثّر الثاني', 'number', '0.5'],
            ['workflow.deadline.soon_hours', 'workflow', 'العدّاد يصير أصفر قبل الديدلاين بـ(ساعات)', 'number', '24'],
            ['workflow.deadline.soon_days', 'workflow', 'مدى «تقترب ديدلايناتها» (أيّام)', 'number', '3'],
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
     * أدوار التطوّع تملك موارد المهامّ، لكنّ شاشات هذا المجال تقرأ موارد إضافيّة
     * (اللوحة العامّة · التودو · التقويم · التقرير الشخصيّ) — فنمنحها بنفس نطاق الدور.
     */
    private function permissionsForVolunteerRoles(): void
    {
        $scopeByRole = [
            'volunteer_gm' => 'ALL',
            'track_supervisor' => 'TRACK',
            'director' => 'ENTITY',
            'supervisor' => 'SUBTREE',
            'team_leader' => 'TEAM',
            'coordinator' => 'SELF',
        ];

        $keys = [
            'public_board.view', 'public_board.list',
            'todos.create', 'todos.view', 'todos.edit', 'todos.delete',
            'calendar.view', 'personal_reports.view',
            'task_comments.view', 'task_comments.create',
        ];

        $permissionIds = Permission::query()->whereIn('key', $keys)->pluck('id')->all();

        foreach ($scopeByRole as $roleKey => $scope) {
            $role = Role::query()->where('key', $roleKey)->first();

            if (! $role || $permissionIds === []) {
                continue;
            }

            $rows = array_map(fn ($id) => [
                'role_id' => $role->id,
                'permission_id' => $id,
                'scope' => $scope,
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ], $permissionIds);

            DB::table('permission_role')->upsert($rows, ['role_id', 'permission_id', 'scope'], ['effect', 'updated_at']);
        }
    }

    private function entity(): Entity
    {
        $track = Track::firstOrCreate(['key' => 'department'], ['name_ar' => 'قسم', 'name_en' => 'Department']);

        return Entity::firstOrCreate(
            ['track_id' => $track->id, 'name_ar' => 'قسم المحتوى'],
            ['icon' => '🎬', 'status' => 'active', 'opened_at' => now()->subYear()],
        );
    }

    private function workItem(Entity $entity): WorkItem
    {
        $project = Project::firstOrCreate(
            ['entity_id' => $entity->id, 'name' => 'المشروع التشغيليّ لقسم المحتوى'],
            ['type' => 'operational', 'is_permanent' => true, 'status' => 'active'],
        );

        $package = WorkPackage::firstOrCreate(
            ['project_id' => $project->id, 'name' => 'حزمة محتوى الموسم'],
            ['entity_id' => $entity->id],
        );

        return WorkItem::firstOrCreate(
            ['work_package_id' => $package->id, 'name' => 'سكربتات الريلز'],
            ['brief' => 'سكربتات قصيرة لمنصّات التواصل', 'deliverable_spec' => 'ملفّ Google Doc بصيغة السكربت المعتمدة', 'vxp_pool' => 500],
        );
    }

    /** @return array<string, TaskType> */
    private function taskTypes(): array
    {
        $rows = [
            ['execution', 'تنفيذ', '⚙️'],
            ['content', 'محتوى', '✍️'],
            ['design', 'تصميم', '🎨'],
            ['followup', 'متابعة', '🔁'],
        ];

        $types = [];

        foreach ($rows as [$key, $label, $icon]) {
            $types[$key] = TaskType::firstOrCreate(['key' => $key], [
                'name_ar' => $label,
                'icon' => $icon,
                'is_active' => true,
            ]);
        }

        return $types;
    }

    /** @return array{0: User, 1: User} */
    private function people(Entity $entity): array
    {
        $leaderPosition = Position::where('key', 'team_leader')->first();
        $coordinatorPosition = Position::where('key', 'coordinator')->first();

        $leader = $this->user('سلمى عبد الرحمن', 'salma.demo@volunteer.local', 'VOL001', 6.5);
        $coordinator = $this->user('يوسف مصطفى', 'youssef.demo@volunteer.local', 'VOL002', -2.25);

        $leaderMembership = Membership::firstOrCreate(
            ['user_id' => $leader->id, 'entity_id' => $entity->id],
            [
                'position_id' => $leaderPosition?->id,
                'is_primary' => true,
                'started_at' => now()->subMonths(14),
                'status' => 'active',
            ],
        );

        Membership::firstOrCreate(
            ['user_id' => $coordinator->id, 'entity_id' => $entity->id],
            [
                'position_id' => $coordinatorPosition?->id,
                'upline_id' => $leaderMembership->id,
                'is_primary' => true,
                'started_at' => now()->subMonths(3),
                'status' => 'active',
            ],
        );

        foreach ([$leader, $coordinator] as $user) {
            $roleKey = $user->is($leader) ? 'team_leader' : 'coordinator';
            $role = Role::where('key', $roleKey)->first();

            if ($role) {
                $user->assignRole($role);
            }
        }

        return [$leader, $coordinator];
    }

    private function user(string $name, string $email, string $code, float $rep): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => 'password',
            'code' => $code,
            'status' => 'active',
            'activated_at' => now(),
        ]);

        RepScore::updateOrCreate(['user_id' => $user->id], ['score' => $rep]);

        return $user;
    }

    private function tasks(Entity $entity, WorkItem $item, array $types, User $leader, User $coordinator): void
    {
        $rows = [
            ['اكتب سكربت ريل «المستوى الرابع»', $coordinator, $leader, 'content', TaskStatus::IN_PROGRESS, now()->addDays(2), 40],
            ['صمّم كوفر حلقة البودكاست', $coordinator, $leader, 'design', TaskStatus::BLOCKED, now()->addDays(4), 35],
            ['راجع خطّة نشر الأسبوع', $leader, $leader, 'followup', TaskStatus::DELIVERED, now()->addDay(), 25],
            ['جهّز تقرير أداء المحتوى', $leader, $leader, 'execution', TaskStatus::APPROVED, now()->subDays(3), 60],
        ];

        foreach ($rows as [$title, $owner, $reviewer, $type, $status, $deadline, $vxp]) {
            $task = Task::firstOrCreate(
                ['title' => $title],
                [
                    'task_type_id' => $types[$type]->id,
                    'work_item_id' => $item->id,
                    'entity_id' => $entity->id,
                    'owner_id' => $owner->id,
                    'reviewer_id' => $reviewer->id,
                    'created_by' => $reviewer->id,
                    'brief' => 'بريف تجريبيّ يشرح المطلوب باختصار.',
                    'deliverable_spec' => 'رابط ملفّ على درايف بصلاحيّة اطّلاع للفريق.',
                    'vxp_value' => $vxp,
                    'deadline_at' => $deadline,
                    'status' => $status,
                    'delivered_at' => in_array($status, [TaskStatus::DELIVERED, TaskStatus::APPROVED], true) ? now()->subDay() : null,
                    'approved_at' => $status === TaskStatus::APPROVED ? now()->subHours(6) : null,
                    'blocked_count' => $status === TaskStatus::BLOCKED ? 1 : 0,
                ],
            );

            if ($task->wasRecentlyCreated && $status === TaskStatus::IN_PROGRESS) {
                foreach (['اقرأ البريف كويّس', 'اكتب المسوّدة الأولى', 'راجع النبرة'] as $index => $body) {
                    TaskTodo::create([
                        'task_id' => $task->id,
                        'body' => $body,
                        'is_done' => $index === 0,
                        'sort_order' => $index,
                    ]);
                }
            }
        }

        // مهامّ عامّة على اللوحة — بلا مالك حتى يسحبها متطوّع
        foreach ([
            ['غطِّ فعاليّة السبت بالتصوير', 'design', 45, 3],
            ['فرّغ محضر اجتماع القسم', 'execution', 20, 5],
        ] as [$title, $type, $vxp, $days]) {
            Task::firstOrCreate(['title' => $title], [
                'task_type_id' => $types[$type]->id,
                'work_item_id' => $item->id,
                'entity_id' => $entity->id,
                'reviewer_id' => $leader->id,
                'created_by' => $leader->id,
                'brief' => 'مهمّة عامّة يسحبها مَن عنده مساحة في سقف انشغاله.',
                'deliverable_spec' => 'ملفّ نهائيّ + رابط مشاركة مفتوح للفريق.',
                'vxp_value' => $vxp,
                'deadline_at' => now()->addDays($days),
                'status' => TaskStatus::IN_PROGRESS,
                'source' => 'public_board',
            ]);
        }
    }
}
