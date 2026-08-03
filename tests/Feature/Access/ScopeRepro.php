<?php

namespace Tests\Feature\Access;

use App\Models\Membership;
use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VolunteerOrgDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopeRepro extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VolunteerOrgDemoSeeder::class);
    }

    public function test_repro(): void
    {
        $c1 = User::where('code', 'VOL-C1')->first();   // كوردنيتور — org_chart.view@SELF
        $c3 = User::where('code', 'VOL-C3')->first();   // كوردنيتور في المونتاج
        $c1->assignRole('coordinator', Membership::where('user_id', $c1->id)->first());
        $foreign = Membership::where('user_id', $c3->id)->first();

        $this->actingAs($c1);
        $r = $this->get('/volunteer/org/node/'.$foreign->id);
        dump('SELF على هدف أجنبيّ ⟵ '.$r->getStatusCode());

        // هل الميدلوير يرى نموذجًا مربوطًا؟
        dump('route params bound?');

        // @ALL على هدف يغطّيه
        $wide = User::create(['name' => 'واسع', 'email' => 'w@t.local', 'password' => 'x', 'code' => 'WIDE1', 'status' => 'active']);
        $role = Role::create(['key' => 'r_wide', 'name_ar' => 'واسع', 'layer' => 'platform']);
        \DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', 'org_chart.view')->value('id'),
            'scope' => 'ALL', 'effect' => 'allow',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $wide->assignRole($role);
        $this->actingAs($wide);
        $r2 = $this->get('/volunteer/org/node/'.$foreign->id);
        dump('ALL على هدف يغطّيه ⟵ '.$r2->getStatusCode());
    }

    public function test_owner_lockout(): void
    {
        $owner = User::create(['name' => 'مالك', 'email' => 'o@t.local', 'password' => 'x', 'code' => 'OWN1', 'status' => 'active']);
        $owner->assignRole('platform_owner');
        PermissionUser::create([
            'permission_id' => Permission::where('key', 'courses.list')->value('id'),
            'user_id' => $owner->id,
            'scope' => 'ALL',
            'effect' => 'deny',
        ]);
        dump('المالك بعد صفّ منع ⟵ '.var_export($owner->allows('courses.list'), true));
    }

    public function test_scope_ceiling_write(): void
    {
        $owner = User::create(['name' => 'مالك', 'email' => 'o2@t.local', 'password' => 'x', 'code' => 'OWN2', 'status' => 'active']);
        $owner->assignRole('platform_owner');
        $victim = User::create(['name' => 'ضحيّة', 'email' => 'v@t.local', 'password' => 'x', 'code' => 'VIC1', 'status' => 'active']);

        dump('allowed_scopes(org_chart.view) = '.json_encode(Permission::where('key', 'org_chart.view')->value('allowed_scopes')));

        $r = $this->actingAs($owner)->from('/admin/roles')->post('/admin/permissions/users/'.$victim->id, [
            'permission' => 'org_chart.view',
            'scope' => 'ALL',
            'effect' => 'allow',
        ]);
        dump('كتابة ALL على سقف TRACK ⟵ '.$r->getStatusCode());
        dump('صفوف: '.PermissionUser::where('user_id', $victim->id)->count());
        dump('نطاق مكتوب: '.PermissionUser::where('user_id', $victim->id)->value('scope'));
    }

    public function test_panel_door(): void
    {
        foreach (['store_products.list', 'events.list', 'referrals.view', 'courses.manage', 'paths.manage'] as $key) {
            $u = User::create(['name' => 'ح', 'email' => str()->random(8).'@t.local', 'password' => 'x', 'code' => str()->upper(str()->random(8)), 'status' => 'active']);
            $role = Role::create(['key' => 'r_'.str_replace('.', '_', $key), 'name_ar' => $key, 'layer' => 'platform']);
            \DB::table('permission_role')->insert([
                'role_id' => $role->id,
                'permission_id' => Permission::where('key', $key)->value('id'),
                'scope' => 'ALL', 'effect' => 'allow',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $u->assignRole($role);
            dump($key.' ⟵ /admin = '.$this->actingAs($u)->get('/admin')->getStatusCode());
        }

        $rec = User::create(['name' => 'موظّف', 'email' => 'rec@t.local', 'password' => 'x', 'code' => 'REC1', 'status' => 'active']);
        $rec->assignRole('recruiter');
        dump('فريق التوظيف ⟵ /admin = '.$this->actingAs($rec)->get('/admin')->getStatusCode());
        dump('فريق التوظيف ⟵ /volunteer/recruitment = '.$this->actingAs($rec)->get('/volunteer/recruitment')->getStatusCode());

        $trainee = User::create(['name' => 'متدرّب', 'email' => 'tr@t.local', 'password' => 'x', 'code' => 'TRN1', 'status' => 'active']);
        $trainee->assignRole('trainee');
        dump('متدرّب ⟵ /admin = '.$this->actingAs($trainee)->get('/admin')->getStatusCode());
    }
}
