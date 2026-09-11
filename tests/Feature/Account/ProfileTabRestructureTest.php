<?php

namespace Tests\Feature\Account;

use App\Models\Attestation;
use App\Models\Cv;
use App\Services\Account\ProfileTabs;

/**
 * تصحيح بنية تابات البروفايل (10.0-د · 10.0-أ · 9.1 — بنودٌ نهائيّة ✅):
 * أربعة تابات فقط بترتيبها المنصوص، الكروت السبعة كاملةً في «نظرة عامّة»،
 * و«خبراتي» = الـCV + الإفادة إن وُجدت.
 */
class ProfileTabRestructureTest extends AccountTestCase
{
    /** ⛔ 10.0-د: أربعة تابات فقط، بلا تاب «تفاصيل» المستحدَث، وبترتيبها النهائيّ */
    public function test_only_the_four_final_tabs_exist_in_order(): void
    {
        $owner = $this->trainee(['code' => 'URESTR01']);

        $response = $this->actingAs($owner)->get(route('profile.me'));

        $response->assertOk()
            ->assertSeeInOrder(['نظرة عامّة', 'الإنجازات', 'الشهادات', 'خبراتي'])
            ->assertDontSee('تفاصيل');

        $this->assertSame(
            ['overview', 'achievements', 'certificates', 'experience'],
            array_column(ProfileTabs::definitions(), 'key'),
        );

        // زيارة الرابط القديم `tab=details` لا تنكسر — تسقط لـ«نظرة عامّة» الافتراضيّة (لا تاب خامس)
        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'details']))
            ->assertOk()
            ->assertViewHas('tab', 'overview')
            ->assertViewHas('overview');
    }

    /** ⭐ 10.0-أ: الكروت السبعة كاملةً في «نظرة عامّة» — لا أربعة فقط */
    public function test_overview_shows_all_seven_kpi_cards(): void
    {
        $owner = $this->trainee(['code' => 'URESTR02']);

        $response = $this->actingAs($owner)->get(route('profile.me'));

        $response->assertOk();

        $kpis = $response->viewData('overview')['kpis'];

        $this->assertCount(7, $kpis);
        $this->assertSame(
            ['level', 'tickets', 'streak', 'certificates', 'courses', 'rank', 'ambassador'],
            array_column($kpis, 'key'),
        );

        $response
            ->assertSee('مستوى الحساب + XP', false)
            ->assertSee('رصيد التذاكر', false)
            ->assertSee('ستريك نادي الخامسة', false)
            ->assertSee('التدريبات (مكتملة/جارية)', false)
            ->assertSee('ترتيب الليدر بورد', false)
            ->assertSee('لقب السفير', false);
    }

    /** ⭐ 9.1 · 10.0-أ: بطاقة الإفادة تظهر في «خبراتي» لمن له إفادة صادرة فعلًا */
    public function test_experience_tab_shows_the_attestation_card_when_one_was_issued(): void
    {
        $owner = $this->trainee(['name' => 'هبة إبراهيم', 'code' => 'URESTR03']);

        Cv::create([
            'user_id' => $owner->id,
            'completion_percent' => 60,
            'data' => ['profile' => ['summary' => 'مصمّمة تجربة مستخدم.']],
        ]);

        Attestation::create([
            'user_id' => $owner->id,
            'from_name' => 'مؤسّسة رسالة',
            'status' => 'approved',
            'body' => 'هبة موظّفة ملتزمة ومتميّزة في عملها.',
        ]);

        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'experience']))
            ->assertOk()
            ->assertSee(setting('account.profile.experience.attestation_title', 'الإفادة من المنصّة'), false)
            ->assertSee('هبة موظّفة ملتزمة ومتميّزة في عملها.', false)
            ->assertSee(setting('attestations.status.approved_label', 'صدرت'), false);
    }

    /** ⛔ وتغيب تمامًا لمن ليس له إفادة صادرة — لا بطاقة فارغة (2.15-د) */
    public function test_experience_tab_hides_the_attestation_card_without_one(): void
    {
        $owner = $this->trainee(['code' => 'URESTR04']);

        Cv::create([
            'user_id' => $owner->id,
            'completion_percent' => 30,
            'data' => ['profile' => ['summary' => 'بلا إفادة لسّه.']],
        ]);

        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'experience']))
            ->assertOk()
            ->assertDontSee(setting('account.profile.experience.attestation_title', 'الإفادة من المنصّة'));
    }

    /** ⛔ الطلب «قيد الانتظار» ليس إفادةً صادرة — لا يظهر كأنّه صدر (9.1) */
    public function test_a_pending_attestation_request_does_not_count_as_issued(): void
    {
        $owner = $this->trainee(['code' => 'URESTR05']);

        Cv::create([
            'user_id' => $owner->id,
            'completion_percent' => 30,
            'data' => ['profile' => ['summary' => 'طلب لسّه ماتردّش عليه.']],
        ]);

        Attestation::create([
            'user_id' => $owner->id,
            'from_name' => 'جهة ما',
            'status' => 'requested',
        ]);

        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'experience']))
            ->assertOk()
            ->assertDontSee(setting('account.profile.experience.attestation_title', 'الإفادة من المنصّة'));
    }
}
