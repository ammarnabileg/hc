<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Entity;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Track;
use App\Services\Admin\Volunteer\CapacityReport;
use App\Services\Admin\Volunteer\CertificateEligibility;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Admin\Volunteer\SettingsWriter;

/**
 * الهيكل والسعة (13.4-ف) والفعاليّات (12.11 · 13.3).
 */
class AdminVolunteerOrgAndEventsTest extends AdminVolunteerTestCase
{
    /** نطاق الإشراف يُضبَط لكلّ بوزشن — أدنى/افتراضيّ/أقصى + سقف الانشغال. */
    public function test_span_of_control_and_task_cap_are_editable_per_position(): void
    {
        $admin = $this->grant($this->makeUser(), 'org_chart.view', 'positions.edit');
        $teamLeader = Position::where('key', 'team_leader')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.volunteer.org.positions.save'), [
                'positions' => [
                    $teamLeader->id => ['span_min' => 3, 'span_default' => 6, 'span_max' => 9, 'task_load_cap' => 12, 'is_active' => 1],
                ],
            ])
            ->assertRedirect();

        $teamLeader->refresh();

        $this->assertSame(3, (int) $teamLeader->span_min);
        $this->assertSame(9, (int) $teamLeader->span_max);
        $this->assertSame(12, (int) $teamLeader->task_load_cap);
    }

    /** ⭐ السعة مؤشّر لا مانع: التجاوز يظهر أحمر ولا يمنع تسكينًا جديدًا. */
    public function test_capacity_overflow_is_an_indicator_not_a_blocker(): void
    {
        $entity = Entity::where('name_ar', 'فريق الكتابة')->firstOrFail();
        $lead = Membership::where('entity_id', $entity->id)
            ->whereHas('position', fn ($q) => $q->where('key', 'team_leader'))
            ->firstOrFail();

        $coordinator = Position::where('key', 'coordinator')->firstOrFail();
        $max = (int) Position::where('key', 'team_leader')->value('span_max');

        // نتخطّى الأقصى عمدًا — والنظام لا يمنع، بل يُظهر
        for ($i = 0; $i <= $max; $i++) {
            Membership::create([
                'user_id' => $this->makeUser('زائد '.$i)->id,
                'entity_id' => $entity->id,
                'position_id' => $coordinator->id,
                'upline_id' => $lead->id,
                'started_at' => now(),
                'status' => 'active',
            ]);
        }

        $row = CapacityReport::spanRows()->firstWhere(fn ($r) => $r['membership']->id === $lead->id);

        $this->assertGreaterThan($max, $row['count']);
        $this->assertSame('danger', $row['state']);
        $this->assertFalse((bool) setting('volunteer.org.capacity_is_blocking'), 'السعة غير مانعة — قفل معلَن.');
    }

    /** عتبات ألوان الإشغال إعدادات لا أرقام محروقة. */
    public function test_occupancy_color_thresholds_come_from_settings(): void
    {
        SettingsWriter::put('volunteer.org.occupancy_warn_percent', 50);
        SettingsWriter::put('volunteer.org.occupancy_danger_percent', 70);

        $this->assertSame('ok', CapacityReport::occupancyState(40));
        $this->assertSame('warn', CapacityReport::occupancyState(55));
        $this->assertSame('danger', CapacityReport::occupancyState(80));
    }

    /** ⭐ 24.2: بحثٌ بلا نتائج في شاشتَي الهيكل والسعة يقول كده صراحةً. */
    public function test_a_search_with_no_matches_shows_a_filtered_empty_message(): void
    {
        $admin = $this->grant($this->makeUser(), 'org_chart.view', 'capacity.view');

        $orgResponse = $this->actingAs($admin)
            ->get(route('admin.volunteer.org', ['q' => 'zzzznotexist']))
            ->assertOk();
        $orgResponse->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $orgResponse->assertDontSee(
            setting('admin.volunteer.org.mfysh_kyanat_lsh_abda_bawl_kyan', 'مفيش كيانات لسّه — ابدأ بأوّل كيان.'),
            false,
        );

        $trackWithoutEntities = Track::query()->create(['key' => 'no_entities_track_test', 'name_ar' => 'مسار بلا كيانات']);

        $capacityResponse = $this->actingAs($admin)
            ->get(route('admin.volunteer.org.capacity', ['track' => $trackWithoutEntities->id]))
            ->assertOk();
        $capacityResponse->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $capacityResponse->assertDontSee(
            setting('admin.volunteer.capacity.mfysh_kyanat_fy_alntaq_dh', 'مفيش كيانات في النطاق ده.'),
            false,
        );
    }

    /** والهيكل الفارغ فعليًّا (بلا كيانات ولا فلتر) يفضل يعرض رسالة البداية الأصليّة. */
    public function test_actually_empty_org_screen_without_filters_keeps_the_original_start_message(): void
    {
        $admin = $this->grant($this->makeUser(), 'org_chart.view', 'capacity.view');

        Membership::query()->delete();
        Entity::query()->delete();

        $response = $this->actingAs($admin)
            ->get(route('admin.volunteer.org'))
            ->assertOk();

        $response->assertSee(
            setting('admin.volunteer.org.mfysh_kyanat_lsh_abda_bawl_kyan', 'مفيش كيانات لسّه — ابدأ بأوّل كيان.'),
            false,
        );
        $response->assertDontSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
    }

    /** الملفّ المؤقّت لا يفتحه إلّا مشرف عام التطوّع. */
    public function test_case_file_entity_is_opened_by_the_volunteer_gm_only(): void
    {
        $admin = $this->grant($this->makeUser(), 'org_chart.view', 'org_chart.edit');
        $caseFile = Track::where('key', 'case_file')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.volunteer.org.entity.save'), [
                'track_id' => $caseFile->id,
                'name_ar' => 'ملفّ حملة رمضان',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('entities', ['name_ar' => 'ملفّ حملة رمضان']);

        // مشرف عام التطوّع بعضويّة فعليّة — يفتحه
        $gm = $this->grant($this->makeUser('مشرف عام'), 'org_chart.view', 'org_chart.edit');

        Membership::create([
            'user_id' => $gm->id,
            'entity_id' => Entity::first()->id,
            'position_id' => Position::where('key', 'volunteer_gm')->value('id'),
            'started_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($gm)
            ->post(route('admin.volunteer.org.entity.save'), [
                'track_id' => $caseFile->id,
                'name_ar' => 'ملفّ حملة رمضان',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('entities', ['name_ar' => 'ملفّ حملة رمضان']);
    }

    /** الفعاليّة تُنشَأ ثنائيّة اللغة بكود حضور ورابط تسجيل خارجيّ. */
    public function test_event_is_created_bilingual_with_attendance_code_and_registration_link(): void
    {
        $admin = $this->grant($this->makeUser(), 'events.list', 'events.create', 'events.edit');

        $this->actingAs($admin)
            ->post(route('admin.events.save'), [
                'title_ar' => 'لقاء الفرق',
                'title_en' => 'Teams Meetup',
                'description' => 'لقاء شهريّ',
                'description_en' => 'Monthly meetup',
                'mode' => 'offline',
                'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'location' => 'الإسكندريّة',
                'registration_link' => 'https://forms.example.com/meetup',
                'capacity' => 50,
                'status' => 'published',
                'reward_tiers' => [['hours' => 12, 'xp' => 300, 'tickets' => 2]],
                'agenda' => [['title' => 'افتتاحيّة', 'speaker' => 'سلمى']],
            ])
            ->assertRedirect();

        $event = Event::where('title_ar', 'لقاء الفرق')->firstOrFail();

        $this->assertSame('Teams Meetup', $event->title_en);
        $this->assertNotEmpty($event->attendance_code, 'كود الحضور يُولَّد تلقائيًّا لو فاضي.');
        $this->assertSame('https://forms.example.com/meetup', $event->registration_link);
        $this->assertSame(1, $event->agenda()->count());
    }

    /** التشيك-إن بالكود الصحيح يصرف الدرجة الزمنيّة المستحقّة. */
    public function test_check_in_with_the_right_code_awards_the_time_tier(): void
    {
        $admin = $this->grant($this->makeUser(), 'events.list', 'event_registrations.list', 'event_attendance.create');
        $event = Event::where('slug', 'volunteer-open-day')->firstOrFail();
        $event->forceFill(['starts_at' => now()->subHours(2)])->save();

        $attendee = $this->makeUser('حاضر');

        $registration = EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'ticket_code' => str()->upper(str()->random(10)),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.events.check-in', $event), [
                'code' => $attendee->code,
                'attendance_code' => $event->attendance_code,
            ])
            ->assertRedirect();

        $this->assertTrue((bool) $registration->fresh()->attended);
        $this->assertSame(200.0, Integrations::balance($attendee->fresh(), 'xp'));
    }

    /** الكود الخاطئ لا يسجّل حضورًا ولا يصرف شيئًا. */
    public function test_check_in_rejects_a_wrong_attendance_code(): void
    {
        $admin = $this->grant($this->makeUser(), 'events.list', 'event_attendance.create');
        $event = Event::where('slug', 'content-workshop')->firstOrFail();
        $attendee = $this->makeUser('حاضر');

        $registration = EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'ticket_code' => str()->upper(str()->random(10)),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.events.check-in', $event), [
                'code' => $attendee->code,
                'attendance_code' => '000000',
            ])
            ->assertRedirect();

        $this->assertFalse((bool) $registration->fresh()->attended);
        $this->assertSame(0.0, Integrations::balance($attendee->fresh(), 'xp'));
    }

    /** الإصدار التلقائيّ يمرّ على المستحقّين ويُصدر لهم دفعةً واحدة. */
    public function test_auto_issue_runs_over_all_eligible_memberships(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'volunteer_certificates.create');

        // كلّ عضويّات السيدر مضى عليها أكثر من الحدّ الأدنى
        $this->assertGreaterThan(0, CertificateEligibility::pending(50)->count());

        $this->actingAs($admin)
            ->post(route('admin.volunteer.certificates.auto-issue'))
            ->assertRedirect();

        $this->assertSame(0, CertificateEligibility::pending(50)->count());
    }
}
