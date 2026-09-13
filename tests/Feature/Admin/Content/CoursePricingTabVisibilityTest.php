<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Course;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * ⭐ تاب «التسعير» داخل فورم التدريب 🔒 (12.4 · «الحالات»: «بلا صلاحيّة (تاب
 * التسعير مخفيّ لمن لا يملكه)») — تطبيقًا للقاعدة العامّة **2.15-أ-7**
 * «المحظور يُخفى لا يُعطَّل».
 *
 * المرصود قبل هذا الملفّ: `courses/form.blade.php` كان يرسم زرّ التاب وقسمه
 * (السعر الأساسيّ · سعر العرض · تاريخ انتهاء العرض · نصّ الـPaywall) بلا أيّ
 * `@can` — ظاهرًا بقيمه الحقيقيّة لأيّ حامل `courses.edit`، بصرف النظر عن
 * امتلاكه `paywall.view`. والحماية الوحيدة كانت خادميّة عند الحفظ
 * (`CourseAdminController::withGuardedPricing` تُسقِط حقول التسعير من غير
 * مالك المنصّة) — لا عند العرض، فتتسرّب الأرقام لمن لا يفترض أن يراها.
 *
 * ولأنّ `content_admin` (يحمل `courses.edit`) لا يحمل مورد `paywall` أصلًا في
 * مصفوفة الأدوار (`RolePermissionSeeder`)، بينما `marketing_admin` يحمله بلا
 * `courses.edit` — فالمفتاحان منفصلان فعلًا في الإنتاج، وهذا بالضبط ما يمسكه
 * الاختبار.
 */
class CoursePricingTabVisibilityTest extends AdminContentTestCase
{
    private const PRICE_MARKER = '78931';

    protected function setUp(): void
    {
        parent::setUp();

        // ⭐ الأساس (AdminContentTestCase) لا يزرع الصلاحيّات ولا ربطها بالأدوار
        // — فأدواره غير platform_owner (مثل content_admin وmarketing_admin)
        // بلا أيّ منحٍ فعليّ إلّا بهذين السيدرين (12.2.2 · 12.2.3).
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_an_editor_without_paywall_view_never_sees_the_pricing_tab(): void
    {
        $course = $this->priceCourse();
        $editor = $this->userWithRoles(['content_admin']);

        $html = $this->actingAs($editor)
            ->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-form-panel="pricing"', $html,
            'قسم تاب التسعير موجود في المخرَج رغم أنّ المحرّر لا يملك paywall.view — يجب حذفه كليًّا لا إخفاؤه بالـCSS (2.15-أ-7).');

        $this->assertStringNotContainsString('data-form-tab="pricing"', $html,
            'زرّ تاب التسعير موجود في المخرَج رغم أنّ المحرّر لا يملك paywall.view.');

        $this->assertStringNotContainsString(self::PRICE_MARKER, $html,
            'قيمة السعر الحقيقيّة تسرّبت في HTML لمحرّرٍ لا يملك صلاحيّة رؤية التسعير.');

        $this->assertStringNotContainsString($course->paywall_text_ar, $html,
            'نصّ الـPaywall تسرّب لمحرّرٍ لا يملك paywall.view.');
    }

    public function test_an_editor_with_paywall_view_sees_the_pricing_tab(): void
    {
        $course = $this->priceCourse();
        // يحمل الدورين معًا — «يجوز حمل أكثر من دور» (12.2.3): courses.edit من
        // content_admin + paywall.view من marketing_admin.
        $editor = $this->userWithRoles(['content_admin', 'marketing_admin']);

        $html = $this->actingAs($editor)
            ->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-form-panel="pricing"', $html,
            'صاحب paywall.view يجب أن يرى قسم تاب التسعير.');

        $this->assertStringContainsString('data-form-tab="pricing"', $html,
            'صاحب paywall.view يجب أن يرى زرّ تاب التسعير.');

        $this->assertStringContainsString(self::PRICE_MARKER, $html,
            'صاحب paywall.view يجب أن يرى قيمة السعر الحقيقيّة.');
    }

    /** ⭐ نفس الحجب يشمل بانر «مسودّة تحرير» — قناة تسرّب ثانية لنفس الحقول. */
    public function test_pending_draft_banner_hides_pricing_fields_from_unauthorized_editors(): void
    {
        $course = $this->priceCourse();
        $course->forceFill([
            'draft_payload' => ['price_coins' => 55555, 'name_ar' => 'اسمٌ مسوَّد'],
            'draft_saved_at' => now(),
        ])->save();

        $editor = $this->userWithRoles(['content_admin']);

        $html = $this->actingAs($editor)
            ->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('55555', $html,
            'بانر المسوّدة عرض قيمة سعرٍ محفوظة تلقائيًّا لمحرّرٍ لا يملك paywall.view — تسرّبٌ من بابٍ آخر.');

        // وبقيّة حقول المسوّدة غير الماليّة تظلّ ظاهرة كما هي
        $this->assertStringContainsString('اسمٌ مسوَّد', $html);
    }

    private function priceCourse(): Course
    {
        return Course::create([
            'name_ar' => 'تدريبٌ مدفوع',
            'slug' => 'paid-course-'.str()->random(8),
            'status' => 'published',
            'is_free' => false,
            'price_coins' => self::PRICE_MARKER,
            'offer_price_coins' => null,
            'paywall_text_ar' => 'نصّ حجبٍ سرّيّ لا يراه إلّا صاحب الصلاحيّة',
        ]);
    }

    /** @param  list<string>  $roleKeys */
    private function userWithRoles(array $roleKeys): User
    {
        $user = $this->makeUser(['name' => 'محرّر اختبار']);

        foreach ($roleKeys as $key) {
            RoleUser::create([
                'role_id' => Role::query()->where('key', $key)->value('id'),
                'user_id' => $user->id,
                'assigned_at' => now(),
            ]);
        }

        return $user;
    }
}
