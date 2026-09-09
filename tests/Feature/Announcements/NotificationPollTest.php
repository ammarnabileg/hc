<?php

namespace Tests\Feature\Announcements;

use App\Models\AppNotification;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * ⭐ استطلاع الإشعارات اللحظيّ — Toast (2.8): «إشعار لحظيّ للأحداث المهمّة +
 * سجلّ دائم في المركز». السجلّ الدائم كان موجودًا؛ الفجوة كانت أنّ لا شيء
 * يدفع Toast للمستخدم الجالس على الصفحة وقت وقوع الحدث.
 */
class NotificationPollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'مستخدم اختبار',
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);
    }

    private function row(User $user, array $overrides = []): AppNotification
    {
        return AppNotification::create($overrides + [
            'user_id' => $user->id,
            'layer' => 'platform',
            'category' => 'task',
            'title' => 'إشعار اختبار',
            'requires_action' => false,
        ]);
    }

    public function test_poll_returns_only_notifications_created_after_the_given_id(): void
    {
        $user = $this->makeUser();
        $first = $this->row($user, ['requires_action' => true]);
        $second = $this->row($user, ['requires_action' => true]);

        $response = $this->actingAs($user)->getJson(route('notifications.poll', ['after_id' => $first->id]));

        $response->assertOk();
        $ids = collect($response->json('items'))->pluck('id');

        $this->assertFalse($ids->contains($first->id));
        $this->assertTrue($ids->contains($second->id));
        $this->assertSame($second->id, $response->json('last_id'));
    }

    public function test_a_user_never_sees_another_users_notifications(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $this->row($owner, ['requires_action' => true]);

        $response = $this->actingAs($stranger)->getJson(route('notifications.poll', ['after_id' => 0]));

        $response->assertOk();
        $this->assertSame([], $response->json('items'));
    }

    /** «للأحداث المهمّة» — لا لكلّ إشعار، وإلّا أغرق مسار التطوّع المستخدم بلا توقّف */
    public function test_only_notifications_that_require_action_toast_by_default(): void
    {
        $user = $this->makeUser();
        $quiet = $this->row($user, ['requires_action' => false]);
        $urgent = $this->row($user, ['requires_action' => true]);

        $response = $this->actingAs($user)->getJson(route('notifications.poll', ['after_id' => 0]));

        $ids = collect($response->json('items'))->pluck('id');

        $this->assertFalse($ids->contains($quiet->id), 'إشعارٌ لا يحتاج إجراءً ولا نوعه محكوم — لا يستحقّ Toast.');
        $this->assertTrue($ids->contains($urgent->id));
        // ولكن last_id يتقدّم فوق الاثنين معًا — وإلّا أُعيد جلب نفس الصفّ الهادئ كلّ نبضة
        $this->assertSame($urgent->id, $response->json('last_id'));
    }

    /** عمود «Toast» في مصفوفة 24.3 — نوعٌ محكوم مفتوحٌ له صراحةً يستحقّ Toast رغم أنّه لا يحتاج إجراءً */
    public function test_a_governed_category_toasts_when_its_matrix_column_is_switched_on(): void
    {
        $user = $this->makeUser();
        Setting::updateOrCreate(
            ['key' => 'notifications.matrix.wallet.toast'],
            ['group' => 'notifications', 'label_ar' => 'محفظة — Toast', 'type' => 'bool', 'default_value' => '0', 'value' => '1'],
        );
        Cache::forget('settings');

        $notification = $this->row($user, ['category' => 'wallet', 'requires_action' => false]);

        $response = $this->actingAs($user)->getJson(route('notifications.poll', ['after_id' => 0]));

        $this->assertTrue(collect($response->json('items'))->pluck('id')->contains($notification->id));
    }

    /** ونفس النوع بلا فتح العمود صراحةً — لا يستحقّ Toast (الافتراض 24.3: بريد/Toast موقوفان) */
    public function test_a_governed_category_does_not_toast_without_an_explicit_setting(): void
    {
        $user = $this->makeUser();
        $notification = $this->row($user, ['category' => 'wallet', 'requires_action' => false]);

        $response = $this->actingAs($user)->getJson(route('notifications.poll', ['after_id' => 0]));

        $this->assertFalse(collect($response->json('items'))->pluck('id')->contains($notification->id));
    }

    public function test_poll_reports_the_current_unread_count(): void
    {
        $user = $this->makeUser();
        $this->row($user, ['requires_action' => true]);
        $this->row($user, ['requires_action' => true, 'read_at' => now()]);

        $response = $this->actingAs($user)->getJson(route('notifications.poll', ['after_id' => 0]));

        $response->assertOk()->assertJson(['unread' => 1]);
    }
}
