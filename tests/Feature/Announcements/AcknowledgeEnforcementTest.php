<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use App\Services\Notifications\AnnouncementFeed;
use Database\Seeders\AnnouncementDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⭐ **الإقرار الإلزاميّ «قبل المتابعة» مفروضٌ على الخادم** (13.2).
 *
 * الالتفاف الذي تقيسه هذه الحالات — وهو **عين ما رصده الأوديت ب-6**: مستخدمٌ
 * عنده توجيهٌ حرجٌ غير مُقَرّ كان يتصفّح `/dashboard` و`/library` و`/events`
 * و`/store` و`/profile` **كلّها 200**، والبوب-أب في صفحة `/announcements` وحدها
 * و**قابل للإغلاق بـ✕**. فالتوجيه الحرج بلا ضمان وصول.
 *
 * والحدّ الثاني مقيسٌ هنا كذلك: **الإقرار ليس سجنًا** — الخروج والإقرار نفسه
 * والأمان تبقى مفتوحة.
 */
class AcknowledgeEnforcementTest extends TestCase
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

    private function criticalAnnouncement(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + [
            'title' => 'سياسة جديدة لازم تقراها',
            'body' => 'تحذير قانونيّ — بيسري من الأسبوع الجاي.',
            'status' => 'published',
            'audience' => ['type' => 'all'],
            'requires_acknowledge' => true,
            'acknowledge_xp' => 25,
            'acknowledge_tickets' => 2,
        ]);
    }

    /**
     * ⭐ **الطلب الذي كان يمرّ وصار يُمنَع:** خمس صفحاتٍ كانت 200 لصاحب توجيهٍ
     * حرجٍ غير مُقَرّ — وصارت تحويلةً إلى صفحة الإقرار.
     */
    public function test_a_pending_critical_notice_stops_browsing_the_platform(): void
    {
        $user = $this->makeUser();
        $this->criticalAnnouncement();

        foreach (['dashboard', 'library.index', 'events.index', 'store.index', 'profile.me'] as $route) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertRedirect(route('announcements.index'));
        }
    }

    /** ونفس المنع على الطلبات غير المتزامنة — 403 لا 200 صامتة (2.9). */
    public function test_json_requests_are_refused_with_an_acknowledge_flag(): void
    {
        $user = $this->makeUser();
        $this->criticalAnnouncement();

        $this->actingAs($user)
            ->getJson(route('notifications.index'))
            ->assertStatus(403)
            ->assertJsonPath('acknowledge_required', true);
    }

    /**
     * ⚠️ **ولا يصير الإقرار سجنًا:** الخروج والإقرار نفسه والأمان تبقى مفتوحة —
     * وإلّا حبسنا المستخدم في جلسةٍ لا يقدر يقفلها.
     */
    public function test_exit_and_acknowledge_paths_stay_open(): void
    {
        $user = $this->makeUser();
        $announcement = $this->criticalAnnouncement();

        // 1) صفحة الإقرار نفسها — وإلّا فالتحويلة حلقةٌ مغلقة
        $this->actingAs($user)->get(route('announcements.index'))->assertOk();

        // 2) دليل المستخدم (وجهة زرّ CTA في التوجيه)
        $this->actingAs($user)->get(route('help.index'))->assertOk();

        // 3) فعل الإقرار نفسه لا يُحجَب
        $this->actingAs($user)
            ->post(route('announcements.acknowledge', $announcement))
            ->assertRedirect();

        // 4) والخروج — حقٌّ مهما كانت الحالة
        $this->actingAs($this->makeUser())->post(route('logout'))->assertRedirect();
    }

    /** وبعد الإقرار يعود التصفّح فورًا — الجدار بابٌ يفتحه المستخدم بنفسه. */
    public function test_browsing_resumes_the_moment_he_acknowledges(): void
    {
        $user = $this->makeUser();
        $announcement = $this->criticalAnnouncement();

        // `/notifications` صفحةٌ يفتحها أيّ مستخدمٍ مسجَّل — فالمقيس هنا الجدارُ وحده
        $this->actingAs($user)->get(route('notifications.index'))->assertRedirect(route('announcements.index'));

        $this->actingAs($user)->post(route('announcements.acknowledge', $announcement));

        $this->actingAs($user)->get(route('notifications.index'))->assertOk();
    }

    /** ومَن لا توجيه حرجَ عليه لا يُمَسّ — الجدار لا يقفل على غير أهله. */
    public function test_a_user_without_a_pending_notice_browses_freely(): void
    {
        $user = $this->makeUser();

        Announcement::create([
            'title' => 'منشور عاديّ',
            'body' => 'بلا إقرار',
            'status' => 'published',
            'audience' => ['type' => 'all'],
            'requires_acknowledge' => false,
        ]);

        $this->actingAs($user)->get(route('notifications.index'))->assertOk();
    }

    /** والاستهداف يحكم: توجيهٌ حرجٌ لشريحةٍ أخرى لا يقفل على من ليس فيها (13.2). */
    public function test_a_notice_aimed_at_another_segment_does_not_block(): void
    {
        $user = $this->makeUser();

        $this->criticalAnnouncement(['audience' => ['type' => 'user', 'ids' => [$user->id + 999]]]);

        $this->actingAs($user)->get(route('notifications.index'))->assertOk();
    }

    /**
     * ⭐ **«تعليم الكلّ كمقروء» لا يُطفئ عدّاد الحرج** (13.2).
     *
     * كانت ضغطةٌ واحدة تمحو الإشارة وتُبقي الواجب: `read_at` يُكتَب للتوجيه الحرج
     * فيسقط العدّاد إلى صفر وتختفي النقطة، والإقرار ما زال معلّقًا.
     */
    public function test_mark_all_as_read_never_silences_an_unacknowledged_notice(): void
    {
        $user = $this->makeUser();
        $announcement = $this->criticalAnnouncement();

        $this->actingAs($user)->post(route('announcements.read-all'))->assertRedirect();

        $this->assertNull(
            AnnouncementRead::where('user_id', $user->id)
                ->where('announcement_id', $announcement->id)
                ->value('read_at'),
            'التوجيه الحرج غير المُقَرّ يفضل غير مقروء',
        );

        $this->assertSame(1, app(AnnouncementFeed::class)->unreadCount($user->fresh()));

        // والإشارة باقية فعلًا: الجدار ما زال قائمًا بعد الضغطة
        $this->actingAs($user)->get(route('notifications.index'))->assertRedirect(route('announcements.index'));
    }

    /** وما لا يطلب إقرارًا يُعلَّم مقروءًا كالمعتاد — الاستثناء أضيق ما يكون. */
    public function test_mark_all_as_read_still_covers_ordinary_posts(): void
    {
        $user = $this->makeUser();
        $this->criticalAnnouncement();

        $ordinary = Announcement::create([
            'title' => 'منشور عاديّ',
            'body' => 'بلا إقرار',
            'status' => 'published',
            'audience' => ['type' => 'all'],
            'requires_acknowledge' => false,
        ]);

        $this->actingAs($user)->post(route('announcements.read-all'));

        $this->assertNotNull(AnnouncementRead::where('user_id', $user->id)
            ->where('announcement_id', $ordinary->id)
            ->value('read_at'));
    }

    /**
     * ⭐ **والبوب-أب لا يُغلَق بـ✕ ولا بـESC**: النافذة التي تُغلَق بضغطة تُلغي
     * شرط «قبل المتابعة». فلا زرّ إغلاقٍ فيها ولا `data-modal` الذي يلتقطه ESC.
     */
    public function test_the_acknowledge_popup_carries_no_dismiss_affordance(): void
    {
        $user = $this->makeUser();
        $this->criticalAnnouncement();

        $html = $this->actingAs($user)->get(route('announcements.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="ack-modal"/', $html);

        preg_match('/<div id="ack-modal"(.*?)>/s', $html, $openingTag);

        $this->assertNotEmpty($openingTag, 'بوب-أب الإقرار لازم يكون في الصفحة');
        $this->assertStringNotContainsString('data-modal', $openingTag[1], 'ESC يقفل كلّ ما يحمل data-modal');

        // ولا زرّ ✕ داخل النافذة نفسها
        preg_match('/<div id="ack-modal".*?<\/div>\s*<\/div>/s', $html, $modal);

        $this->assertStringNotContainsString('data-modal-close', $modal[0] ?? '');
    }

    /** والمكافأة على حالها كما أثبتها الأوديت: 25 XP وتذكرتان، والإعادة صفر. */
    public function test_the_reward_and_the_single_grant_rule_are_untouched(): void
    {
        $user = $this->makeUser();
        $announcement = $this->criticalAnnouncement();

        $first = $this->actingAs($user)
            ->postJson(route('announcements.acknowledge', $announcement))
            ->assertOk()
            ->json();

        $this->assertSame(25, $first['xp']);
        $this->assertSame(2, $first['tickets']);

        $again = $this->actingAs($user)
            ->postJson(route('announcements.acknowledge', $announcement))
            ->assertOk()
            ->json();

        $this->assertSame(0, $again['xp']);
        $this->assertSame(0, $again['tickets']);
    }

    /** ومنشورٌ لا يطلب إقرارًا ⟵ 422 كما هو (تحقّق خادم). */
    public function test_acknowledging_a_post_that_does_not_ask_for_it_is_rejected(): void
    {
        $user = $this->makeUser();

        $ordinary = Announcement::create([
            'title' => 'منشور عاديّ',
            'body' => 'بلا إقرار',
            'status' => 'published',
            'audience' => ['type' => 'all'],
            'requires_acknowledge' => false,
        ]);

        $this->actingAs($user)
            ->post(route('announcements.acknowledge', $ordinary))
            ->assertStatus(422);
    }
}
