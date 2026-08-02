<?php

namespace Tests\Feature\Announcements;

use App\Models\AppNotification;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Track;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Database\Seeders\AnnouncementDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * مركز الإشعارات — الصفحة الكاملة (2.8): ثلاثة تابات وتجميع زمنيّ ومهل ملوّنة.
 */
class AnnouncementsNotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AnnouncementDemoSeeder::class);
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

    private function makeVolunteer(): User
    {
        $user = $this->makeUser();

        $entity = Entity::create([
            'track_id' => Track::where('key', 'department')->value('id'),
            'name_ar' => 'كيان اختبار',
            'status' => 'active',
        ]);

        Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::where('key', 'coordinator')->value('id'),
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);

        return $user;
    }

    /** تاب التطوّع لا يظهر إلّا للمتطوّعين — وغيرُهم يُردّ إلى «الكلّ» (2.8). */
    public function test_volunteer_tab_is_hidden_from_non_volunteers(): void
    {
        $trainee = $this->makeUser();
        $volunteer = $this->makeVolunteer();

        Notifier::send($trainee, 'system', 'إشعار منصّة');
        Notifier::send($volunteer, 'task', 'مهمّة تطوّع', null, null, 'volunteer');

        $this->actingAs($trainee)->get(route('notifications.index'))
            ->assertSee('المنصّة')
            ->assertDontSee('التطوّع');

        $this->actingAs($volunteer)->get(route('notifications.index'))
            ->assertSee('التطوّع');
    }

    /** التاب يفلتر بالطبقة فعلًا — لا بصريًّا فقط. */
    public function test_tabs_filter_by_layer(): void
    {
        $volunteer = $this->makeVolunteer();

        Notifier::send($volunteer, 'system', 'عنوان منصّة');
        Notifier::send($volunteer, 'task', 'عنوان تطوّع', null, null, 'volunteer');

        // نقرأ محتوى القائمة نفسها لا الصفحة كلّها — لأنّ جرس الهيدر يعرض الطبقتين معًا
        $this->assertSame(['عنوان منصّة'], $this->listedTitles($volunteer, 'platform'));
        $this->assertSame(['عنوان تطوّع'], $this->listedTitles($volunteer, 'volunteer'));

        // غير المتطوّع لو جرّب تاب التطوّع يرجع للكلّ بدل 403 مربك
        $trainee = $this->makeUser();
        Notifier::send($trainee, 'system', 'إشعاري أنا');

        $this->assertSame(['إشعاري أنا'], $this->listedTitles($trainee, 'volunteer'));
    }

    /** @return array<int, string> عناوين الصفوف المعروضة فعلًا في التاب */
    private function listedTitles(User $user, string $tab): array
    {
        $response = $this->actingAs($user)->get(route('notifications.index', ['tab' => $tab]));
        $response->assertOk();

        return collect($response->viewData('groups'))
            ->flatten(1)
            ->pluck('title')
            ->all();
    }

    /** التجميع الزمنيّ: اليوم · أمس · أقدم. */
    public function test_notifications_are_grouped_by_day(): void
    {
        $user = $this->makeUser();

        Notifier::send($user, 'system', 'إشعار النهارده');
        Notifier::send($user, 'system', 'إشعار امبارح')->forceFill(['created_at' => now()->subDay()])->save();
        Notifier::send($user, 'system', 'إشعار قديم')->forceFill(['created_at' => now()->subDays(5)])->save();

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertSee('اليوم')
            ->assertSee('أمس')
            ->assertSee('أقدم');
    }

    /** المهلة عدّاد ملوّن برمزه من قاموس الحالة (2.16) + صفّ «يحتاج إجراء» بزرّه. */
    public function test_deadline_badge_and_inline_action(): void
    {
        $user = $this->makeUser();

        Notifier::send($user, 'exam', 'مهلة قريبة', null, '/x', 'platform', now()->addHours(2), true);
        Notifier::send($user, 'exam', 'مهلة متّسعة', null, null, 'platform', now()->addDays(10));
        Notifier::send($user, 'exam', 'مهلة فاتت', null, null, 'platform', now()->subDay());

        $response = $this->actingAs($user)->get(route('notifications.index'))->assertOk();

        // الرموز الثلاثة من قاموس الحالة: ▲ انتبه · ● سليم · ◉ خطر
        $response->assertSee('▲')->assertSee('●')->assertSee('◉');

        // زرّ الإجراء المباشر داخل الصفّ
        $response->assertSee('نفّذ الآن');

        $this->assertSame('warn', Notifier::deadlineState(now()->addHours(2)));
        $this->assertSame('ok', Notifier::deadlineState(now()->addDays(10)));
        $this->assertSame('danger', Notifier::deadlineState(now()->subDay()));
        $this->assertNull(Notifier::deadlineState(null));
    }

    /** تعليم كمقروء: فرديّ وجماعيّ عبر POST — والجماعيّ داخل التاب المفتوح. */
    public function test_mark_read_single_and_bulk(): void
    {
        $volunteer = $this->makeVolunteer();

        $platform = Notifier::send($volunteer, 'system', 'إشعار منصّة');
        $volunteerNotification = Notifier::send($volunteer, 'task', 'إشعار تطوّع', null, null, 'volunteer');

        $this->actingAs($volunteer)->post(route('notifications.read', $platform))->assertRedirect();
        $this->assertNotNull($platform->fresh()->read_at);
        $this->assertNull($volunteerNotification->fresh()->read_at);

        $this->actingAs($volunteer)->post(route('notifications.read-all'), ['tab' => 'volunteer'])->assertRedirect();
        $this->assertNotNull($volunteerNotification->fresh()->read_at);
        $this->assertSame(0, Notifier::unreadCount($volunteer));
    }

    /** لا يقرأ أحدٌ إشعار غيره — الملكيّة هي الحارس. */
    public function test_cannot_mark_someone_elses_notification(): void
    {
        $mine = $this->makeUser();
        $other = $this->makeUser();

        $notification = Notifier::send($other, 'system', 'إشعار غيري');

        $this->actingAs($mine)
            ->post(route('notifications.read', $notification))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    /** الحالة الفارغة تشجّع ولا تعاتب (2.17-ج). */
    public function test_empty_state_message(): void
    {
        AppNotification::query()->delete();

        $this->actingAs($this->makeUser())
            ->get(route('notifications.index'))
            ->assertSee('مفيش إشعارات جديدة');
    }
}
