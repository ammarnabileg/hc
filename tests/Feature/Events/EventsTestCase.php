<?php

namespace Tests\Feature\Events;

use App\Models\Currency;
use App\Models\Event;
use App\Models\User;
use App\Models\WalletBalance;
use Database\Seeders\CoreSeeder;
use Database\Seeders\EventDemoSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** أساس اختبارات مجال الفعاليّات والدعوات — يهيّئ الصلاحيّات والإعدادات والعملات */
abstract class EventsTestCase extends TestCase
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
        $this->seed(EventDemoSeeder::class);
    }

    protected function trainee(string $name = 'مريم حسن'): User
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::random(8).'@test.local',
            'password' => 'secret-password',
            'code' => 'U'.Str::upper(Str::random(7)),
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $user->assignRole('trainee');

        return $user->fresh();
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    protected function makeEvent(array $attributes = []): Event
    {
        return Event::create([
            'slug' => 'test-'.Str::lower(Str::random(8)),
            'title_ar' => 'فعاليّة اختبار',
            'mode' => 'online',
            'category' => 'اختبار',
            'starts_at' => now()->addDays(3)->setTime(20, 0),
            'ends_at' => now()->addDays(3)->setTime(21, 0),
            'join_link' => 'https://meet.example.com/test-room',
            'attendance_code' => '246810',
            'xp_reward' => 100,
            'ticket_reward' => 1,
            'status' => 'published',
            ...$attributes,
        ]);
    }

    protected function giveBalance(User $user, string $currencyCode, float $amount): void
    {
        $currency = Currency::where('code', $currencyCode)->firstOrFail();

        WalletBalance::updateOrCreate(
            ['user_id' => $user->id, 'currency_id' => $currency->id],
            ['balance' => $amount],
        );
    }

    protected function balanceOf(User $user, string $currencyCode): float
    {
        $currency = Currency::where('code', $currencyCode)->firstOrFail();

        return (float) WalletBalance::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $currency->id)
            ->value('balance');
    }
}
