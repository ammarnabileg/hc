<?php

namespace Tests\Feature\Volunteer\Meetings;

use App\Models\Meeting;
use App\Models\Objection;
use App\Models\RepRule;
use App\Models\Setting;
use Database\Seeders\VolunteerMeetingsDemoSeeder;

/**
 * سيدر الديمو لازم يشتغل من أوّل مرّة على قاعدة نظيفة،
 * وإلّا فالشاشات تُسلَّم بلا بيانات يراها أحد.
 */
class MeetingsDemoSeederTest extends MeetingsTestCase
{
    public function test_demo_seeder_creates_meetings_settings_and_an_objection(): void
    {
        $manager = $this->volunteer('مسؤول');
        $member = $this->makeUser('عضو');
        $this->makeMembership($member, $manager->memberships()->first());
        $this->makeMembership($this->makeUser('عضو تاني'), $manager->memberships()->first());

        $this->seed(VolunteerMeetingsDemoSeeder::class);

        $this->assertGreaterThanOrEqual(3, Meeting::count());
        $this->assertTrue(Setting::where('key', 'meetings.attendance.tier1_hours')->exists());
        $this->assertTrue(RepRule::where('key', 'meeting.late_registration')->exists());
        $this->assertSame(1, Objection::count());

        // تشغيلها مرّتين لا يكرّر البيانات
        $this->seed(VolunteerMeetingsDemoSeeder::class);

        $this->assertSame(3, Meeting::count());
        $this->assertSame(1, Objection::count());
    }
}
