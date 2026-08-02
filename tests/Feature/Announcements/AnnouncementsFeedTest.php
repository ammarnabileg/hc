<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Notifications\AnnouncementFeed;
use Database\Seeders\AnnouncementDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * التعليمات (13.2 · 24.5): قناة بثّ اتّجاه واحد — كلّ اختبار يقابل قاعدةً منصوصة.
 */
class AnnouncementsFeedTest extends TestCase
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
            'name' => 'متدرّب اختبار',
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);
    }

    private function makeAnnouncement(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + [
            'title' => 'عنوان '.str()->random(5),
            'body' => 'نصّ المنشور',
            'status' => 'published',
            'audience' => ['type' => 'all'],
        ]);
    }

    /** الفيد يعرض المنشورات الحيّة، والمثبَّت أعلى القائمة (13.2). */
    public function test_feed_shows_live_announcements_with_pinned_first(): void
    {
        $user = $this->makeUser();

        $normal = $this->makeAnnouncement(['title' => 'منشور عاديّ']);
        $pinned = $this->makeAnnouncement(['title' => 'منشور مثبَّت', 'is_pinned' => true]);

        $response = $this->actingAs($user)->get(route('announcements.index'));

        $response->assertOk()
            ->assertSee('التعليمات')
            ->assertSee($pinned->title)
            ->assertSee($normal->title);

        $html = $response->getContent();
        $this->assertLessThan(
            strpos($html, $normal->title),
            strpos($html, $pinned->title),
            'المثبَّت لازم يظهر أعلى القائمة',
        );
    }

    /** المنشور المنتهي أو المجدول للمستقبل لا يظهر — أرشفة تلقائيّة (24.5). */
    public function test_expired_and_future_announcements_are_hidden(): void
    {
        $user = $this->makeUser();

        $expired = $this->makeAnnouncement(['title' => 'منشور منتهي', 'expires_at' => now()->subDay()]);
        $future = $this->makeAnnouncement(['title' => 'منشور مجدول', 'scheduled_at' => now()->addDay()]);
        $draft = $this->makeAnnouncement(['title' => 'مسوّدة', 'status' => 'draft']);

        $this->actingAs($user)->get(route('announcements.index'))
            ->assertDontSee($expired->title)
            ->assertDontSee($future->title)
            ->assertDontSee($draft->title);
    }

    /** الاستهداف (audience): الدور والمستخدم بعينه — لا يرى غيرُ المستهدَف المنشورَ. */
    public function test_audience_targeting_is_respected(): void
    {
        $targeted = $this->makeUser();
        $other = $this->makeUser();
        $targeted->assignRole(Role::where('key', 'trainee')->firstOrFail());

        $byRole = $this->makeAnnouncement([
            'title' => 'موجّه للمتدرّبين',
            'audience' => ['type' => 'role', 'keys' => ['trainee']],
        ]);

        $byUser = $this->makeAnnouncement([
            'title' => 'موجّه لشخص بعينه',
            'audience' => ['type' => 'user', 'ids' => [$targeted->id]],
        ]);

        $this->actingAs($targeted)->get(route('announcements.index'))
            ->assertSee($byRole->title)
            ->assertSee($byUser->title);

        $this->actingAs($other)->get(route('announcements.index'))
            ->assertDontSee($byRole->title)
            ->assertDontSee($byUser->title);
    }

    /** فلاتر: غير المقروء · النوع · بحث. */
    public function test_filters_unread_type_and_search(): void
    {
        $user = $this->makeUser();

        $read = $this->makeAnnouncement(['title' => 'منشور مقروء']);
        $unread = $this->makeAnnouncement(['title' => 'منشور غير مقروء']);
        $critical = $this->makeAnnouncement(['title' => 'منشور حرج', 'requires_acknowledge' => true]);

        AnnouncementRead::create([
            'announcement_id' => $read->id,
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        $this->actingAs($user)->get(route('announcements.index', ['unread' => 1]))
            ->assertSee($unread->title)
            ->assertDontSee($read->title);

        $this->actingAs($user)->get(route('announcements.index', ['type' => 'critical']))
            ->assertSee($critical->title)
            ->assertDontSee($unread->title);

        $this->actingAs($user)->get(route('announcements.index', ['q' => 'غير مقروء']))
            ->assertSee($unread->title)
            ->assertDontSee($read->title);
    }

    /** [تعليم الكلّ كمقروء] يغطّي كلّ ما يخصّ المستخدم فقط. */
    public function test_mark_all_as_read(): void
    {
        $user = $this->makeUser();
        $first = $this->makeAnnouncement();
        $second = $this->makeAnnouncement();

        $this->actingAs($user)->post(route('announcements.read-all'))->assertRedirect();

        foreach ([$first, $second] as $announcement) {
            $this->assertNotNull(AnnouncementRead::where('user_id', $user->id)
                ->where('announcement_id', $announcement->id)
                ->value('read_at'));
        }

        $this->assertSame(0, app(AnnouncementFeed::class)->unreadCount($user->fresh()));
    }

    /** الإقرار الإلزاميّ يمنح المكافأة **مرّة واحدة** — والتحقّق في الخادم (13.2). */
    public function test_acknowledge_rewards_xp_only_once(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement([
            'title' => 'منشور حرج بمكافأة',
            'requires_acknowledge' => true,
            'acknowledge_xp' => 10,
        ]);

        $this->actingAs($user)->post(route('announcements.acknowledge', $announcement))->assertRedirect();
        $this->actingAs($user)->post(route('announcements.acknowledge', $announcement))->assertRedirect();

        $xpCurrency = Currency::where('code', 'xp')->firstOrFail();

        $this->assertSame(1, Transaction::where('user_id', $user->id)
            ->where('currency_id', $xpCurrency->id)
            ->where('source', 'announcement')
            ->count());

        $this->assertSame(10, (int) $user->fresh()->xp);
        $this->assertNotNull(AnnouncementRead::where('user_id', $user->id)
            ->where('announcement_id', $announcement->id)
            ->value('acknowledged_at'));
    }

    /** المنشور الذي لا يحتاج إقرارًا لا يُقبَل إقراره (تحقّق خادم). */
    public function test_acknowledge_is_rejected_when_not_required(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['requires_acknowledge' => false]);

        $this->actingAs($user)
            ->post(route('announcements.acknowledge', $announcement))
            ->assertStatus(422);
    }

    /** التفاعل يظهر ويُقبَل فقط لو الأدمن سمح به لهذا المنشور (13.2). */
    public function test_reactions_only_when_admin_enabled_them(): void
    {
        $user = $this->makeUser();

        $open = $this->makeAnnouncement(['title' => 'منشور بتفاعل', 'reactions_enabled' => true]);
        $closed = $this->makeAnnouncement(['title' => 'منشور بلا تفاعل', 'reactions_enabled' => false]);

        $this->actingAs($user)
            ->post(route('announcements.react', $closed), ['reaction' => '👍'])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('announcements.react', $open), ['reaction' => '👍'])
            ->assertRedirect();

        $this->assertSame('👍', AnnouncementRead::where('user_id', $user->id)
            ->where('announcement_id', $open->id)
            ->value('reaction'));

        // الضغط على نفس الإيموجي يلغيه
        $this->actingAs($user)->post(route('announcements.react', $open), ['reaction' => '👍']);

        $this->assertNull(AnnouncementRead::where('user_id', $user->id)
            ->where('announcement_id', $open->id)
            ->value('reaction'));
    }

    /** لا يُعلَّم كمقروء منشورٌ خارج شريحة المستخدم. */
    public function test_cannot_read_announcement_outside_own_audience(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $announcement = $this->makeAnnouncement([
            'audience' => ['type' => 'user', 'ids' => [$other->id]],
        ]);

        $this->actingAs($user)
            ->post(route('announcements.read', $announcement))
            ->assertNotFound();
    }

    /** الحالة الفارغة: «لا تعليمات جديدة» — سطر واحد وزرّ واحد (2.15-د). */
    public function test_empty_state_message(): void
    {
        Announcement::query()->delete();

        $this->actingAs($this->makeUser())
            ->get(route('announcements.index'))
            ->assertSee('لا تعليمات جديدة');
    }
}
