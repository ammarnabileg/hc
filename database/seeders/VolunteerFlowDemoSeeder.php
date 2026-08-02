<?php

namespace Database\Seeders;

use App\Models\Arbitration;
use App\Models\ContributionCheckpoint;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\TaskSubmission;
use App\Models\Track;
use App\Models\User;
use App\Services\Volunteer\Contributions\ActivityWindow;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * بيانات تجريبيّة لمجال دورة العمل (المساهمات · المراجعة · التصعيد · التحكيم).
 *
 * ولا تُسجَّل في `DatabaseSeeder` — تُجمَّع مركزيًّا (BUILD.md §7).
 */
class VolunteerFlowDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();

        $entity = $this->entity();
        [$director, $supervisor, $owner, $contributor] = $this->people($entity);

        $task = $this->task($entity, $owner, $supervisor);
        $contribution = $this->contribution($task, $owner, $contributor);
        $this->checkpoints($contribution);
        $this->delivered($task, $owner);
        $this->cases($task, $contribution, $owner, $contributor, $supervisor);

        $this->command?->info('دورة العمل: مهمّة ومساهمة ونقطتا تفتيش وحالتان على المحرّك وقضيّة تحكيم.');
    }

    // ------------------------------------------------------------------ الإعدادات

    /**
     * إعدادات المجال بنمط «المجال.الميزة.المفتاح» — ولا رقم محروق في الكود (2.13).
     * وما كان موجودًا في `SettingSeeder` لا يُكرَّر هنا.
     */
    private function settings(): void
    {
        $rows = [
            ['workflow.activity_window.timezone', 'workflow', 'منطقة نافذة النشاط الزمنيّة', 'string', 'Africa/Cairo'],
            ['workflow.contribution.internal_deadline_gap_hours', 'workflow', 'فجوة الديدلاين الداخليّ عن ديدلاين المهمّة (ساعات)', 'number', '24'],
            ['workflow.contribution.checkpoints_max', 'workflow', 'أقصى عدد نقاط تفتيش للبند', 'number', '2'],
            ['workflow.review.fix_hours', 'workflow', 'مهلة الإصلاح بعد الإرجاع (ساعات)', 'number', '24'],
            ['workflow.review.escalate_after_returns', 'workflow', 'عدد الإرجاعات قبل التصعيد', 'number', '2'],
            ['workflow.review.return_reasons', 'workflow', 'أسباب الإرجاع العشرة', 'json', json_encode([
                'quality_below' => 'جودة أقلّ من المطلوب',
                'wrong_data' => 'خطأ في البيانات أو الأرقام',
                'has_errors' => 'يحتوي على أخطاء',
                'off_identity' => 'مخالف للهويّة',
                'copied_or_generated' => 'منقول أو مُولَّد آليًّا بلا مراجعة',
                'empty_or_dead_link' => 'تسليم فارغ أو رابط لا يفتح',
                'wrong_format' => 'صيغة مخالفة',
                'access_closed' => 'صلاحيّة الوصول مغلقة',
                'out_of_scope' => 'خارج المطلوب',
                'incomplete' => 'غير مكتمل',
            ], JSON_UNESCAPED_UNICODE)],
            ['workflow.escalation.soon_hours', 'workflow', 'عتبة «اقتربت» للعدّاد الأصفر (ساعات)', 'number', '6'],
            ['workflow.escalation.top_position', 'workflow', 'بوزشن السقف صاحب نافذة الـ48', 'string', 'volunteer_gm'],
            ['workflow.arbitration.window_hours', 'workflow', 'نافذة المحكّم (ساعات)', 'number', '24'],
            ['workflow.arbitration.settlement_percent', 'workflow', 'نسبة التسوية الآليّة للطرفين (%)', 'number', '50'],
            ['workflow.arbitration.mask_visible_digits', 'workflow', 'عدد الأرقام الظاهرة في الرقم المقنَّع', 'number', '2'],
            ['workflow.repeated_return.rep_min', 'workflow', 'أدنى قيمة Rep يدويّة في الإرجاع المتكرّر', 'number', '-0.5'],
            ['workflow.repeated_return.rep_max', 'workflow', 'أقصى قيمة Rep يدويّة في الإرجاع المتكرّر', 'number', '0.25'],
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

    // ------------------------------------------------------------------ الهيكل

    private function entity(): Entity
    {
        $track = Track::query()->where('key', 'department')->first()
            ?? Track::query()->firstOrCreate(['key' => 'department'], ['name_ar' => 'قسم']);

        return Entity::query()->firstOrCreate(
            ['track_id' => $track->id, 'name_ar' => 'قسم المحتوى'],
            ['status' => 'active'],
        );
    }

    /** @return array{0:User,1:User,2:User,3:User} */
    private function people(Entity $entity): array
    {
        $director = $this->user('مروة عبد الرحمن', 'VF-DIR');
        $supervisor = $this->user('أحمد سمير', 'VF-SUP');
        $owner = $this->user('خالد منصور', 'VF-OWN');
        $contributor = $this->user('نورهان فتحي', 'VF-CON');

        $directorMembership = $this->membership($director, $entity, 'director', null);
        $supervisorMembership = $this->membership($supervisor, $entity, 'supervisor', $directorMembership);
        $this->membership($owner, $entity, 'team_leader', $supervisorMembership);
        $this->membership($contributor, $entity, 'coordinator', $supervisorMembership);

        return [$director, $supervisor, $owner, $contributor];
    }

    private function user(string $name, string $code): User
    {
        return User::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'email' => mb_strtolower($code).'@demo.local',
                'password' => Hash::make('secret-password'),
                'phone' => '0100'.random_int(1000000, 9999999),
                'status' => 'active',
            ],
        );
    }

    private function membership(User $user, Entity $entity, string $positionKey, ?Membership $upline): Membership
    {
        return Membership::query()->firstOrCreate(
            ['user_id' => $user->id, 'entity_id' => $entity->id],
            [
                'position_id' => Position::query()->where('key', $positionKey)->value('id'),
                'upline_id' => $upline?->id,
                'is_primary' => true,
                'started_at' => now()->subMonths(3),
                'status' => 'active',
            ],
        );
    }

    // ------------------------------------------------------------------ العمل

    private function task(Entity $entity, User $owner, User $reviewer): Task
    {
        return Task::query()->firstOrCreate(
            ['title' => 'اكتب سكربت ريل المستوى الرابع'],
            [
                'entity_id' => $entity->id,
                'owner_id' => $owner->id,
                'reviewer_id' => $reviewer->id,
                'created_by' => $reviewer->id,
                'brief' => 'ريل تعريفيّ بالمستوى الرابع من المسار التأهيليّ، مدّته دقيقة ونصف.',
                'deliverable_spec' => 'ملفّ Google Doc: مشهد افتتاحيّ + 5 مشاهد + دعوة للفعل، بالعاميّة المصريّة المهذّبة.',
                'vxp_value' => 200,
                'deadline_at' => now()->addDays(5),
                'status' => 'in_progress',
            ],
        );
    }

    private function contribution(Task $task, User $owner, User $contributor): TaskContribution
    {
        return TaskContribution::query()->firstOrCreate(
            ['task_id' => $task->id, 'contributor_id' => $contributor->id],
            [
                'invited_by' => $owner->id,
                'item_title' => 'اكتب المشاهد الثلاثة الأولى',
                'instructions' => 'خلّي المشهد الأوّل هوك في أوّل 3 ثوانٍ.',
                // شكل المخرجات مهمّ جدًّا عشان ميحصلش أيّ خلاف (23 — القسم 4)
                'deliverable_spec' => 'ثلاثة مشاهد في ملفّ واحد، لكلّ مشهد: الصورة · التعليق الصوتيّ · المدّة بالثواني.',
                // الديدلاين الداخليّ ≤ ديدلاين المهمّة − 24 ساعة
                'internal_deadline_at' => now()->addDays(3),
                'vxp_value' => 60,
                'vxp_source' => 'task_pool',
                'status' => 'accepted',
                'invited_at' => now()->subDay(),
                'responded_at' => now()->subDay()->addHours(2),
            ],
        );
    }

    private function checkpoints(TaskContribution $contribution): void
    {
        foreach ([12, 30] as $index => $hours) {
            $scheduled = now()->addHours($hours);

            ContributionCheckpoint::query()->firstOrCreate(
                ['task_contribution_id' => $contribution->id, 'sequence' => $index + 1],
                [
                    'scheduled_at' => $scheduled,
                    'response_due_at' => ActivityWindow::addHours($scheduled, (float) setting('workflow.checkpoint.response_hours', 2)),
                    'status' => 'pending',
                ],
            );
        }
    }

    private function delivered(Task $task, User $owner): void
    {
        TaskSubmission::query()->firstOrCreate(
            ['task_id' => $task->id, 'user_id' => $owner->id, 'version' => 1],
            [
                'body' => 'المسوّدة الأولى للسكربت.',
                'link' => 'https://docs.example.test/script-v1',
                'note' => 'محتاج مراجعة على المشهد الأخير.',
            ],
        );
    }

    /** حالتان على المحرّك + قضيّة تحكيم — لتظهر الشاشات بمحتوى واقعيّ */
    private function cases(Task $task, TaskContribution $contribution, User $owner, User $contributor, User $supervisor): void
    {
        $engine = app(EscalationEngine::class);

        if ($engine->openFor($task, CaseCatalog::EXTENSION)->isEmpty()) {
            $engine->open(CaseCatalog::EXTENSION, $task, $owner, [
                'note' => 'المصمّم اتأخّر على الفيجوال المرجعيّ.',
                'new_deadline' => now()->addDays(7)->toDateTimeString(),
            ]);
        }

        if ($engine->openFor($task, CaseCatalog::NO_DELIVERY)->isEmpty()) {
            $engine->open(CaseCatalog::NO_DELIVERY, $task, $owner, [
                'note' => 'المهمّة يتيمة — تدور على مالك جديد.',
            ], $supervisor);
        }

        $exists = Arbitration::query()->where('task_id', $task->id)->exists();

        if (! $exists) {
            app(\App\Services\Volunteer\Escalation\ArbitrationService::class)->open($task, $contribution, $contributor, [
                'claim' => 'اترجّع البند مرّتين بلا سبب واضح، والمطلوب مكتوب في شكل المخرجات بالحرف.',
            ]);
        }
    }
}
