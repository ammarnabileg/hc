<?php

namespace Tests\Feature\Setup;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

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

        // المضيف والمنفذ ثابتان (2.2) ولا يُرسَلان في الطلب، لكنّهما يدخلان في
        // بصمة الاتّصال داخل المتحكّم — فنُحاكيهما هنا كما سيشتقّهما هو بالضبط.
        $fingerprintCredentials = $credentials + ['db_host' => '127.0.0.1', 'db_port' => '3306'];

        $this->withSession([
            'setup' => [
                'token_ok' => true,
                'completed' => ['requirements'],
                'draft' => $credentials,
                'db_fingerprint' => $this->fingerprintFor($fingerprintCredentials),
            ],
        ])->post(route('setup.database.store'), $credentials)
            ->assertRedirect(route('setup.migrate'))
            ->assertSessionHasNoErrors();

        $this->assertFileExists($this->root.'/.env');
        $env = (string) file_get_contents($this->root.'/.env');
        $this->assertStringContainsString('DB_DATABASE='.$credentials['db_database'], $env);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $env);
        $this->assertStringContainsString('DB_PORT=3306', $env);
    }

    public function test_database_form_no_longer_accepts_or_needs_host_and_port(): void
    {
        $credentials = $this->credentials();
        $fingerprintCredentials = $credentials + ['db_host' => '127.0.0.1', 'db_port' => '3306'];

        // حتّى لو أُرسِل db_host/db_port بالغلط (عميلٌ قديم مثلًا) فهما يُتجاهَلان
        // تمامًا؛ القيمتان الثابتتان من الإعدادات هما اللتان تُكتَبان دائمًا.
        $this->withSession([
            'setup' => [
                'token_ok' => true,
                'completed' => ['requirements'],
                'draft' => $credentials,
                'db_fingerprint' => $this->fingerprintFor($fingerprintCredentials),
            ],
        ])->post(route('setup.database.store'), $credentials + ['db_host' => '203.0.113.9', 'db_port' => '9999'])
            ->assertRedirect(route('setup.migrate'))
            ->assertSessionHasNoErrors();

        $env = (string) file_get_contents($this->root.'/.env');
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $env);
        $this->assertStringContainsString('DB_PORT=3306', $env);
        $this->assertStringNotContainsString('203.0.113.9', $env);
        $this->assertStringNotContainsString('DB_PORT=9999', $env);
    }

    // ------------------------------------------------ حساب مالك المنصّة

    public function test_owner_account_is_created_active_and_holds_the_platform_owner_role(): void
    {
        $this->seed(RoleSeeder::class);

        $this->state(['requirements', 'database', 'migrate', 'platform'])
            ->post(route('setup.owner.store'), [
                'name' => 'مالك المنصّة',
                'email' => 'owner@platform.test',
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
        // العمود Nullable+Unique؛ هاتف المالك لم يعد يُطلَب في فورم /setup (2.2)
        $this->assertNull($owner->phone);
    }

    public function test_owner_form_no_longer_requires_a_phone_number(): void
    {
        $this->seed(RoleSeeder::class);

        $this->state(['requirements', 'database', 'migrate', 'platform'])
            ->get(route('setup.owner'))
            ->assertOk()
            ->assertDontSee('name="phone"', false);
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
            ->assertSee('اسم المنصّة')
            ->assertDontSee('name="app_url"', false)
            ->assertDontSee('name="timezone"', false)
            ->assertDontSee('name="locale"', false);

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

    // ------------------------------------------------ التدفّق الكامل بلا الحقول الزائدة (2.2 — القرار 25)

    /**
     * إثبات شامل: التنصيب الكامل من التوكن حتّى «أنهِ التنصيب» ينجح رغم أنّ
     * أيًّا من الطلبات لا يحمل db_host/db_port/app_url/timezone/locale،
     * وأنّ القيم الافتراضيّة الصحيحة (127.0.0.1 · 3306 · رابط الطلب · Africa/Cairo · ar)
     * تنتهي فعليًّا في ‎.env‎ — لا نظريًّا فقط.
     */
    public function test_full_installation_succeeds_without_the_removed_fields_and_defaults_land_in_env(): void
    {
        $this->seed(RoleSeeder::class);

        // 1) التوكن
        $this->get(route('setup.token'))->assertOk();

        $this->post(route('setup.token.verify'), ['token' => $this->tokenFromFile()])
            ->assertRedirect(route('setup.requirements'))
            ->assertSessionHas('setup.token_ok', true);

        // 2) فحص المتطلّبات
        @mkdir($this->root.'/bootstrap/cache', 0777, true);

        $this->post(route('setup.requirements.store'))
            ->assertRedirect(route('setup.database'))
            ->assertSessionHasNoErrors();

        // 3) قاعدة البيانات — بلا db_host ولا db_port في الطلب إطلاقًا
        $dbInput = [
            'db_database' => 'platform_db',
            'db_username' => 'platform_user',
            'db_password' => 'platform-secret',
        ];

        // بيئة الاختبار بلا خادم MySQL فعليّ، فنُحاكي نجاح [اختبار الاتّصال]
        // بوضع بصمة مطابقة لما سيشتقّه المتحكّم تلقائيًّا (مضيف/منفذ ثابتان).
        session()->put(
            'setup.db_fingerprint',
            $this->fingerprintFor($dbInput + ['db_host' => '127.0.0.1', 'db_port' => '3306'])
        );

        $this->post(route('setup.database.store'), $dbInput)
            ->assertRedirect(route('setup.migrate'))
            ->assertSessionHasNoErrors();

        $this->assertFileExists($this->root.'/.env');

        // 4) المايجريشن: تشغيله الفعليّ يُبدّل اتّصال قاعدة بيانات الاختبار
        // نفسه (RefreshDatabase) — فنُتِمّه في الجلسة كبقيّة اختبارات هذا الملف
        // (owner/platform) بلا تشغيلٍ فعليّ، لأنّ الجداول والأدوار مُجهَّزة
        // بالفعل عبر seed(RoleSeeder::class) أعلاه.
        session()->put('setup.completed', [...session('setup.completed', []), 'migrate']);

        // 5) بيانات المنصّة — بلا app_url ولا timezone ولا locale في الطلب إطلاقًا
        $this->post(route('setup.platform.store'), ['app_name' => 'منصّة الاختبار الكاملة'])
            ->assertRedirect(route('setup.owner'))
            ->assertSessionHasNoErrors();

        // 6) حساب المالك — بلا phone في الطلب إطلاقًا
        $this->post(route('setup.owner.store'), [
            'name' => 'مالك المنصّة',
            'email' => 'owner-full-flow@platform.test',
            'password' => 'a-very-strong-password',
            'password_confirmation' => 'a-very-strong-password',
        ])->assertRedirect(route('setup.finish'))
            ->assertSessionHasNoErrors();

        // 7) الإنهاء
        Setting::create([
            'key' => 'setup.finish.link_storage',
            'group' => 'setup',
            'label_ar' => 'ربط مجلّد التخزين بعد التنصيب',
            'type' => 'bool',
            'value' => '0',
        ]);

        Cache::forget('settings');

        $this->post(route('setup.finish.install'))
            ->assertOk()
            ->assertSee('منصّة الاختبار الكاملة');

        $this->assertFileExists($this->root.'/storage/installed.lock');

        $env = (string) file_get_contents($this->root.'/.env');

        $this->assertStringContainsString('DB_HOST=127.0.0.1', $env);
        $this->assertStringContainsString('DB_PORT=3306', $env);
        $this->assertStringContainsString('DB_DATABASE=platform_db', $env);
        $this->assertStringContainsString('APP_URL=http://localhost', $env);
        $this->assertStringContainsString('APP_TIMEZONE=Africa/Cairo', $env);
        $this->assertStringContainsString('APP_LOCALE=ar', $env);

        $owner = User::where('email', 'owner-full-flow@platform.test')->firstOrFail();

        $this->assertTrue($owner->hasRole('platform_owner'));
        $this->assertSame('active', $owner->status);
        // العمود Nullable+Unique؛ هاتف المالك لم يعد يُطلَب في فورم /setup (2.2)
        $this->assertNull($owner->phone);
    }

    /**
     * ⭐ العطل الحقيقيّ الذي وقع أوّل تجربة نشرٍ فعليّة (2026-08-06): كلّ
     * شاشات المعالج كانت تستدعي ‎setting()‎ العاديّة — وهي تضرب قاعدة البيانات
     * مباشرةً بلا التقاط أخطاء — بينما لا قاعدة بيانات ولا جدول ‎settings‎
     * موجودان بعد (الخطوة الأولى في السلسلة كلّها، قبل حتّى خطوة قاعدة
     * البيانات). فسقطت الصفحة الأولى بـ500 على أوّل خادمٍ حقيقيّ. العلاج:
     * كلّ شاشات المعالج يجب أن تستخدم ‎SetupSettings::text()‎ فقط (تُرجِع
     * الافتراضيّ بصمتٍ عند أيّ فشل قراءة)، لا ‎setting()‎ الخام أبدًا.
     */
    public function test_wizard_pages_render_even_before_the_settings_table_exists(): void
    {
        Schema::dropIfExists('settings');

        $this->get(route('setup.token'))->assertOk();

        $this->post(route('setup.token.verify'), ['token' => $this->tokenFromFile()]);

        $this->get(route('setup.requirements'))->assertOk();
    }

    /**
     * الحقول الثلاثة التي يطلبها فورم قاعدة البيانات فقط بعد 2.2 — المضيف
     * والمنفذ لم يعودا يُرسَلان من العميل إطلاقًا (ثابتان من الإعدادات).
     *
     * @return array<string, string>
     */
    private function credentials(): array
    {
        return [
            'db_database' => 'platform_db',
            'db_username' => 'platform_user',
            'db_password' => 'platform-secret',
        ];
    }
}
