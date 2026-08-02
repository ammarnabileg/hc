<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Complaint;
use App\Services\Account\ComplaintService;
use Illuminate\Support\Facades\Cache;

/**
 * «الأسباب قابلة للإدارة من لوحة الأدمن (إضافة/تعديل/حذف)» — الدستور 11.
 *
 * ⚠️ لم تكن هناك شاشة تحرير أصلًا: الموجود دروب-داون **فلترة** فقط.
 */
class AdminComplaintReasonsTest extends AdminContentTestCase
{
    public function test_reasons_screen_opens_with_the_current_list(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.guidance.complaint_reasons'))
            ->assertOk()
            ->assertSee(setting('complaints.reasons.page_title', 'أسباب الشكاوى والمقترحات'), false)
            ->assertSee('المنصّة', false);
    }

    public function test_admin_adds_edits_and_deletes_reasons(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.guidance.complaint_reasons.update'), [
            'reasons' => ['المنصّة', 'سبب جديد', '', 'سبب جديد'],
        ])->assertRedirect();

        Cache::forget('settings');

        // الفراغات تُحذَف والمكرّر يُدمَج — والقائمة هي ما كتبه الأدمن بالضبط
        $this->assertSame(['المنصّة', 'سبب جديد'], ComplaintService::categories());

        // والحذف يُقلّص القائمة فعلًا
        $this->actingAs($admin)->put(route('admin.guidance.complaint_reasons.update'), [
            'reasons' => ['المنصّة'],
        ])->assertRedirect();

        Cache::forget('settings');

        $this->assertSame(['المنصّة'], ComplaintService::categories());
    }

    public function test_an_empty_list_is_refused_so_the_form_never_loses_its_choices(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.guidance.complaint_reasons.update'), ['reasons' => []])
            ->assertSessionHasErrors('reasons');
    }

    public function test_screen_shows_how_many_tickets_use_each_reason(): void
    {
        $user = $this->makeUser();

        Complaint::create([
            'number' => 'TK-RS-1', 'user_id' => $user->id, 'type' => 'complaint',
            'category' => 'المنصّة', 'title' => 'عطل', 'body' => 'وصف العطل.', 'status' => 'open',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.guidance.complaint_reasons'))
            ->assertOk()
            ->assertViewHas('inUse', fn (array $inUse) => ($inUse['المنصّة'] ?? 0) === 1);
    }
}
