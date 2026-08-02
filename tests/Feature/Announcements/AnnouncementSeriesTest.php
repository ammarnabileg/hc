<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use Database\Seeders\AnnouncementDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * سلسلة Onboarding متدرّجة + التخصيص الديناميكيّ في نصّ المنشور (12.6-أ).
 *
 * القاعدتان المُختبَرتان:
 * • الخطوة تنتظر **مهلتها** و**قراءة ما قبلها** — تسلسلٌ حقيقيّ لا ترتيبُ عرض.
 * • **لا وسمَ خامًا يقرؤه مستخدم** — «مرحبًا [اسم]» تصله باسمه هو.
 */
class AnnouncementSeriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AnnouncementDemoSeeder::class);
    }

    private function makeUser(string $name = 'مستخدم جديد', ?string $joinedAt = null): User
    {
        $user = User::create([
            'name' => $name,
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);

        if ($joinedAt) {
            $user->forceFill(['created_at' => $joinedAt])->save();
        }

        return $user->refresh();
    }

    private function step(int $order, int $delayDays, string $title): Announcement
    {
        return Announcement::create([
            'title' => $title,
            'body' => 'خطوة تعريفيّة.',
            'status' => 'published',
            'audience' => ['type' => 'all'],
            'onboarding_step' => $order,
            'onboarding_delay_days' => $delayDays,
        ]);
    }

    /** الخطوة المؤجَّلة لا تظهر قبل مهلتها — ولا تُغرِق المستخدم يومه الأوّل. */
    public function test_delayed_step_stays_hidden_until_its_day_arrives(): void
    {
        $this->step(1, 0, 'أهلًا بيك');
        $this->step(2, 3, 'خطوتك التانية');

        $fresh = $this->makeUser('مستجدّ اليوم');

        $titles = $this->actingAs($fresh)->get(route('announcements.index'))
            ->assertOk()->viewData('items')->pluck('title');

        $this->assertTrue($titles->contains('أهلًا بيك'));
        $this->assertFalse($titles->contains('خطوتك التانية'), 'خطوة مؤجَّلة ظهرت قبل ميعادها.');
    }

    /** الخطوة التالية تنتظر **قراءة** التي قبلها — لا مجرّد مرور الوقت. */
    public function test_next_step_waits_until_the_previous_one_is_read(): void
    {
        $first = $this->step(1, 0, 'الخطوة الأولى');
        $this->step(2, 0, 'الخطوة التانية');

        $user = $this->makeUser('متدرّب متسلسل');

        $before = $this->actingAs($user)->get(route('announcements.index'))
            ->viewData('items')->pluck('title');

        $this->assertFalse($before->contains('الخطوة التانية'), 'الخطوة التانية سبقت قراءة الأولى.');

        AnnouncementRead::create([
            'announcement_id' => $first->id,
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        $after = $this->actingAs($user)->get(route('announcements.index'))
            ->viewData('items')->pluck('title');

        $this->assertTrue($after->contains('الخطوة التانية'), 'الخطوة التانية ما ظهرتش بعد قراءة الأولى.');
    }

    /** خطوةٌ لم يحن وقتها لا تُفتَح بمسارها المباشر أيضًا (الحارس على الخادم). */
    public function test_a_step_that_is_not_due_cannot_be_acknowledged_directly(): void
    {
        $this->step(1, 0, 'الأولى');
        $late = $this->step(2, 30, 'المتأخّرة');
        $late->update(['requires_acknowledge' => true]);

        $this->actingAs($this->makeUser('مستعجل'))
            ->post(route('announcements.acknowledge', $late))
            ->assertNotFound();
    }

    /** «مرحبًا [اسم]» تصل باسم القارئ — ولا يبقى وسمٌ خامّ في الصفحة. */
    public function test_personalization_replaces_tokens_with_reader_values(): void
    {
        Announcement::create([
            'title' => 'مرحبًا [اسم]',
            'body' => 'كودك [الكود] وتدريبك [التدريب].',
            'status' => 'published',
            'audience' => ['type' => 'all'],
        ]);

        $user = $this->makeUser('سلمى محمود');

        $response = $this->actingAs($user)->get(route('announcements.index'))->assertOk();

        $response->assertSee('مرحبًا سلمى محمود', false);
        $response->assertSee($user->code, false);
        $response->assertDontSee('[اسم]', false);
        $response->assertDontSee('[الكود]', false);
        // بلا تدريبٍ نشط يظهر بديلٌ مهذّب لا وسمٌ خامّ (2.17)
        $response->assertDontSee('[التدريب]', false);
    }
}
