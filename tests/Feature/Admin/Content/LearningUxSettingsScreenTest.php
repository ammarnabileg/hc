<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\AdminScreenTextLearningDemoSeeder;
use Database\Seeders\LearningDemoSeeder;
use Database\Seeders\ServiceTextsDemoSeeder;
use Database\Seeders\SettingGapSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\DB;

/**
 * 🖥️ شاشة «إعدادات التعلّم» (24.4 · 12.2.2 `learning_ux.*`) — خمس مجموعات،
 * وكلّ حفظ/Reset محصور بمفاتيح الشاشة نفسها (2.13) لا أيّ مفتاحٍ في المنصّة.
 */
class LearningUxSettingsScreenTest extends AdminContentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LearningDemoSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(SettingGapSeeder::class);
        $this->seed(ServiceTextsDemoSeeder::class);
        $this->seed(AdminScreenTextLearningDemoSeeder::class);
    }

    private function permission(string $key): Permission
    {
        [$resource, $action] = array_pad(explode('.', $key, 2), 2, 'view');

        return Permission::firstOrCreate(
            ['key' => $key],
            ['resource' => $resource, 'action' => $action, 'group' => 'اختبار', 'label_ar' => $key, 'allowed_scopes' => ['ALL']],
        );
    }

    private function withPermissions(array $keys): User
    {
        $user = $this->makeUser(['name' => 'أدمن محتوى']);

        foreach ($keys as $key) {
            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $this->permission($key)->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($user);

        return $user;
    }

    public function test_a_user_without_any_learning_ux_permission_is_forbidden(): void
    {
        $user = $this->withPermissions(['courses.list']);

        $this->actingAs($user)
            ->get(route('admin.learning-settings.index'))
            ->assertForbidden();
    }

    public function test_the_screen_lists_all_five_groups_and_defaults_to_the_first(): void
    {
        $viewer = $this->withPermissions(['learning_ux.view']);

        $this->actingAs($viewer)
            ->get(route('admin.learning-settings.index'))
            ->assertOk()
            ->assertSee('عرض الدرس')
            ->assertSee('التعليقات')
            ->assertSee('الملاحظات')
            ->assertSee('التوقيت والقفل')
            ->assertSee('الاختبارات')
            // المجموعة الأولى مفتوحة افتراضًا — مفاتيحها ظاهرة
            ->assertSee('learning.ux.resume_enabled');
    }

    public function test_switching_groups_shows_only_that_groups_own_keys(): void
    {
        $viewer = $this->withPermissions(['learning_ux.view']);

        $this->actingAs($viewer)
            ->get(route('admin.learning-settings.index', ['group' => 'comments']))
            ->assertOk()
            ->assertSee('learning.comments.enabled')
            ->assertDontSee('learning.ux.resume_enabled');
    }

    public function test_a_view_only_user_sees_no_input_and_cannot_save(): void
    {
        $viewer = $this->withPermissions(['learning_ux.view']);
        $setting = Setting::query()->where('key', 'learning.comments.max_length')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('admin.learning-settings.index', ['group' => 'comments']))
            ->assertOk()
            ->assertSee((string) setting('admin.content.learning_settings.index.read_only', 'عرض فقط — بلا صلاحيّة تعديل.'));

        $this->actingAs($viewer)
            ->postJson(route('admin.learning-settings.field'), ['key' => $setting->key, 'value' => 999])
            ->assertForbidden();
    }

    public function test_an_editor_can_save_a_field_and_the_new_value_is_read_immediately(): void
    {
        $editor = $this->withPermissions(['learning_ux.edit']);
        $setting = Setting::query()->where('key', 'learning.comments.max_length')->firstOrFail();

        $this->actingAs($editor)
            ->postJson(route('admin.learning-settings.field'), ['key' => $setting->key, 'value' => 500])
            ->assertOk()
            ->assertJson(['saved' => true, 'value' => '500']);

        $this->assertSame('500', $setting->refresh()->value);
        $this->assertSame(500, (int) setting('learning.comments.max_length'));
    }

    /** ⭐ الحصر (2.13): مفتاحٌ من خارج مجموعات هذه الشاشة يُرفَض بـ404 مهما كانت الصلاحيّة */
    public function test_saving_a_key_outside_the_screens_own_groups_is_rejected(): void
    {
        $editor = $this->withPermissions(['learning_ux.manage']);
        Setting::query()->firstOrCreate(['key' => 'unrelated.domain.key'], ['group' => 'test', 'label_ar' => 'test', 'type' => 'string', 'value' => 'x', 'default_value' => 'x']);

        $this->actingAs($editor)
            ->postJson(route('admin.learning-settings.field'), ['key' => 'unrelated.domain.key', 'value' => 'y'])
            ->assertNotFound();

        $this->assertSame('x', Setting::query()->where('key', 'unrelated.domain.key')->value('value'));
    }

    public function test_resetting_a_field_restores_its_default_value(): void
    {
        $editor = $this->withPermissions(['learning_ux.edit']);
        $setting = Setting::query()->where('key', 'learning.comments.max_length')->firstOrFail();
        $default = $setting->default_value;

        $setting->update(['value' => '9999']);

        $this->actingAs($editor)
            ->postJson(route('admin.learning-settings.reset'), ['key' => $setting->key])
            ->assertOk()
            ->assertJson(['saved' => true, 'value' => $default]);

        $this->assertSame($default, $setting->refresh()->value);
    }

    /** ⭐ «إعادة الكلّ للافتراضيّ» لمجموعة أوسع أثرًا من حقلٍ واحد — تتطلّب `manage` لا `edit` وحدها */
    public function test_reset_group_requires_manage_not_just_edit(): void
    {
        $editor = $this->withPermissions(['learning_ux.edit']);

        $this->actingAs($editor)
            ->postJson(route('admin.learning-settings.reset-group'), ['group' => 'comments'])
            ->assertForbidden();

        $manager = $this->withPermissions(['learning_ux.manage']);
        $setting = Setting::query()->where('key', 'learning.comments.max_length')->firstOrFail();
        $setting->update(['value' => '1']);

        $this->actingAs($manager)
            ->postJson(route('admin.learning-settings.reset-group'), ['group' => 'comments'])
            ->assertOk();

        $this->assertSame($setting->default_value, $setting->refresh()->value);
    }
}
