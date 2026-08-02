<?php

namespace Tests\Feature\Volunteer\Meetings;

use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\MeetingPost;

/**
 * كلّ شاشة وكلّ تاب في المجال يُفتَح فعلًا (2.15: التاب يُحمَّل عند فتحه).
 */
class MeetingsScreensTest extends MeetingsTestCase
{
    public function test_every_meeting_tab_renders(): void
    {
        $owner = $this->volunteer('صاحب الاجتماع', null, ['meetings.create' => 'ENTITY', 'meetings.manage' => 'ENTITY']);
        $meeting = $this->endedMeeting($owner, ['title' => 'اجتماع الشاشات']);

        MeetingPost::create([
            'meeting_id' => $meeting->id,
            'user_id' => $owner->id,
            'body' => 'بوست مثبَّت للنقاش',
            'is_pinned' => true,
            'votes' => 2,
        ]);

        foreach (['details', 'attendance', 'minutes', 'discussion'] as $tab) {
            $this->actingAs($owner)
                ->get(route('volunteer.meetings.show', ['meeting' => $meeting, 'tab' => $tab]))
                ->assertOk()
                ->assertSee('اجتماع الشاشات');
        }

        $this->actingAs($owner)
            ->get(route('volunteer.meetings.show', ['meeting' => $meeting, 'tab' => 'discussion']))
            ->assertSee('بوست مثبَّت للنقاش');
    }

    public function test_upcoming_tab_shows_the_create_action_only_for_the_permitted(): void
    {
        $creator = $this->volunteer('صاحب صلاحيّة', null, ['meetings.create' => 'ENTITY']);
        $plain = $this->volunteer('متطوّع عاديّ');

        Meeting::create([
            'title' => 'اجتماع قادم',
            'entity_id' => $this->entity->id,
            'audience' => 'entity',
            'owner_id' => $creator->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($creator)->get(route('volunteer.meetings'))->assertOk()->assertSee('اجتماع جديد');
        // بلا صلاحيّة = مخفيّ فعلًا لا معطَّل (2.15-أ-7)
        $this->actingAs($plain)->get(route('volunteer.meetings'))->assertOk()->assertDontSee('اجتماع جديد');
    }

    public function test_attendance_screen_renders_both_tabs(): void
    {
        $owner = $this->volunteer('صاحب الاجتماع');
        $member = $this->volunteer('عضو');
        $meeting = $this->endedMeeting($owner, ['title' => 'اجتماع المحضر']);

        MeetingAttendance::create([
            'meeting_id' => $meeting->id,
            'user_id' => $member->id,
            'status' => 'registered',
            'registered_at' => now(),
            'hours_after_end' => 1,
            'rep_value' => rep_rule('meeting.within_3h'),
        ]);

        $this->actingAs($member)
            ->get(route('volunteer.attendance'))
            ->assertOk()
            ->assertSee('نسبة الحضور')
            ->assertSee('اجتماع المحضر');

        $this->actingAs($member)
            ->get(route('volunteer.attendance', ['tab' => 'minutes']))
            ->assertOk()
            ->assertSee('معاينة');
    }

    public function test_discussion_vote_and_pin_work(): void
    {
        $owner = $this->volunteer('صاحب الاجتماع', null, ['meetings.manage' => 'ENTITY', 'meetings.create' => 'ENTITY']);
        $member = $this->volunteer('عضو');
        $meeting = $this->endedMeeting($owner);

        $this->actingAs($member)
            ->post(route('volunteer.meetings.posts', $meeting), ['body' => 'أوّل بوست في النقاش'])
            ->assertRedirect();

        $post = MeetingPost::where('meeting_id', $meeting->id)->firstOrFail();

        $this->actingAs($member)->post(route('volunteer.meetings.posts.vote', $post), ['value' => 1]);
        $this->assertSame(1, (int) $post->fresh()->votes);

        // إعادة نفس الصوت تسحبه
        $this->actingAs($member)->post(route('volunteer.meetings.posts.vote', $post), ['value' => 1]);
        $this->assertSame(0, (int) $post->fresh()->votes);

        // التثبيت لصاحب الاجتماع، ومحجوب عن غيره
        $this->actingAs($member)->post(route('volunteer.meetings.posts.pin', $post))->assertForbidden();
        $this->actingAs($owner)->post(route('volunteer.meetings.posts.pin', $post))->assertRedirect();
        $this->assertTrue((bool) $post->fresh()->is_pinned);
    }

    public function test_meeting_can_be_created_with_code_and_questions(): void
    {
        $creator = $this->volunteer('صاحب صلاحيّة', null, ['meetings.create' => 'ENTITY']);

        $this->actingAs($creator)->post(route('volunteer.meetings.store'), [
            'title' => 'اجتماع بكود',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'audience' => 'entity',
            'entity_id' => $this->entity->id,
            'attendance_code' => 'ABC123',
            'questions' => [['prompt' => 'إيه الأجندة؟', 'options' => 'أ,ب', 'correct_answer' => 'أ']],
        ])->assertRedirect();

        $meeting = Meeting::where('title', 'اجتماع بكود')->firstOrFail();

        $this->assertSame('ABC123', $meeting->attendance_code);
        $this->assertSame(1, $meeting->questions()->count());
    }
}
