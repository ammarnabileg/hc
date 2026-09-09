<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Offboarding;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use App\Services\Admin\Volunteer\OffboardingService;
use App\Services\Volunteer\Tasks\TaskStatus;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ تصفية المهامّ المفتوحة قبل انتهاء عضويّة (23-0.2-H3): «تنتقل ملكيّتها
 * لأبلاينه المباشر بنفس ديدلاييناتها … بلا أيّ خصم على المنقول». كان هذا
 * البند سطرًا في تشيك-ليست الأوفبوردنج **بلا تنفيذٍ فعليّ** — فمهامّ الخارج
 * المفتوحة تبقى يتيمةً على مالكٍ ما عاد عضوًا في الكيان.
 */
class TaskOwnershipTransferTest extends AdminVolunteerTestCase
{
    private function entity(): Entity
    {
        return Entity::create([
            'track_id' => Track::query()->where('key', 'department')->value('id'),
            'name_ar' => 'قسم الاختبار',
            'status' => 'active',
        ]);
    }

    private function membership(User $user, Entity $entity, string $positionKey, ?Membership $upline = null): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::query()->where('key', $positionKey)->value('id'),
            'upline_id' => $upline?->id,
            'is_primary' => true,
            'started_at' => now()->subDays(200),
            'status' => 'active',
        ]);
    }

    private function task(Entity $entity, User $owner, string $status, $deadline = null): Task
    {
        return Task::create([
            'title' => 'مهمّة '.str()->random(4),
            'entity_id' => $entity->id,
            'owner_id' => $owner->id,
            'status' => $status,
            'deadline_at' => $deadline,
            'source' => 'assigned',
        ]);
    }

    private function complete(User $target, User $actor): void
    {
        Setting::updateOrCreate(['key' => 'volunteer.offboarding.clearance_items'], [
            'group' => 'offboarding', 'label_ar' => 'x', 'type' => 'json',
            'default_value' => json_encode(['نقل المهامّ'], JSON_UNESCAPED_UNICODE),
            'value' => json_encode(['نقل المهامّ'], JSON_UNESCAPED_UNICODE),
        ]);
        Cache::forget('settings');

        $record = Offboarding::create([
            'user_id' => $target->id,
            'type' => 'resignation',
            'initiated_by' => $actor->id,
            'notice_until' => now(),
            'clearance_checklist' => [['label' => 'نقل المهامّ', 'done' => true]],
        ]);

        OffboardingService::complete($record->fresh(), $actor);
    }

    public function test_open_tasks_move_to_the_direct_upline_with_the_same_deadline(): void
    {
        $entity = $this->entity();
        $upline = $this->makeUser('أبلاين');
        $uplineM = $this->membership($upline, $entity, 'director');
        $leaving = $this->makeUser('دايركتور مستقيل');
        $leavingM = $this->membership($leaving, $entity, 'supervisor', $uplineM);

        $deadline = now()->addDays(5);
        $task = $this->task($entity, $leaving, TaskStatus::IN_PROGRESS, $deadline);

        $actor = $this->makeUser('يعتمد الخروج');
        $this->complete($leaving, $actor);

        $task->refresh();
        $this->assertSame($upline->id, $task->owner_id, 'ملكيّة المهمّة المفتوحة لم تنتقل للأبلاين.');
        $this->assertSame($deadline->format('Y-m-d H:i:s'), $task->deadline_at->format('Y-m-d H:i:s'), 'الديدلاين اتغيّر — والنصّ يوجب نفس الديدلاينات بلا مساس.');
    }

    public function test_closed_tasks_are_left_alone(): void
    {
        $entity = $this->entity();
        $uplineM = $this->membership($this->makeUser('أبلاين'), $entity, 'director');
        $leaving = $this->makeUser('دايركتور مستقيل');
        $this->membership($leaving, $entity, 'supervisor', $uplineM);

        $closed = $this->task($entity, $leaving, TaskStatus::CLOSED);

        $actor = $this->makeUser('يعتمد الخروج');
        $this->complete($leaving, $actor);

        $this->assertSame($leaving->id, $closed->refresh()->owner_id, 'مهمّةٌ مُغلَقة بالفعل لا تنتقل ملكيّتها — لا معنى لنقل ما انتهى.');
    }

    /** مهمّةٌ في كيانٍ لا صلة له بالعضويّة المنتهية — النقل مقيَّدٌ بكيان العضويّة نفسها */
    public function test_tasks_in_an_unrelated_entity_are_not_touched(): void
    {
        $entity = $this->entity();
        $otherEntity = $this->entity();
        $uplineM = $this->membership($this->makeUser('أبلاين'), $entity, 'director');
        $leaving = $this->makeUser('دايركتور مستقيل');
        $this->membership($leaving, $entity, 'supervisor', $uplineM);

        // مهمّةٌ حاملة اسمه في كيانٍ آخر — دون أن تكون له عضويّة هناك أصلًا
        $elsewhere = $this->task($otherEntity, $leaving, TaskStatus::IN_PROGRESS);

        $actor = $this->makeUser('يعتمد الخروج');
        $this->complete($leaving, $actor);

        $this->assertSame($leaving->id, $elsewhere->refresh()->owner_id, 'مهمّة كيانٍ لا صلة له بالعضويّة المنتهية انتقلت خطأً.');
    }
}
