<?php

namespace App\Services\Setup;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * تنفيذ خطوات التنصيب الفعليّة **من داخل الويب** (2.2 — بلا تيرمينال):
 * تشغيل المايجريشنز والسيدر · إنشاء مالك المنصّة · تدوير المفتاح · القفل.
 */
class Installer
{
    public function __construct(
        private readonly SetupPaths $paths,
        private readonly EnvWriter $env,
    ) {}

    /** تحويل الاتّصال الحاليّ لبيانات المنصِّب بلا إعادة نشر ولا إعادة تشغيل */
    public function useConnection(array $credentials): string
    {
        $driver = SetupSettings::text('setup.database.driver', 'mysql');

        config([
            'database.default' => $driver,
            'database.connections.'.$driver.'.driver' => $driver,
            'database.connections.'.$driver.'.host' => $credentials['db_host'],
            'database.connections.'.$driver.'.port' => (int) $credentials['db_port'],
            'database.connections.'.$driver.'.database' => $credentials['db_database'],
            'database.connections.'.$driver.'.username' => $credentials['db_username'],
            'database.connections.'.$driver.'.password' => (string) ($credentials['db_password'] ?? ''),
        ]);

        DB::purge($driver);

        return $driver;
    }

    /** كتابة بيانات الاتّصال في ‎.env‎ — ولا تُستدعى إلّا بعد نجاح الاختبار */
    public function writeDatabaseEnv(array $credentials): bool
    {
        return $this->env->put([
            'DB_CONNECTION' => SetupSettings::text('setup.database.driver', 'mysql'),
            'DB_HOST' => $credentials['db_host'],
            'DB_PORT' => (string) (int) $credentials['db_port'],
            'DB_DATABASE' => $credentials['db_database'],
            'DB_USERNAME' => $credentials['db_username'],
            'DB_PASSWORD' => (string) ($credentials['db_password'] ?? ''),
        ]);
    }

    /** @return array{ok:bool,output:string} */
    public function migrate(): array
    {
        $status = Artisan::call('migrate', ['--force' => true]);

        return ['ok' => $status === 0, 'output' => trim(Artisan::output())];
    }

    /** @return array{ok:bool,output:string} */
    public function seed(): array
    {
        $seeder = SetupSettings::text('setup.seed.class', 'Database\\Seeders\\DatabaseSeeder');

        $status = Artisan::call('db:seed', ['--force' => true, '--class' => $seeder]);

        return ['ok' => $status === 0, 'output' => trim(Artisan::output())];
    }

    public function writePlatformEnv(array $platform): bool
    {
        return $this->env->put([
            'APP_NAME' => $platform['app_name'],
            'APP_URL' => rtrim($platform['app_url'], '/'),
            'APP_TIMEZONE' => $platform['timezone'],
            'APP_LOCALE' => $platform['locale'],
            'APP_FALLBACK_LOCALE' => $platform['locale'],
        ]);
    }

    /** الشعار (2.2-2): يُحفَظ كإعداد فيقدر المالك يغيّره بعدين من اللوحة (2.13) */
    public function storeLogo(string $relativePath): void
    {
        Setting::updateOrCreate(
            ['key' => 'platform.branding.logo_path'],
            [
                'group' => 'platform',
                'label_ar' => setting('setup.installer.store_logo_1', 'شعار المنصّة'),
                'type' => 'media',
                'value' => $relativePath,
                'default_value' => '',
                'hint' => setting('setup.installer.store_logo_2', 'الشعار الظاهر في الهيدر وصفحات الدخول والشهادات.'),
            ],
        );
    }

    /**
     * حساب مالك المنصّة: يُنشَأ **مفعَّلًا** (لا يمرّ باعتماد إداريّ لأنّه هو
     * صاحب الاعتماد أصلًا) ويُسنَد له دور ‎platform_owner‎ من ‎RoleSeeder‎.
     */
    public function createOwner(array $data): User
    {
        $roleKey = SetupSettings::text('setup.owner.role_key', 'platform_owner');

        $role = Role::where('key', $roleKey)->first();

        if (! $role) {
            throw new RuntimeException(strtr(setting('setup.installer.create_owner_1', 'دور «:p1» مش موجود في قاعدة البيانات — يبدو إنّ خطوة تجهيز البيانات الأساسيّة ماتمّتش. ارجع لخطوة قاعدة البيانات وشغّل التجهيز تاني.'), [':p1' => (string) ($roleKey)]));
        }

        return DB::transaction(function () use ($data, $role) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                // العمود Nullable+Unique أصلًا؛ هاتف المالك لم يعد يُطلَب في /setup (2.2)
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'code' => $this->generateCode(),
                'status' => 'active',
                'activated_at' => now(),
                'email_verified_at' => now(),
                'locale' => SetupSettings::text('setup.platform.default_locale', 'ar'),
            ]);

            $user->assignRole($role);

            return $user->fresh();
        });
    }

    private function generateCode(): string
    {
        $prefix = SetupSettings::text('accounts.code.prefix', 'U');

        do {
            $code = $prefix.Str::upper(Str::random(7));
        } while (User::where('code', $code)->exists());

        return $code;
    }

    /** رابط ملفّات التخزين العامّة — نعمله من الويب فلا يحتاج المالك تيرمينال */
    public function linkStorage(): bool
    {
        if (! SetupSettings::flag('setup.finish.link_storage', true)) {
            return false;
        }

        try {
            return Artisan::call('storage:link') === 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** مفتاح جديد عند الإنهاء: المفتاح المؤقّت وُلِّد قبل التنصيب فلا نُبقيه */
    public function rotateAppKey(): ?string
    {
        if (! SetupSettings::flag('setup.finish.rotate_app_key', true)) {
            return $this->env->get('APP_KEY');
        }

        $key = $this->env->generateAppKey();

        config(['app.key' => $key]);

        return $key;
    }

    /** علامة الاكتمال: وجودها يقفل ‎/setup‎ نهائيًّا (404) */
    public function lock(array $meta = []): bool
    {
        $payload = json_encode([
            'installed_at' => now()->toIso8601String(),
            'platform' => $meta['app_name'] ?? null,
            'owner_email' => $meta['owner_email'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return @file_put_contents($this->paths->lock(), $payload.PHP_EOL) !== false;
    }
}
