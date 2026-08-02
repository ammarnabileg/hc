<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\MembershipAbsence;
use App\Models\Position;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use App\Services\Volunteer\Org\AbsenceService;
use App\Services\Volunteer\Tasks\TaskStatus;

/**
 * ⭐ شاشة إدارة الغيابات والتفويض المؤقّت (23-6 · 24).
 *
 * كلّ اختبار هنا **يفشل حين يقع الخلل** لا يمرّ دائمًا: يقيس أثرًا في القاعدة
 * أو حجبًا في الواجهة، لا مجرّد كود 200.
 */
class AdminDelegationsScreenTest extends AdminVolunteerTestCase
{
    private Entity $entity;

    private User $absentee;

    private User $delegate;

    private Membership $absenteeMembership;

    private Membership $delegateMembership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::create([
            'track_id' => Track::query()->where('key', 'department')->value('id'),
            'name_ar' => 'قسم الاختبار',
            'status' => 'active',
        ]);

        $this->delegate = $this->makeUser('البديل المفوَّض');
        $this->absentee = $this->makeUser('العضو الغائب');

        $this->delegateMembership = $this->membership($this->delegate, 'director');
        $this->absenteeMembership = $this->membership($this->absentee, 'team_leader', $this->delegateMembership);
    }

    private function membership(User $user, string $position, ?Membership $upline = null): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $this->entity->id,
            'position_id' => Position::query()->where('key', $position)->value('id'),
            'upline_id' => $upline?->id,
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);
    }

    private function absence(array $attributes = []): MembershipAbsence
    {
        return MembershipAbsence::create(array_merge([
            'membership_id' => $this->absenteeMembership->id,
            'delegate_membership_id' => $this->delegateMembership->id,
            'from_date' => today()->subDay(),
            'to_date' => today()->addDays(5),
            'reason' => 'سفر عائليّ',
            'created_by' => $this->delegate->id,
        ], $attributes));
    }

    // ------------------------------------------------------------------ الباب

    /** بلا `delegations.list` الشاشة **مغلقة** لا معطَّلة — والصلاحيّة على المسار (12.2.1) */
    public function test_the_screen_is_closed_without_the_listing_permission(): void
    {
        $viewer = $this->grant($this->makeUser(), 'org_chart.view');

        $this->actingAs($viewer)
            ->get(route('admin.volunteer.delegations'))
            ->assertForbidden();
    }

    /** ومَن يملكها يرى الغائب وبديله ومدّته وسببه في صفٍّ واحد (24) */
    public function test_the_screen_shows_the_absentee_the_delegate_the_span_and_the_reason(): void
    {
        $this->absence();
        $viewer = $this->grant($this->makeUser(), 'delegations.list');

        $this->actingAs($viewer)
            ->get(route('admin.volunteer.delegations'))
            ->assertOk()
            ->assertSee('العضو الغائب')
            ->assertSee('البديل المفوَّض')
            ->assertSee('سفر عائليّ')
            ->assertSee(today()->addDays(5)->format('Y-m-d'));
    }

    /** والتابات الثلاثة تفصل فعلًا: القادم لا يظهر في «سارية» والعكس */
    public function test_the_three_states_really_separate_the_rows(): void
    {
        $this->absence(['reason' => 'غياب سارٍ']);

        $future = $this->makeUser('عضو تاني');
        $futureMembership = $this->membership($future, 'coordinator', $this->delegateMembership);

        $this->absence([
            'membership_id' => $futureMembership->id,
            'from_date' => today()->addDays(4),
            'to_date' => today()->addDays(8),
            'reason' => 'غياب قادم',
        ]);

        $viewer = $this->grant($this->makeUser(), 'delegations.list');

        $this->actingAs($viewer)
            ->get(route('admin.volunteer.delegations', ['state' => 'current']))
            ->assertSee('غياب سارٍ')
            ->assertDontSee('غياب قادم');

        $this->actingAs($viewer)
            ->get(route('admin.volunteer.delegations', ['state' => 'upcoming']))
            ->assertSee('غياب قادم')
            ->assertDontSee('غياب سارٍ');
    }

    /** زرّ الإنهاء المبكّر **مخفيّ** عمّن يقرأ فقط — لا معطَّلًا (2.15-أ-7) */
    public function test_the_early_end_action_is_hidden_from_a_read_only_viewer(): void
    {
        $this->absence();

        $reader = $this->grant($this->makeUser(), 'delegations.list');
        $editor = $this->grant($this->makeUser('محرّر'), 'delegations.list', 'delegations.edit');

        $this->actingAs($reader)
            ->get(route('admin.volunteer.delegations'))
            ->assertDontSee('أنهِ الغياب');

        $this->actingAs($editor)
            ->get(route('admin.volunteer.delegations'))
            ->assertSee('أنهِ الغياب');
    }

    // ------------------------------------------------------------------ الإنهاء المبكّر

    /**
     * ⭐ الإنهاء المبكّر يُنهي الغياب **فعلًا**: الغائب يرجع غير غائب لحظتَه،
     * ولو كان `to_date` لسّه في المستقبل. (لو ظلّ الفحص على التاريخ وحده
     * سيمرّ الاختبار خطأً — ولذلك نقيس `isAbsent` لا الصفّ.)
     */
    public function test_ending_early_really_ends_the_absence_before_its_declared_date(): void
    {
        $absence = $this->absence();
        $service = app(AbsenceService::class);

        $this->assertTrue($service->isAbsent($this->absentee), 'قبل الإنهاء: غائب.');

        $actor = $this->grant($this->makeUser('دايركتور مخوَّل'), 'delegations.list', 'delegations.edit');
        $this->membership($actor, 'director');

        $this->actingAs($actor)
            ->post(route('admin.volunteer.delegations.end', $absence), ['note' => 'رجع من سفره بدري'])
            ->assertRedirect();

        $absence->refresh();

        $this->assertNotNull($absence->ended_at);
        $this->assertSame($actor->id, (int) $absence->ended_by);
        $this->assertFalse($service->isAbsent($this->absentee), 'بعد الإنهاء: مش غائب — ولو تاريخ النهاية لسّه جايّ.');

        // وقراراته ترجع له: لم يعد يُستبعَد من قوائم الإسناد
        $this->assertContains($this->absentee->id, $service->withoutAbsent([$this->absentee->id]));
    }

    /** ⭐ ولا يقفله صاحبه: الوضع بيد مَن فوقه لا بيده (نفس قاعدة الفتح — 23-6) */
    public function test_the_absentee_cannot_close_his_own_absence(): void
    {
        $absence = $this->absence();
        $this->grant($this->absentee, 'delegations.list', 'delegations.edit');

        $this->actingAs($this->absentee)
            ->post(route('admin.volunteer.delegations.end', $absence), ['note' => 'أنا رجعت'])
            ->assertRedirect();

        $this->assertNull($absence->refresh()->ended_at, 'الغياب فضل شغّال — القفل مش بيده.');
        $this->assertTrue(app(AbsenceService::class)->isAbsent($this->absentee));
    }

    /** ومعه **سجلّ تدقيق** بمن أنهى ولماذا (24) */
    public function test_ending_early_writes_an_audit_row_with_its_reason(): void
    {
        $absence = $this->absence();

        $actor = $this->grant($this->makeUser('دايركتور مخوَّل'), 'delegations.list', 'delegations.edit');
        $this->membership($actor, 'director');

        $this->actingAs($actor)
            ->post(route('admin.volunteer.delegations.end', $absence), ['note' => 'انتهى سبب الغياب']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'action' => 'delegation.end_early',
            'auditable_id' => $absence->id,
        ]);

        $this->actingAs($actor)
            ->get(route('admin.volunteer.delegations', ['state' => 'ended']))
            ->assertSee('انتهى سبب الغياب');
    }

    /**
     * ⭐ وفكّ التجميد يقع **بالمدّة الفعليّة** لا المعلَنة: مهلة مهمّة الغائب
     * تُزاح بيومٍ واحد (غيابه الفعليّ) لا بسبعة أيّام أعلنها.
     */
    public function test_the_thaw_shifts_clocks_by_the_real_span_not_the_declared_one(): void
    {
        $absence = $this->absence(['from_date' => today()->subDay(), 'to_date' => today()->addDays(6)]);

        $deadline = now()->addDays(10);
        $task = Task::create([
            'title' => 'مهمّة الغائب',
            'owner_id' => $this->absentee->id,
            'status' => TaskStatus::IN_PROGRESS,
            'deadline_at' => $deadline,
        ]);

        $actor = $this->grant($this->makeUser('دايركتور مخوَّل'), 'delegations.list', 'delegations.edit');
        $this->membership($actor, 'director');

        $this->actingAs($actor)
            ->post(route('admin.volunteer.delegations.end', $absence), ['note' => 'رجع بدري']);

        $shift = $deadline->diffInHours($task->refresh()->deadline_at);

        $this->assertGreaterThan(20, $shift, 'الإزاحة وقعت فعلًا بمدّة الغياب.');
        $this->assertLessThan(60, $shift, 'وبالمدّة الفعليّة (يوم ونصف) لا بالمعلَنة (7 أيّام).');
    }

    /** ولا يُنهى غيابٌ مقفول مرّتين — ولا تُزاح الساعات مرّتين معه */
    public function test_an_already_closed_absence_cannot_be_closed_again(): void
    {
        $absence = $this->absence();

        $actor = $this->grant($this->makeUser('دايركتور مخوَّل'), 'delegations.list', 'delegations.edit');
        $this->membership($actor, 'director');

        $this->actingAs($actor)->post(route('admin.volunteer.delegations.end', $absence), ['note' => 'رجع']);
        $firstEnd = $absence->refresh()->ended_at;

        $this->actingAs($actor)->post(route('admin.volunteer.delegations.end', $absence), ['note' => 'تاني']);

        $this->assertEquals($firstEnd, $absence->refresh()->ended_at, 'الإنهاء يقع مرّة واحدة.');
        $this->assertSame(1, AuditLog::query()->where('action', 'delegation.end_early')->count());
    }
}
