<?php

namespace Tests\Feature\Account;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Track;
use App\Models\User;
use App\Services\Account\UserSearch;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;

/**
 * صفحة البحث الكبيرة (الدستور 13.1 · 24.5).
 */
class SearchTest extends AccountTestCase
{
    /** عضويّة نشطة لهذا المستخدم تحت أبلاين اختياريّ — بها يُقاس النطاق (12.2.1-ب) */
    private function placeIn(User $user, ?Membership $upline = null): Membership
    {
        $track = Track::firstOrCreate(['key' => 'main'], ['name_ar' => 'المسار الرئيسيّ']);
        $entity = Entity::firstOrCreate(
            ['track_id' => $track->id, 'name_ar' => 'قسم البحث'],
            ['status' => 'active'],
        );

        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::where('key', 'coordinator')->value('id'),
            'upline_id' => $upline?->id,
            'is_primary' => true,
            'started_at' => now()->subMonth(),
            'status' => 'active',
        ]);
    }

    /** إسناد فرديّ فوق الأدوار — به نضبط **نطاق** الباحث بلا اختراع صلاحيّة */
    private function grant(User $user, string $key, string $scope, string $effect = 'allow'): void
    {
        DB::table('permission_user')->insert([
            'user_id' => $user->id,
            'permission_id' => Permission::where('key', $key)->value('id'),
            'scope' => $scope,
            'effect' => $effect,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AccessEngine::class)->forget($user);
    }

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

    /**
     * ⛔ التسريب الأوّل: الدليل كان يعرض **كلّ** مَن يطابق الاسم مهما كانت حاله —
     * والكارت في 24.5 حاله **«فعّال»** وحده. فالمحظور والموقوف والمرفوض ومَن هو
     * تحت المراجعة **لا يخرجون من الاستعلام أصلًا**.
     */
    public function test_banned_and_suspended_never_surface_in_results(): void
    {
        $viewer = $this->trainee();

        $active = $this->trainee(['name' => 'سامي الحالة', 'code' => 'USAMI001']);

        foreach (['banned' => 'UBAN0001', 'suspended' => 'USUS0001', 'rejected' => 'UREJ0001', 'pending' => 'UPEN0001'] as $status => $code) {
            $this->trainee(['name' => 'سامي الحالة', 'code' => $code, 'status' => $status]);
        }

        $response = $this->actingAs($viewer)->get(route('search', [
            'q' => 'سامي الحالة', 'fields' => ['name'], 'last' => 'name',
        ]));

        $response->assertOk();

        $codes = $response->viewData('results')->pluck('code')->all();

        $this->assertSame(['USAMI001'], $codes, 'الفعّال وحده يظهر في الدليل.');
        $this->assertSame(1, $response->viewData('total'));

        $response->assertDontSee('UBAN0001')
            ->assertDontSee('USUS0001')
            ->assertDontSee('UREJ0001')
            ->assertDontSee('UPEN0001')
            // ولا تُرسَم لهم حالةٌ رماديّة: ما لا يجوز ظهوره يُخفى لا يُعطَّل (2.15-أ-7)
            ->assertDontSee('غير فعّال');

        // ولا يُطالُ بالكود المباشر أيضًا — الحصر على الاستعلام لا على حقل الاسم
        $this->actingAs($viewer)
            ->get(route('search', ['q' => 'UBAN0001', 'fields' => ['code'], 'last' => 'code']))
            ->assertOk()
            ->assertViewHas('total', 0);
    }

    /**
     * ⛔ التسريب الثاني: **نفس الاستعلام يعطي نفس القائمة لكلّ من طلبها** — بلا
     * أيّ حصرٍ بنطاق الباحث. والنطاق **إلزاميّ مع كلّ صلاحيّة** (12.2.1-ب):
     * صاحب `user_search.list@SUBTREE` يرى مَن تحته وحدهم، لا دليل المنصّة كلّه.
     */
    public function test_results_are_bound_to_the_searcher_scope(): void
    {
        // باحثٌ بلا دور المتدرّب: نطاقه يأتي من إسناده الفرديّ وحده
        $viewer = User::create([
            'name' => 'ليلى الباحثة', 'email' => 'scope.viewer@test.local', 'password' => 'secret-password',
            'code' => 'USCOPE01', 'status' => 'active',
        ]);
        $this->grant($viewer, 'user_search.view', 'ALL');
        $this->grant($viewer, 'user_search.list', 'SUBTREE');

        $context = $this->placeIn($viewer);

        $downline = $this->trainee(['name' => 'طارق النطاق', 'code' => 'UDOWN001']);
        $this->placeIn($downline, $context);

        $stranger = $this->trainee(['name' => 'طارق النطاق', 'code' => 'USTRA001']);

        $response = $this->actingAs($viewer)->get(route('search', [
            'q' => 'طارق النطاق', 'fields' => ['name'], 'last' => 'name',
        ]));

        $response->assertOk();

        $codes = $response->viewData('results')->pluck('code')->all();

        $this->assertSame(['UDOWN001'], $codes, 'مَن تحته وحده داخل نطاقه.');
        $this->assertSame(1, $response->viewData('total'));
        $response->assertDontSee($stranger->code);

        // والدليل نفسه يبقى كاملًا لصاحب النطاق الواسع — فالحصر نطاقٌ لا حجبٌ عامّ
        $wide = $this->actingAs($this->trainee())->get(route('search', [
            'q' => 'طارق النطاق', 'fields' => ['name'], 'last' => 'name',
        ]));

        $this->assertSame(2, $wide->viewData('total'));
    }

    /** ⛔ التسريب الثالث: المسار كان **بلا أيّ مفتاح** — والصلاحيّة إلزاميّة (12.2.1) */
    public function test_search_is_shut_for_whoever_lacks_its_permission(): void
    {
        $stranger = User::create([
            'name' => 'زائر بلا مفتاح', 'email' => 'nokey.search@test.local', 'password' => 'secret-password',
            'code' => 'UNOKEY01', 'status' => 'active',
        ]);

        $this->actingAs($stranger)->get(route('search', ['q' => 'أيّ حد']))->assertForbidden();
        $this->actingAs($stranger)->get(route('search.more', ['q' => 'أيّ حد', 'offset' => 0]))->assertForbidden();

        // والمنع الصريح يغلب الإذن ولو حمل صاحبه دور المتدرّب (12.2.1-ز-1)
        $denied = $this->trainee(['code' => 'UDENY001']);
        $this->grant($denied, 'user_search.view', 'ALL', 'deny');

        $this->actingAs($denied)->get(route('search', ['q' => 'أيّ حد']))->assertForbidden();
    }
}
