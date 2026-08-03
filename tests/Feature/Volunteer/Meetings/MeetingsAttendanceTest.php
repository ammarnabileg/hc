<?php

namespace Tests\Feature\Volunteer\Meetings;

use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\MeetingQuestion;
use App\Models\Transaction;
use App\Services\Volunteer\Meetings\AttendanceService;

/**
 * تسجيل حضور الاجتماعات وقيمته على درجة الالتزام (13.4-ن-ب · 24.4).
 * كلّ اختبار هنا يقابل سطرًا منصوصًا في جدول Rep الموحَّد.
 */
class MeetingsAttendanceTest extends MeetingsTestCase
{
    public function test_meetings_screen_lists_scoped_meetings_and_opens_attendance_banner(): void
    {
        $owner = $this->volunteer('صاحب الاجتماع');
        $member = $this->volunteer('عضو');
        $meeting = $this->endedMeeting($owner, ['title' => 'اجتماع المتابعة']);

        $response = $this->actingAs($member)->get(route('volunteer.meetings', ['tab' => 'ended']));

        $response->assertOk()
            ->assertSee('اجتماع المتابعة')
            ->assertSee('سجّل حضورك');
    }

    /** ⭐ القيمة تتغيّر حسب زمن التسجيل بعد الانتهاء */
    public function test_registration_value_follows_hours_after_end(): void
    {
        $owner = $this->volunteer('صاحب الاجتماع');

        // داخل الشريحة الأولى
        $early = $this->volunteer('مبكّر');
        $first = $this->endedMeeting($owner, ['ended_at' => now()->subHour()]);

        $this->actingAs($early)
            ->post(route('volunteer.meetings.register', $first), ['code' => 'HC-CODE'])
            ->assertRedirect();

        $this->assertEqualsWithDelta(
            rep_rule('meeting.within_3h'),
            (float) MeetingAttendance::where('meeting_id', $first->id)->where('user_id', $early->id)->value('rep_value'),
            0.001,
        );

        // بعد الشريحة الأولى وقبل الثانية
        $late = $this->volunteer('متأخّر');
        $second = $this->endedMeeting($owner, [
            'title' => 'اجتماع تاني',
            'ended_at' => now()->subHours(6),
            'attendance_closes_at' => now()->addHours(6),
        ]);

        $this->actingAs($late)
            ->post(route('volunteer.meetings.register', $second), ['code' => 'HC-CODE'])
            ->assertRedirect();

        $this->assertEqualsWithDelta(
            rep_rule('meeting.within_12h'),
            (float) MeetingAttendance::where('meeting_id', $second->id)->where('user_id', $late->id)->value('rep_value'),
            0.001,
        );

        $this->assertNotEquals(rep_rule('meeting.within_3h'), rep_rule('meeting.within_12h'));
    }

    /** ⭐ الكود الخاطئ لا يستهلك المحاولة الصحيحة ولا يكتب سطر حضور */
    public function test_wrong_code_does_not_consume_a_valid_attempt(): void
    {
        $owner = $this->volunteer('صاحب الاجتماع');
        $member = $this->volunteer('عضو');
        $meeting = $this->endedMeeting($owner);

        $this->actingAs($member)
            ->post(route('volunteer.meetings.register', $meeting), ['code' => 'غلط'])
            ->assertRedirect();

        $this->assertDatabaseCount('meeting_attendances', 0);
        $this->assertSame(0, Transaction::where('user_id', $member->id)->count());

        $this->actingAs($member)
            ->post(route('volunteer.meetings.register', $meeting), ['code' => 'HC-CODE'])
            ->assertRedirect();

        $attendance = MeetingAttendance::where('meeting_id', $meeting->id)->where('user_id', $member->id)->first();

        $this->assertNotNull($attendance);
        $this->assertSame('registered', $attendance->status);
        $this->assertEqualsWithDelta(rep_rule('meeting.within_3h'), (float) $attendance->rep_value, 0.001);
    }

    /** إجابة خاطئة على سؤال الاختيارات تُرفَض على الخادم */
    public function test_choice_question_is_verified_server_side(): void
    {
        $owner = $this->volunteer('صاحب الاجتماع');
        $member = $this->volunteer('عضو');
        $meeting = $this->endedMeeting($owner, ['attendance_code' => null]);

        $question = MeetingQuestion::create([
            'meeting_id' => $meeting->id,
            'created_by' => $owner->id,
            'type' => 'choice',
            'prompt' => 'إيه البند اللي اتقفل؟',
            'options' => ['التصميم', 'المحتوى'],
            'correct_answer' => 'التصميم',
        ]);

        $this->actingAs($member)
            ->post(route('volunteer.meetings.register', $meeting), ['answers' => [$question->id => 'المحتوى']]);

        $this->assertDatabaseCount('meeting_attendances', 0);

        $this->actingAs($member)
            ->post(route('volunteer.meetings.register', $meeting), ['answers' => [$question->id => 'التصميم']]);

        $this->assertDatabaseHas('meeting_attendances', [
            'meeting_id' => $meeting->id,
            'user_id' => $member->id,
            'status' => 'registered',
        ]);
    }

    /** ⭐ الاعتذار المسبق يمنع خصم الغياب */
    public function test_prior_excuse_prevents_the_absence_penalty(): void
    {
        $owner = $this->volunteer('صاحب الاجتماع');
        $excused = $this->volunteer('معتذر');
        $silent = $this->volunteer('غائب');

        $meeting = Meeting::create([
            'title' => 'اجتماع الاعتذار',
            'entity_id' => $this->entity->id,
            'audience' => 'entity',
            'owner_id' => $owner->id,
            'scheduled_at' => now()->addHour(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($excused)
            ->post(route('volunteer.meetings.excuse', $meeting), ['reason' => 'سفر مفاجئ'])
            ->assertRedirect();

        // الإنهاء ثمّ قفل النافذة ثمّ التسوية
        $meeting->forceFill([
            'status' => 'ended',
            'ended_at' => now()->subHours(13),
            'attendance_window_hours' => 12,
            'attendance_closes_at' => now()->subHour(),
        ])->save();

        app(AttendanceService::class)->settleAbsences($meeting->fresh());

        $excusedRow = MeetingAttendance::where('meeting_id', $meeting->id)->where('user_id', $excused->id)->first();
        $absentRow = MeetingAttendance::where('meeting_id', $meeting->id)->where('user_id', $silent->id)->first();

        $this->assertEqualsWithDelta(rep_rule('meeting.excused_absence'), (float) $excusedRow->rep_value, 0.001);
        $this->assertEqualsWithDelta(rep_rule('meeting.unexcused_absence'), (float) $absentRow->rep_value, 0.001);
        $this->assertTrue(rep_rule('meeting.unexcused_absence') < 0);
    }

    /** إنهاء الاجتماع بمحضر موثَّق يمنح صاحبه قيمة الإدارة */
    public function test_ending_with_documented_minutes_rewards_the_owner(): void
    {
        $owner = $this->volunteer('صاحب الاجتماع', null, ['meetings.manage' => 'ENTITY', 'meetings.create' => 'ENTITY']);

        $meeting = Meeting::create([
            'title' => 'اجتماع للإنهاء',
            'entity_id' => $this->entity->id,
            'audience' => 'entity',
            'owner_id' => $owner->id,
            'scheduled_at' => now()->subHour(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($owner)
            ->post(route('volunteer.meetings.end', $meeting), [
                'window_hours' => 6,
                'minutes' => 'محضر مكتوب بالتفصيل.',
            ])
            ->assertRedirect();

        $meeting->refresh();

        $this->assertSame('ended', $meeting->status);
        $this->assertNotNull($meeting->attendance_closes_at);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $owner->id,
            'source' => 'meeting',
            'layer' => 'volunteer',
            'amount' => number_format(rep_rule('meeting.managed'), 2, '.', ''),
        ]);
    }

    /** صفحة الاجتماع: لغير المخوَّل تُعرَض حالتي فقط في تاب الحضور */
    public function test_attendance_tab_shows_only_my_row_for_unauthorized_member(): void
    {
        $owner = $this->volunteer('صاحب الاجتماع');
        $other = $this->volunteer('زميل');
        $member = $this->volunteer('عضو');
        $meeting = $this->endedMeeting($owner);

        foreach ([$other, $member] as $user) {
            MeetingAttendance::create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => 'registered',
                'registered_at' => now(),
                'hours_after_end' => 1,
                'rep_value' => rep_rule('meeting.within_3h'),
            ]);
        }

        $this->actingAs($member)
            ->get(route('volunteer.meetings.show', ['meeting' => $meeting, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('عضو')
            ->assertDontSee('زميل');
    }

    /**
     * الاجتماع خارج نطاقي لا يُفتَح.
     *
     * ⭐ والردّ الآن **403 من الحارس** لا 404 من الشاشة: بعد أن صار النطاق يُقيَّم
     * على الهدف (12.2.1-ب) يُحسَم المنعُ **قبل** الوصول إلى الكنترولر — فالحارس
     * هو مَن يردّ، والردّ الواحد لكلّ رفضٍ في المنصّة.
     */
    public function test_meeting_outside_my_scope_is_not_visible(): void
    {
        $owner = $this->volunteer('صاحب الاجتماع');
        $meeting = $this->endedMeeting($owner);

        $stranger = $this->makeUser('غريب');
        $other = $this->makeEntity();
        $this->makeMembership($stranger, null, 'coordinator', $other);
        $this->grant($stranger, $this->baseGrants());

        $this->actingAs($stranger)
            ->get(route('volunteer.meetings.show', $meeting))
            ->assertForbidden();
    }
}
