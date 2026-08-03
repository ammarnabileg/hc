<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\Permission;
use App\Models\User;
use App\Models\WalletBalance;
use App\Models\WarMatch;
use App\Services\Gamification\WalletGateway;
use App\Services\Gamification\Wars\MatchmakingService;
use App\Support\Access\AccessEngine;
use Database\Seeders\ChallengeDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * أساس اختبارات مجال الحروب: العملات والأدوار والإعدادات وبيانات المجال.
 * الصلاحيّات تُزرَع هنا مباشرةً (بدل مصفوفة الـ1006) عشان الاختبار يفضل سريعًا.
 */
abstract class ChallengeTestCase extends TestCase
{
    use RefreshDatabase;

    /** الصلاحيّات التي تحرس شاشات المجال */
    protected const PERMISSIONS = [
        'war_participation.view',
        'war_participation.create',
        'war_participation.delete',
        'wars_matches.view',
        'wars_matches.create',
        'wars_matches.edit',
        'wars_matches.reject',
        'leaderboards.view',
        'achievements.view',
        'badges.view',
        'streaks.view',
        'streaks.create',
    ];

    /** صلاحيّات لوحة الإدارة في هذا المجال (12.10-ب · 24.2) */
    protected const ADMIN_PERMISSIONS = [
        'wars_bank.list',
        'wars_bank.view',
        'wars_bank.create',
        'wars_bank.edit',
        'wars_bank.delete',
        'wars_bank.archive',
        'wars_bank.import',
        'wars_bank.export',
        'wars_settings.view',
        'xp_rules.view',
        'badges.view',
        'celebrations.view',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);

        foreach ([...self::PERMISSIONS, ...self::ADMIN_PERMISSIONS] as $key) {
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

    /** متدرّب مفعَّل برصيد تذاكر جاهز — 20 تذكرة تكفي بوّابة الـ12 */
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

    /** أدمن بصلاحيّات لوحة الحروب — بنطاق ALL (12.2.1) */
    protected function warAdmin(): User
    {
        $user = $this->trainee(tickets: 0, attributes: ['name' => 'أدمن الحروب']);

        foreach (self::ADMIN_PERMISSIONS as $key) {
            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => Permission::query()->where('key', $key)->value('id'),
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($user);

        return $user->refresh();
    }

    protected function ticketsOf(User $user): float
    {
        return app(WalletGateway::class)->balance($user, 'tickets');
    }

    /** مجموع تذاكر النظام كلّه — أساس اختبار المحصّلة الصفريّة (15.2-6) */
    protected function systemTickets(): float
    {
        $currencyId = app(WalletGateway::class)->currency('tickets')?->id;

        return (float) WalletBalance::query()->where('currency_id', $currencyId)->sum('balance');
    }

    protected function challenge(string $key = 'knowledge_war'): Challenge
    {
        return Challenge::query()->where('key', $key)->firstOrFail();
    }

    /** مواجهة جاهزة بين طرفين — استعداد الاثنين ثمّ بدء بقفل ذرّيّ */
    protected function startMatch(User $a, User $b, string $key = 'knowledge_war'): WarMatch
    {
        $challenge = $this->challenge($key);
        $matchmaking = app(MatchmakingService::class);

        $matchmaking->ready($a, $challenge);
        $matchmaking->ready($b, $challenge);

        return $matchmaking->start($a, $b, $challenge);
    }
}
