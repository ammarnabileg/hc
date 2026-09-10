<?php

namespace Tests\Feature\AdminScreens;

use App\Models\Meeting;
use App\Models\MeetingAttendance;

/**
 * مرآة اجتماعات التطوّع في لوحة الإدارة (24.2-أوّلًا):
 * ترندر · الصلاحيّة تحجب · الفلاتر تشتغل · وإنهاءٌ يفتح نافذة الحضور.
 */
class MeetingsAdminTest extends ScreensTestCase
{
    public function test_screen_renders_for_its_owner(): void
    {
        $this->makeMeeting($this->makeUser('صاحب الاجتماع'));

        $this->actingAs($this->admin(['meetings.list', 'meetings.view']))
            ->get(route('admin.meetings.index'))
            ->assertOk()
            ->assertSee('اجتماعات التطوّع')
            ->assertSee('اجتماع الاختبار');
    }

    public function test_empty_state_does_not_break_the_screen(): void
    {
        $this->actingAs($this->owner())
            ->get(route('admin.meetings.index'))
            ->assertOk()
            ->assertSee('مافيش اجتماعات في النطاق ده');
    }

    /**
     * ⭐ 24.2: بحثٌ بلا نتائج يقول كده صراحةً بدل «مافيش اجتماعات في النطاق ده».
     * الملاحظة: نصّ الحالة الافتراضيّة يظهر أيضًا داخل «لوحة الإعدادات» أسفل
     * الشاشة كقيمةٍ قابلة للتعديل — فالتحقّق هنا بعدد التكرار لا بمجرّد الوجود.
     */
    public function test_a_search_with_no_matches_shows_a_filtered_empty_message(): void
    {
        $this->makeMeeting($this->makeUser('صاحب الاجتماع'));

        $html = $this->actingAs($this->owner())
            ->get(route('admin.meetings.index', ['q' => 'zzzznotexist']))
            ->assertOk()
            ->getContent();

        $emptyCardStart = strpos($html, 'card p-8 text-center');
        $emptyCard = substr($html, $emptyCardStart, 400);

        $this->assertStringContainsString(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            $emptyCard,
        );
        // نصّ البداية الافتراضيّ يظهر في لوحة الإعدادات أسفل الشاشة دائمًا — فالتحقّق
        // هنا داخل بطاقة الحالة الفارغة نفسها لا الصفحة كلّها.
        $this->assertStringNotContainsString('مافيش اجتماعات في النطاق ده', $emptyCard);
    }

    public function test_permission_blocks_the_screen_and_its_actions(): void
    {
        $meeting = $this->makeMeeting($this->makeUser('صاحب الاجتماع'));

        $this->actingAs($this->admin(['users.list']))
            ->get(route('admin.meetings.index'))
            ->assertForbidden();

        // القراءة وحدها لا تُنهي اجتماعًا ولا تمنح حضورًا
        $reader = $this->admin(['meetings.list', 'meetings.view']);

        $this->actingAs($reader)
            ->post(route('admin.meetings.end', $meeting), ['window_hours' => 6])
            ->assertForbidden();

        $this->actingAs($reader)
            ->post(route('admin.meetings.grant', $meeting), ['user_id' => 1, 'reason' => 'سبب'])
            ->assertForbidden();
    }

    /** الفلاتر: الحالة · «بلا محضر» */
    public function test_filters_narrow_the_meetings(): void
    {
        $owner = $this->owner();
        $documented = $this->makeMeeting($this->makeUser('صاحب أوّل'), null, [
            'title' => 'اجتماع موثَّق',
            'status' => 'ended',
            'minutes' => 'محضر مكتوب',
        ]);

        $pending = $this->makeMeeting($this->makeUser('صاحب تاني'), null, [
            'title' => 'اجتماع بلا محضر',
            'status' => 'ended',
        ]);

        $this->actingAs($owner)
            ->get(route('admin.meetings.index', ['no_minutes' => '1']))
            ->assertOk()
            ->assertSee('اجتماع بلا محضر')
            ->assertDontSee('اجتماع موثَّق');

        $this->actingAs($owner)
            ->get(route('admin.meetings.index', ['entity' => $documented->entity_id]))
            ->assertOk()
            ->assertSee('اجتماع موثَّق')
            ->assertDontSee('اجتماع بلا محضر');

        $this->assertSame('ended', $pending->status);
    }

    /** الإنهاء من اللوحة يفتح نافذة تسجيل الحضور بعدد الساعات المطلوب */
    public function test_ending_a_meeting_opens_the_attendance_window(): void
    {
        $meeting = $this->makeMeeting($this->makeUser('صاحب الاجتماع'));

        $this->actingAs($this->owner())
            ->post(route('admin.meetings.end', $meeting), [
                'window_hours' => 6,
                'minutes' => 'اتناقشنا في خطّة الشهر.',
            ])
            ->assertRedirect();

        $meeting->refresh();

        $this->assertSame('ended', $meeting->status);
        $this->assertSame(6, (int) $meeting->attendance_window_hours);
        $this->assertTrue($meeting->attendance_closes_at->isFuture());
    }

    /** منح الحضور الاستثنائيّ بسببٍ إلزاميّ — وبلا سبب لا يمرّ */
    public function test_exceptional_attendance_requires_a_reason(): void
    {
        $meeting = $this->makeMeeting($this->makeUser('صاحب الاجتماع'), null, ['status' => 'ended']);
        $member = $this->makeUser('عضو غاب');

        $this->actingAs($this->owner())
            ->post(route('admin.meetings.grant', $meeting), ['user_id' => $member->id, 'reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->owner())
            ->post(route('admin.meetings.grant', $meeting), [
                'user_id' => $member->id,
                'reason' => 'انقطع النت وأثبت حضوره بالتسجيل.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('meeting_attendances', [
            'meeting_id' => $meeting->id,
            'user_id' => $member->id,
            'status' => 'registered',
        ]);
    }

    /** تصدير الحضور يخرج صفًّا لكلّ عضو */
    public function test_attendance_export_streams_rows(): void
    {
        $meeting = $this->makeMeeting($this->makeUser('صاحب الاجتماع'), null, ['status' => 'ended']);
        $member = $this->makeUser('عضو حاضر');

        MeetingAttendance::create([
            'meeting_id' => $meeting->id,
            'user_id' => $member->id,
            'status' => 'registered',
            'registered_at' => now(),
            'rep_value' => 1,
        ]);

        $response = $this->actingAs($this->owner())->get(route('admin.meetings.export'));

        $response->assertOk();
        $this->assertStringContainsString('عضو حاضر', $response->streamedContent());
    }

    public function test_settings_are_editable_and_resettable(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.meetings.settings'), [
            'settings' => ['admin_meetings.per_page' => 35],
        ])->assertRedirect();

        $this->assertSame(35, (int) setting('admin_meetings.per_page'));

        $this->actingAs($owner)->post(route('admin.meetings.settings.reset'))->assertRedirect();

        $this->assertSame(20, (int) setting('admin_meetings.per_page'));

        $this->assertNotNull(Meeting::query()->count());
    }
}
