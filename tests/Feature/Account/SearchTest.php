<?php

namespace Tests\Feature\Account;

use App\Services\Account\UserSearch;

/**
 * صفحة البحث الكبيرة (الدستور 13.1 · 24.5).
 */
class SearchTest extends AccountTestCase
{
    public function test_all_filter_is_exclusive_in_both_directions(): void
    {
        // ⭐ اختيار «الكلّ» يلغي باقي الحقول
        $this->assertSame(['all'], UserSearch::normalizeFields(['all', 'code', 'phone'], 'all'));

        // ⭐ واختيار أيّ حقل محدَّد يلغي «الكلّ»
        $this->assertSame(['code', 'phone'], UserSearch::normalizeFields(['all', 'code', 'phone'], 'code'));

        // بلا اختيار: الافتراضيّ أبسط شيء (2.15-أ-10)
        $this->assertSame(['all'], UserSearch::normalizeFields([]));

        // «الكلّ» وحده يبقى كما هو، والحقول وحدها كذلك
        $this->assertSame(['all'], UserSearch::normalizeFields(['all']));
        $this->assertSame(['name'], UserSearch::normalizeFields(['name'], 'name'));
    }

    public function test_all_filter_is_exclusive_through_the_request(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('search', ['q' => 'محمد', 'fields' => ['all', 'code'], 'last' => 'code']))
            ->assertOk()
            ->assertViewHas('fields', ['code']);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'محمد', 'fields' => ['all', 'code'], 'last' => 'all']))
            ->assertOk()
            ->assertViewHas('fields', ['all']);
    }

    public function test_results_never_expose_sensitive_data(): void
    {
        $viewer = $this->trainee();
        $target = $this->trainee([
            'name' => 'سلمى حسن',
            'email' => 'salma.search@test.local',
            'phone' => '+201111222333',
            'code' => 'USALMA01',
        ]);

        // البحث بالبريد وسيلة وصول فقط: يوصّل للبروفايل العامّ بلا عرض البيانات
        $response = $this->actingAs($viewer)->get(route('search.more', [
            'q' => 'salma.search@test.local', 'fields' => ['email'], 'last' => 'email', 'offset' => 0,
        ]));

        $response->assertOk()
            ->assertSee($target->name)
            ->assertSee('#'.$target->code)
            ->assertDontSee('salma.search@test.local')
            ->assertDontSee('+201111222333');

        // ولا يخرج من الخدمة أصلًا أيّ عمود حسّاس
        $row = $response->viewData('results')->first();
        $this->assertNull($row->phone);
        $this->assertNull($row->email);
    }

    public function test_progressive_scroll_returns_page_size_at_a_time(): void
    {
        $viewer = $this->trainee();

        for ($i = 0; $i < 9; $i++) {
            $this->trainee(['name' => 'مستخدم بحث '.$i]);
        }

        $size = UserSearch::pageSize();
        $this->assertSame(6, $size); // 6 مستخدمين في المرّة (13.1)

        $first = $this->actingAs($viewer)->get(route('search', ['q' => 'مستخدم بحث', 'fields' => ['name'], 'last' => 'name']));
        $first->assertOk();
        $this->assertCount($size, $first->viewData('results'));
        $this->assertTrue($first->viewData('hasMore'));

        $more = $this->actingAs($viewer)->get(route('search.more', [
            'q' => 'مستخدم بحث', 'fields' => ['name'], 'last' => 'name', 'offset' => $size,
        ]));
        $more->assertOk();
        $this->assertCount(3, $more->viewData('results'));
    }

    public function test_pending_account_cannot_browse_people(): void
    {
        $pending = $this->trainee(['status' => 'pending']);

        $this->actingAs($pending)->get(route('search', ['q' => 'أيّ حد']))->assertForbidden();
    }
}
