<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use App\Services\Volunteer\Org\TransferService;
use App\Services\Volunteer\Tasks\TaskStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ⭐ النقل بين الأقسام الرئيسيّة (23-0.2 — قاعدة النقل بين الأقسام): «أيّ نقل
 * من قسم رئيسي إلى قسم رئيسي آخر ⟵ المتطوّع يشغل بوزشن «منسّق» في القسم
 * الجديد أيًّا كانت درجته السابقة». وكان لا وجود لأيّ مسار نقلٍ حقيقيّ في
 * المنصّة — ثلاثة مواضع فقط تُنشئ عضويّة: التسكين والسلّم ودعوة الملفّ.
 */
class TransferServiceTest extends AdminVolunteerTestCase
{
    private function department(string $name = 'قسم', ?Entity $parent = null): Entity
    {
        return Entity::create([
            'track_id' => Track::query()->where('key', 'department')->value('id'),
            'parent_id' => $parent?->id,
            'name_ar' => $name,
            'status' => 'active',
        ]);
    }

    private function governorate(string $name = 'محافظة'): Entity
    {
        return Entity::create([
            'track_id' => Track::query()->where('key', 'governorate')->value('id'),
            'name_ar' => $name,
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

    public function test_a_transfer_to_a_different_main_department_demotes_to_coordinator(): void
    {
        $fromDept = $this->department('قسم أوّل');
        $toDept = $this->department('قسم تانٍ');
        $toUpline = $this->membership($this->makeUser('دايركتور القسم الجديد'), $toDept, 'director');

        $user = $this->makeUser('سوبرفايزر ينتقل');
        $from = $this->membership($user, $fromDept, 'supervisor');

        $actor = $this->makeUser('أدمن ينفّذ');

        $new = app(TransferService::class)->transfer($from, $toDept, $actor, 'سدّ احتياج القسم الجديد');

        $this->assertSame('coordinator', $new->position?->key, 'النقل بين قسمين رئيسيّين يبدأ ببوزشن كوردنيتور دائمًا — أيًّا كانت الدرجة السابقة.');
        $this->assertSame($toDept->id, $new->entity_id);
        $this->assertSame($toUpline->id, $new->upline_id, 'الأبلاين أُعيد ربطه لأقرب صاحب قرارٍ في الكيان المستقبِل.');

        $this->assertSame('ended', $from->fresh()->status);
        $this->assertSame('transfer', $from->fresh()->end_reason);
    }

    /** «النقل بين الأقسام الفرعيّة داخل نفس القسم الرئيسي لا يُعَدّ نقلًا بين أقسام — يحتفظ بدرجته» */
    public function test_a_transfer_within_the_same_main_department_keeps_the_rank(): void
    {
        $main = $this->department('القسم الرئيسي');
        $subA = $this->department('فرعي أ', $main);
        $subB = $this->department('فرعي ب', $main);

        $user = $this->makeUser('سوبرفايزر ينتقل داخليًّا');
        $from = $this->membership($user, $subA, 'supervisor');

        $actor = $this->makeUser('أدمن ينفّذ');

        $new = app(TransferService::class)->transfer($from, $subB, $actor);

        $this->assertSame('supervisor', $new->position?->key, 'التنقّل الداخليّ يحتفظ بالدرجة — ليس نقلًا بين أقسام رئيسيّة.');
        $this->assertSame($subB->id, $new->entity_id);
    }

    public function test_a_transfer_across_tracks_is_rejected(): void
    {
        $fromDept = $this->department();
        $governorate = $this->governorate();
        $user = $this->makeUser('صاحب عضويّة قسم');
        $from = $this->membership($user, $fromDept, 'coordinator');
        $actor = $this->makeUser('أدمن ينفّذ');

        try {
            app(TransferService::class)->transfer($from, $governorate, $actor);
            $this->fail('النقل عبر مسارٍ آخر لازم يُرفَض — «النقل داخل المسار الواحد فقط».');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    /** تصفية قبل النقل (23-0.2-H3): المهامّ المفتوحة داخل القسم القديم تنتقل لأبلاينه قبل إغلاق عضويّته */
    public function test_open_tasks_move_to_the_old_upline_before_the_transfer(): void
    {
        $fromDept = $this->department('قسم قديم');
        $toDept = $this->department('قسم جديد');
        $oldUpline = $this->makeUser('أبلاين قديم');
        $oldUplineM = $this->membership($oldUpline, $fromDept, 'director');

        $user = $this->makeUser('كوردنيتور ينتقل');
        $from = $this->membership($user, $fromDept, 'coordinator', $oldUplineM);

        $deadline = now()->addDays(3);
        $task = Task::create([
            'title' => 'مهمّة مفتوحة',
            'entity_id' => $fromDept->id,
            'owner_id' => $user->id,
            'status' => TaskStatus::IN_PROGRESS,
            'deadline_at' => $deadline,
            'source' => 'assigned',
        ]);

        $actor = $this->makeUser('أدمن ينفّذ');
        app(TransferService::class)->transfer($from, $toDept, $actor);

        $task->refresh();
        $this->assertSame($oldUpline->id, $task->owner_id, 'المهمّة المفتوحة لم تنتقل لأبلاينه المباشر في القسم القديم.');
        $this->assertSame($deadline->format('Y-m-d H:i:s'), $task->deadline_at->format('Y-m-d H:i:s'));
    }

    /** لا فترة شغور أصلًا (القسم 0): المقعد القديم يُملأ فورًا من داونلاينه المباشر */
    public function test_the_vacated_seat_is_filled_immediately(): void
    {
        $fromDept = $this->department();
        $toDept = $this->department('قسم آخر');

        $user = $this->makeUser('دايركتور ينتقل');
        $from = $this->membership($user, $fromDept, 'director');

        $successor = $this->makeUser('سوبرفايزر خليفة');
        $this->membership($successor, $fromDept, 'supervisor', $from);

        $actor = $this->makeUser('أدمن ينفّذ');
        app(TransferService::class)->transfer($from, $toDept, $actor);

        $newDirector = Membership::query()
            ->where('entity_id', $fromDept->id)
            ->where('status', 'active')
            ->whereHas('position', fn ($q) => $q->where('key', 'director'))
            ->first();

        $this->assertNotNull($newDirector, 'سلّم الترقية لم يملأ شغور القسم القديم فورًا.');
        $this->assertSame($successor->id, $newDirector->user_id);
    }

    // ------------------------------------------------------------ HTTP الحقيقيّ

    public function test_admin_can_transfer_a_membership_via_the_http_action(): void
    {
        $fromDept = $this->department('قسم HTTP أوّل');
        $toDept = $this->department('قسم HTTP تانٍ');

        $user = $this->makeUser('عضو منقول');
        $from = $this->membership($user, $fromDept, 'coordinator');

        $actor = $this->grant($this->makeUser('أدمن HTTP'), 'memberships.edit');

        $this->actingAs($actor)
            ->post(route('admin.volunteer.org.memberships.transfer', $from), [
                'entity_id' => $toDept->id,
                'reason' => 'اختبار',
            ])
            ->assertRedirect();

        $this->assertSame('ended', $from->fresh()->status);

        $newMembership = Membership::query()->where('user_id', $user->id)->where('status', 'active')->first();
        $this->assertNotNull($newMembership);
        $this->assertSame($toDept->id, $newMembership->entity_id);
    }
}
