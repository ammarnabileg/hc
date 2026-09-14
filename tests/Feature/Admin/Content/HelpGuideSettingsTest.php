<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Setting;
use App\Services\Admin\Content\HelpGuideSettings;
use Database\Seeders\AccountDemoSeeder;

/**
 * بلوك إعدادات دليل المستخدم (12.6-ج سطر 5087 — فجوة مسدودة): كانت الشاشة
 * بلا إعدادات إطلاقًا، والبحث و«هل كان مفيدًا؟» ظاهرَين دائمًا بلا Toggle،
 * والتصنيفات والوسوم حقولًا حرّة بلا CRUD تديرها.
 */
class HelpGuideSettingsTest extends AdminContentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // `account.help.page_size` مفتاحٌ مشترك يزرعه مجال الحساب — والبلوك
        // يكتب قيمته فقط (لا يعيد تعريفه)، فلازم يوجد صفّه قبل الاختبار.
        $this->seed(AccountDemoSeeder::class);
    }

    /** الصفحة والحفظ والـReset كلّها بصلاحيّة `user_guide.edit` فقط (12.2.1). */
    public function test_settings_screen_requires_permission(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.guidance.help.settings'))
            ->assertOk()
            ->assertSee('إعدادات دليل المستخدم');

        $stranger = $this->makeUser();

        $this->actingAs($stranger)->get(route('admin.guidance.help.settings'))->assertForbidden();
        $this->actingAs($stranger)->post(route('admin.guidance.help.settings.update'), ['settings' => []])->assertForbidden();
        $this->actingAs($stranger)->post(route('admin.guidance.help.settings.reset'))->assertForbidden();
        $this->actingAs($stranger)->put(route('admin.guidance.help.settings.categories'), ['categories' => ['أ']])->assertForbidden();
        $this->actingAs($stranger)->put(route('admin.guidance.help.settings.tags'), ['tags' => ['أ']])->assertForbidden();
    }

    /** الحفظ يكتب القيمة فعليًّا — والشاشة تعرضها بعد التحديث. */
    public function test_toggles_and_texts_save_and_show_as_modified(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.guidance.help.settings.update'), [
            'settings' => [
                'help.search_enabled' => '0',
                'help.feedback_enabled' => '0',
                'help.sidebar_categories_enabled' => '0',
                'account.help.page_size' => '5',
            ],
        ])->assertRedirect();

        $this->assertFalse((bool) setting('help.search_enabled'));
        $this->assertFalse((bool) setting('help.feedback_enabled'));
        $this->assertFalse((bool) setting('help.sidebar_categories_enabled'));
        $this->assertSame(5, (int) setting('account.help.page_size'));

        $rows = collect(HelpGuideSettings::rows())->keyBy('key');
        $this->assertTrue($rows['help.search_enabled']['modified']);
        $this->assertTrue($rows['account.help.page_size']['modified']);

        // ولم تُمسَّ بطاقة المفتاح المشترك (المجموعة/اللافتة) — نكتب قيمته فقط
        $shared = Setting::query()->where('key', 'account.help.page_size')->firstOrFail();
        $this->assertSame('account', $shared->group);
    }

    /** ↺ Reset يرجّع كلّ مفاتيح البلوك لافتراضيّها دفعةً واحدة. */
    public function test_reset_restores_defaults(): void
    {
        $admin = $this->admin();

        HelpGuideSettings::putMany([
            'help.search_enabled' => '0',
            'help.feedback_enabled' => '0',
            'account.help.page_size' => '99',
        ], $admin);

        $this->assertFalse((bool) setting('help.search_enabled'));

        $this->actingAs($admin)->post(route('admin.guidance.help.settings.reset'))->assertRedirect();

        $this->assertTrue((bool) setting('help.search_enabled'));
        $this->assertTrue((bool) setting('help.feedback_enabled'));
        $this->assertSame(12, (int) setting('account.help.page_size'));
    }

    /** فورمٌ مزوَّر بمفتاحٍ خارج الكتالوج يُهمَل بصمت — لا تلويث لجدول الإعدادات. */
    public function test_unknown_setting_key_is_silently_ignored(): void
    {
        $this->actingAs($this->admin())->post(route('admin.guidance.help.settings.update'), [
            'settings' => ['not.a.real.key' => '1'],
        ])->assertRedirect();

        $this->assertDatabaseMissing('settings', ['key' => 'not.a.real.key']);
    }

    // ============================================================== CRUD: تصنيفات

    public function test_category_can_be_added_edited_and_deleted(): void
    {
        $admin = $this->admin();

        // إضافة
        $this->actingAs($admin)->put(route('admin.guidance.help.settings.categories'), [
            'categories' => ['البداية', 'تصنيف اختباريّ'],
        ])->assertRedirect();

        $this->assertSame(['البداية', 'تصنيف اختباريّ'], HelpGuideSettings::categories());

        // تعديل (إعادة تسمية عنصر قائم)
        $this->actingAs($admin)->put(route('admin.guidance.help.settings.categories'), [
            'categories' => ['البداية', 'تصنيف بعد التعديل'],
        ])->assertRedirect();

        $this->assertSame(['البداية', 'تصنيف بعد التعديل'], HelpGuideSettings::categories());

        // حذف
        $this->actingAs($admin)->put(route('admin.guidance.help.settings.categories'), [
            'categories' => ['البداية'],
        ])->assertRedirect();

        $this->assertSame(['البداية'], HelpGuideSettings::categories());
        $this->assertNotContains('تصنيف بعد التعديل', HelpGuideSettings::categories());
    }

    /** قائمة فاضية مرفوضة — الفورم محتاج تصنيفًا واحدًا يختار منه المستخدم. */
    public function test_categories_cannot_all_be_removed(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.guidance.help.settings.categories'), ['categories' => ['', '  ']])
            ->assertSessionHasErrors('categories');
    }

    /** والتصنيف المُدار يظهر فعلًا في اقتراحات محرّر الدليل (12.6-ج). */
    public function test_managed_category_appears_as_a_suggestion_in_the_article_editor(): void
    {
        $admin = $this->admin();

        HelpGuideSettings::saveCategories(['تصنيف ظاهر في المحرّر'], $admin);

        $this->actingAs($admin)->get(route('admin.guidance.help'))
            ->assertOk()
            ->assertSee('تصنيف ظاهر في المحرّر');
    }

    // ============================================================== CRUD: وسوم

    public function test_tag_can_be_added_edited_and_deleted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.guidance.help.settings.tags'), [
            'tags' => ['أمان', 'حساب'],
        ])->assertRedirect();

        $this->assertSame(['أمان', 'حساب'], HelpGuideSettings::tags());

        $this->actingAs($admin)->put(route('admin.guidance.help.settings.tags'), [
            'tags' => ['أمان', 'دفع'],
        ])->assertRedirect();

        $this->assertSame(['أمان', 'دفع'], HelpGuideSettings::tags());
        $this->assertNotContains('حساب', HelpGuideSettings::tags());
    }

    /** الوسوم تقبل قائمةً فاضية تمامًا (بخلاف التصنيفات) — كلّها اختياريّة. */
    public function test_tags_can_be_cleared_entirely(): void
    {
        HelpGuideSettings::saveTags(['وسم مؤقّت'], $this->admin());
        $this->assertNotEmpty(HelpGuideSettings::tags());

        $this->actingAs($this->admin())
            ->put(route('admin.guidance.help.settings.tags'), ['tags' => []])
            ->assertRedirect();

        $this->assertSame([], HelpGuideSettings::tags());
    }
}
