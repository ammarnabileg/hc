<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\Goal;
use App\Models\LeadershipCriterion;
use App\Models\Membership;
use App\Models\Milestone;
use App\Models\Position;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use App\Services\Volunteer\Goals\Integrations;
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\Goals\RollupService;
use App\Services\Volunteer\Goals\VxpDistributionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * بيانات تجريبيّة لمجال «المشاريع والأهداف والأداء» (24.4 · 23 · 13.4-ن).
 *
 * ⚠️ لا يُسجَّل في `DatabaseSeeder` — التجميع يتمّ لاحقًا (دليل البناء 7).
 * ويبدأ بإضافة **إعدادات المجال** لأنّ أيّ رقم في الكود ممنوع (2.13).
 */
class VolunteerGoalsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->criteria();

        $entity = $this->entity();
        $people = $this->people($entity);

        $goal = $this->goalTree($entity, $people);
        $this->operationalProject($entity, $people);
        $this->performance($people);

        app(RollupService::class)->recalcGoal($goal);

        $this->command?->info('بيانات مجال الأهداف والأداء جاهزة — الهدف: '.$goal->name);
    }

    // ------------------------------------------------------------------ الإعدادات

    /** إعدادات المجال بنمط «المجال.الميزة.المفتاح» ولكلّ واحد قيمة افتراضيّة (2.13) */
    public function settings(): void
    {
        $rows = [
            // الصعود الآليّ للنِّسَب — المُغلَقة مستبعَدة من المقام
            ['goals.rollup.done_statuses', 'goals', 'حالات «تمّ» التي ترفع النسبة', 'json', '["approved"]'],
            ['goals.rollup.excluded_statuses', 'goals', 'الحالات المستبعَدة من مقام النسبة', 'json', '["closed"]'],
            ['goals.visible_statuses', 'goals', 'حالات الهدف الظاهرة للتنفيذ', 'json', '["sent_to_execution","active","completed","closed"]'],
            ['goals.deadline.soon_hours', 'goals', 'عتبة «اقترب» للعدّاد الملوّن (ساعات)', 'number', '48'],
            ['goals.verification.approval_hours', 'goals', 'نافذة اعتماد إعلان تحقّق المعيار (ساعات)', 'number', '24'],
            ['goals.objection.window_hours', 'goals', 'مهلة الاعتراض على نسخة الاعتماد (ساعات)', 'number', '24'],
            // شاشة إطلاق الهدف — المعاينة النهائيّة و«إرسال للتنفيذ» (23 — 1.5)
            ['goals.launch.rows', 'goals', 'عدد الأهداف المعروضة في شاشة الإطلاق', 'number', '20'],

            // البنود المتكرّرة والموازن
            ['recurring.default_relative_deadline_hours', 'goals', 'الديدلاين النسبيّ الافتراضيّ (ساعات)', 'number', '24'],
            ['recurring.load.open_statuses', 'goals', 'حالات المهامّ المحتسَبة في الحمل', 'json', '["in_progress","blocked","in_review","returned"]'],
            ['recurring.load.sources', 'goals', 'مصادر المهامّ الداخلة في الحمل (بلا مساهمات)', 'json', '["assigned","public_board","recurring"]'],
            ['recurring.history.rows', 'goals', 'عدد أسطر سجلّ التوليدات في البوب-أب', 'number', '10'],

            // الأداء
            ['performance.vxp.curve_days', 'performance', 'أيّام منحنى تراكم VXP', 'number', '30'],
            ['performance.rep.curve_days', 'performance', 'أيّام منحنى Rep اليوميّ', 'number', '30'],
            ['performance.rep.how_to_earn_groups', 'performance', 'مجموعات كارت «كيف تكسب»', 'json', '["tasks","meetings","academy","leadership"]'],

            // مشرف الشهر
            ['champion.window_days', 'performance', 'نافذة حساب مشرف الشهر (أيّام)', 'number', '30'],
            ['champion.update_hour_cairo', 'performance', 'ساعة تحديث مشرف الشهر بتوقيت القاهرة', 'number', '5'],
            ['champion.candidates_count', 'performance', 'عدد المرشّحين المعروضين', 'number', '10'],

            // مؤشّر القيادة
            ['evaluations.min_raters', 'performance', 'عتبة المقيّمين لإظهار المتوسّط', 'number', '3'],
            ['evaluations.window_weeks', 'performance', 'نافذة عرض التقييمات (أسابيع)', 'number', '12'],
            ['evaluations.max_score', 'performance', 'أقصى درجة للمنزلق', 'number', '10'],
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

    /** معايير مؤشّر القيادة — والمعيار المؤرشف يبقى بدرجته بوسم */
    private function criteria(): void
    {
        $rows = [
            ['clarity', 'وضوح التوجيه', 1],
            ['support', 'الدعم عند التعثّر', 1],
            ['fairness', 'العدل في التوزيع', 1],
            ['responsiveness', 'سرعة الردّ في المهل', 1],
        ];

        foreach ($rows as $i => [$key, $label, $weight]) {
            LeadershipCriterion::updateOrCreate(['key' => $key], [
                'label_ar' => $label, 'weight' => $weight, 'sort_order' => $i,
            ]);
        }
    }

    // ------------------------------------------------------------------ الكيان والناس

    private function entity(): Entity
    {
        $track = Track::query()->where('key', 'department')->firstOrFail();

        return Entity::updateOrCreate(
            ['name_ar' => 'قسم الإعلام'],
            ['track_id' => $track->id, 'status' => 'active', 'opened_at' => now()->subMonths(8)],
        );
    }

    /** @return array<string,User> */
    private function people(Entity $entity): array
    {
        $rows = [
            'director' => ['سلمى عبد الرحمن', 'director'],
            'supervisor' => ['كريم مصطفى', 'supervisor'],
            'leader' => ['هبة السيّد', 'team_leader'],
            'coordinator_a' => ['يوسف عادل', 'coordinator'],
            'coordinator_b' => ['ندى إبراهيم', 'coordinator'],
        ];

        $users = [];
        $memberships = [];

        foreach ($rows as $key => [$name, $positionKey]) {
            $user = User::updateOrCreate(
                ['email' => $key.'@volunteer.local'],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'code' => 'V'.str_pad((string) (crc32($key) % 9999), 4, '0', STR_PAD_LEFT),
                    'status' => 'active',
                ],
            );

            $users[$key] = $user;
        }

        $chain = [
            'director' => null,
            'supervisor' => 'director',
            'leader' => 'supervisor',
            'coordinator_a' => 'leader',
            'coordinator_b' => 'leader',
        ];

        foreach ($rows as $key => [$name, $positionKey]) {
            $memberships[$key] = Membership::updateOrCreate(
                ['user_id' => $users[$key]->id, 'entity_id' => $entity->id],
                [
                    'position_id' => Position::query()->where('key', $positionKey)->value('id'),
                    'upline_id' => $chain[$key] ? ($memberships[$chain[$key]]->id ?? null) : null,
                    'is_primary' => true,
                    'started_at' => now()->subMonths(6),
                    'status' => 'active',
                ],
            );
        }

        return $users;
    }

    // ------------------------------------------------------------------ شجرة الهدف

    private function goalTree(Entity $entity, array $people): Goal
    {
        $goal = Goal::updateOrCreate(
            ['name' => 'رفع وعي 20 ألف شاب بمهارات التطوّع'],
            [
                'description' => 'حملة إعلاميّة وتدريبيّة تغطّي ثلاث محافظات خلال الموسم.',
                'verification_type' => 'numeric',
                'target_from' => 0,
                'target_to' => 20000,
                'end_date' => now()->addMonths(3)->toDateString(),
                'priority' => 1,
                // ⭐ الهدف ظاهر لأنّه أُرسِل للتنفيذ فعلًا
                'status' => 'sent_to_execution',
                'sent_to_execution_at' => now()->subWeeks(3),
                'created_by' => $people['director']->id,
            ],
        );

        // هدف ثانٍ ما زال في البناء — **ولا يظهر في أيّ شاشة** (23 — القسم 1)
        Goal::updateOrCreate(
            ['name' => 'مسودّة: توسّع الملفّات الموسميّة'],
            [
                'verification_type' => 'boolean',
                'status' => 'draft',
                'end_date' => now()->addMonths(6)->toDateString(),
                'created_by' => $people['director']->id,
            ],
        );

        $milestones = [
            ['إنتاج المحتوى المرئيّ', 'نشر 12 فيديو معتمدًا', true],
            ['إطلاق الحملة الميدانيّة', 'تغطية 3 محافظات', false],
        ];

        $first = null;

        foreach ($milestones as $i => [$name, $criteria, $verified]) {
            $milestone = Milestone::updateOrCreate(
                ['goal_id' => $goal->id, 'name' => $name],
                [
                    'verification_criteria' => $criteria,
                    'due_date' => now()->addWeeks(4 + $i * 4)->toDateString(),
                    'sort_order' => $i,
                ],
            );

            $first ??= $milestone;

            $package = WorkPackage::updateOrCreate(
                ['milestone_id' => $milestone->id, 'name' => 'حزمة '.$name],
                [
                    'entity_id' => $entity->id,
                    'sort_order' => $i,
                    // فرق النسخة ومهلة الاعتراض 24 ساعة — والسكوت قبول (23 — 1.6)
                    'submitted_snapshot' => json_encode(['الاسم' => 'حزمة '.$name, 'قيمة VXP' => 120], JSON_UNESCAPED_UNICODE),
                    'approved_snapshot' => json_encode(['الاسم' => 'حزمة '.$name, 'قيمة VXP' => 100], JSON_UNESCAPED_UNICODE),
                    'objection_due_at' => now()->addHours((int) setting('goals.objection.window_hours', 24)),
                    'objection_status' => 'open',
                ],
            );

            $this->packageItems($package, $entity, $people, $i);
        }

        return $goal;
    }

    private function packageItems(WorkPackage $package, Entity $entity, array $people, int $index): void
    {
        $items = [
            ['كتابة السكربتات', 'سكربت لكلّ فيديو بمراجعة لغويّة', 300],
            ['المونتاج والإخراج', 'فيديو 60 ثانية بصيغة رأسيّة', 400],
        ];

        foreach ($items as $j => [$name, $spec, $pool]) {
            $item = WorkItem::updateOrCreate(
                ['work_package_id' => $package->id, 'name' => $name],
                [
                    'brief' => 'بريف ثابت: '.$name.' حسب دليل الهويّة.',
                    'deliverable_spec' => $spec,
                    'vxp_pool' => $pool,
                ],
            );

            $this->itemTasks($item, $entity, $people, $index + $j);
        }
    }

    private function itemTasks(WorkItem $item, Entity $entity, array $people, int $seed): void
    {
        // خليط مقصود: معتمدة · قيد التنفيذ · **مُغلَقة** (لتظهر قاعدة الاستبعاد من المقام)
        $rows = [
            ['approved', $people['coordinator_a'], -6, 120],
            ['approved', $people['coordinator_b'], -4, 100],
            ['in_progress', $people['leader'], 5, 90],
            ['closed', $people['coordinator_a'], -2, 0],
        ];

        $parent = null;

        foreach ($rows as $k => [$status, $owner, $dayOffset, $vxp]) {
            $task = Task::updateOrCreate(
                ['work_item_id' => $item->id, 'title' => $item->name.' — دفعة '.($k + 1)],
                [
                    'entity_id' => $entity->id,
                    'owner_id' => $owner->id,
                    'created_by' => $people['director']->id,
                    'brief' => $item->brief,
                    'deliverable_spec' => $item->deliverable_spec,
                    'vxp_value' => $vxp,
                    'deadline_at' => now()->addDays($dayOffset),
                    'approved_at' => $status === 'approved' ? now()->addDays($dayOffset) : null,
                    'status' => $status,
                    'source' => 'assigned',
                ],
            );

            $parent ??= $task;
        }

        // أب بوعاء وأبناء — ليعمل بوب-أب توزيع VXP بقيديه
        if ($parent && $seed === 0) {
            $parent->forceFill(['vxp_value' => 200, 'owner_id' => $people['leader']->id])->save();

            foreach (['coordinator_a', 'coordinator_b'] as $n => $key) {
                Task::updateOrCreate(
                    ['parent_task_id' => $parent->id, 'title' => $parent->title.' — شريحة '.($n + 1)],
                    [
                        'work_item_id' => $item->id,
                        'entity_id' => $entity->id,
                        'owner_id' => $people[$key]->id,
                        'vxp_value' => 60,
                        'deadline_at' => now()->addDays(3),
                        'status' => 'in_progress',
                        'source' => 'assigned',
                    ],
                );
            }
        }

        app(VxpDistributionService::class)->syncItemSpent((int) $item->id);
    }

    // ------------------------------------------------------------------ المشروع التشغيليّ

    private function operationalProject(Entity $entity, array $people): void
    {
        $project = Project::updateOrCreate(
            ['entity_id' => $entity->id, 'type' => 'operational'],
            ['name' => 'المشروع التشغيليّ لقسم الإعلام', 'is_permanent' => true, 'status' => 'active'],
        );

        $package = WorkPackage::updateOrCreate(
            ['project_id' => $project->id, 'name' => 'الإيقاع اليوميّ'],
            ['entity_id' => $entity->id],
        );

        $rows = [
            ['بوست يوميّ على الصفحة', 'daily', 8, 'rotation', 25, null],
            ['تقرير أسبوعيّ للمحتوى', 'weekly', 48, 'individual', 60, 'supervisor'],
            ['تصميم بانر المناسبات', 'weekly', 24, 'public_board', 40, null],
        ];

        foreach ($rows as [$name, $recurrence, $hours, $audience, $pool, $assignee]) {
            WorkItem::updateOrCreate(
                ['work_package_id' => $package->id, 'name' => $name],
                [
                    'brief' => 'بريف ثابت: '.$name.'.',
                    'deliverable_spec' => 'مخرج جاهز للنشر بمقاسات الهويّة.',
                    'vxp_pool' => $pool,
                    'is_recurring' => true,
                    'recurrence' => $recurrence,
                    'relative_deadline_hours' => $hours,
                    'audience_mode' => $audience,
                    'assigned_user_id' => $assignee ? $people[$assignee]->id : null,
                    'is_public_board_candidate' => $audience === 'public_board',
                    'last_generated_at' => now()->subDay(),
                    'next_generation_at' => now()->addHours(2),
                    'generated_count' => 6,
                    'missed_count' => $audience === 'public_board' ? 1 : 0,
                ],
            );
        }

        // نوبات وصلت فعلًا — بعضها من الموازن ليظهر سطر «الأقلّ حملًا»
        $item = WorkItem::query()->where('work_package_id', $package->id)->where('audience_mode', 'rotation')->first();

        foreach ([['coordinator_a', true, 1], ['coordinator_b', false, 2]] as [$key, $byBalancer, $day]) {
            Task::updateOrCreate(
                ['work_item_id' => $item->id, 'title' => $item->name.' — نوبة '.$day],
                [
                    'entity_id' => $entity->id,
                    'owner_id' => $people[$key]->id,
                    'vxp_value' => $item->vxp_pool,
                    'deadline_at' => now()->addHours(8),
                    'status' => 'in_progress',
                    'source' => 'recurring',
                    'assigned_by_balancer' => $byBalancer,
                ],
            );
        }
    }

    // ------------------------------------------------------------------ الأداء

    /** حركات VXP وRep واقعيّة — بينها واحدة تخطّت حدّ الخسارة اليوميّ */
    private function performance(array $people): void
    {
        $rep = app(RepService::class);

        $plan = [
            'director' => [900, [['meeting.managed', 'إدارة اجتماع الأسبوع']]],
            'supervisor' => [740, [['task.early', 'تسليم قبل الموعد']]],
            'leader' => [610, [['task.early', 'تسليم قبل الموعد'], ['meeting.within_3h', 'حضور اجتماع خلال 3 ساعات']]],
            'coordinator_a' => [430, [['task.early', 'تسليم قبل الموعد'], ['task.late_under_24h', 'تأخير أقلّ من 24 ساعة']]],
            'coordinator_b' => [280, [['task.no_delivery', 'عدم تسليم'], ['behavior.severe', 'مخالفة جسيمة موثّقة']]],
        ];

        foreach ($plan as $key => [$vxp, $moves]) {
            $user = $people[$key];

            Integrations::credit(
                user: $user,
                currencyCode: VxpDistributionService::CURRENCY,
                amount: $vxp,
                source: 'task',
                reason: 'اعتماد مهامّ الحملة',
            );

            foreach ($moves as [$ruleKey, $reason]) {
                $value = rep_rule($ruleKey);

                $value >= 0
                    ? Integrations::credit($user, RepService::CURRENCY, $value, $this->sourceOf($ruleKey), null, $reason)
                    : Integrations::debit($user, RepService::CURRENCY, $value, $this->sourceOf($ruleKey), null, $reason);
            }

            $rep->syncScore($user);
        }
    }

    private function sourceOf(string $ruleKey): string
    {
        return match (true) {
            str_starts_with($ruleKey, 'meeting.') => 'meeting',
            str_starts_with($ruleKey, 'academy.') => 'academy',
            str_starts_with($ruleKey, 'leadership.') => 'leadership',
            str_starts_with($ruleKey, 'behavior.') => 'behavior',
            default => 'task',
        };
    }
}
