<?php

namespace Tests\Feature\Library;

use App\Models\Attestation;

/** الإفادة (9.1 · 24.5): الطلب والقائمة بالحالة ومكان الظهور والرابط العامّ. */
class AttestationTest extends LibraryTestCase
{
    public function test_page_shows_request_action_and_placements(): void
    {
        $user = $this->trainee('UATT0001');

        $this->actingAs($user)->get(route('attestations.index'))
            ->assertOk()
            ->assertSee(setting('attestations.request.action_label', 'اطلب إفادة'), false)
            ->assertSee(setting('attestations.placements.title', 'بتظهر فين؟'), false)
            ->assertSee(setting('attestations.empty.message', 'مفيش إفادات لسّه'), false);
    }

    public function test_request_is_stored_with_its_status(): void
    {
        $user = $this->trainee('UATT0002');

        $this->actingAs($user)->post(route('attestations.store'), [
            'from_name' => 'أ. منى عبد الرحمن',
            'reason' => 'التقديم على وظيفة محلّل بيانات',
            'note' => 'محتاجها قبل آخر الشهر.',
        ])->assertRedirect();

        $attestation = Attestation::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('requested', $attestation->status);
        $this->assertStringContainsString('محلّل بيانات', $attestation->body);

        $this->actingAs($user)->get(route('attestations.index'))
            ->assertSee(setting('attestations.status.requested_label', 'قيد الانتظار'), false);
    }

    public function test_open_requests_are_capped_by_setting(): void
    {
        $user = $this->trainee('UATT0003');
        $max = (int) setting('attestations.request.max_open', 3);

        for ($i = 0; $i < $max; $i++) {
            Attestation::create(['user_id' => $user->id, 'from_name' => 'جهة '.$i, 'status' => 'requested']);
        }

        $this->actingAs($user)->post(route('attestations.store'), [
            'from_name' => 'جهة زائدة',
            'reason' => 'طلب فوق السقف',
        ])->assertRedirect();

        $this->assertSame($max, Attestation::where('user_id', $user->id)->count());
    }

    public function test_public_link_and_export_are_free_of_tickets(): void
    {
        $user = $this->trainee('UATT0004', 'هدى كامل');

        $this->get(route('attestations.public', $user->code))
            ->assertOk()
            ->assertSee('هدى كامل', false)
            ->assertSee(setting('attestations.sheet.title', 'إفادة من المنصّة'), false);

        $this->actingAs($user)->get(route('attestations.export'))->assertOk();
    }
}
