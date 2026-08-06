<?php

namespace Tests\Feature\Announcements;

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
 * فلاتر إشعارات التطوّع (13): الفئة (من الموجود فعلًا) · «الكلّ/يحتاج إجراء» ·
 * غير المقروء · وانتهاء المهلة يُسقط زرّ الفعل ويستبدل رمز الخطر ببشارة نصّيّة.
 */
class NotificationFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AnnouncementDemoSeeder::class);
    }

    private function makeVolunteer(): User
    {
        $user = User::create([
            'name' => 'متطوّع اختبار',
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);

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

    /** @return array<int, string> */
    private function listedTitles($response): array
    {
        return collect($response->viewData('groups'))->flatten(1)->pluck('title')->all();
    }

    public function test_category_bucket_maps_raw_values_correctly(): void
    {
        $this->assertSame('tasks', Notifier::categoryBucket('task'));
        $this->assertSame('tasks', Notifier::categoryBucket('task_blocked'));
        $this->assertSame('meetings', Notifier::categoryBucket('meeting.attendance_registered'));
        $this->assertSame('transactions', Notifier::categoryBucket('objection'));
        $this->assertSame('escalations', Notifier::categoryBucket('escalation'));
        $this->assertNull(Notifier::categoryBucket('certificate'));
    }

    /** ⭐ الفلتر يعرض فقط الفئات الموجودة فعلًا في إشعارات هذا المستخدم (13) */
    public function test_only_categories_present_in_my_own_notifications_appear_in_the_filter(): void
    {
        $volunteer = $this->makeVolunteer();

        Notifier::send($volunteer, 'task', 'مهمّة', null, null, 'volunteer');

        $response = $this->actingAs($volunteer)->get(route('notifications.index', ['tab' => 'volunteer']))->assertOk();

        $this->assertSame(['tasks' => 'مهامّ'], $response->viewData('availableCategories'));
        $response->assertDontSee('مساهمات ونقاط تفتيش'); // لن يظهر في الفلتر — لا إشعار من هذه الفئة
    }

    public function test_category_filter_narrows_the_list(): void
    {
        $volunteer = $this->makeVolunteer();

        Notifier::send($volunteer, 'task', 'إشعار مهمّة', null, null, 'volunteer');
        Notifier::send($volunteer, 'escalation', 'إشعار تصعيد', null, null, 'volunteer');

        $response = $this->actingAs($volunteer)
            ->get(route('notifications.index', ['tab' => 'volunteer', 'category' => 'tasks']))
            ->assertOk();

        $this->assertSame(['إشعار مهمّة'], $this->listedTitles($response));
    }

    /** فئة لا تخصّ هذا المستخدم تُتجاهَل بصمت — لا 403 ولا فلترة زائفة (2.15-أ-7) */
    public function test_a_category_not_belonging_to_me_is_silently_ignored(): void
    {
        $volunteer = $this->makeVolunteer();
        Notifier::send($volunteer, 'task', 'إشعار مهمّة', null, null, 'volunteer');

        $response = $this->actingAs($volunteer)
            ->get(route('notifications.index', ['tab' => 'volunteer', 'category' => 'recruitment']))
            ->assertOk();

        $this->assertNull($response->viewData('category'));
        $this->assertSame(['إشعار مهمّة'], $this->listedTitles($response));
    }

    /** ⭐ مبدّل «الكلّ / يحتاج إجراء» (13) */
    public function test_needs_action_toggle_shows_only_action_required_rows(): void
    {
        $volunteer = $this->makeVolunteer();

        Notifier::send($volunteer, 'task', 'يحتاج إجراء', null, '/x', 'volunteer', null, true);
        Notifier::send($volunteer, 'task', 'إعلاميّ فقط', null, null, 'volunteer', null, false);

        $response = $this->actingAs($volunteer)
            ->get(route('notifications.index', ['tab' => 'volunteer', 'need_action' => 1]))
            ->assertOk();

        $this->assertSame(['يحتاج إجراء'], $this->listedTitles($response));
    }

    /** ⭐ فلتر «غير المقروء فقط» (13) */
    public function test_unread_only_filter(): void
    {
        $volunteer = $this->makeVolunteer();

        $read = Notifier::send($volunteer, 'task', 'مقروء بالفعل', null, null, 'volunteer');
        Notifier::markRead($read);
        Notifier::send($volunteer, 'task', 'لسّه ما اتقراش', null, null, 'volunteer');

        $response = $this->actingAs($volunteer)
            ->get(route('notifications.index', ['tab' => 'volunteer', 'unread' => 1]))
            ->assertOk();

        $this->assertSame(['لسّه ما اتقراش'], $this->listedTitles($response));
    }

    /** ⭐ منتهية المهلة: بلا زرّ فعل — «الصفّ يبقى مقروءًا بشارة انتهت المهلة» (13) */
    public function test_an_expired_deadline_row_loses_its_action_button(): void
    {
        $volunteer = $this->makeVolunteer();

        $notification = Notifier::send($volunteer, 'task', 'فات ميعادها', null, '/x', 'volunteer', now()->subHour(), true);

        $row = $this->renderRow($notification);

        $this->assertStringContainsString('انتهت المهلة', $row);
        // زرّ الفعل المباشر رابطٌ `<a href="...">نفّذ الآن</a>` — يختفي بعد انتهاء المهلة
        $this->assertDoesNotMatchRegularExpression('/<a\s+href="\/x"[^>]*>نفّذ الآن<\/a>/u', $row);
    }

    /** وفي المقابل: طلبٌ لسّه في مهلته يبقى بزرّه كما هو (لا كسرٌ للحالة السليمة) */
    public function test_a_still_valid_deadline_keeps_its_action_button(): void
    {
        $volunteer = $this->makeVolunteer();

        $notification = Notifier::send($volunteer, 'task', 'لسّه في الوقت', null, '/x', 'volunteer', now()->addDay(), true);

        $row = $this->renderRow($notification);

        $this->assertMatchesRegularExpression('/<a\s+href="\/x"[^>]*>نفّذ الآن<\/a>/u', $row);
    }

    /** يعزل الصفّ عن ضجيج الصفحة الكاملة (بوب-أب الجرس يعرض نفس الإشعار برابطه الخاصّ أيضًا) */
    private function renderRow($notification): string
    {
        return view('notifications.partials.row', [
            'notification' => $notification,
            'actionLabel' => (string) setting('notifications.action.default_label', 'نفّذ الآن'),
        ])->render();
    }
}
