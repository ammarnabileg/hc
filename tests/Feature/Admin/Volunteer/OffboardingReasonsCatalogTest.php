<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Offboarding;
use App\Services\Admin\Volunteer\OffboardingService;

/**
 * أسباب إنهاء الخدمة صارت قائمة مقنَّنة يديرها الأدمن (§س-ي) — لا نصّ حرّ
 * كما كان. السبب نفسه يظلّ **لا يُنشَر للفريق** (23-0.2 · §س-هـ) — هذا
 * الاختبار يغطّي القائمة المدارة والتحقّق من الاختيار وحده.
 */
class OffboardingReasonsCatalogTest extends AdminVolunteerTestCase
{
    public function test_default_reasons_list_is_seeded_and_readable(): void
    {
        $reasons = OffboardingService::reasons();

        $this->assertNotEmpty($reasons);
        $this->assertContains('أخرى', $reasons);
    }

    public function test_offboarding_screen_renders_the_reasons_as_options(): void
    {
        $admin = $this->grant($this->makeUser(), 'offboarding.view', 'offboarding.create');

        $response = $this->actingAs($admin)->get(route('admin.volunteer.offboarding'));

        $response->assertOk();

        foreach (OffboardingService::reasons() as $reason) {
            $response->assertSee($reason, false);
        }
    }

    public function test_open_accepts_a_reason_from_the_managed_list(): void
    {
        $admin = $this->grant($this->makeUser(), 'offboarding.create');
        $target = $this->makeUser('متطوّع مستقيل');

        $response = $this->actingAs($admin)->post(route('admin.volunteer.offboarding.open'), [
            'code' => $target->code,
            'type' => 'resignation',
            'reason' => 'لا وقت كافٍ',
        ]);

        $response->assertSessionDoesntHaveErrors('reason');
        $this->assertSame('لا وقت كافٍ', Offboarding::where('user_id', $target->id)->value('reason'));
    }

    public function test_open_rejects_an_arbitrary_free_text_reason(): void
    {
        $admin = $this->grant($this->makeUser(), 'offboarding.create');
        $target = $this->makeUser('متطوّع مستقيل');

        $response = $this->actingAs($admin)->post(route('admin.volunteer.offboarding.open'), [
            'code' => $target->code,
            'type' => 'resignation',
            'reason' => 'سبب مكتوب بحرّيّة مش من القائمة',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(0, Offboarding::where('user_id', $target->id)->count());
    }

    public function test_open_still_allows_no_reason_at_all(): void
    {
        $admin = $this->grant($this->makeUser(), 'offboarding.create');
        $target = $this->makeUser('متطوّع مستقيل');

        $response = $this->actingAs($admin)->post(route('admin.volunteer.offboarding.open'), [
            'code' => $target->code,
            'type' => 'resignation',
        ]);

        $response->assertSessionDoesntHaveErrors('reason');
        $this->assertSame(1, Offboarding::where('user_id', $target->id)->count());
    }
}
