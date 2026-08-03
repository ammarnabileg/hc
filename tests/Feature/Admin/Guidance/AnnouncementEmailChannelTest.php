<?php

namespace Tests\Feature\Admin\Guidance;

use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\AnnouncementFeed;
use App\Services\Notifications\AnnouncementMailer;
use Database\Seeders\AnnouncementDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * قناة البريد داخل «القنوات الموحّدة» (12.6-أ) وحدّ هدوئها (12.6-ب).
 *
 * كلّ اختبار هنا يقابل وعدًا في الدستور، ووعدٌ بلا حارس يسقط أوّل مرّة يمسّه أحد.
 */
class AnnouncementEmailChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AnnouncementDemoSeeder::class);

        Mail::fake();
    }

    // ------------------------------------------------------------------ أدوات

    private function reader(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'قارئ',
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function announcement(array $overrides = []): Announcement
    {
        return Announcement::create(array_merge([
            'title' => 'منشور بالبريد',
            'body' => 'نصّ المنشور',
            'audience' => ['type' => 'all'],
            'status' => 'published',
            'show_in_feed' => true,
            'email_enabled' => true,
            'scheduled_at' => now()->subMinute(),
        ], $overrides));
    }

    private function setSetting(string $key, string $value): void
    {
        Setting::query()->where('key', $key)->update(['value' => $value]);
        Cache::forget('settings');
    }

    // ------------------------------------------------------------ قناة البريد

    /**
     * القناة تعمل من مكان واحد في المحرّر: منشورٌ فُتِحت له قناة البريد يخرج
     * `Mailable` لا `Mail::raw` (12.6-أ).
     */
    public function test_email_channel_sends_a_mailable_to_its_audience(): void
    {
        $reader = $this->reader();
        $announcement = $this->announcement();

        $result = app(AnnouncementMailer::class)->deliver($announcement);

        $this->assertSame(1, $result['sent']);
        Mail::assertSent(AnnouncementMail::class, fn (AnnouncementMail $mail) => $mail->hasTo($reader->email));
    }

    /** قناة مطفأة = بلا بريد: القنوات مستقلّة بعضها عن بعض (12.6-أ). */
    public function test_closed_email_channel_sends_nothing(): void
    {
        $this->reader();
        $announcement = $this->announcement(['email_enabled' => false]);

        app(AnnouncementMailer::class)->deliver($announcement);

        Mail::assertNothingSent();
    }

    // -------------------------------------------- تفضيل المستخدم وحالته (خادميًّا)

    /** مَن بريده غير موثَّق لا يُرسَل له — والفحص على الخادم لا في الواجهة. */
    public function test_unverified_email_is_skipped_on_the_server(): void
    {
        $reader = $this->reader(['email_verified_at' => null]);

        $result = app(AnnouncementMailer::class)->deliver($this->announcement());

        Mail::assertNothingSent();
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseHas('announcement_deliveries', [
            'user_id' => $reader->id,
            'channel' => 'email',
            'status' => 'skipped',
        ]);
    }

    /** مَن أوقف قناة البريد لا يُرسَل له مهما كان المنشور (12.6-أ). */
    public function test_opted_out_user_is_skipped_on_the_server(): void
    {
        $reader = $this->reader(['email_optout_at' => now()]);

        app(AnnouncementMailer::class)->deliver($this->announcement());

        Mail::assertNothingSent();
        $this->assertDatabaseHas('announcement_deliveries', [
            'user_id' => $reader->id,
            'status' => 'skipped',
        ]);
    }

    // ------------------------------------------------- حدّ الهدوء على البريد (12.6-ب)

    /**
     * ⭐ الزيادة **تتأجّل** لا تنهال: بحدٍّ يوميّ قدره 1، المنشور الثاني يُخزَّن
     * مؤجَّلًا بموعدٍ في المستقبل ولا تخرج له رسالة.
     */
    public function test_second_email_in_a_day_is_deferred_not_dumped(): void
    {
        $this->setSetting('announcements.email.rate_limit.per_user_per_day', '1');

        $reader = $this->reader();
        $mailer = app(AnnouncementMailer::class);

        $mailer->deliver($this->announcement(['title' => 'الأوّل']));
        $mailer->deliver($this->announcement(['title' => 'التاني']));

        Mail::assertSent(AnnouncementMail::class, 1);

        $deferred = AnnouncementDelivery::query()->where('status', 'deferred')->firstOrFail();
        $this->assertSame($reader->id, (int) $deferred->user_id);
        $this->assertTrue($deferred->deferred_until->isFuture());
    }

    /** والمؤجَّل يخرج حين يحين وقته — تأجيلٌ لا إلغاء (12.6-ب). */
    public function test_deferred_email_goes_out_when_its_window_opens(): void
    {
        $this->setSetting('announcements.email.rate_limit.per_user_per_day', '1');

        $this->reader();
        $mailer = app(AnnouncementMailer::class);

        $mailer->deliver($this->announcement(['title' => 'الأوّل']));
        $mailer->deliver($this->announcement(['title' => 'التاني']));

        $this->assertSame(1, AnnouncementDelivery::query()->where('status', 'deferred')->count());

        $this->travel(2)->days();

        $mailer->dispatchDue();

        // الرسالة المؤجَّلة خرجت في نافذتها، ولم يخرج معها المكرَّر
        Mail::assertSent(AnnouncementMail::class, 2);
        $this->assertSame(0, AnnouncementDelivery::query()->where('status', 'deferred')->count());
    }

    // ------------------------------------------------------------ عدم إعادة الإرسال

    /**
     * ⭐ الإرسال لا يُعيد نفسه: تشغيل الجدولة ثلاث مرّات لا يُخرِج إلّا رسالة
     * واحدة لكلّ مستخدم — الصفّ يثبت الإرسال (12.6-أ).
     */
    public function test_rerunning_the_schedule_never_sends_twice(): void
    {
        $this->reader();
        $this->announcement();

        $mailer = app(AnnouncementMailer::class);
        $mailer->dispatchDue();
        $mailer->dispatchDue();
        $mailer->dispatchDue();

        Mail::assertSent(AnnouncementMail::class, 1);
        $this->assertSame(1, AnnouncementDelivery::query()->where('status', 'sent')->count());
    }

    /** والأمر نفسه من سطر الأوامر — نفس الحارس لا حارسٌ ثانٍ. */
    public function test_console_command_is_idempotent_too(): void
    {
        $this->reader();
        $this->announcement();

        $this->artisan('announcements:deliver-emails')->assertSuccessful();
        $this->artisan('announcements:deliver-emails')->assertSuccessful();

        Mail::assertSent(AnnouncementMail::class, 1);
    }

    // ------------------------------------------------------------------ عزل الفشل

    /**
     * ⭐ فشل بريد مستخدمٍ واحد لا يُسقِط الدفعة كلّها: يُسجَّل ويُكمَل —
     * سابقةُ هذا المشروع أنّ اعتراضًا عالقًا واحدًا أسقط محرّك التصعيد كلّه.
     */
    public function test_one_failing_recipient_does_not_drop_the_batch(): void
    {
        $broken = $this->reader(['name' => 'المكسور']);
        $healthy = $this->reader(['name' => 'السليم']);

        Mail::shouldReceive('to')->andReturnUsing(function (string $address) use ($broken) {
            if ($address === $broken->email) {
                throw new \RuntimeException('عنوان مرفوض من الخادم');
            }

            return new class
            {
                public function send($mailable): void {}
            };
        });

        $result = app(AnnouncementMailer::class)->deliver($this->announcement());

        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, $result['sent']);
        $this->assertDatabaseHas('announcement_deliveries', ['user_id' => $broken->id, 'status' => 'failed']);
        $this->assertDatabaseHas('announcement_deliveries', ['user_id' => $healthy->id, 'status' => 'sent']);
    }

    // ------------------------------------------------------------ قناة التاب مستقلّة

    /** منشورٌ بالبريد وحده لا يظهر في فيد التعليمات — القنوات مستقلّة (12.6-أ). */
    public function test_email_only_announcement_stays_out_of_the_feed(): void
    {
        $reader = $this->reader();
        $announcement = $this->announcement(['show_in_feed' => false, 'title' => 'بالبريد وحده']);

        $feed = app(AnnouncementFeed::class);

        $this->assertFalse($feed->for($reader)->contains(fn (Announcement $a) => $a->id === $announcement->id));
        $this->assertFalse($feed->isVisibleTo($announcement, $reader));
    }
}
