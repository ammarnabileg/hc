<?php

namespace Tests\Feature\Onboarding;

use App\Models\Country;
use App\Models\Governorate;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * أساس اختبارات «رحلة التسجيل» (2.5) — بمصفوفة الصلاحيّات كاملةً كما في
 * الإنتاج، فما يمرّ هنا يمرّ هناك. وكلّ اختبار يقابل **قاعدةً منصوصةً**
 * لا مجرّد شاشة تفتح.
 */
abstract class OnboardingTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        Cache::forget('settings');
    }

    /** مُسجَّل جديد كما يخرج من التسجيل تمامًا: «تحت المراجعة» وبلا خطوة مُتمَّة */
    protected function newcomer(array $attributes = []): User
    {
        $user = User::create([
            'title' => 'المهندس',
            'name' => 'أحمد محمود سعيد',
            'name_ar' => 'أحمد محمود سعيد',
            'name_en' => 'Ahmed Mahmoud Saeed',
            'gender' => 'male',
            'address_line' => 'شارع الجمهوريّة، وسط البلد',
            'email' => Str::lower(Str::random(9)).'@test.local',
            'phone' => '+2010'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'code' => 'U'.Str::upper(Str::random(7)),
            'status' => 'pending',
            ...$attributes,
        ]);

        $user->assignRole('pending_review');

        return $user->fresh();
    }

    /** حساب مفعَّل أتمّ رحلته — لاختبارات ما بعد الباب */
    protected function member(array $attributes = []): User
    {
        $user = $this->newcomer([
            'status' => 'active',
            'instructions_agreed_at' => now(),
            'placement_completed_at' => now(),
            'acceptance_seen_at' => now(),
            ...$attributes,
        ]);

        $user->assignRole('trainee');

        return $user->fresh();
    }

    protected function egypt(): Country
    {
        return Country::firstOrCreate(
            ['iso2' => 'EG'],
            ['name_ar' => 'مصر', 'name_en' => 'Egypt', 'phone_code' => '+20', 'is_active' => true],
        );
    }

    protected function cairo(): Governorate
    {
        return Governorate::firstOrCreate(
            ['country_id' => $this->egypt()->id, 'name_ar' => 'القاهرة'],
            ['name_en' => 'Cairo', 'is_active' => true],
        );
    }
}
