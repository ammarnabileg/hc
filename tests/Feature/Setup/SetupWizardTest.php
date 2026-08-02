<?php

namespace Tests\Feature\Setup;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * معالج التنصيب (الدستور 2.2): بلا تيرمينال وبلا تحكّم من داخل السيرفر.
 * الاختبارات هنا تحرس الخطّ الأمنيّ قبل أيّ شيء: التوكن · الاختبار قبل الكتابة · القفل.
 */
class SetupWizardTest extends SetupTestCase
{
    // ------------------------------------------------ التوكن (أمان لحظة الرفع)

    public function test_first_open_generates_a_token_and_a_wrong_one_is_rejected(): void
    {
        $this->get(route('setup.token'))->assertOk();

        $this->assertFileExists($this->root.'/storage/setup-token.txt');

        $this->post(route('setup.token.verify'), ['token' => 'not-the-real-token'])
            ->assertRedirect()
            ->assertSessionHasErrors('token');

        $this->assertNotTrue(session('setup.token_ok'));

        $this->post(route('setup.token.verify'), ['token' => $this->tokenFromFile()])
            ->assertRedirect(route('setup.requirements'))
            ->assertSessionHas('setup.token_ok', true);
    }

    public function test_steps_are_closed_before_the_token_is_verified(): void
    {
        $this->get(route('setup.requirements'))->assertRedirect(route('setup.token'));
        $this->get(route('setup.database'))->assertRedirect(route('setup.token'));
        $this->get(route('setup.owner'))->assertRedirect(route('setup.token'));
    }

    // ------------------------------------------------ فحص المتطلّبات

    public function test_requirements_screen_flags_a_missing_extension_and_blocks_the_next_step(): void
    {
        Setting::create([
            'key' => 'setup.requirements.extensions',
            'group' => 'setup',
            'label_ar' => 'الامتدادات الإلزاميّة',
            'type' => 'json',
            'value' => json_encode(['pdo', 'extension_that_does_not_exist']),
        ]);

        Cache::forget('settings');

        $this->state()->get(route('setup.requirements'))
            ->assertOk()
            ->assertSee('extension_that_does_not_exist')
            ->assertSee('❌', false);

        $this->state()->post(route('setup.requirements.store'))
            ->assertRedirect()
            ->assertSessionHasErrors('requirements');
    }

    public function test_requirements_screen_flags_an_unwritable_path_then_passes_once_fixed(): void
    {
        // الجذر المؤقّت فيه storage فقط، فـbootstrap/cache ناقص عمدًا
        $this->state()->get(route('setup.requirements'))
            ->assertOk()
            ->assertSee('bootstrap/cache');

        $this->state()->post(route('setup.requirements.store'))
            ->assertRedirect()
            ->assertSessionHasErrors('requirements');

        @mkdir($this->root.'/bootstrap/cache', 0777, true);

        $this->state()->post(route('setup.requirements.store'))
            ->assertRedirect(route('setup.database'))
            ->assertSessionHasNoErrors();
    }

    // ------------------------------------------------ قاعدة البيانات

    public function test_env_is_not_written_before_a_successful_connection_test(): void
    {
        $this->state(['requirements'])
            ->post(route('setup.database.store'), $this->credentials())
            ->assertRedirect()
            ->assertSessionHasErrors('connection');

        $this->assertFileDoesNotExist($this->root.'/.env');
    }

    public function test_env_is_written_only_after_the_connection_was_verified(): void
    {
        $credentials = $this->credentials();

        $this->withSession([
            'setup' => [
                'token_ok' => true,
                'completed' => ['requirements'],
                'draft' => $credentials,
                'db_fingerprint' => $this->fingerprintFor($credentials),
            ],
        ])->post(route('setup.database.store'), $credentials)
            ->assertRedirect(route('setup.migrate'))
            ->assertSessionHasNoErrors();

        $this->assertFileExists($this->root.'/.env');
        $this->assertStringContainsString('DB_DATABASE='.$credentials['db_database'], file_get_contents($this->root.'/.env'));
    }

    // ------------------------------------------------ حساب مالك المنصّة

    public function test_owner_account_is_created_active_and_holds_the_platform_owner_role(): void
    {
        $this->seed(RoleSeeder::class);

        $this->state(['requirements', 'database', 'migrate', 'platform'])
            ->post(route('setup.owner.store'), [
                'name' => 'مالك المنصّة',
                'email' => 'owner@platform.test',
                'phone' => '+201000000000',
                'password' => 'a-very-strong-password',
                'password_confirmation' => 'a-very-strong-password',
            ])
            ->assertRedirect(route('setup.finish'))
            ->assertSessionHasNoErrors();

        $owner = User::where('email', 'owner@platform.test')->firstOrFail();

        $this->assertSame('active', $owner->status);
        $this->assertNotNull($owner->activated_at);
        $this->assertNotEmpty($owner->code);
        $this->assertTrue($owner->hasRole('platform_owner'));
    }

    public function test_owner_step_is_not_reachable_before_the_earlier_steps(): void
    {
        $this->state(['requirements'])
            ->get(route('setup.owner'))
            ->assertRedirect(route('setup.database'));
    }

    // ------------------------------------------------ شاشات المعالج

    public function test_each_step_screen_renders_in_arabic(): void
    {
        $this->state(['requirements'], $this->credentials())
            ->get(route('setup.database'))
            ->assertOk()
            ->assertSee('اختبار الاتّصال')
            ->assertSee('platform_db');

        $this->state(['requirements', 'database'])
            ->get(route('setup.migrate'))
            ->assertOk()
            ->assertSee('ابدأ التجهيز');

        $this->state(['requirements', 'database', 'migrate'])
            ->get(route('setup.platform'))
            ->assertOk()
            ->assertSee('Africa/Cairo');

        $this->state(['requirements', 'database', 'migrate', 'platform'])
            ->get(route('setup.owner'))
            ->assertOk()
            ->assertSee('حساب مالك المنصّة');

        $this->state(['requirements', 'database', 'migrate', 'platform', 'owner'], ['app_name' => 'منصّة الاختبار'])
            ->get(route('setup.finish'))
            ->assertOk()
            ->assertSee('أنهِ التنصيب')
            ->assertSee('منصّة الاختبار');
    }

    // ------------------------------------------------ القفل النهائيّ

    public function test_every_setup_route_returns_404_once_the_lock_file_exists(): void
    {
        file_put_contents($this->root.'/storage/installed.lock', '{}');

        $this->get('/setup')->assertNotFound();
        $this->get(route('setup.token'))->assertNotFound();
        $this->get(route('setup.requirements'))->assertNotFound();
        $this->get(route('setup.database'))->assertNotFound();
        $this->get(route('setup.owner'))->assertNotFound();
        $this->get(route('setup.finish'))->assertNotFound();

        $this->post(route('setup.token.verify'), ['token' => 'anything'])->assertNotFound();
        $this->post(route('setup.owner.store'), [])->assertNotFound();
        $this->post(route('setup.finish.install'))->assertNotFound();
    }

    public function test_finishing_writes_the_lock_and_shows_the_success_screen(): void
    {
        @copy(base_path('.env.example'), $this->root.'/.env');

        // ربط مجلّد التخزين يلمس ملفّات خارج الجذر المؤقّت، فنوقفه في الاختبار
        Setting::create([
            'key' => 'setup.finish.link_storage',
            'group' => 'setup',
            'label_ar' => 'ربط مجلّد التخزين بعد التنصيب',
            'type' => 'bool',
            'value' => '0',
        ]);

        Cache::forget('settings');

        $this->state(['requirements', 'database', 'migrate', 'platform', 'owner'], [
            'app_name' => 'منصّة الاختبار',
            'owner_email' => 'owner@platform.test',
            'owner_code' => 'UTEST123',
        ])->post(route('setup.finish.install'))
            ->assertOk()
            ->assertSee('منصّة الاختبار')
            ->assertSee('ادخل للمنصّة');

        $this->assertFileExists($this->root.'/storage/installed.lock');
        $this->assertStringContainsString('APP_KEY=base64:', file_get_contents($this->root.'/.env'));
    }

    /** @return array<string, string> */
    private function credentials(): array
    {
        return [
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_database' => 'platform_db',
            'db_username' => 'platform_user',
            'db_password' => 'platform-secret',
        ];
    }
}
