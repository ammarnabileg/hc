<?php

namespace Tests\Feature\Challenges;

use App\Models\Permission;
use App\Models\User;
use App\Services\Gamification\WalletGateway;
use Database\Seeders\ChallengeDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * أساس اختبارات مجال التحديات: العملات والأدوار والإعدادات وبيانات المجال.
 * الصلاحيّات تُزرَع هنا مباشرةً (بدل مصفوفة الـ1006) عشان الاختبار يفضل سريعًا.
 */
abstract class ChallengeTestCase extends TestCase
{
    use RefreshDatabase;

    /** الصلاحيّات التي تحرس شاشات المجال */
    protected const PERMISSIONS = [
        'war_participation.view',
        'war_participation.create',
        'wars_matches.view',
        'wars_matches.edit',
        'leaderboards.view',
        'achievements.view',
        'badges.view',
        'streaks.view',
        'streaks.create',
        'games.view',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);

        foreach (self::PERMISSIONS as $key) {
            [$resource, $action] = explode('.', $key);

            Permission::updateOrCreate(['key' => $key], [
                'resource' => $resource,
                'action' => $action,
                'group' => 'التفاعل والفعاليّات والتلعيب',
                'label_ar' => $resource,
                'allowed_scopes' => ['SELF', 'ALL'],
            ]);
        }

        $this->seed(ChallengeDemoSeeder::class);
    }

    /** متدرّب مفعَّل برصيد تذاكر جاهز */
    protected function trainee(float $tickets = 20, array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'متدرّب تجريبيّ',
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ], $attributes));

        $user->assignRole('trainee');

        if ($tickets > 0) {
            app(WalletGateway::class)->credit($user, 'tickets', $tickets, 'رصيد اختبار');
        }

        return $user->refresh();
    }

    protected function ticketsOf(User $user): float
    {
        return app(WalletGateway::class)->balance($user, 'tickets');
    }
}
