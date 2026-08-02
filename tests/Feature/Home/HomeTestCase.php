<?php

namespace Tests\Feature\Home;

use App\Models\Course;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\HomeDemoSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** أساس اختبارات الواجهة العامّة والرسائل الإيجابيّة والسفراء */
abstract class HomeTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(HomeDemoSeeder::class);
    }

    protected function user(string $role = 'trainee', string $name = 'سلمى عادل', string $status = 'active'): User
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::random(10).'@test.local',
            'password' => 'secret-password',
            'code' => 'U'.Str::upper(Str::random(7)),
            'status' => $status,
            'activated_at' => $status === 'active' ? now() : null,
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    protected function makeCourse(array $attributes = []): Course
    {
        return Course::create([
            'slug' => 'c-'.Str::lower(Str::random(8)),
            'name_ar' => 'تدريب تجريبيّ',
            'description_ar' => 'وصف قصير للتدريب.',
            'is_free' => true,
            'free_preview_lessons' => 1,
            'is_indexable' => true,
            'status' => 'published',
            'published_at' => now()->subDay(),
            ...$attributes,
        ]);
    }

    /** دعوات مفعَّلة حقيقيّة: المدعوّ حسابه نشط فعلًا (7.6.1) */
    protected function giveActivatedInvites(User $referrer, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $invited = $this->user('trainee', 'مدعوّ رقم '.($i + 1));

            Referral::create([
                'referrer_id' => $referrer->id,
                'referred_id' => $invited->id,
                'code' => $referrer->code,
                'commission_percent' => 7,
            ]);
        }
    }

    /** إجبار احتمال ظهور الأيقونة أو التذكرة على قيمة قاطعة داخل الاختبار */
    protected function forceSetting(string $key, string $value): void
    {
        Setting::query()->where('key', $key)->update(['value' => $value]);
        cache()->forget('settings');
    }
}
