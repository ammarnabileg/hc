<?php

namespace Tests\Feature\Account;

use App\Models\Country;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Governorate;
use App\Models\StreakDay;
use App\Models\UserPrivacySetting;
use App\Services\Account\ProfileVisibility;

/**
 * البروفايل بطبقتيه ومستويات المشاهدة الأربعة (الدستور 10 · 10.0 · 13.4-م).
 */
class PublicProfileTest extends AccountTestCase
{
    private function located(array $attributes = [])
    {
        $country = Country::firstOrCreate(['iso2' => 'EG'], [
            'name_ar' => 'مصر', 'name_en' => 'Egypt', 'phone_code' => '20',
        ]);

        $governorate = Governorate::firstOrCreate(
            ['country_id' => $country->id, 'name_ar' => 'الإسكندريّة'],
            ['name_en' => 'Alexandria'],
        );

        return $this->trainee($attributes + [
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
        ]);
    }

    public function test_public_profile_hides_sensitive_fields_from_a_peer(): void
    {
        $owner = $this->located([
            'name' => 'منى عبد الرحمن',
            'email' => 'mona.profile@test.local',
            'phone' => '+201234567890',
            'code' => 'UMONA001',
        ]);

        $peer = $this->trainee();

        $response = $this->actingAs($peer)->get(route('u.profile', ['code' => $owner->code]));

        $response->assertOk()
            ->assertSee('منى عبد الرحمن')
            ->assertSee('#UMONA001')
            // الحسّاس مخفيّ افتراضيًّا (13.4-م)
            ->assertDontSee('mona.profile@test.local')
            ->assertDontSee('+201234567890')
            ->assertSee('مش متاح');

        $this->assertSame(ProfileVisibility::PEER, $response->viewData('level'));
    }

    public function test_owner_sees_own_sensitive_fields(): void
    {
        $owner = $this->located([
            'email' => 'owner.profile@test.local',
            'phone' => '+201098765432',
        ]);

        $this->actingAs($owner)->get(route('profile.me'))
            ->assertOk()
            ->assertSee('owner.profile@test.local')
            ->assertSee('+201098765432')
            ->assertViewHas('level', ProfileVisibility::OWNER);
    }

    public function test_governorate_is_always_public_even_to_a_guest(): void
    {
        $owner = $this->located(['name' => 'يوسف عبد الله', 'code' => 'UYOUS001']);

        // ⭐ المحافظة حقل عامّ دائمًا ولا يجوز إخفاؤها (12.14-د)
        // حتى لو حُشِر لها سطرُ خصوصيّة في القاعدة، فالعرض لا يتأثّر
        UserPrivacySetting::create([
            'user_id' => $owner->id, 'field' => 'governorate', 'visibility' => 'supervisors',
        ]);

        $this->get(route('u.profile', ['code' => $owner->code]))
            ->assertOk()
            ->assertSee('الإسكندريّة');
    }

    /**
     * ⛔ المستويات **أربعة** (10.0-ج · 13.4-م) وكلّها لأصحاب الحسابات: صاحبه ·
     * زميل · أبلاين مخوَّل · أدمن. والزائر بلا جلسة كان يُقحَم في «زميل» فيصير
     * خامسًا بابُه الإنترنت — فيقرأ حقلًا فتحه صاحبه «لكلّ **المستخدمين**»،
     * وهو ليس مستخدمًا أصلًا. ولا يبقى له إلّا **العامّ دائمًا** (12.14-د).
     */
    public function test_a_guest_falls_outside_the_four_levels(): void
    {
        $owner = $this->located(['email' => 'guest.leak@test.local', 'code' => 'UGUEST01']);

        UserPrivacySetting::create([
            'user_id' => $owner->id, 'field' => 'email', 'visibility' => 'all_users',
        ]);

        $visibility = app(ProfileVisibility::class);

        $this->assertNull($visibility->levelFor(null, $owner), 'الزائر ليس «زميلًا».');
        $this->assertFalse($visibility->canSee('email', null, $owner));
        $this->assertFalse($visibility->canSee('country', null, $owner));

        // ⭐ والمحافظة وحدها تبقى عامّة دائمًا ولا يجوز إخفاؤها
        $this->assertTrue($visibility->canSee('governorate', null, $owner));

        $this->get(route('u.profile', ['code' => $owner->code]))
            ->assertOk()
            ->assertDontSee('guest.leak@test.local')
            ->assertSee('الإسكندريّة');
    }

    public function test_peer_sees_a_field_opened_to_all_users(): void
    {
        $owner = $this->located(['email' => 'open.profile@test.local']);
        $peer = $this->trainee();

        UserPrivacySetting::create([
            'user_id' => $owner->id, 'field' => 'email', 'visibility' => 'all_users',
        ]);

        $this->actingAs($peer)->get(route('u.profile', ['code' => $owner->code]))
            ->assertOk()
            ->assertSee('open.profile@test.local');
    }

    public function test_tabs_are_ordered_and_leave_room_for_volunteer_tabs(): void
    {
        $owner = $this->located();

        $response = $this->actingAs($owner)->get(route('profile.me'));

        $response->assertOk()
            ->assertSeeInOrder(['نظرة عامّة', 'الإنجازات', 'الشهادات', 'خبراتي'])
            ->assertSee('نسخ رابطي');

        // التابات تُحمَّل كسولًا: تاب واحد فقط هو المحمَّل (2.15-د)
        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'certificates']))
            ->assertOk()
            ->assertViewHas('certificates')
            ->assertViewMissing('achievements');

        // مسارات الإنجازات الخمسة بعتبات 10.1
        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'achievements']))
            ->assertOk()
            ->assertSee('نادي الخامسة صباحًا')
            ->assertViewHas('achievements', fn ($tracks) => count($tracks) === 5);

        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'experience']))
            ->assertOk()
            ->assertViewHas('experience');
    }

    public function test_unknown_code_returns_not_found(): void
    {
        $this->get(route('u.profile', ['code' => 'UNOTHERE']))->assertNotFound();
    }

    /**
     * ⭐ 10.0-أ: بار تقدّم ونسبة لكلّ تدريب أخذه، وخريطة حراريّة للحضور —
     * كانا غائبين كلّيًّا عن تاب «نظرة عامّة».
     */
    public function test_overview_shows_course_progress_cards_and_an_attendance_heatmap(): void
    {
        $owner = $this->located();

        $course = Course::create([
            'slug' => 'excel-profile-test',
            'name_ar' => 'تدريب إكسل',
            'status' => 'published',
            'xp_max' => 300,
        ]);
        Enrollment::create([
            'user_id' => $owner->id,
            'course_id' => $course->id,
            'status' => 'active',
            'progress_percent' => 40,
        ]);

        StreakDay::create([
            'user_id' => $owner->id,
            'day' => now()->toDateString(),
            'club_5am' => true,
        ]);

        $response = $this->actingAs($owner)->get(route('profile.me'));

        $response->assertOk()
            ->assertSee('تدريب إكسل')
            ->assertSee('40%')
            ->assertSeeText(setting('account.profile.overview.heatmap_title', 'خريطة الحضور'));

        $this->assertStringContainsString('<svg', $response->getContent());
    }

    /** بلا تدريبات أو أيّام مسجّلة: القسم لا ينكسر ولا يعرض كارت فارغًا (2.15-د) */
    public function test_overview_hides_the_progress_section_when_there_is_nothing_to_show(): void
    {
        $owner = $this->located();

        $this->actingAs($owner)->get(route('profile.me'))
            ->assertOk()
            ->assertDontSee(setting('account.profile.overview.progress_title', 'تقدّم التدريبات'));
    }
}
